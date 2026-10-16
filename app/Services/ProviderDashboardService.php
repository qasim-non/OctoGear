<?php

namespace App\Services;

use App\Enums\OfferStatus;
use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Enums\PaymentStatus;
use App\Models\OrderOffer;
use App\Models\Payment;
use App\Models\Store;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProviderDashboardService
{
    public function __construct(private ProviderOrderReadService $read) {}

    public function summary(int $providerId, ?int $storeId, string $period): array
    {
        // Keep counts, bounded previews, eager-loaded relations, and store choices
        // in the same read snapshot. Production MySQL/MariaDB uses its default
        // REPEATABLE READ isolation; SQLite also holds a consistent read snapshot.
        // Do not change this connection to READ COMMITTED without retaining this
        // guarantee, otherwise a newly arriving order can outgrow its preview total.
        return DB::transaction(fn () => $this->snapshot($providerId, $storeId, $period));
    }

    private function snapshot(int $providerId, ?int $storeId, string $period): array
    {
        $stores = Store::query()->where('user_id', $providerId)->orderBy('name')->orderBy('id')->get(['id', 'name']);
        // A store may be removed after FormRequest validation but before this
        // snapshot starts. Return a validation response instead of an incoherent
        // selection that the client cannot display.
        if ($storeId !== null && ! $stores->contains('id', $storeId)) {
            throw ValidationException::withMessages(['store_id' => [__('validation.exists', ['attribute' => 'store_id'])]]);
        }
        $timezone = config('app.business_timezone');
        $now = CarbonImmutable::now()->utc();
        $local = $now->setTimezone($timezone);
        $from = (match ($period) {
            'today' => $local->startOfDay(),
            'week' => $local->startOfWeek(CarbonImmutable::SATURDAY),
            default => $local->startOfMonth(),
        })->utc();
        $owned = $this->owned($providerId, $storeId);
        $pendingDirect = $this->section(clone $owned, 'pending_direct');
        $awaiting = $this->section(clone $owned, 'awaiting_payment');
        $paid = $this->section(clone $owned, 'paid');
        $pendingDirectTotal = (clone $pendingDirect)->count();
        $awaitingTotal = (clone $awaiting)->count();
        $paidTotal = (clone $paid)->count();

        // Payment rows are created as pending. Their successful update is the
        // settlement timestamp in the current schema, which has no paid_at.
        // Never infer recorded sales from order/offer prices or creation dates.
        $sales = Payment::query()
            ->where('payment_status', PaymentStatus::Paid)
            ->where('updated_at', '>=', $from)
            ->where('updated_at', '<', $now)
            ->whereIn('order_id', (clone $owned)->whereIn('status', [OrderStatus::Paid, OrderStatus::Completed])->select('orders.id'))
            ->selectRaw('COALESCE(SUM(amount), 0) AS total_amount, COUNT(*) AS payment_count')
            ->first();

        return [
            'stores' => $stores,
            'selected_store_id' => $storeId,
            'period' => $period,
            'generated_at' => $now->toISOString(),
            'counts' => [
                'pending_direct_requests' => $pendingDirectTotal,
                'pending_offers' => OrderOffer::query()->where('status', OfferStatus::Pending)
                    ->whereHas('order', fn ($q) => $q->where('order_type', OrderType::General)->where('status', OrderStatus::Pending))
                    ->whereHas('store', fn ($q) => $q->where('user_id', $providerId)->when($storeId, fn ($q) => $q->whereKey($storeId)))
                    ->count(),
                'accepted_offers' => (clone $awaiting)->where('order_type', OrderType::General)
                    ->whereHas('acceptedOffer', fn ($q) => $q->where('status', OfferStatus::Accepted))->count(),
                'paid_orders' => $paidTotal,
            ],
            'sales' => [
                'amount' => (int) $sales->total_amount,
                'count' => (int) $sales->payment_count,
                'currency' => 'SAR', 'price_scale' => 100,
                'from' => $from->toISOString(), 'to' => $now->toISOString(),
                'timezone' => $timezone,
            ],
            'attention' => [
                'pending_direct' => $this->preview($pendingDirect, $pendingDirectTotal, 3),
                'awaiting_payment' => $this->preview($awaiting, $awaitingTotal, 3),
                'paid' => $this->preview($paid, $paidTotal, 3),
            ],
            'recent_orders' => $this->preview(clone $owned, (clone $owned)->count(), 5),
        ];
    }

    public function orders(int $providerId, ?int $storeId, string $section): Builder
    {
        return $this->section($this->owned($providerId, $storeId), $section)
            ->with($this->relations())->orderByDesc('updated_at')->orderByDesc('id');
    }

    private function owned(int $providerId, ?int $storeId): Builder
    {
        // Reuse the same ownership and soft-delete visibility as provider order
        // details. Store filtering narrows this scope; it never grants access.
        return $this->read->owned($providerId)->when($storeId, fn ($q) => $q->where(function ($query) use ($storeId) {
            $query->where(fn ($q) => $q->where('order_type', OrderType::General)
                ->whereHas('acceptedOffer.store', fn ($q) => $q->whereKey($storeId)))
                ->orWhere(fn ($q) => $q->where('order_type', OrderType::Specific)
                    ->whereHas('storeCarComponent.storeCar.store', fn ($q) => $q->whereKey($storeId)));
        }));
    }

    private function section(Builder $query, string $section): Builder
    {
        return match ($section) {
            'pending_direct' => $query->where('order_type', OrderType::Specific)->where('status', OrderStatus::Pending),
            'awaiting_payment' => $query->where('status', OrderStatus::AwaitingPayment),
            'paid' => $query->where('status', OrderStatus::Paid),
            default => $query,
        };
    }

    private function relations(): array
    {
        return ['customer', 'component', 'payment', 'acceptedOffer.store', 'storeCarComponent.component', 'storeCarComponent.storeCar.store'];
    }

    private function preview(Builder $query, int $total, int $limit): array
    {
        return [
            'total' => $total,
            'items' => $query->with($this->relations())->orderByDesc('updated_at')->orderByDesc('id')->limit($limit)->get(),
        ];
    }
}

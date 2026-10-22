<?php

namespace App\Http\Controllers\Api;

use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\OfferImage;
use App\Models\Order;
use App\Models\OrderImage;
use App\Models\OrderOffer;
use App\Models\Store;
use App\Models\StoreCarPicture;
use App\Models\StorePicture;
use App\Models\StoreRequest;
use App\Models\StoreRequestPicture;
use App\Models\StoresCar;
use App\Models\User;
use App\Services\ImageStorageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Files are addressed by authorized records, never by client-supplied paths. */
class MediaController extends Controller
{
    public function __construct(private ImageStorageService $images) {}

    public function storePicture(Request $request, Store $store, StorePicture $storePicture)
    {
        $this->viewer($request, 'stores.view');
        abort_unless($storePicture->store_id === $store->id, 404);

        return $this->streamImage($storePicture->only(['disk', 'path', 'mime_type', 'size_bytes']));
    }

    public function storeCarPicture(Request $request, Store $store, StoresCar $storeCar, StoreCarPicture $storeCarPicture)
    {
        $this->viewer($request, 'stores.view');
        abort_unless($storeCar->store_id === $store->id && $storeCarPicture->car_id === $storeCar->id, 404);

        return $this->streamImage($storeCarPicture->only(['disk', 'path', 'mime_type', 'size_bytes']));
    }

    public function storeRegistration(Request $request, Store $store)
    {
        $viewer = $this->viewer($request, 'stores.view');
        if ($viewer instanceof User) {
            $this->authorize('manage', $store);
        }

        return $this->streamImage($store->registrationImage());
    }

    public function storeRequestPicture(Request $request, StoreRequest $storeRequest, StoreRequestPicture $storeRequestPicture)
    {
        $viewer = $this->viewer($request, 'requests.view');
        abort_unless($storeRequestPicture->store_request_id === $storeRequest->id, 404);
        if ($viewer instanceof User) {
            $this->authorize('view', $storeRequest);
        }

        return $this->streamImage($storeRequestPicture->image());
    }

    public function storeRequestRegistration(Request $request, StoreRequest $storeRequest)
    {
        $viewer = $this->viewer($request, 'requests.view');
        if ($viewer instanceof User) {
            $this->authorize('view', $storeRequest);
        }

        return $this->streamImage($storeRequest->registrationImage());
    }

    public function orderImage(Request $request, Order $order, OrderImage $orderImage)
    {
        $viewer = $this->viewer($request, 'orders.view');
        abort_unless($orderImage->order_id === $order->id, 404);
        if ($viewer instanceof User) {
            // General pending orders are browsable by providers; that rule must
            // never grant other customers access to a customer's attachment.
            abort_unless($viewer->id === $order->customer_id || $viewer->isProvider(), 403);
            $this->authorize('view', $order);
        }

        return $this->streamImage($orderImage->fileMetadata());
    }

    public function offerImage(Request $request, OrderOffer $offer, OfferImage $offerImage)
    {
        $viewer = $this->viewer($request, 'orders.view');
        abort_unless($offerImage->order_offer_id === $offer->id, 404);
        if ($viewer instanceof User) {
            $this->authorize('view', $offer);
        }

        return $this->streamImage($offerImage->fileMetadata());
    }

    private function viewer(Request $request, string $permission): User|Admin
    {
        $viewer = $request->user();
        abort_unless(
            ($viewer instanceof Admin && $viewer->isActive())
            || ($viewer instanceof User && $viewer->status === UserStatus::Unblocked),
            403,
        );

        if ($viewer instanceof Admin) {
            $this->authorize('permission', [Admin::class, $permission]);
        }

        return $viewer;
    }

    private function streamImage(array $image): StreamedResponse|JsonResponse
    {
        return $this->images->stream($image) ?? $this->notFound();
    }
}

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
use App\Models\StoresCar;
use App\Models\User;
use App\Services\ImageStorageService;
use Illuminate\Http\Request;

/** Files are addressed by authorized records, never by client-supplied paths. */
class MediaController extends Controller
{
    public function __construct(private ImageStorageService $images) {}

    public function storePicture(Request $request, Store $store, StorePicture $storePicture)
    {
        $this->viewer($request);
        abort_unless($storePicture->store_id === $store->id, 404);

        return $this->images->stream($storePicture->only(['disk', 'path', 'mime_type', 'size_bytes']))
            ?? $this->notFound();
    }

    public function storeCarPicture(Request $request, Store $store, StoresCar $storeCar, StoreCarPicture $storeCarPicture)
    {
        $this->viewer($request);
        abort_unless($storeCar->store_id === $store->id && $storeCarPicture->car_id === $storeCar->id, 404);

        return $this->images->stream($storeCarPicture->only(['disk', 'path', 'mime_type', 'size_bytes']))
            ?? $this->notFound();
    }

    public function storeRegistration(Request $request, Store $store)
    {
        $viewer = $this->viewer($request);
        if ($viewer instanceof User) {
            $this->authorize('manage', $store);
        }

        return $this->images->stream($store->registrationImage()) ?? $this->notFound();
    }

    public function storeRequestRegistration(Request $request, StoreRequest $storeRequest)
    {
        $viewer = $this->viewer($request);
        if ($viewer instanceof User) {
            $this->authorize('view', $storeRequest);
        }

        return $this->images->stream($storeRequest->registrationImage()) ?? $this->notFound();
    }

    public function orderImage(Request $request, Order $order, OrderImage $orderImage)
    {
        $viewer = $this->viewer($request);
        abort_unless($orderImage->order_id === $order->id, 404);
        if ($viewer instanceof User) {
            // General pending orders are browsable by providers; that rule must
            // never grant other customers access to a customer's attachment.
            abort_unless($viewer->id === $order->customer_id || $viewer->isProvider(), 403);
            $this->authorize('view', $order);
        }

        return $this->images->stream($orderImage->fileMetadata()) ?? $this->notFound();
    }

    public function offerImage(Request $request, OrderOffer $offer, OfferImage $offerImage)
    {
        $viewer = $this->viewer($request);
        abort_unless($offerImage->order_offer_id === $offer->id, 404);
        if ($viewer instanceof User) {
            $this->authorize('view', $offer);
        }

        return $this->images->stream($offerImage->fileMetadata()) ?? $this->notFound();
    }

    private function viewer(Request $request): User|Admin
    {
        $viewer = $request->user();
        abort_unless(
            ($viewer instanceof Admin && $viewer->isActive())
            || ($viewer instanceof User && $viewer->status === UserStatus::Unblocked),
            403,
        );

        return $viewer;
    }
}

<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\OrderType;
use App\Models\Order;
use App\Models\OrderOffer;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProviderOfferImagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['images.disk' => 'images_local']);
        Storage::fake('images_local');
    }

    public function test_provider_can_add_multiple_private_offer_images_and_customer_can_view_them(): void
    {
        [$provider, $store, $customer, $order] = $this->setupOrder();

        $offerId = $this->actingAs($provider, 'sanctum')
            ->post("/api/provider/orders/{$order->id}/offer", [
                'store_id' => $store->id,
                'price' => 30000,
                'images' => [$this->image('front.png'), $this->image('back.png')],
            ])
            ->assertCreated()
            ->assertJsonCount(2, 'data.images')
            ->json('data.id');

        $offer = OrderOffer::findOrFail($offerId);
        $this->assertCount(2, $offer->images);
        foreach ($offer->images as $image) {
            Storage::disk('images_local')->assertExists($image->path);
            $this->assertStringNotContainsString($image->path, json_encode($offer->toArray()));
        }

        $imageUrl = $this->actingAs($customer, 'sanctum')
            ->getJson("/api/customer/orders/{$order->id}/offers")
            ->assertOk()
            ->json('data.0.images.0.url');
        $imagePath = parse_url($imageUrl, PHP_URL_PATH);
        $this->actingAs($customer, 'sanctum')->get($imagePath)->assertOk();

        $otherCustomer = User::factory()->customer()->create();
        $this->actingAs($otherCustomer, 'sanctum')->get($imagePath)->assertForbidden();
    }

    public function test_offer_images_are_replaced_only_when_sent_and_removed_with_hard_deleted_order(): void
    {
        [$provider, $store, , $order] = $this->setupOrder();
        $offerId = $this->actingAs($provider, 'sanctum')
            ->post("/api/provider/orders/{$order->id}/offer", [
                'store_id' => $store->id,
                'price' => 30000,
                'images' => [$this->image('first.png'), $this->image('second.png')],
            ])
            ->assertCreated()
            ->json('data.id');
        $offer = OrderOffer::findOrFail($offerId);
        $oldPaths = $offer->images->pluck('path')->all();

        $this->actingAs($provider, 'sanctum')
            ->putJson("/api/provider/orders/{$order->id}/offer/{$offer->id}", ['price' => 31000])
            ->assertOk();
        $this->assertCount(2, $offer->fresh()->images);

        $this->actingAs($provider, 'sanctum')
            ->put("/api/provider/orders/{$order->id}/offer/{$offer->id}", [
                'images' => [$this->image('replacement.png')],
            ])
            ->assertOk()
            ->assertJsonCount(1, 'data.images');
        foreach ($oldPaths as $path) {
            Storage::disk('images_local')->assertMissing($path);
        }
        $newPath = $offer->fresh()->images->sole()->path;
        Storage::disk('images_local')->assertExists($newPath);

        $order->forceDelete();
        Storage::disk('images_local')->assertMissing($newPath);
        $this->assertDatabaseMissing('offer_images', ['order_offer_id' => $offer->id]);
    }

    public function test_invalid_offer_images_are_rejected_without_creating_an_offer(): void
    {
        [$provider, $store, , $order] = $this->setupOrder();
        $this->actingAs($provider, 'sanctum')
            ->post("/api/provider/orders/{$order->id}/offer", [
                'store_id' => $store->id,
                'price' => 30000,
                'images' => [UploadedFile::fake()->create('not-image.txt', 1)],
            ])
            ->assertUnprocessable();

        $this->assertDatabaseCount('order_offers', 0);
        $this->assertDatabaseCount('offer_images', 0);
    }

    public function test_offer_history_and_photos_remain_readable_and_locked_through_payment_and_completion(): void
    {
        [$provider, $store, $customer, $order] = $this->setupOrder();
        $otherCustomer = User::factory()->customer()->create();
        $competingProvider = User::factory()->provider()->create();
        $competingStore = Store::factory()->create(['user_id' => $competingProvider->id]);
        $rejectedProvider = User::factory()->provider()->create();
        $rejectedStore = Store::factory()->create(['user_id' => $rejectedProvider->id]);
        $offers = [];

        foreach ([[$provider, $store], [$competingProvider, $competingStore], [$rejectedProvider, $rejectedStore]] as [$owner, $offerStore]) {
            $offerId = $this->actingAs($owner, 'sanctum')
                ->post("/api/provider/orders/{$order->id}/offer", [
                    'store_id' => $offerStore->id,
                    'price' => 30000,
                    'notes' => 'Complete mirror set',
                    'images' => [$this->image('mirrors.png')],
                ])->assertCreated()->json('data.id');
            $offers[] = OrderOffer::with('images')->findOrFail($offerId);
        }

        [$accepted, $notSelected, $rejected] = $offers;
        $this->actingAs($customer, 'sanctum')
            ->postJson("/api/customer/orders/{$order->id}/offers/{$rejected->id}/reject", [
                'rejection_reason' => 'Not suitable',
            ])->assertOk();
        $this->postJson("/api/customer/orders/{$order->id}/accept-offer", ['offer_id' => $accepted->id])
            ->assertOk();

        foreach ([OrderStatus::AwaitingPayment, OrderStatus::Paid, OrderStatus::Completed] as $status) {
            // Each stage represents a later visit, with its own API rate-limit window.
            $this->travel(1)->minutes();
            $this->actingAs($customer, 'sanctum');
            if ($status === OrderStatus::Paid) {
                $this->postJson("/api/customer/orders/{$order->id}/pay", [
                    'payment_method' => 'credit_card',
                    'card_token' => 'tok_test_history',
                ])->assertOk()->assertJsonPath('data.payment.amount', 30000);
            } elseif ($status === OrderStatus::Completed) {
                $this->postJson("/api/customer/orders/{$order->id}/received")
                    ->assertOk()->assertJsonCount(3, 'data.offers');
            }

            $this->getJson("/api/customer/orders/{$order->id}/offers")
                ->assertOk()->assertJsonCount(3, 'data');
            $this->getJson("/api/customer/orders/{$order->id}")
                ->assertOk()->assertJsonPath('data.status', $status->value)
                ->assertJsonPath('data.accepted_offer_id', $accepted->id)
                ->assertJsonCount(3, 'data.offers');
            $this->getJson('/api/customer/orders')
                ->assertOk()->assertJsonCount(3, 'data.0.offers');

            foreach ($offers as $index => $offer) {
                $this->travel(1)->minutes();
                $expectedStatus = ['accepted', 'not_selected', 'rejected'][$index];
                $image = $offer->images->sole();
                $response = $this->actingAs($customer, 'sanctum')
                    ->getJson("/api/customer/orders/{$order->id}/offers/{$offer->id}")
                    ->assertOk()->assertJsonPath('data.status', $expectedStatus)
                    ->assertJsonPath('data.price', 30000)
                    ->assertJsonPath('data.notes', 'Complete mirror set')
                    ->assertJsonPath('data.store.id', $offer->store_id)
                    ->assertJsonPath('data.rejection_reason', $index === 2 ? 'Not suitable' : null)
                    ->assertJsonPath('data.images.0.id', $image->id);
                $imagePath = parse_url($response->json('data.images.0.url'), PHP_URL_PATH);
                $this->get($imagePath)->assertOk();
                $this->postJson("/api/customer/orders/{$order->id}/offers/{$offer->id}/reject")
                    ->assertForbidden();

                $this->actingAs($offer->store->owner, 'sanctum')->get($imagePath)->assertOk();
                $this->putJson("/api/provider/orders/{$order->id}/offer/{$offer->id}", [
                    'price' => 1, 'notes' => 'Changed', 'images' => [],
                ])->assertForbidden();
                $this->deleteJson("/api/provider/orders/{$order->id}/offer/{$offer->id}")
                    ->assertForbidden();

                $this->actingAs($otherCustomer, 'sanctum')
                    ->getJson("/api/customer/orders/{$order->id}/offers/{$offer->id}")->assertForbidden();
                $this->get($imagePath)->assertForbidden();
                Storage::disk('images_local')->assertExists($image->path);
                $this->assertDatabaseHas('order_offers', [
                    'id' => $offer->id, 'deleted_at' => null, 'status' => $expectedStatus,
                    'price' => 30000, 'notes' => 'Complete mirror set',
                ]);
                $this->assertDatabaseHas('offer_images', ['id' => $image->id]);
            }

            $this->actingAs($otherCustomer, 'sanctum')
                ->getJson("/api/customer/orders/{$order->id}/offers")->assertForbidden();
            $this->actingAs($competingProvider, 'sanctum')
                ->get("/api/media/offers/{$accepted->id}/images/{$accepted->images->sole()->id}")
                ->assertForbidden();
            $this->actingAs($customer, 'sanctum')
                ->postJson("/api/customer/orders/{$order->id}/accept-offer", ['offer_id' => $notSelected->id])
                ->assertStatus(409);
        }
    }

    private function setupOrder(): array
    {
        $provider = User::factory()->provider()->create();
        $store = Store::factory()->create(['user_id' => $provider->id]);
        $customer = User::factory()->customer()->create();
        $order = Order::factory()->create([
            'customer_id' => $customer->id,
            'order_type' => OrderType::General,
        ]);

        return [$provider, $store, $customer, $order];
    }

    private function image(string $name): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVQIHWP4z8DwHwAF/gL+9/3K8QAAAABJRU5ErkJggg=='
        ));
    }
}

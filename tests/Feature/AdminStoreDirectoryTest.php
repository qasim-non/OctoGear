<?php

namespace Tests\Feature;

use App\Http\Resources\AdminStoreResource;
use App\Models\Admin;
use App\Models\CarCompany;
use App\Models\City;
use App\Models\Order;
use App\Models\Rating;
use App\Models\Store;
use App\Models\StoreRequest;
use App\Models\User;
use App\Services\AdminStoreService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AdminStoreDirectoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_city_combines_with_existing_filters_before_pagination(): void
    {
        $city = City::factory()->create();
        $owner = User::factory()->provider()->create();
        $match = Store::factory()->inactive()->create(['city_id' => $city->id, 'user_id' => $owner->id, 'name' => 'Blue Parts', 'mobile' => '+966511111111']);
        Store::factory()->inactive()->create(['user_id' => $owner->id, 'name' => 'Blue Parts', 'mobile' => '+966511111112']);
        Store::factory()->create(['city_id' => $city->id, 'user_id' => $owner->id, 'name' => 'Blue Active', 'mobile' => '+966511111113']);
        Store::factory()->inactive()->create(['city_id' => $city->id, 'name' => 'Blue Other', 'mobile' => '+966511111114']);
        $query = http_build_query(['city_id' => $city->id, 'owner_id' => $owner->id, 'name' => 'Blue', 'mobile' => '111111', 'status' => 'inactive']);
        $this->actingAs(Admin::factory()->create(), 'sanctum')->getJson('/api/admin/stores?'.$query)
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $match->id);
        $this->getJson('/api/admin/stores')->assertOk()->assertJsonPath('meta.total', 4);
    }

    public function test_invalid_city_and_page_are_rejected(): void
    {
        $city = City::factory()->create();
        $city->delete();
        $this->actingAs(Admin::factory()->create(), 'sanctum');
        foreach (['abc', '0', '-1', '1.5', '999999', (string) $city->id] as $id) {
            $this->getJson('/api/admin/stores?city_id='.$id)->assertUnprocessable()->assertJsonValidationErrors('city_id');
        }
        foreach (['0', '-1', '1.2', 'abc'] as $page) {
            $this->getJson('/api/admin/stores?page='.$page)->assertUnprocessable()->assertJsonValidationErrors('page');
        }
    }

    public function test_equal_creation_times_have_stable_pages_and_zero_remains_searchable(): void
    {
        $stores = Store::factory()->count(16)->sequence(fn ($sequence) => ['mobile' => '+9665111111'.str_replace('0', '9', (string) ($sequence->index + 11))])
            ->create(['created_at' => '2026-10-21 12:00:00', 'name' => 'Parts']);
        $last = $stores->last();
        $last->update(['name' => 'Parts 0', 'mobile' => '+966511111110']);
        $this->actingAs(Admin::factory()->create(), 'sanctum')->getJson('/api/admin/stores')
            ->assertOk()->assertJsonCount(15, 'data')->assertJsonPath('data.0.id', $last->id);
        $this->getJson('/api/admin/stores?page=2')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $stores->first()->id);
        foreach (['name', 'mobile'] as $field) {
            $this->getJson('/api/admin/stores?'.$field.'=0')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $last->id);
        }
    }

    public function test_details_include_localized_brands_ordered_media_and_pending_review(): void
    {
        $store = Store::factory()->create(['employee_name' => 'Store Contact', 'commercial_registration_number' => '1234567890', 'url_location' => 'https://example.test/location']);
        $brand = CarCompany::factory()->create(['name_en' => 'Toyota', 'name_ar' => 'تويوتا']);
        $store->companies()->attach($brand);
        $later = $store->pictures()->create(['disk' => 'images_local', 'path' => 'test/second.png', 'mime_type' => 'image/png', 'size_bytes' => 100, 'sort_order' => 1]);
        $first = $store->pictures()->create(['disk' => 'images_local', 'path' => 'test/first.png', 'mime_type' => 'image/png', 'size_bytes' => 100, 'sort_order' => 0]);
        $request = StoreRequest::factory()->create(['user_id' => $store->user_id, 'store_id' => $store->id, 'pending_store_id' => $store->id]);
        $this->actingAs(Admin::factory()->create(), 'sanctum');
        foreach (['en' => 'Toyota', 'ar' => 'تويوتا'] as $language => $label) {
            $this->getJson('/api/admin/stores/'.$store->id, ['Accept-Language' => $language])->assertOk()
                ->assertJsonPath('data.employee_name', 'Store Contact')->assertJsonPath('data.commercial_registration_number', '1234567890')
                ->assertJsonPath('data.url_location', 'https://example.test/location')->assertJsonPath('data.companies.0.name', $label)
                ->assertJsonPath('data.pending_change_request_id', $request->id)->assertJsonPath('data.pictures.0.id', $first->id)
                ->assertJsonPath('data.pictures.1.id', $later->id)
                ->assertJsonPath('data.pictures.0.url', '/api/media/stores/'.$store->id.'/pictures/'.$first->id)
                ->assertJsonMissingPath('data.pictures.0.path')->assertJsonMissingPath('data.pictures.0.disk');
        }
        $this->getJson('/api/admin/stores')->assertOk()->assertJsonMissingPath('data.0.pictures')->assertJsonMissingPath('data.0.companies');
    }

    public function test_rating_serialization_uses_prepared_aggregates_with_no_extra_queries(): void
    {
        $store = Store::factory()->create();
        foreach ([4, 5] as $rating) {
            $order = Order::factory()->create(['customer_id' => User::factory()->customer()]);
            Rating::factory()->create(['store_id' => $store->id, 'customer_id' => $order->customer_id, 'order_id' => $order->id, 'rating' => $rating]);
        }
        $service = app(AdminStoreService::class);
        foreach ([$service->index([])->first(), $service->show($store)] as $prepared) {
            DB::enableQueryLog();
            DB::flushQueryLog();
            $data = (new AdminStoreResource($prepared))->response()->getData(true)['data'];
            $this->assertSame(4.5, $data['average_rating']);
            $this->assertSame([], DB::getQueryLog());
            DB::disableQueryLog();
        }
        $store->city->delete();
        $this->actingAs(Admin::factory()->create(), 'sanctum')->getJson('/api/admin/stores/'.$store->id)
            ->assertOk()->assertJsonPath('data.city', null);
    }

    public function test_status_changes_preserve_owner_and_pending_proposal_and_repeat_safely(): void
    {
        $store = Store::factory()->create();
        $request = StoreRequest::factory()->create(['user_id' => $store->user_id, 'store_id' => $store->id, 'pending_store_id' => $store->id]);
        $ownerBefore = $store->owner->getAttributes();
        $this->actingAs(Admin::factory()->create(), 'sanctum');
        foreach (['inactive', 'inactive', 'active'] as $status) {
            $this->patchJson('/api/admin/stores/'.$store->id.'/status', ['status' => $status])->assertOk()
                ->assertJsonPath('data.status', $status)->assertJsonPath('data.pending_change_request_id', $request->id)
                ->assertJsonPath('data.name', $store->name)->assertJsonPath('data.mobile', $store->mobile);
        }
        $this->assertSame($ownerBefore, $store->owner->fresh()->getAttributes());
        $this->assertDatabaseHas('store_requests', ['id' => $request->id, 'request_status' => 'pending']);
    }

    public function test_store_detail_and_activation_remain_admin_only(): void
    {
        $store = Store::factory()->create();
        $this->getJson('/api/admin/stores/'.$store->id)->assertUnauthorized();
        foreach ([User::factory()->customer()->create(), User::factory()->provider()->create(), Admin::factory()->blocked()->create()] as $actor) {
            $this->actingAs($actor, 'sanctum')->getJson('/api/admin/stores/'.$store->id)->assertForbidden();
            $this->patchJson('/api/admin/stores/'.$store->id.'/status', ['status' => 'inactive'])->assertForbidden();
        }
        $this->assertDatabaseHas('stores', ['id' => $store->id, 'status' => 'active']);
    }
}

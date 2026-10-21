<?php

namespace Tests\Feature;

use App\Http\Resources\AdminStoreRequestResource;
use App\Models\Admin;
use App\Models\CarCompany;
use App\Models\City;
use App\Models\Store;
use App\Models\StoreRequest;
use App\Models\User;
use App\Services\AdminStoreRequestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AdminStoreRequestReviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_queue_filters_are_combined_and_searches_include_the_applicant(): void
    {
        $city = City::factory()->create();
        $owner = User::factory()->create(['full_name' => 'Review Applicant', 'mobile' => '+966555123456']);
        $store = Store::factory()->create(['user_id' => $owner->id]);
        $match = StoreRequest::factory()->create(['user_id' => $owner->id, 'city_id' => $city->id, 'store_id' => $store->id, 'name' => 'Unique Parts', 'mobile' => '+966555987654']);
        StoreRequest::factory()->accepted()->create(['user_id' => $owner->id, 'city_id' => $city->id, 'store_id' => $store->id]);
        StoreRequest::factory()->create(['user_id' => $owner->id, 'city_id' => $city->id]);
        StoreRequest::factory()->create(['user_id' => $owner->id, 'store_id' => $store->id]);
        $this->actingAs(Admin::factory()->create(), 'sanctum');

        foreach (['Review Applicant', '555123456', 'Unique Parts', '555987654'] as $search) {
            $query = http_build_query(['status' => 'pending', 'city_id' => $city->id, 'request_type' => 'store_change', 'search' => $search]);
            $this->getJson('/api/admin/store-requests?'.$query)->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $match->id);
        }
        $this->getJson('/api/admin/store-requests?request_type=new_store')->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_queue_pagination_is_stable_for_equal_submission_times(): void
    {
        $requests = StoreRequest::factory()->count(16)->create(['created_at' => '2026-10-21 10:00:00']);
        $this->actingAs(Admin::factory()->create(), 'sanctum')
            ->getJson('/api/admin/store-requests')->assertOk()->assertJsonCount(15, 'data')
            ->assertJsonPath('meta.total', 16)->assertJsonPath('data.0.id', $requests->last()->id);
        $this->getJson('/api/admin/store-requests?page=2')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $requests->first()->id);
    }

    public function test_zero_is_a_search_term_instead_of_an_empty_filter(): void
    {
        $owner = User::factory()->create(['full_name' => 'Applicant', 'mobile' => '+966511111111']);
        $match = StoreRequest::factory()->create(['user_id' => $owner->id, 'name' => 'Parts 0', 'mobile' => '+966522222222']);
        StoreRequest::factory()->create(['user_id' => $owner->id, 'name' => 'Other parts', 'mobile' => '+966533333333']);
        $this->actingAs(Admin::factory()->create(), 'sanctum')->getJson('/api/admin/store-requests?search=0')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $match->id);
    }

    public function test_invalid_queue_filters_are_rejected(): void
    {
        $city = City::factory()->create();
        $city->delete();
        $this->actingAs(Admin::factory()->create(), 'sanctum');
        foreach (['search' => str_repeat('x', 101), 'city_id' => $city->id, 'request_type' => 'anything', 'page' => 0] as $field => $value) {
            $this->getJson('/api/admin/store-requests?'.http_build_query([$field => $value]))->assertUnprocessable()->assertJsonValidationErrors($field);
        }
    }

    public function test_details_prepare_localized_historical_labels_without_resource_queries(): void
    {
        $brand = CarCompany::factory()->create(['name_en' => 'Historic Brand', 'name_ar' => 'علامة سابقة']);
        $city = City::factory()->create(['name_en' => 'Historic City', 'name_ar' => 'مدينة سابقة']);
        $request = StoreRequest::factory()->create(['company_ids' => [$brand->id], 'previous_details' => ['company_ids' => [$brand->id], 'city_id' => $city->id]]);
        $brand->delete();
        $city->delete();
        $this->actingAs(Admin::factory()->create(), 'sanctum')->withHeader('Accept-Language', 'en')
            ->getJson('/api/admin/store-requests/'.$request->id)->assertOk()
            ->assertJsonPath('data.review_companies.0.name', 'Historic Brand')->assertJsonPath('data.previous_city.name', 'Historic City');
        $this->withHeader('Accept-Language', 'ar')->getJson('/api/admin/store-requests/'.$request->id)->assertOk()
            ->assertJsonPath('data.review_companies.0.name', 'علامة سابقة')->assertJsonPath('data.previous_city.name', 'مدينة سابقة');

        $prepared = app(AdminStoreRequestService::class)->show($request->fresh());
        DB::enableQueryLog();
        DB::flushQueryLog();
        (new AdminStoreRequestResource($prepared))->resolve(request());
        $this->assertSame([], DB::getQueryLog());
        DB::disableQueryLog();
    }

    public function test_deleted_city_does_not_break_request_details(): void
    {
        $request = StoreRequest::factory()->create();
        $request->city->delete();
        $this->actingAs(Admin::factory()->create(), 'sanctum')->getJson('/api/admin/store-requests/'.$request->id)
            ->assertOk()->assertJsonPath('data.user.id', $request->user_id)->assertJsonPath('data.city', null);
    }
}

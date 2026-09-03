<?php

namespace Tests\Feature;

use App\Enums\RequestStatus;
use App\Models\City;
use App\Models\StoreRequest;
use App\Models\User;
use App\Services\OtpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class ProviderStoreRequestTest extends TestCase
{
    use RefreshDatabase;

    private function storeRequestPayload(City $city, string $tempToken): array
    {
        return [
            'temp_token'                       => $tempToken,
            'name'                             => 'AlFaris Auto Parts',
            'nick_name'                        => 'AlFaris',
            'employee_name'                    => 'John Doe',
            'url_location'                     => 'https://maps.example.com/location',
            'commercial_registration_number'   => '1234567890',
            'commercial_registration_picture'  => 'uploads/reg/abc.jpg',
            'city_id'                          => $city->id,
        ];
    }

    public function test_provider_can_send_store_mobile_otp(): void
    {
        $provider = User::factory()->provider()->create();

        $mock = Mockery::mock(OtpService::class);
        $mock->shouldReceive('sendOtp')->once()->with('+966555555555');
        $this->app->instance(OtpService::class, $mock);

        $this->actingAs($provider, 'sanctum')
            ->postJson('/api/provider/store-requests/verify-mobile', ['mobile' => '0555555555'])
            ->assertOk();
    }

    public function test_provider_can_verify_store_mobile_otp_and_get_temp_token(): void
    {
        $provider = User::factory()->provider()->create();

        $mock = Mockery::mock(OtpService::class);
        $mock->shouldReceive('verifyOtp')->once()->with('+966555555555', '1234')->andReturn(true);
        $mock->shouldReceive('createPendingToken')->once()->with('store', '+966555555555')->andReturn('store-token');
        $this->app->instance(OtpService::class, $mock);

        $this->actingAs($provider, 'sanctum')
            ->postJson('/api/provider/store-requests/verify-code', ['mobile' => '0555555555', 'otp' => '1234'])
            ->assertOk()
            ->assertJsonPath('data.temp_token', 'store-token');
    }

    public function test_provider_can_submit_a_store_request_with_verified_mobile(): void
    {
        $city = City::factory()->create();
        $provider = User::factory()->provider()->create();
        $tempToken = $this->storePendingToken('+966555555555');

        $response = $this->actingAs($provider, 'sanctum')
            ->postJson('/api/provider/store-requests', $this->storeRequestPayload($city, $tempToken))
            ->assertStatus(201)
            ->assertJsonPath('data.name', 'AlFaris Auto Parts')
            ->assertJsonPath('data.mobile', '+966555555555')
            ->assertJsonPath('data.request_status', RequestStatus::Pending->value)
            ->assertJsonPath('data.city.id', $city->id);

        $this->assertDatabaseHas('store_requests', [
            'user_id'   => $provider->id,
            'name'      => 'AlFaris Auto Parts',
            'mobile'    => '+966555555555',
            'request_status' => RequestStatus::Pending->value,
        ]);
    }

    public function test_provider_cannot_submit_store_request_with_invalid_temp_token(): void
    {
        $city = City::factory()->create();
        $provider = User::factory()->provider()->create();

        $this->actingAs($provider, 'sanctum')
            ->postJson('/api/provider/store-requests', $this->storeRequestPayload($city, 'bad-token'))
            ->assertStatus(422);

        $this->assertDatabaseCount('store_requests', 0);
    }

    public function test_temp_token_is_single_use(): void
    {
        $city = City::factory()->create();
        $provider = User::factory()->provider()->create();
        $tempToken = $this->storePendingToken('+966555555555');

        $payload = $this->storeRequestPayload($city, $tempToken);

        $this->actingAs($provider, 'sanctum')
            ->postJson('/api/provider/store-requests', $payload)
            ->assertStatus(201);

        $this->actingAs($provider, 'sanctum')
            ->postJson('/api/provider/store-requests', $payload)
            ->assertStatus(422);

        $this->assertDatabaseCount('store_requests', 1);
    }

    public function test_provider_can_list_their_store_requests(): void
    {
        $provider = User::factory()->provider()->create();
        StoreRequest::factory()->count(2)->create(['user_id' => $provider->id]);
        StoreRequest::factory()->create(['user_id' => User::factory()->provider()]);

        $this->actingAs($provider, 'sanctum')
            ->getJson('/api/provider/store-requests')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_provider_can_view_one_of_their_store_requests(): void
    {
        $provider = User::factory()->provider()->create();
        $city = City::factory()->create();
        $storeRequest = StoreRequest::factory()->create([
            'user_id' => $provider->id,
            'city_id' => $city->id,
        ]);

        $this->actingAs($provider, 'sanctum')
            ->getJson("/api/provider/store-requests/{$storeRequest->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $storeRequest->id)
            ->assertJsonPath('data.city.id', $city->id);
    }

    public function test_provider_cannot_view_another_providers_store_request(): void
    {
        $provider = User::factory()->provider()->create();
        $other = StoreRequest::factory()->create(['user_id' => User::factory()->provider()]);

        $this->actingAs($provider, 'sanctum')
            ->getJson("/api/provider/store-requests/{$other->id}")
            ->assertStatus(403);
    }

    public function test_customer_cannot_access_store_requests(): void
    {
        $customer = User::factory()->customer()->create();

        $this->actingAs($customer, 'sanctum')
            ->getJson('/api/provider/store-requests')
            ->assertStatus(403);

        $this->actingAs($customer, 'sanctum')
            ->postJson('/api/provider/store-requests', [])
            ->assertStatus(403);
    }

    public function test_submitting_store_request_validates_required_fields(): void
    {
        $provider = User::factory()->provider()->create();

        $this->actingAs($provider, 'sanctum')
            ->postJson('/api/provider/store-requests', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['temp_token', 'name', 'nick_name', 'employee_name', 'city_id']);
    }

    public function test_submitting_store_request_validates_city_exists(): void
    {
        $provider = User::factory()->provider()->create();
        $tempToken = $this->storePendingToken('+966555555555');
        $payload = $this->storeRequestPayload(City::factory()->create(), $tempToken);
        $payload['city_id'] = 999999;

        $this->actingAs($provider, 'sanctum')
            ->postJson('/api/provider/store-requests', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors('city_id');
    }

    private function storePendingToken(string $mobile): string
    {
        return $this->app->make(OtpService::class)->createPendingToken('store', $mobile);
    }
}

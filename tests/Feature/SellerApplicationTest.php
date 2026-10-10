<?php

namespace Tests\Feature;

use App\Enums\RequestStatus;
use App\Models\Admin;
use App\Models\CarCompany;
use App\Models\City;
use App\Models\Store;
use App\Models\StoreRequest;
use App\Models\User;
use App\Services\OtpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SellerApplicationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(config('images.disk'));
    }

    private function payload(): array
    {
        return [
            'name' => 'Octo Parts', 'nick_name' => 'Octo', 'employee_name' => 'Test Owner',
            'url_location' => 'https://maps.example.test/store',
            'commercial_registration_number' => '1234567890',
            'city_id' => City::factory()->create()->id,
            'commercial_registration_picture' => UploadedFile::fake()->createWithContent('registration.png', base64_decode(
                'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVQIHWP4z8DwHwAF/gL+9/3K8QAAAABJRU5ErkJggg=='
            )),
        ];
    }

    private function submit(User $customer, array $extra = []): StoreRequest
    {
        $token = app(OtpService::class)->createPendingToken('store', '+966555555555');
        $this->actingAs($customer, 'sanctum')->postJson('/api/provider/store-requests',
            [...$this->payload(), ...$extra, 'temp_token' => $token])->assertCreated()->assertJsonPath('data.type', 'customer');

        return $customer->storeRequests()->firstOrFail();
    }

    public function test_application_keeps_customer_access_and_approval_changes_next_profile_read(): void
    {
        $customer = User::factory()->customer()->create(['mobile' => '+966511111111']);
        $company = CarCompany::factory()->create();
        $this->actingAs($customer, 'sanctum')->getJson('/api/seller-application')->assertOk()->assertJsonPath('data', null);
        $application = $this->submit($customer, ['company_ids' => [$company->id]]);
        $this->actingAs($customer->fresh(), 'sanctum')->getJson('/api/profile')->assertOk()->assertJsonPath('data.type', 'customer');
        $this->getJson('/api/customer/orders')->assertOk();
        $this->getJson('/api/seller-application')->assertOk()->assertJsonPath('data.id', $application->id)
            ->assertJsonPath('data.company_ids.0', $company->id);
        $this->getJson('/api/provider/stores')->assertForbidden();
        $this->actingAs(Admin::factory()->create(), 'sanctum')->postJson("/api/admin/store-requests/{$application->id}/accept")->assertOk();
        $this->actingAs($customer->fresh(), 'sanctum')->getJson('/api/profile')->assertOk()->assertJsonPath('data.type', 'service provider');
        $this->getJson('/api/provider/stores')->assertOk();
        $this->assertTrue(Store::where('user_id', $customer->id)->firstOrFail()->companies()->whereKey($company->id)->exists());
    }

    public function test_customer_can_correct_rejection_retain_document_and_stay_customer(): void
    {
        $customer = User::factory()->customer()->create(['mobile' => '+966511111111']);
        $application = $this->submit($customer);
        $oldPath = $application->commercial_registration_path;
        $this->actingAs(Admin::factory()->create(), 'sanctum')->postJson("/api/admin/store-requests/{$application->id}/reject", ['reason' => 'Please correct the store name'])->assertOk();
        $this->actingAs($customer->fresh(), 'sanctum')->getJson('/api/seller-application')->assertOk()
            ->assertJsonPath('data.rejection_reason', 'Please correct the store name');
        $payload = $this->payload();
        unset($payload['commercial_registration_picture']);
        $this->postJson("/api/customer/seller-application/{$application->id}/resubmit", [...$payload, 'name' => 'Corrected Store'])
            ->assertOk()->assertJsonPath('data.request_status', 'pending')->assertJsonPath('data.rejection_reason', null);
        $this->assertSame($oldPath, $application->fresh()->commercial_registration_path);
        Storage::disk(config('images.disk'))->assertExists($oldPath);
        $this->assertSame('customer', $customer->fresh()->type->value);
        $this->assertSame('+966555555555', $application->fresh()->mobile);
        $this->postJson("/api/customer/seller-application/{$application->id}/resubmit", $payload)->assertStatus(409);
        $this->assertDatabaseCount('store_requests', 1);
    }

    public function test_submission_cannot_create_multiple_first_applications(): void
    {
        $customer = User::factory()->customer()->create(['mobile' => '+966511111111']);
        $this->submit($customer);
        $token = app(OtpService::class)->createPendingToken('store', '+966522222222');
        $this->postJson('/api/provider/store-requests', [...$this->payload(), 'temp_token' => $token])->assertStatus(409);
        $this->assertDatabaseCount('store_requests', 1);
    }

    public function test_correction_can_replace_document_and_clear_manufacturers_before_approval(): void
    {
        $customer = User::factory()->customer()->create(['mobile' => '+966511111111']);
        $company = CarCompany::factory()->create();
        $application = $this->submit($customer, ['company_ids' => [$company->id]]);
        $oldPath = $application->commercial_registration_path;
        $application->update(['request_status' => RequestStatus::Rejected]);
        $this->postJson("/api/customer/seller-application/{$application->id}/resubmit",
            [...$this->payload(), 'company_ids' => ''])->assertOk()->assertJsonPath('data.company_ids', []);
        $application->refresh();
        $this->assertNotSame($oldPath, $application->commercial_registration_path);
        Storage::disk(config('images.disk'))->assertExists($application->commercial_registration_path);
        $this->actingAs(Admin::factory()->create(), 'sanctum')
            ->postJson("/api/admin/store-requests/{$application->id}/accept")->assertOk();
        $this->assertSame(0, Store::where('user_id', $customer->id)->firstOrFail()->companies()->count());
    }

    public function test_customer_cannot_read_or_correct_another_application_or_change_verified_mobile(): void
    {
        $customer = User::factory()->customer()->create(['mobile' => '+966511111111']);
        $application = $this->submit($customer);
        $application->update(['request_status' => RequestStatus::Rejected]);
        $payload = $this->payload();
        $this->actingAs(User::factory()->customer()->create(), 'sanctum')->getJson('/api/seller-application')->assertOk()->assertJsonPath('data', null);
        $this->postJson("/api/customer/seller-application/{$application->id}/resubmit", $payload)->assertForbidden();
        $this->actingAs($customer->fresh(), 'sanctum')->postJson("/api/customer/seller-application/{$application->id}/resubmit",
            [...$payload, 'mobile' => '+966533333333'])->assertUnprocessable()->assertJsonValidationErrors('mobile');
    }

    public function test_store_code_is_exposed_only_in_opted_in_local_or_staging(): void
    {
        $customer = User::factory()->customer()->create(['mobile' => '+966511111111']);
        config(['otp.expose_for_testing' => true]);
        foreach (['local', 'staging', 'production'] as $environment) {
            $this->app->instance('env', $environment);
            $response = $this->actingAs($customer, 'sanctum')->postJson('/api/provider/store-requests/verify-mobile', ['mobile' => '0555555555'])->assertOk();
            $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
            if ($environment === 'production') {
                $response->assertJsonMissingPath('data.test_otp');
            } else {
                $code = $response->json('data.test_otp');
                $this->assertMatchesRegularExpression('/^[0-9]{4}$/', $code);
                $this->postJson('/api/provider/store-requests/verify-code', ['mobile' => '0555555555', 'otp' => $code])->assertOk()->assertJsonStructure(['data' => ['temp_token']]);
            }
        }
    }

    public function test_failed_approval_does_not_promote_customer(): void
    {
        $customer = User::factory()->customer()->create(['mobile' => '+966511111111']);
        $application = $this->submit($customer);
        Store::factory()->create(['mobile' => $application->mobile]);
        $this->actingAs(Admin::factory()->create(), 'sanctum')->postJson("/api/admin/store-requests/{$application->id}/accept")->assertUnprocessable();
        $this->assertSame('customer', $customer->fresh()->type->value);
        $this->assertSame(RequestStatus::Pending, $application->fresh()->request_status);
    }
}

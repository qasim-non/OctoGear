<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Store;
use App\Models\StoreRequest;
use App\Models\StoresCar;
use App\Models\User;
use App\Services\ImageStorageService;
use App\Services\StoreMediaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MediaAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['images.disk' => 'images_local']);
        Storage::fake('images_local');
    }

    public function test_registration_documents_are_available_only_to_the_owner_and_active_admins(): void
    {
        $owner = User::factory()->provider()->create();
        $store = Store::factory()->create(['user_id' => $owner->id, ...StoreMediaService::registrationAttributes($this->image())]);
        $request = StoreRequest::factory()->create(['user_id' => $owner->id, ...StoreMediaService::registrationAttributes($this->image())]);

        foreach ([route('media.store-registration.show', $store, false), route('media.store-request-registration.show', $request, false)] as $url) {
            $this->actingAs($owner, 'sanctum')->get($url)->assertOk()->assertHeader('Content-Type', 'image/png');
            $this->actingAs(User::factory()->provider()->create(), 'sanctum')->getJson($url)->assertForbidden();
            $this->actingAs(User::factory()->customer()->create(), 'sanctum')->getJson($url)->assertForbidden();
            $this->actingAs(Admin::factory()->create(), 'sanctum')->get($url)->assertOk();
            $this->actingAs(Admin::factory()->blocked()->create(), 'sanctum')->getJson($url)->assertForbidden();
            $this->app['auth']->forgetGuards();
            $this->getJson($url)->assertUnauthorized();
        }
        $this->assertArrayNotHasKey('commercial_registration_path', $store->toArray());
        $this->assertArrayNotHasKey('commercial_registration_disk', $request->toArray());
    }

    public function test_gallery_routes_require_authentication_and_matching_parent_records(): void
    {
        $store = Store::factory()->create();
        $otherStore = Store::factory()->create();
        $picture = $store->pictures()->create($this->image());
        $car = StoresCar::factory()->create(['store_id' => $store->id]);
        $carPicture = $car->pictures()->create($this->image());
        $storeUrl = route('media.store-pictures.show', [$store, $picture], false);
        $carUrl = route('media.store-car-pictures.show', [$store, $car, $carPicture], false);

        $this->getJson($storeUrl)->assertUnauthorized();
        $this->getJson($carUrl)->assertUnauthorized();
        $this->actingAs(User::factory()->customer()->create(), 'sanctum');
        $this->get($storeUrl)->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->get($carUrl)->assertOk();
        $this->getJson(route('media.store-pictures.show', [$otherStore, $picture], false))->assertNotFound();
        $this->getJson(route('media.store-car-pictures.show', [$otherStore, $car, $carPicture], false))->assertNotFound();
        $otherCar = StoresCar::factory()->create(['store_id' => $store->id]);
        $this->getJson(route('media.store-car-pictures.show', [$store, $otherCar, $carPicture], false))->assertNotFound();

        $this->actingAs(User::factory()->blocked()->customer()->create(), 'sanctum');
        $this->getJson($storeUrl)->assertForbidden();
        $this->getJson($carUrl)->assertForbidden();

        $this->actingAs($store->owner, 'sanctum');
        $car->delete();
        $this->getJson($carUrl)->assertNotFound();
        $store->delete();
        $this->getJson($storeUrl)->assertNotFound();
    }

    public function test_missing_files_and_legacy_gallery_paths_return_not_found(): void
    {
        $store = Store::factory()->create();
        $picture = $store->pictures()->create($this->image());
        Storage::disk($picture->disk)->delete($picture->path);
        $legacyPicture = $store->pictures()->create(['path' => 'https://example.test/legacy.png']);

        $this->actingAs($store->owner, 'sanctum');
        $this->getJson(route('media.store-pictures.show', [$store, $picture], false))->assertNotFound();
        $this->getJson(route('media.store-pictures.show', [$store, $legacyPicture], false))->assertNotFound();
    }

    private function image(): array
    {
        $stored = [];

        return app(ImageStorageService::class)->store(
            UploadedFile::fake()->createWithContent('photo.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVQIHWP4z8DwHwAF/gL+9/3K8QAAAABJRU5ErkJggg==')),
            'test-media',
            $stored,
        );
    }
}

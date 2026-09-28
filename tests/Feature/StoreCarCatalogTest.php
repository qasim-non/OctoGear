<?php

namespace Tests\Feature;

use App\Enums\StoreStatus;
use App\Models\CarCompany;
use App\Models\CarName;
use App\Models\CarSection;
use App\Models\Component;
use App\Models\Store;
use App\Models\StoreCarComponent;
use App\Models\StoreCarSection;
use App\Models\StoresCar;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StoreCarCatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_localized_car_detail_is_public_safe_and_includes_condition_report(): void
    {
        $company = CarCompany::factory()->create(['name_en' => 'Toyota', 'name_ar' => 'تويوتا']);
        $name = CarName::factory()->create(['car_company_id' => $company->id]);
        $car = StoresCar::factory()->create(['car_name_id' => $name->id]);
        $section = CarSection::factory()->create(['name_en' => 'Engine', 'name_ar' => 'المحرك']);
        StoreCarSection::factory()->create(['store_car_id' => $car->id, 'section_id' => $section->id, 'condition' => 'damaged']);
        StoreCarComponent::factory()->create(['store_car_id' => $car->id]);
        $this->actingAs(User::factory()->create(['type' => 'customer']), 'sanctum');

        foreach (['en' => ['Toyota', 'Engine'], 'ar' => ['تويوتا', 'المحرك']] as $locale => $labels) {
            $this->withHeader('Accept-Language', $locale)
                ->getJson("/api/stores/{$car->store_id}/cars/{$car->id}")
                ->assertOk()->assertJsonPath('data.company.name', $labels[0])
                ->assertJsonPath('data.sections.0.name', $labels[1])
                ->assertJsonPath('data.sections.0.condition', 'damaged')
                ->assertJsonPath('data.components_count', 1)
                ->assertJsonMissingPath('data.vehicle_plat_number')
                ->assertJsonMissingPath('data.can_manage');
        }
        $this->getJson("/api/stores/{$car->store_id}/cars")
            ->assertOk()->assertJsonMissingPath('data.0.vehicle_plat_number')
            ->assertJsonMissingPath('data.0.can_manage');
    }

    public function test_components_include_money_units_localized_section_and_zero_stock(): void
    {
        $car = StoresCar::factory()->create();
        $section = CarSection::factory()->create(['name_en' => 'Engine', 'name_ar' => 'المحرك']);
        $reference = Component::factory()->create(['section_id' => $section->id, 'name_en' => 'Alternator', 'name_ar' => 'دينمو']);
        StoreCarComponent::factory()->create(['store_car_id' => $car->id, 'component_id' => $reference->id,
            'price' => 52025, 'stock_quantity' => 0, 'warranty_months' => null, 'description' => null, 'part_number' => 'ALT-20']);
        $this->actingAs(User::factory()->create(), 'sanctum');
        foreach (['en' => ['Alternator', 'Engine'], 'ar' => ['دينمو', 'المحرك']] as $locale => $labels) {
            $this->withHeader('Accept-Language', $locale)
                ->getJson("/api/stores/{$car->store_id}/cars/{$car->id}/components")
                ->assertOk()->assertJsonPath('data.0.component.name', $labels[0])
                ->assertJsonPath('data.0.section.name', $labels[1])
                ->assertJsonPath('data.0.price', 52025)->assertJsonPath('data.0.price_scale', 100)
                ->assertJsonPath('data.0.currency', 'SAR')->assertJsonPath('data.0.stock_quantity', 0)
                ->assertJsonPath('data.0.description', null)->assertJsonPath('data.0.warranty_months', null)
                ->assertJsonPath('meta.total', 1);
        }
    }

    public function test_pagination_is_stable_and_excludes_removed_stock_and_references(): void
    {
        $car = StoresCar::factory()->create();
        StoreCarComponent::factory()->count(17)->create(['store_car_id' => $car->id]);
        $removed = StoreCarComponent::factory()->create(['store_car_id' => $car->id]);
        $removed->delete();
        $orphan = StoreCarComponent::factory()->create(['store_car_id' => $car->id]);
        $orphan->component->delete();
        $this->actingAs(User::factory()->create(), 'sanctum');
        $url = "/api/stores/{$car->store_id}/cars/{$car->id}";
        $first = $this->getJson("{$url}/components")->assertOk()->assertJsonCount(15, 'data')->assertJsonPath('meta.total', 17);
        $second = $this->getJson("{$url}/components?page=2")->assertOk()->assertJsonCount(2, 'data');
        $this->assertCount(17, array_unique(array_merge(array_column($first->json('data'), 'id'), array_column($second->json('data'), 'id'))));
        $this->getJson($url)->assertOk()->assertJsonPath('data.components_count', 17);
        $this->getJson("{$url}/components/{$orphan->id}")->assertNotFound();
    }

    public function test_catalog_enforces_authentication_active_store_and_nested_ownership(): void
    {
        $car = StoresCar::factory()->create();
        $part = StoreCarComponent::factory()->create(['store_car_id' => $car->id]);
        $url = "/api/stores/{$car->store_id}/cars/{$car->id}";
        foreach ([$url, "{$url}/components", "{$url}/components/{$part->id}"] as $path) {
            $this->getJson($path)->assertUnauthorized();
        }
        $this->actingAs(User::factory()->create(), 'sanctum');
        $foreignStore = Store::factory()->create();
        $foreignPart = StoreCarComponent::factory()->create();
        $this->getJson("{$url}/components/{$foreignPart->id}")->assertNotFound();
        foreach (['', '/components', "/components/{$part->id}"] as $suffix) {
            $this->getJson("/api/stores/{$foreignStore->id}/cars/{$car->id}{$suffix}")->assertNotFound();
        }
        $car->store->update(['status' => StoreStatus::Inactive]);
        foreach ([$url, "{$url}/components", "{$url}/components/{$part->id}"] as $path) {
            $this->getJson($path)->assertNotFound();
        }
    }

    public function test_empty_inventory_and_removed_sections_remain_valid_resources(): void
    {
        $car = StoresCar::factory()->create();
        $report = StoreCarSection::factory()->create(['store_car_id' => $car->id]);
        $report->section->delete();
        $this->actingAs(User::factory()->create(), 'sanctum');
        $url = "/api/stores/{$car->store_id}/cars/{$car->id}";
        $this->getJson($url)->assertOk()->assertJsonCount(0, 'data.sections');
        $this->getJson("{$url}/components")->assertOk()->assertJsonCount(0, 'data')->assertJsonPath('meta.total', 0);
    }
}

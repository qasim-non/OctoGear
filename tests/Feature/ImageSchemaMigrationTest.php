<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Store;
use App\Models\StoreRequest;
use App\Models\StoresCar;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ImageSchemaMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_image_migration_and_rollback_preserve_existing_values_without_trusting_them(): void
    {
        $store = Store::factory()->create(['commercial_registration_path' => 'old/store.png']);
        $request = StoreRequest::factory()->create(['commercial_registration_path' => 'https://example.test/document.png']);
        $car = StoresCar::factory()->create(['store_id' => $store->id]);
        $storePicture = $store->pictures()->create(['path' => 'old/gallery.png']);
        $carPicture = $car->pictures()->create(['path' => 'old/car.png']);
        $order = Order::factory()->create(['customer_id' => User::factory()->customer()->create()->id, 'customer_image_path' => 'old/order.png']);
        $migration = require database_path('migrations/2026_09_26_160000_standardize_image_storage.php');

        $migration->down();

        $cases = [
            ['stores', $store->id, 'commercial_registration_picture', 'commercial_registration_path', 'commercial_registration_disk', 'old/store.png'],
            ['store_requests', $request->id, 'commercial_registration_picture', 'commercial_registration_path', 'commercial_registration_disk', 'https://example.test/document.png'],
            ['store_pictures', $storePicture->id, 'picture', 'path', 'disk', 'old/gallery.png'],
            ['store_car_pictures', $carPicture->id, 'picture', 'path', 'disk', 'old/car.png'],
            ['orders', $order->id, 'customer_image', 'customer_image_path', 'customer_image_disk', 'old/order.png'],
        ];
        foreach ($cases as [$table, $id, $oldColumn, $newColumn, $diskColumn, $path]) {
            $this->assertSame($path, DB::table($table)->where('id', $id)->value($oldColumn));
        }

        $migration->up();

        foreach ($cases as [$table, $id, $oldColumn, $newColumn, $diskColumn, $path]) {
            $row = DB::table($table)->find($id);
            $this->assertSame($path, $row->$newColumn);
            $this->assertNull($row->$diskColumn);
        }
        $this->assertCount(0, $store->fresh()->pictures);
        $this->assertCount(0, $car->fresh()->pictures);
        $this->assertNull($order->fresh()->customerImageUrl());
    }
}

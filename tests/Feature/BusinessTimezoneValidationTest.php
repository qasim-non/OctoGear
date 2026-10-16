<?php

namespace Tests\Feature;

use App\Enums\OrderType;
use App\Http\Requests\Customer\StoreCustomerCarRequest;
use App\Http\Requests\Customer\StoreOrderRequest;
use App\Http\Requests\Customer\UpdateCustomerCarRequest;
use App\Http\Requests\Customer\UpdateOrderRequest;
use App\Http\Requests\Provider\CreateProviderStoreCarRequest;
use App\Http\Requests\Provider\UpdateProviderStoreCarRequest;
use App\Models\Order;
use Carbon\CarbonImmutable;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class BusinessTimezoneValidationTest extends TestCase
{
    public function test_all_vehicle_year_limits_follow_the_configured_calendar_at_new_year(): void
    {
        // It is already January 1 in Saudi Arabia but still December 31 in New York.
        $this->travelTo(CarbonImmutable::parse('2026-12-31T21:30:00Z'));

        foreach (['Asia/Riyadh' => 2027, 'America/New_York' => 2026] as $timezone => $year) {
            config(['app.business_timezone' => $timezone]);

            foreach ([
                StoreCustomerCarRequest::class,
                UpdateCustomerCarRequest::class,
                CreateProviderStoreCarRequest::class,
                UpdateProviderStoreCarRequest::class,
                StoreOrderRequest::class,
                UpdateOrderRequest::class,
            ] as $requestClass) {
                $request = $requestClass::create('/orders/1', 'POST', ['order_type' => 'general']);
                $route = new Route(['POST'], 'orders/{order}', fn () => null);
                $route->bind($request);
                $route->setParameter('order', new Order(['order_type' => OrderType::General]));
                $request->setRouteResolver(fn () => $route);
                $nested = in_array($requestClass, [StoreOrderRequest::class, UpdateOrderRequest::class], true);
                $field = $nested ? 'vehicle.manufacturing_year' : 'manufacturing_year';
                $rules = [$field => $request->rules()[$field]];

                foreach ([$year => true, $year + 1 => false] as $candidate => $valid) {
                    $data = ['manufacturing_year' => $candidate];
                    $validator = Validator::make($nested ? ['vehicle' => $data] : $data, $rules);
                    $this->assertSame($valid, $validator->passes(), "$requestClass: $candidate in $timezone");
                }
            }
        }
    }
}

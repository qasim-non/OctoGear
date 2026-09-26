<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use RuntimeException;
use Tests\TestCase;

class ApiExceptionResponseTest extends TestCase
{
    public function test_an_unexpected_api_exception_does_not_expose_technical_details(): void
    {
        app()->setLocale('en');

        Route::get('/api/__tests/unexpected-api-exception', function (): void {
            throw new RuntimeException('Database password and internal SQL must stay private.');
        });

        $response = $this->getJson('/api/__tests/unexpected-api-exception');

        $response
            ->assertStatus(500)
            ->assertExactJson([
                'success' => false,
                'message' => __('auth.general.unexpected'),
            ])
            ->assertDontSee('Database password')
            ->assertDontSee('internal SQL');
    }
}

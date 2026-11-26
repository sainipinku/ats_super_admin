<?php

namespace Tests\Feature;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class PublicLegalPagesAccessTest extends TestCase
{
    public function test_legal_page_get_routes_are_public(): void
    {
        foreach ([
            '/api/pages/privacy-policy',
            '/api/pages/terms-and-conditions',
        ] as $uri) {
            $route = Route::getRoutes()->match(Request::create($uri, 'GET'));

            $this->assertNotContains('auth:sanctum', $route->gatherMiddleware());
        }
    }

    public function test_legal_page_update_routes_remain_authenticated(): void
    {
        foreach ([
            '/api/pages/privacy-policy',
            '/api/pages/terms-and-conditions',
        ] as $uri) {
            $route = Route::getRoutes()->match(Request::create($uri, 'POST'));

            $this->assertContains('auth:sanctum', $route->gatherMiddleware());
        }
    }
}

<?php

namespace Tests\Feature;

use App\Http\Middleware\RoleScopedSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class RoleScopedSessionTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test that owner routes use scoped session configuration.
     */
    public function test_owner_routes_use_scoped_session(): void
    {
        // Create a mock request to owner area
        $request = Request::create('/owner/dashboard', 'GET');

        // Apply middleware
        $middleware = new RoleScopedSession;
        $middleware->handle($request, function ($req) {
            // Verify session config was modified for owner routes
            $this->assertEquals('keyhome_owner_session', config('session.cookie'));
            $this->assertEquals('/owner', config('session.path'));

            return $req;
        });
    }

    /**
     * Test that customer routes use default session configuration.
     */
    public function test_customer_routes_use_default_session(): void
    {
        // Create a mock request to customer area
        $request = Request::create('/home', 'GET');

        // Apply middleware
        $middleware = new RoleScopedSession;
        $middleware->handle($request, function ($req) {
            // Verify session config remains default for customer routes
            $this->assertEquals('laravel_session', config('session.cookie'));
            $this->assertEquals('/', config('session.path'));

            return $req;
        });
    }

    /**
     * Test that API routes are not affected by session scoping.
     */
    public function test_api_routes_use_default_session(): void
    {
        // Create a mock request to API area
        $request = Request::create('/api/v1/health', 'GET');

        // Apply middleware
        $middleware = new RoleScopedSession;
        $middleware->handle($request, function ($req) {
            // API should use default session config
            $this->assertEquals('laravel_session', config('session.cookie'));
            $this->assertEquals('/', config('session.path'));

            return $req;
        });
    }

    /**
     * Test that nested owner routes are properly scoped.
     */
    public function test_nested_owner_routes_use_scoped_session(): void
    {
        // Create a mock request to nested owner area
        $request = Request::create('/owner/ads/create', 'GET');

        // Apply middleware
        $middleware = new RoleScopedSession;
        $middleware->handle($request, function ($req) {
            // Still should have owner session config
            $this->assertEquals('keyhome_owner_session', config('session.cookie'));
            $this->assertEquals('/owner', config('session.path'));

            return $req;
        });
    }
}

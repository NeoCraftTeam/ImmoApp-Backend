<?php

namespace Tests\Feature;

use App\Http\Middleware\RoleScopedSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
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
        $expectedOwnerCookie = Str::snake((string) config('app.name')).'_owner_session';

        $middleware->handle($request, function ($req) use ($expectedOwnerCookie) {
            // Verify session config was modified for owner routes
            $this->assertSame($expectedOwnerCookie, config('session.cookie'));
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

        $expectedCustomerCookie = (string) config('session.cookie');

        // Apply middleware
        $middleware = new RoleScopedSession;
        $middleware->handle($request, function ($req) use ($expectedCustomerCookie) {
            // Verify session config remains the application default for customer routes
            $this->assertSame($expectedCustomerCookie, config('session.cookie'));
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

        $expectedCustomerCookie = (string) config('session.cookie');

        // Apply middleware
        $middleware = new RoleScopedSession;
        $middleware->handle($request, function ($req) use ($expectedCustomerCookie) {
            // API should use default session config
            $this->assertSame($expectedCustomerCookie, config('session.cookie'));
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
        $expectedOwnerCookie = Str::snake((string) config('app.name')).'_owner_session';

        $middleware->handle($request, function ($req) use ($expectedOwnerCookie) {
            // Still should have owner session config
            $this->assertSame($expectedOwnerCookie, config('session.cookie'));
            $this->assertEquals('/owner', config('session.path'));

            return $req;
        });
    }
}

<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

/**
 * Regular app users (role 2) used to reach almost every /api/admin route — some
 * groups only had auth:api and AdminAuth's role check was commented out.
 */
class AdminRoutesAccessTest extends TestCase
{
    use DatabaseTransactions;

    /** Admin auth flow — must stay reachable without being an admin. */
    private const PUBLIC = [
        'api/admin/login', 'api/admin/verify-otp', 'api/admin/send-otp', 'api/admin/resend-otp',
        'api/admin/resend-otp-email', 'api/admin/forgot-password', 'api/admin/reset-password', 'api/admin/refresh',
    ];

    public function test_regular_user_is_refused_on_every_admin_route(): void
    {
        $user = User::factory()->create(['role_id' => 2, 'is_active' => true, 'email' => 'regular_' . uniqid() . '@test.local']);
        $headers = ['Authorization' => 'Bearer ' . JWTAuth::fromUser($user), 'Accept' => 'application/json'];

        $leaks = [];
        foreach (Route::getRoutes() as $route) {
            $uri = $route->uri();
            if (!str_starts_with($uri, 'api/admin') || in_array($uri, self::PUBLIC, true)) {
                continue;
            }
            $method = collect($route->methods())->first(fn ($m) => $m !== 'HEAD');
            $url = '/' . preg_replace('/\{[^}]+\}/', '1', $uri);

            $status = $this->withHeaders($headers)->json($method, $url)->status();
            // 404 = route-model binding ran before the middleware (no row 1); nothing leaked
            if (!in_array($status, [401, 403, 404], true)) {
                $leaks[] = "{$method} {$uri} → {$status}";
            }
        }

        $this->assertSame([], $leaks);
    }

    public function test_panel_roles_still_get_in(): void
    {
        // Every non-"user" role (prod: 1 admin, 3 Manager, 7 dashboard)
        $panelRoles = \DB::table('roles')->where('id', '!=', 2)->pluck('id');
        $this->assertNotEmpty($panelRoles);

        foreach ($panelRoles as $role) {
            $admin = User::factory()->create([
                'role_id' => $role, 'is_active' => true, 'email' => 'panel_' . uniqid() . '@test.local',
            ]);
            $headers = ['Authorization' => 'Bearer ' . JWTAuth::fromUser($admin)];

            $this->app['auth']->forgetGuards();
            $this->withHeaders($headers)->getJson('/api/admin/trainers')->assertOk();
        }
    }
}

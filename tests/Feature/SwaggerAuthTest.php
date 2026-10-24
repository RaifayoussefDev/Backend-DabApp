<?php

namespace Tests\Feature;

use App\Http\Middleware\SwaggerAuth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 * The Swagger basic-auth middleware must never write the username or password to the log.
 */
class SwaggerAuthTest extends TestCase
{
    private function runMiddleware(?string $user, ?string $pass)
    {
        config(['swagger-auth.user' => 'docs-user', 'swagger-auth.password' => 'docs-pass-123']);

        $server = $user === null ? [] : ['PHP_AUTH_USER' => $user, 'PHP_AUTH_PW' => $pass];
        $request = Request::create('/api/documentation', 'GET', [], [], [], $server);

        return (new SwaggerAuth())->handle($request, fn () => response('ok', 200));
    }

    public function test_valid_credentials_pass_and_are_not_logged(): void
    {
        Log::spy();

        $this->assertSame(200, $this->runMiddleware('docs-user', 'docs-pass-123')->getStatusCode());

        Log::shouldHaveReceived('info')->withArgs(function ($message, $context) {
            return $message === 'SwaggerAuth attempt'
                && $context === ['user_match' => true, 'password_match' => true];
        })->once();
    }

    public function test_wrong_password_is_rejected_and_not_logged(): void
    {
        Log::spy();

        $response = $this->runMiddleware('docs-user', 'typed-wrong-pass');

        $this->assertSame(401, $response->getStatusCode());
        Log::shouldHaveReceived('info')->withArgs(function ($message, $context) {
            $flat = json_encode($context);

            return $context === ['user_match' => true, 'password_match' => false]
                && ! str_contains($flat, 'typed-wrong-pass')
                && ! str_contains($flat, 'docs-pass-123');
        })->once();
    }

    public function test_missing_credentials_are_rejected(): void
    {
        $this->assertSame(401, $this->runMiddleware(null, null)->getStatusCode());
    }
}

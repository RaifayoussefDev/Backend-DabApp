<?php

namespace Tests\Feature\Marketplace;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

/**
 * POST /api/marketplace/upload-image: the real, decoupled upload pipeline every marketplace resource
 * builds on (same pattern as listings). Uploading here never creates or touches a vendor/category/
 * brand/product row; the caller attaches the returned URL to one on the next call.
 * DatabaseTransactions: nothing is persisted, safe on the dev database.
 */
class ImageUploadTest extends TestCase
{
    use DatabaseTransactions;

    private function headers(int $roleId = 2): array
    {
        $user = User::factory()->create(['email' => Str::uuid() . '@mp-test.local', 'role_id' => $roleId, 'is_active' => true]);
        $token = JWTAuth::fromUser($user);

        // Within one test the JWT guard and the JWT singleton keep the first user / token they saw. A real
        // request is always fresh, so reset both to be able to act as several users in the same test.
        JWTAuth::unsetToken();
        $this->app->make('tymon.jwt')->unsetToken();
        $this->app['auth']->forgetGuards();

        return ['Authorization' => 'Bearer ' . $token];
    }

    public function test_guest_gets_the_structured_401(): void
    {
        $this->postJson('/api/marketplace/upload-image', ['type' => 'vendor'])
            ->assertUnauthorized()->assertJson(['requires_auth' => true, 'action' => 'login']);
    }

    /**
     * ImageUploadController::saveImage() writes straight to storage_path('app/public/...'), bypassing the
     * Storage facade, so Storage::fake('public') does not intercept it (true for every endpoint on that
     * controller, listings included). Assert against the real disk instead, and clean up after.
     */
    private function assertRealFileExistsThenDelete(string $url): void
    {
        $relative = Str::after($url, '/storage/');
        $fullPath = storage_path('app/public/' . $relative);
        $this->assertFileExists($fullPath);
        @unlink($fullPath);
    }

    public function test_a_regular_user_can_upload_a_vendor_image_and_gets_a_real_url_back(): void
    {
        $response = $this->withHeaders($this->headers())->post('/api/marketplace/upload-image', [
            'type' => 'vendor', 'images' => [UploadedFile::fake()->image('logo.png', 200, 200)],
        ], ['Accept' => 'application/json'])->assertOk();

        $response->assertJsonPath('type', 'vendor');
        $paths = $response->json('paths');
        $this->assertCount(1, $paths);
        $this->assertStringContainsString('/storage/marketplace/vendors/', $paths[0]);
        $this->assertStringStartsWith('http', $paths[0]);

        $this->assertRealFileExistsThenDelete($paths[0]);
    }

    public function test_a_regular_user_can_upload_a_product_image(): void
    {
        $paths = $this->withHeaders($this->headers())->post('/api/marketplace/upload-image', [
            'type' => 'product', 'images' => [UploadedFile::fake()->image('part.jpg', 400, 400)],
        ], ['Accept' => 'application/json'])->assertOk()->json('paths');

        $this->assertStringContainsString('/storage/marketplace/products/', $paths[0]);
        $this->assertRealFileExistsThenDelete($paths[0]);
    }

    public function test_multiple_images_in_one_call_return_one_url_each_in_order(): void
    {
        $paths = $this->withHeaders($this->headers())->post('/api/marketplace/upload-image', [
            'type' => 'product', 'images' => [
                UploadedFile::fake()->image('a.jpg'), UploadedFile::fake()->image('b.jpg'), UploadedFile::fake()->image('c.jpg'),
            ],
        ], ['Accept' => 'application/json'])->assertOk()->json('paths');

        $this->assertCount(3, $paths);
        $this->assertCount(3, array_unique($paths), 'each file gets its own random filename');
        array_map([$this, 'assertRealFileExistsThenDelete'], $paths);
    }

    public function test_category_and_brand_types_are_admin_only(): void
    {
        foreach (['category', 'brand'] as $type) {
            $this->withHeaders($this->headers(2))->post('/api/marketplace/upload-image', [
                'type' => $type, 'images' => [UploadedFile::fake()->image('x.png')],
            ], ['Accept' => 'application/json'])->assertStatus(403)->assertJsonPath('code', 'ADMIN_ONLY');

            $paths = $this->withHeaders($this->headers(1))->post('/api/marketplace/upload-image', [
                'type' => $type, 'images' => [UploadedFile::fake()->image('x.png')],
            ], ['Accept' => 'application/json'])->assertOk()->assertJsonPath('type', $type)->json('paths');

            $this->assertRealFileExistsThenDelete($paths[0]);
        }
    }

    public function test_validation_rejects_unknown_type_missing_file_and_non_image(): void
    {
        $h = $this->headers();

        $this->withHeaders($h)->postJson('/api/marketplace/upload-image', ['type' => 'listing', 'images' => []])
            ->assertStatus(422)->assertJsonValidationErrors('type');

        $this->withHeaders($h)->postJson('/api/marketplace/upload-image', ['type' => 'vendor'])
            ->assertStatus(422)->assertJsonValidationErrors('images');

        $this->withHeaders($h)->post('/api/marketplace/upload-image', [
            'type' => 'vendor', 'images' => [UploadedFile::fake()->create('doc.pdf', 10)],
        ], ['Accept' => 'application/json'])->assertStatus(422)->assertJsonValidationErrors('images.0');
    }

    public function test_the_upload_does_not_create_or_attach_to_any_vendor(): void
    {
        $before = \App\Models\Marketplace\Vendor::count();

        $path = $this->withHeaders($this->headers())->post('/api/marketplace/upload-image', [
            'type' => 'vendor', 'images' => [UploadedFile::fake()->image('logo.png')],
        ], ['Accept' => 'application/json'])->assertOk()->json('paths.0');

        $this->assertSame($before, \App\Models\Marketplace\Vendor::count(), 'uploading is decoupled: nothing is attached until a create/update call sends the URL');
        $this->assertRealFileExistsThenDelete($path);
    }
}

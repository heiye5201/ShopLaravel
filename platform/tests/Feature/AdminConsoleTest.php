<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminConsoleTest extends TestCase
{
    use RefreshDatabase;

    public function test_console_endpoints_require_authentication_and_registration_is_private(): void
    {
        foreach (['me', 'dashboard', 'products'] as $endpoint) {
            $this->getJson('/api/v1/admin/'.$endpoint)->assertUnauthorized();
        }
        $this->postJson('/api/v1/admin/register', [])->assertUnauthorized();
        $this->postJson('/api/v1/admin/logout')->assertUnauthorized();
        $this->patchJson('/api/v1/admin/profile', [])->assertUnauthorized();
    }

    public function test_current_user_and_logout_clear_authentication(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->getJson('/api/v1/admin/me')->assertOk()
            ->assertJsonPath('data.id', $user->id)->assertJsonMissingPath('data.password');
        $this->postJson('/api/v1/admin/logout')->assertOk();
        $this->assertGuest();
        $this->getJson('/api/v1/admin/me')->assertUnauthorized();
    }

    public function test_dashboard_counts_real_products_and_returns_recent_items(): void
    {
        Product::factory()->count(3)->create();
        Product::factory()->count(2)->unavailable()->create();
        $latest = Product::query()->latest('id')->first();
        $this->actingAs(User::factory()->create())->getJson('/api/v1/admin/dashboard')->assertOk()
            ->assertJsonPath('data.products', 5)->assertJsonPath('data.available', 3)
            ->assertJsonPath('data.unavailable', 2)->assertJsonCount(5, 'data.recent_products')
            ->assertJsonPath('data.recent_products.0.id', $latest->id);
    }

    public function test_product_search_status_and_pagination_are_combined(): void
    {
        Product::factory()->create(['title' => 'Speaker available']);
        $match = Product::factory()->unavailable()->create(['title' => 'Speaker draft']);
        Product::factory()->unavailable()->create(['title' => 'Unrelated']);
        $this->actingAs(User::factory()->create())
            ->getJson('/api/v1/admin/products?search=Speaker&status=unavailable&per_page=1')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $match->id)
            ->assertJsonPath('meta.total', 1)->assertJsonPath('meta.per_page', 1);
        $this->getJson('/api/v1/admin/products?per_page=101&status=invalid&page=0')
            ->assertUnprocessable()->assertJsonValidationErrors(['per_page', 'status', 'page']);
    }

    public function test_profile_requires_current_password_and_preserves_unique_email(): void
    {
        $user = User::factory()->create(['password' => 'old-password']);
        $other = User::factory()->create();
        $this->actingAs($user)->patchJson('/api/v1/admin/profile', [
            'name' => 'Changed', 'email' => $other->email, 'current_password' => 'wrong',
        ])->assertUnprocessable()->assertJsonValidationErrors(['email', 'current_password']);
        $this->assertSame($user->name, $user->fresh()->name);
    }

    public function test_product_can_be_created_and_saved_without_optional_tags(): void
    {
        $this->actingAs(User::factory()->create());
        $product = $this->postJson('/api/v1/admin/products', [
            'title' => 'Canvas Bag', 'slug' => 'canvas-bag', 'price' => 89.50,
            'description' => 'Daily bag', 'tags' => [], 'is_available' => false,
        ])->assertCreated()->assertJsonPath('data.tags', [])->json('data');
        $this->patchJson('/api/v1/admin/products/'.$product['id'], ['tags' => []])
            ->assertOk()->assertJsonPath('data.tags', []);
    }

    public function test_profile_can_change_password_and_login_with_it(): void
    {
        $user = User::factory()->create(['password' => 'old-password']);
        $this->actingAs($user)->patchJson('/api/v1/admin/profile', [
            'name' => 'Updated Admin', 'email' => $user->email, 'current_password' => 'old-password',
            'password' => 'new-password', 'password_confirmation' => 'new-password',
        ])->assertOk()->assertJsonPath('data.name', 'Updated Admin')->assertJsonMissingPath('data.password');
        $this->assertTrue(Hash::check('new-password', $user->fresh()->password));
        $this->postJson('/api/v1/admin/logout')->assertOk();
        $this->postJson('/api/v1/admin/login', ['email' => $user->email, 'password' => 'new-password'])->assertOk();
    }

    public function test_login_is_rate_limited_after_five_attempts(): void
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->postJson('/api/v1/admin/login', ['email' => 'missing@example.com', 'password' => 'wrong'])
                ->assertUnprocessable();
        }
        $this->postJson('/api/v1/admin/login', ['email' => 'missing@example.com', 'password' => 'wrong'])
            ->assertTooManyRequests();
    }

    public function test_csrf_endpoint_supplies_token_and_unsafe_requests_require_it(): void
    {
        $this->app->instance('env', 'local');
        $token = $this->getJson('/api/v1/admin/csrf')->assertOk()->json('token');
        $this->assertIsString($token);
        $this->postJson('/api/v1/admin/login', ['email' => 'x@example.com', 'password' => 'wrong'])
            ->assertStatus(419);
        $this->withHeader('X-CSRF-TOKEN', $token)
            ->postJson('/api/v1/admin/login', ['email' => 'x@example.com', 'password' => 'wrong'])
            ->assertUnprocessable();
    }

    public function test_admin_creation_command_validates_password_and_does_not_overwrite_users(): void
    {
        $this->artisan('admin:create', ['email' => 'owner@example.com', '--name' => 'Owner'])
            ->expectsQuestion('请输入密码（至少 8 位）', 'secure-password')
            ->expectsQuestion('请再次输入密码', 'secure-password')->assertSuccessful();
        $this->assertDatabaseHas('users', ['email' => 'owner@example.com', 'name' => 'Owner']);
        $this->artisan('admin:create', ['email' => 'owner@example.com'])
            ->expectsQuestion('请输入密码（至少 8 位）', 'short')
            ->expectsQuestion('请再次输入密码', 'different')->assertFailed();
        $this->assertDatabaseCount('users', 1);
    }

    public function test_built_spa_supports_direct_navigation_and_never_serves_arbitrary_files(): void
    {
        if (! is_file(public_path('admin-app/index.html'))) {
            $this->get('/admin/products')->assertStatus(503);

            return;
        }
        foreach (['/admin', '/admin/products', '/admin/settings', '/admin/unknown'] as $path) {
            $response = $this->get($path)->assertOk();
            $this->assertSame(public_path('admin-app/index.html'), $response->baseResponse->getFile()->getPathname());
            $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        }
    }

    public function test_product_images_require_authentication_and_store_valid_images(): void
    {
        Storage::fake('public');
        $this->postJson('/api/v1/admin/product-images')->assertUnauthorized();
        $image = UploadedFile::fake()->createWithContent('speaker.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aX1sAAAAASUVORK5CYII='));
        $response = $this->actingAs(User::factory()->create())
            ->postJson('/api/v1/admin/product-images', ['image' => $image])
            ->assertCreated();
        $url = $response->json('data.url');
        $this->assertStringStartsWith('/storage/products/', $url);
        Storage::disk('public')->assertExists(substr($url, strlen('/storage/')));
    }

    public function test_product_image_upload_rejects_missing_non_image_and_oversized_files(): void
    {
        Storage::fake('public');
        $this->actingAs(User::factory()->create());
        foreach ([null, UploadedFile::fake()->create('script.php', 1, 'text/plain'), UploadedFile::fake()->createWithContent('large.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aX1sAAAAASUVORK5CYII='))->size(2049)] as $file) {
            $this->postJson('/api/v1/admin/product-images', $file ? ['image' => $file] : [])
                ->assertUnprocessable()->assertJsonValidationErrors('image');
        }
        $this->assertSame([], Storage::disk('public')->allFiles());
    }
}

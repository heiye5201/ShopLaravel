<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_admin_can_register_without_a_captcha(): void
    {
        $response = $this->postJson('/api/v1/admin/register', [
            'name' => 'Shop Admin',
            'email' => 'admin@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('message', '注册成功')
            ->assertJsonPath('data.email', 'admin@example.com')
            ->assertJsonMissingPath('data.password');

        $user = User::query()->where('email', 'admin@example.com')->firstOrFail();

        $this->assertAuthenticatedAs($user);
        $this->assertTrue(Hash::check('password123', $user->password));
    }

    public function test_registration_input_is_validated(): void
    {
        User::factory()->create(['email' => 'admin@example.com']);

        $this->postJson('/api/v1/admin/register', [
            'name' => '',
            'email' => 'admin@example.com',
            'password' => 'short',
            'password_confirmation' => 'different',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'email', 'password']);
    }

    public function test_an_admin_can_login_without_a_captcha(): void
    {
        $user = User::factory()->create([
            'email' => 'admin@example.com',
            'password' => 'password123',
        ]);

        $this->postJson('/api/v1/admin/login', [
            'email' => 'admin@example.com',
            'password' => 'password123',
        ])->assertOk()
            ->assertJsonPath('message', '登录成功')
            ->assertJsonPath('data.id', $user->id);

        $this->assertAuthenticatedAs($user);
    }

    public function test_login_rejects_invalid_credentials(): void
    {
        User::factory()->create([
            'email' => 'admin@example.com',
            'password' => 'password123',
        ]);

        $this->postJson('/api/v1/admin/login', [
            'email' => 'admin@example.com',
            'password' => 'wrong-password',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);

        $this->assertGuest();
    }

    public function test_a_guest_cannot_create_or_update_products(): void
    {
        $product = Product::factory()->create();

        $this->postJson('/api/v1/admin/products', [])
            ->assertUnauthorized();

        $this->patchJson("/api/v1/admin/products/{$product->id}", [
            'title' => 'Updated title',
        ])->assertUnauthorized();
    }

    public function test_an_authenticated_admin_can_create_a_product(): void
    {
        $this->actingAs(User::factory()->create());

        $payload = [
            'title' => 'Aurora Speaker',
            'slug' => 'aurora-speaker',
            'price' => 199.99,
            'description' => 'A compact wireless speaker.',
            'image_path' => '/images/aurora-speaker.svg',
            'badge' => '新品',
            'is_available' => true,
            'tags' => ['audio', 'wireless'],
        ];

        $this->postJson('/api/v1/admin/products', $payload)
            ->assertCreated()
            ->assertJsonPath('message', '商品创建成功')
            ->assertJsonPath('data.slug', 'aurora-speaker')
            ->assertJsonPath('data.tags.1', 'wireless');

        $this->assertDatabaseHas('products', [
            'slug' => 'aurora-speaker',
            'price' => 199.99,
            'is_available' => true,
        ]);
    }

    public function test_product_creation_input_is_validated(): void
    {
        $this->actingAs(User::factory()->create());
        Product::factory()->create(['slug' => 'existing-product']);

        $this->postJson('/api/v1/admin/products', [
            'title' => '',
            'slug' => 'existing-product',
            'price' => -1,
            'description' => '',
            'tags' => ['duplicate', 'duplicate'],
        ])->assertUnprocessable()
            ->assertJsonValidationErrors([
                'title',
                'slug',
                'price',
                'description',
                'tags.1',
            ]);
    }

    public function test_an_authenticated_admin_can_update_a_product(): void
    {
        $this->actingAs(User::factory()->create());
        $product = Product::factory()->create([
            'slug' => 'aurora-speaker',
            'price' => 199.99,
        ]);

        $this->patchJson("/api/v1/admin/products/{$product->id}", [
            'slug' => 'aurora-speaker',
            'price' => 249.50,
            'is_available' => false,
        ])->assertOk()
            ->assertJsonPath('message', '商品更新成功')
            ->assertJsonPath('data.price', '249.50')
            ->assertJsonPath('data.is_available', false);

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'slug' => 'aurora-speaker',
            'price' => 249.50,
            'is_available' => false,
        ]);
    }

    public function test_product_update_rejects_another_products_slug(): void
    {
        $this->actingAs(User::factory()->create());
        $product = Product::factory()->create();
        $otherProduct = Product::factory()->create();

        $this->patchJson("/api/v1/admin/products/{$product->id}", [
            'slug' => $otherProduct->slug,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['slug']);
    }
}

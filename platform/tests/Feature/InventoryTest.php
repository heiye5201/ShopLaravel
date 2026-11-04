<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_inventory_requires_authentication(): void
    {
        $this->getJson('/api/v1/admin/inventory')->assertUnauthorized();
        $this->patchJson('/api/v1/admin/inventory/1', ['adjustment' => 1, 'expected_quantity' => 0])->assertUnauthorized();
    }

    public function test_inventory_lists_and_searches_variants_with_product_names(): void
    {
        $product = Product::factory()->create(['title' => '无线音箱']);
        $variant = ProductVariant::factory()->for($product)->create(['title' => '白色 / M', 'sku' => 'WHITE-M', 'inventory_quantity' => 12]);
        ProductVariant::factory()->create(['title' => '其他规格']);
        $this->actingAs(User::factory()->create());
        foreach (['无线音箱', '白色', 'WHITE-M'] as $search) {
            $this->getJson('/api/v1/admin/inventory?search='.urlencode($search).'&per_page=1')
                ->assertOk()->assertJsonPath('meta.total', 1)
                ->assertJsonPath('data.0.id', $variant->id)
                ->assertJsonPath('data.0.product_title', '无线音箱')
                ->assertJsonPath('data.0.inventory_quantity', 12);
        }
        $this->getJson('/api/v1/admin/inventory?per_page=101&page=0')->assertUnprocessable();
    }

    public function test_inventory_adjustments_and_policy_are_persisted_independently(): void
    {
        $variant = ProductVariant::factory()->create(['inventory_quantity' => 10]);
        $url = '/api/v1/admin/inventory/'.$variant->id;
        $this->actingAs(User::factory()->create());
        $this->patchJson($url, ['adjustment' => 5, 'expected_quantity' => 10])->assertOk()->assertJsonPath('data.inventory_quantity', 15);
        $this->patchJson($url, ['adjustment' => -15, 'expected_quantity' => 15])->assertOk()->assertJsonPath('data.inventory_quantity', 0);
        $this->patchJson($url, ['continue_selling' => true])->assertOk()->assertJsonPath('data.continue_selling', true)->assertJsonPath('data.inventory_quantity', 0);
        $this->assertTrue($variant->fresh()->continue_selling);
        $this->assertTrue($variant->fresh()->is_available);
        $this->assertSame(0, $variant->fresh()->inventory_quantity);
    }

    public function test_invalid_and_stale_adjustments_do_not_change_stock_or_policy(): void
    {
        $variant = ProductVariant::factory()->create(['inventory_quantity' => 5]);
        $url = '/api/v1/admin/inventory/'.$variant->id;
        $this->actingAs(User::factory()->create());
        foreach ([[], ['adjustment' => 1], ['adjustment' => 1.5, 'expected_quantity' => 5], ['adjustment' => -6, 'expected_quantity' => 5], ['adjustment' => 2147483647, 'expected_quantity' => 5], ['continue_selling' => 'invalid']] as $payload) {
            $this->patchJson($url, $payload)->assertUnprocessable();
        }
        $this->patchJson($url, ['adjustment' => 1, 'expected_quantity' => 4, 'continue_selling' => true])->assertConflict();
        $this->assertSame(5, $variant->fresh()->inventory_quantity);
        $this->assertFalse($variant->fresh()->continue_selling);
        $this->patchJson('/api/v1/admin/inventory/999999', ['continue_selling' => true])->assertNotFound();
    }
}

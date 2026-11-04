<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_categories_require_authentication(): void
    {
        $this->getJson('/api/v1/admin/categories')->assertUnauthorized();
        $this->postJson('/api/v1/admin/categories', [])->assertUnauthorized();
        $this->patchJson('/api/v1/admin/categories/1/pin', ['is_pinned' => true])->assertUnauthorized();
    }

    public function test_manual_categories_count_products_and_edit_associations(): void
    {
        $available = Product::factory()->create(['is_available' => true]);
        $unavailable = Product::factory()->create(['is_available' => false]);
        $this->actingAs(User::factory()->create());
        $id = $this->postJson('/api/v1/admin/categories', ['name' => '精选', 'type' => 'manual', 'product_ids' => [$available->id, $unavailable->id]])
            ->assertCreated()->assertJsonPath('data.products_count', 2)->assertJsonPath('data.available_products_count', 1)->json('data.id');
        $this->getJson('/api/v1/admin/categories/'.$id)->assertOk()->assertJsonCount(2, 'data.products')->assertJsonCount(2, 'data.product_ids');
        $available->update(['is_available' => false]);
        $this->getJson('/api/v1/admin/categories')->assertOk()->assertJsonPath('data.0.available_products_count', 0);
        $this->patchJson('/api/v1/admin/categories/'.$id, ['name' => '新精选', 'type' => 'manual', 'product_ids' => []])
            ->assertOk()->assertJsonPath('data.name', '新精选')->assertJsonPath('data.products_count', 0);
        $this->assertDatabaseCount('category_product', 0);
    }

    public function test_automatic_categories_match_tags_dynamically_and_switch_types(): void
    {
        $matching = Product::factory()->create(['tags' => ['新品', '音频'], 'is_available' => true]);
        Product::factory()->create(['tags' => ['新品推荐'], 'is_available' => true]);
        $this->actingAs(User::factory()->create());
        $id = $this->postJson('/api/v1/admin/categories', ['name' => '新品', 'type' => 'automatic', 'match_tag' => '新品'])
            ->assertCreated()->assertJsonPath('data.products_count', 1)->assertJsonPath('data.available_products_count', 1)->json('data.id');
        $matching->update(['tags' => ['音频']]);
        $this->getJson('/api/v1/admin/categories/'.$id)->assertOk()->assertJsonPath('data.products_count', 0);
        $this->patchJson('/api/v1/admin/categories/'.$id, ['name' => '手选', 'type' => 'manual', 'product_ids' => [$matching->id]])
            ->assertOk()->assertJsonPath('data.match_tag', null)->assertJsonPath('data.products_count', 1);
        $this->patchJson('/api/v1/admin/categories/'.$id, ['name' => '自动音频', 'type' => 'automatic', 'match_tag' => '音频'])->assertOk();
        $this->assertDatabaseCount('category_product', 0);
        $matching->delete();
        $this->getJson('/api/v1/admin/categories/'.$id)->assertOk()->assertJsonPath('data.products_count', 0);
    }

    public function test_pin_is_persisted_and_categories_are_searchable_and_paginated(): void
    {
        $older = Category::factory()->create(['name' => '服装']);
        $this->travel(1)->seconds();
        $newer = Category::factory()->create(['name' => '新品', 'type' => 'automatic', 'match_tag' => '新品']);
        $this->actingAs(User::factory()->create());
        $this->getJson('/api/v1/admin/categories?per_page=1')->assertOk()->assertJsonPath('data.0.id', $newer->id)->assertJsonPath('meta.total', 2);
        $this->patchJson('/api/v1/admin/categories/'.$older->id.'/pin', ['is_pinned' => true])->assertOk()->assertJsonPath('data.is_pinned', true);
        $this->getJson('/api/v1/admin/categories?per_page=1')->assertOk()->assertJsonPath('data.0.id', $older->id);
        $this->getJson('/api/v1/admin/categories?search='.urlencode('新品').'&type=automatic')->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.id', $newer->id);
        $this->patchJson('/api/v1/admin/categories/'.$older->id.'/pin', ['is_pinned' => false])->assertOk()->assertJsonPath('data.is_pinned', false);
        $this->getJson('/api/v1/admin/categories?per_page=1')->assertOk()->assertJsonPath('data.0.id', $newer->id);
    }

    public function test_invalid_category_data_does_not_create_partial_records(): void
    {
        $this->actingAs(User::factory()->create());
        foreach ([['name' => ' ', 'type' => 'manual', 'product_ids' => []], ['name' => ['bad'], 'type' => 'manual', 'product_ids' => []], ['name' => 'Bad', 'type' => 'other'], ['name' => 'Bad', 'type' => 'manual', 'product_ids' => [999999]], ['name' => 'Bad', 'type' => 'automatic'], ['name' => 'Bad', 'type' => 'automatic', 'match_tag' => '   ']] as $payload) {
            $this->postJson('/api/v1/admin/categories', $payload)->assertUnprocessable();
        }
        $this->assertDatabaseCount('categories', 0);
        $this->getJson('/api/v1/admin/categories/999999')->assertNotFound();
        $this->getJson('/api/v1/admin/categories?type=invalid&per_page=101&page=0')->assertUnprocessable();
    }
}

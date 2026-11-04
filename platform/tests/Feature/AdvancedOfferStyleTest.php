<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Admin\Models\AdvancedOfferStyle;
use Modules\Admin\Services\StorefrontAdvancedStyles;
use Tests\TestCase;

class AdvancedOfferStyleTest extends TestCase
{
    use RefreshDatabase;

    public function test_advanced_style_routes_require_login(): void
    {
        $this->getJson('/api/v1/admin/advanced-styles')->assertUnauthorized();
        $this->getJson('/api/v1/admin/advanced-styles/options')->assertUnauthorized();
        $this->postJson('/api/v1/admin/advanced-styles', [])->assertUnauthorized();
        $style = AdvancedOfferStyle::factory()->create();
        $this->getJson('/api/v1/admin/advanced-styles/'.$style->id)->assertUnauthorized();
        $this->patchJson('/api/v1/admin/advanced-styles/'.$style->id, [])->assertUnauthorized();
        $this->patchJson('/api/v1/admin/advanced-styles/'.$style->id.'/enabled', ['enabled' => true])->assertUnauthorized();
    }

    public function test_create_edit_toggle_and_reload_preserve_all_design_settings(): void
    {
        $this->actingAs(User::factory()->create());
        $product = Product::factory()->create();
        $variant = ProductVariant::factory()->for($product)->create();
        $data = [...AdvancedOfferStyle::defaults(), 'name' => '阶梯组合', 'scope' => 'variants', 'variant_ids' => [$variant->id]];
        $data['design']['badge_shape'] = 'ribbon';
        $data['design']['badge_mode'] = 'fixed';
        $data['design']['badge_text'] = '人气推荐';
        $data['design']['marquee'] = true;
        $data['design']['radius'] = 12;
        $id = $this->postJson('/api/v1/admin/advanced-styles', $data)->assertCreated()
            ->assertJsonPath('data.design.marquee', true)->assertJsonPath('data.design.radius', 12)->json('data.id');
        $this->getJson('/api/v1/admin/advanced-styles/'.$id)->assertOk()
            ->assertJsonPath('data.variant_ids.0', $variant->id)->assertJsonPath('products.0.variants.0.id', $variant->id);
        $this->patchJson('/api/v1/admin/advanced-styles/'.$id, [...$data, 'name' => '新版组合'])
            ->assertOk()->assertJsonPath('data.name', '新版组合')->assertJsonPath('data.design.badge_text', '人气推荐');
        $this->patchJson('/api/v1/admin/advanced-styles/'.$id.'/enabled', ['enabled' => true])->assertOk()->assertJsonPath('data.enabled', true);
        $this->getJson('/api/v1/admin/advanced-styles?search=新版')->assertOk()->assertJsonPath('total', 1);
        $this->getJson('/api/v1/admin/advanced-styles/options')->assertOk()->assertJsonCount(18, 'data.badge_shapes');
    }

    public function test_unknown_or_dangerous_design_values_are_rejected(): void
    {
        $this->actingAs(User::factory()->create());
        $data = [...AdvancedOfferStyle::defaults(), 'name' => 'Invalid'];
        $data['design']['colors']['text'] = 'red; background:url(javascript:alert(1))';
        $data['design']['radius'] = 999;
        $data['design']['badge_shape'] = '<script>';
        $data['design']['unexpected_css'] = 'url(...)';
        $this->postJson('/api/v1/admin/advanced-styles', $data)->assertUnprocessable()
            ->assertJsonValidationErrors(['design', 'design.colors.text', 'design.radius', 'design.badge_shape']);
        $this->assertDatabaseCount('advanced_offer_styles', 0);
    }

    public function test_associations_and_tiers_are_validated(): void
    {
        $this->actingAs(User::factory()->create());
        $data = [...AdvancedOfferStyle::defaults(), 'name' => 'Invalid', 'scope' => 'products', 'product_ids' => [999999]];
        $data['tiers'][0]['quantity'] = 0;
        $this->postJson('/api/v1/admin/advanced-styles', $data)->assertUnprocessable()->assertJsonValidationErrors(['product_ids.0', 'tiers.0.quantity']);
        $data = [...AdvancedOfferStyle::defaults(), 'name' => 'Invalid'];
        $data['tiers'][] = [...$data['tiers'][0], 'quantity' => 1];
        $this->postJson('/api/v1/admin/advanced-styles', $data)->assertUnprocessable()->assertJsonValidationErrors('tiers.1.quantity');
        $data['tiers'][0]['quantity'] = 3;
        $data['tiers'][1]['quantity'] = 2;
        $this->postJson('/api/v1/admin/advanced-styles', $data)->assertUnprocessable()->assertJsonValidationErrors('tiers.1.quantity');
        $this->postJson('/api/v1/admin/advanced-styles', [...AdvancedOfferStyle::defaults(), 'name' => 'Missing', 'scope' => 'variants'])->assertUnprocessable()->assertJsonValidationErrors('variant_ids');
        $this->postJson('/api/v1/admin/advanced-styles', [...AdvancedOfferStyle::defaults(), 'name' => 'Unsupported', 'scope' => 'category'])->assertUnprocessable()->assertJsonValidationErrors('scope');
    }

    public function test_storefront_matching_respects_enable_product_exclusion_and_variant_scope(): void
    {
        $product = Product::factory()->create();
        $variant = ProductVariant::factory()->for($product)->create();
        $other = Product::factory()->create();
        $all = AdvancedOfferStyle::factory()->create(['enabled' => true]);
        $selected = AdvancedOfferStyle::factory()->create(['enabled' => true, 'scope' => 'products', 'product_ids' => [$product->id]]);
        $excluded = AdvancedOfferStyle::factory()->create(['enabled' => true, 'scope' => 'exclude', 'product_ids' => [$product->id]]);
        $spec = AdvancedOfferStyle::factory()->create(['enabled' => true, 'scope' => 'variants', 'variant_ids' => [$variant->id]]);
        AdvancedOfferStyle::factory()->create(['enabled' => false]);
        $resolver = app(StorefrontAdvancedStyles::class);
        $ids = array_column($resolver->forProduct($product->load('variants')), 'id');
        $this->assertSame([$spec->id, $selected->id, $all->id], $ids);
        $otherIds = array_column($resolver->forProduct($other->load('variants')), 'id');
        $this->assertSame([$excluded->id, $all->id], $otherIds);
        $this->travel(1)->seconds();
        $all->touch();
        $this->assertSame($all->id, $resolver->forProduct($product)[0]['id']);
        $all->update(['enabled' => false]);
        $this->assertNotContains($all->id, array_column($resolver->forProduct($product), 'id'));
    }

    public function test_style_json_is_safe_and_does_not_modify_product_prices(): void
    {
        $product = Product::factory()->create(['slug' => 'styled-product', 'price' => '19.50']);
        ProductVariant::factory()->for($product)->create(['price' => '22.50']);
        $style = AdvancedOfferStyle::factory()->create(['enabled' => true]);
        $design = $style->design;
        $design['title_mode'] = 'custom';
        $design['title'] = '</script><script>alert(1)</script>';
        $style->update(['design' => $design]);
        $response = $this->get('/liquid/theme-demo/product?slug=styled-product')->assertOk()
            ->assertSee('data-advanced-styles', false)->assertDontSee('</script><script>alert(1)</script>', false);
        $this->assertStringContainsString('\\u003C', $response->getContent());
        $this->assertSame('19.50', $product->fresh()->price);
        $this->assertSame('22.50', $product->variants()->first()->price);
    }
}

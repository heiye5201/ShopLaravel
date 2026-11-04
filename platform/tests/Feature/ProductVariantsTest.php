<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\ProductContentSanitizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class ProductVariantsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
    }

    private function payload(): array
    {
        return ['title' => 'Speaker', 'slug' => 'speaker', 'price' => '199.00', 'description' => 'Portable speaker', 'tags' => [], 'is_available' => false];
    }

    public function test_option_matrix_and_sku_metadata_are_saved_returned_and_preserved(): void
    {
        $options = [['name' => 'color', 'values' => ['白色', '红色']], ['name' => '尺寸', 'values' => ['S']]];
        $response = $this->postJson('/api/v1/admin/products', [...$this->payload(), 'variant_options' => $options, 'variants' => [
            ['title' => '白色 / S', 'price' => '199.00', 'sku' => 'speaker-white-s', 'image_path' => '/images/white.jpg', 'compare_at_price' => '259.00', 'track_inventory' => true, 'option_values' => ['白色', 'S']],
            ['title' => '红色 / S', 'price' => '209.00', 'sku' => 'speaker-red-s', 'option_values' => ['红色', 'S']],
        ]])->assertCreated()->assertJsonPath('data.variant_options', $options)
            ->assertJsonPath('data.variants.0.sku', 'speaker-white-s')
            ->assertJsonPath('data.variants.0.compare_at_price', '259.00')
            ->assertJsonPath('data.variants.0.track_inventory', true)
            ->assertJsonPath('data.variants.0.image_path', '/images/white.jpg')
            ->assertJsonPath('data.variants.0.option_values', ['白色', 'S']);
        $id = $response->json('data.id');
        $first = $response->json('data.variants.0.id');
        $second = $response->json('data.variants.1.id');
        $this->patchJson('/api/v1/admin/products/'.$id, ['title' => 'Changed'])->assertOk()
            ->assertJsonPath('data.variants.0.id', $first)->assertJsonPath('data.variants.0.sku', 'speaker-white-s');
        $this->getJson('/api/v1/admin/products')->assertOk()->assertJsonPath('data.0.variant_options', $options);
        $this->patchJson('/api/v1/admin/products/'.$id, [
            'variant_options' => [['name' => '尺寸', 'values' => ['S']], ['name' => 'color', 'values' => ['红色', '白色']]],
            'variants' => [
                ['id' => $second, 'title' => 'S / 红色', 'price' => 209, 'option_values' => ['S', '红色']],
                ['id' => $first, 'title' => 'S / 白色', 'price' => 199, 'compare_at_price' => null, 'track_inventory' => false, 'option_values' => ['S', '白色']],
            ],
        ])->assertOk()->assertJsonPath('data.variants.1.id', $first)
            ->assertJsonPath('data.variants.1.sku', 'speaker-white-s')->assertJsonPath('data.variants.1.compare_at_price', null)
            ->assertJsonPath('data.variants.1.track_inventory', false);
    }

    public function test_invalid_or_incomplete_option_combinations_roll_back_the_product(): void
    {
        $options = [['name' => 'color', 'values' => ['White', 'Red']]];
        foreach ([
            [['title' => 'White', 'price' => 1, 'option_values' => ['White']]],
            [['title' => 'White', 'price' => 1, 'option_values' => ['White']], ['title' => 'Again', 'price' => 2, 'option_values' => ['White']]],
            [['title' => 'White', 'price' => 1, 'option_values' => ['White']], ['title' => 'Blue', 'price' => 2, 'option_values' => ['Blue']]],
        ] as $variants) {
            $this->postJson('/api/v1/admin/products', [...$this->payload(), 'variant_options' => $options, 'variants' => $variants])->assertUnprocessable();
        }
        $this->assertDatabaseCount('products', 0);
        $this->assertDatabaseCount('product_variants', 0);
    }

    public function test_option_limits_duplicates_and_invalid_sku_metadata_are_rejected(): void
    {
        $this->postJson('/api/v1/admin/products', [...$this->payload(), 'variant_options' => [
            ['name' => 'color', 'values' => ['White', 'White']],
        ]])->assertUnprocessable();
        $this->postJson('/api/v1/admin/products', [...$this->payload(), 'variant_options' => [
            ['name' => 'color', 'values' => ['White']], ['name' => 'color', 'values' => ['Red']],
        ]])->assertUnprocessable()->assertJsonValidationErrors('variant_options.0.name');
        $this->postJson('/api/v1/admin/products', [...$this->payload(), 'variants' => [
            ['title' => 'White', 'price' => 1, 'sku' => 'same'], ['title' => 'Red', 'price' => 1, 'sku' => 'same'],
        ]])->assertUnprocessable()->assertJsonValidationErrors('variants.0.sku');
        $this->postJson('/api/v1/admin/products', [...$this->payload(), 'variants' => [
            ['title' => 'White', 'price' => 1, 'compare_at_price' => -1, 'track_inventory' => 'invalid'],
        ]])->assertUnprocessable()->assertJsonValidationErrors(['variants.0.compare_at_price', 'variants.0.track_inventory']);
        $this->postJson('/api/v1/admin/products', [...$this->payload(), 'variant_options' => [
            ['name' => 'color', 'values' => array_map('strval', range(1, 11))],
            ['name' => 'size', 'values' => array_map('strval', range(1, 10))],
        ]])->assertUnprocessable();
        $this->assertDatabaseCount('products', 0);
    }

    public function test_rich_content_is_saved_returned_rendered_updated_and_cleared(): void
    {
        $html = '<h2>产品介绍</h2><p>高品质<strong>声音</strong></p><ul><li>便携</li></ul>';
        $response = $this->postJson('/api/v1/admin/products', [...$this->payload(), 'content' => $html])->assertCreated()
            ->assertJsonPath('data.content', $html);
        $id = $response->json('data.id');
        $this->get('/liquid/theme-demo/product?slug=speaker')->assertOk()->assertSee($html, false)->assertSee('class="product-content"', false);
        $this->patchJson('/api/v1/admin/products/'.$id, ['title' => 'Updated'])->assertOk()->assertJsonPath('data.content', $html);
        $this->patchJson('/api/v1/admin/products/'.$id, ['content' => '<p>新详情</p>'])->assertOk()->assertJsonPath('data.content', '<p>新详情</p>');
        $this->getJson('/api/v1/admin/products')->assertOk()->assertJsonPath('data.0.content', '<p>新详情</p>');
        $this->patchJson('/api/v1/admin/products/'.$id, ['content' => null])->assertOk()->assertJsonPath('data.content', null);
        $this->patchJson('/api/v1/admin/products/'.$id, ['content' => '<p><br></p>'])->assertOk()->assertJsonPath('data.content', null);
        $this->get('/liquid/theme-demo/product?slug=speaker')->assertOk()->assertDontSee('class="product-content"', false);
    }

    public function test_rich_content_removes_scripts_handlers_and_unsafe_urls(): void
    {
        $response = $this->postJson('/api/v1/admin/products', [...$this->payload(), 'content' => '<p onclick="alert(1)">Safe</p><script>alert(1)</script><img src="javascript:alert(1)" onerror="alert(1)"><a href="jav&#x61;script:alert(1)">Bad link</a><img src="https://example.com/image.jpg" onerror="alert(1)"><a href="https://example.com">Good link</a><iframe src="https://example.com"></iframe>'])->assertCreated();
        $html = $response->json('data.content');
        $this->assertStringContainsString('<p>Safe</p>', $html);
        $this->assertStringContainsString('src="https://example.com/image.jpg"', $html);
        $this->assertStringContainsString('href="https://example.com"', $html);
        foreach (['onclick', 'onerror', 'javascript:', '<script', '<iframe', 'alert(1)'] as $unsafe) {
            $this->assertStringNotContainsString($unsafe, $html);
        }
        $this->postJson('/api/v1/admin/products', [...$this->payload(), 'slug' => 'invalid', 'content' => ['invalid']])->assertUnprocessable()->assertJsonValidationErrors('content');
    }

    public function test_create_without_variants_creates_a_default_variant(): void
    {
        $response = $this->postJson('/api/v1/admin/products', $this->payload())->assertCreated()
            ->assertJsonCount(1, 'data.variants')->assertJsonPath('data.variants.0.title', '默认规格')
            ->assertJsonPath('data.variants.0.price', '199.00')->assertJsonPath('data.variants.0.is_available', false);
        $this->assertDatabaseHas('product_variants', ['product_id' => $response->json('data.id'), 'title' => '默认规格', 'position' => 0]);
    }

    public function test_storefront_details_reflect_variants_created_and_edited_from_admin(): void
    {
        $response = $this->postJson('/api/v1/admin/products', [...$this->payload(), 'is_available' => true, 'variants' => [
            ['title' => '黑色款', 'price' => '199.00'], ['title' => '白色款', 'price' => '209.00'],
        ]])->assertCreated();
        $this->get('/liquid/theme-demo/product?slug=speaker')->assertOk()
            ->assertSeeText('黑色款')->assertSeeText('白色款')->assertSeeText('209.00');
        $this->patchJson('/api/v1/admin/products/'.$response->json('data.id'), ['variants' => [
            ['id' => $response->json('data.variants.0.id'), 'title' => '升级黑色款', 'price' => '229.00'],
        ]])->assertOk();
        $this->get('/liquid/theme-demo/product?slug=speaker')->assertOk()
            ->assertSeeText('款式（1）')->assertSeeText('升级黑色款')->assertSeeText('229.00')->assertDontSeeText('白色款');
    }

    public function test_create_persists_multiple_variants_and_returns_them_in_position_order(): void
    {
        $this->postJson('/api/v1/admin/products', [...$this->payload(), 'variants' => [
            ['title' => 'Black', 'price' => '209.50', 'position' => 2],
            ['title' => 'White', 'price' => '199.00', 'is_available' => false, 'position' => 1],
        ]])->assertCreated()->assertJsonCount(2, 'data.variants')
            ->assertJsonPath('data.variants.0.title', 'White')->assertJsonPath('data.variants.1.price', '209.50');
        $this->assertDatabaseCount('products', 1);
        $this->assertDatabaseCount('product_variants', 2);
        $this->getJson('/api/v1/admin/products')->assertOk()->assertJsonCount(2, 'data.0.variants')
            ->assertJsonPath('data.0.variants.0.title', 'White');
    }

    public function test_edit_updates_ids_adds_and_removes_variants_and_reorders_them(): void
    {
        $product = Product::factory()->create();
        $keep = ProductVariant::factory()->for($product)->create();
        $remove = ProductVariant::factory()->for($product)->create();
        $response = $this->patchJson('/api/v1/admin/products/'.$product->id, [
            'title' => 'Updated', 'variants' => [
                ['title' => 'New variant', 'price' => 300],
                ['id' => $keep->id, 'title' => 'Updated variant', 'price' => '250.50', 'is_available' => false],
            ],
        ])->assertOk()->assertJsonCount(2, 'data.variants')->assertJsonPath('data.variants.0.title', 'New variant')
            ->assertJsonPath('data.variants.1.id', $keep->id)->assertJsonPath('data.variants.1.position', 1)
            ->assertJsonPath('data.variants.1.is_available', false);
        $this->assertDatabaseMissing('product_variants', ['id' => $remove->id]);
        $this->assertDatabaseHas('product_variants', ['id' => $keep->id, 'price' => '250.50']);
        $this->assertNotSame($keep->id, $response->json('data.variants.0.id'));
    }

    public function test_omitting_variants_preserves_existing_ids_prices_and_availability(): void
    {
        $product = Product::factory()->create();
        $variant = ProductVariant::factory()->for($product)->create(['price' => '155.00', 'is_available' => true]);
        $this->patchJson('/api/v1/admin/products/'.$product->id, ['price' => '222.00', 'is_available' => false])
            ->assertOk()->assertJsonCount(1, 'data.variants')->assertJsonPath('data.variants.0.id', $variant->id)
            ->assertJsonPath('data.variants.0.price', '155.00')->assertJsonPath('data.variants.0.is_available', true);
    }

    public function test_editing_a_legacy_product_without_variants_adds_a_default(): void
    {
        $product = Product::factory()->create();
        $this->patchJson('/api/v1/admin/products/'.$product->id, ['price' => '233.00'])
            ->assertOk()->assertJsonCount(1, 'data.variants')->assertJsonPath('data.variants.0.price', '233.00');
    }

    public function test_cannot_use_or_reassign_another_products_variant(): void
    {
        $product = Product::factory()->create(['title' => 'Original']);
        $otherVariant = ProductVariant::factory()->create();
        $this->patchJson('/api/v1/admin/products/'.$product->id, [
            'title' => 'Should not save', 'variants' => [['id' => $otherVariant->id, 'title' => 'Hijacked', 'price' => 1]],
        ])->assertUnprocessable()->assertJsonValidationErrors('variants.0.id');
        $this->assertSame('Original', $product->fresh()->title);
        $this->assertNotSame($product->id, $otherVariant->fresh()->product_id);
        $this->postJson('/api/v1/admin/products', [...$this->payload(), 'variants' => [
            ['title' => 'Injected', 'price' => 1, 'product_id' => $otherVariant->product_id],
        ]])->assertUnprocessable()->assertJsonValidationErrors('variants.0');
    }

    public function test_duplicate_variant_ids_and_empty_variant_lists_are_rejected(): void
    {
        $product = Product::factory()->create();
        $variant = ProductVariant::factory()->for($product)->create();
        $row = ['id' => $variant->id, 'title' => 'Repeated', 'price' => 1];
        $this->patchJson('/api/v1/admin/products/'.$product->id, ['variants' => [$row, $row]])
            ->assertUnprocessable()->assertJsonValidationErrors('variants.1.id');
        $this->patchJson('/api/v1/admin/products/'.$product->id, ['variants' => []])->assertUnprocessable();
        $this->postJson('/api/v1/admin/products', [...$this->payload(), 'variants' => []])->assertUnprocessable();
        $this->assertDatabaseHas('product_variants', ['id' => $variant->id]);
    }

    public function test_invalid_nested_fields_reject_the_whole_product(): void
    {
        $this->postJson('/api/v1/admin/products', [...$this->payload(), 'variants' => [
            ['title' => '', 'price' => '12.345', 'position' => 65536, 'is_available' => 'invalid'],
        ]])->assertUnprocessable()->assertJsonValidationErrors(['variants.0.title', 'variants.0.price', 'variants.0.position', 'variants.0.is_available']);
        $this->assertDatabaseCount('products', 0);
        $this->assertDatabaseCount('product_variants', 0);
    }

    public function test_variant_insert_failure_rolls_back_new_product(): void
    {
        $this->withoutExceptionHandling();
        ProductVariant::creating(fn () => throw new RuntimeException('Simulated variant insert failure'));
        try {
            $this->postJson('/api/v1/admin/products', $this->payload());
            $this->fail('Expected insertion to fail');
        } catch (RuntimeException $exception) {
            $this->assertSame('Simulated variant insert failure', $exception->getMessage());
        } finally {
            ProductVariant::flushEventListeners();
        }
        $this->assertDatabaseCount('products', 0);
        $this->assertDatabaseCount('product_variants', 0);
    }

    public function test_variant_insert_failure_rolls_back_product_and_existing_variant_updates(): void
    {
        $product = Product::factory()->create(['title' => 'Original']);
        $variant = ProductVariant::factory()->for($product)->create(['title' => 'Original variant']);
        $this->withoutExceptionHandling();
        ProductVariant::creating(fn () => throw new RuntimeException('Simulated variant insert failure'));
        try {
            $this->patchJson('/api/v1/admin/products/'.$product->id, [
                'title' => 'Changed', 'variants' => [
                    ['id' => $variant->id, 'title' => 'Changed variant', 'price' => 2],
                    ['title' => 'New variant', 'price' => 3],
                ],
            ]);
            $this->fail('Expected insertion to fail');
        } catch (RuntimeException $exception) {
            $this->assertSame('Simulated variant insert failure', $exception->getMessage());
        } finally {
            ProductVariant::flushEventListeners();
        }
        $this->assertSame('Original', $product->fresh()->title);
        $this->assertSame('Original variant', $variant->fresh()->title);
        $this->assertDatabaseCount('product_variants', 1);
    }

    public function test_summernote_content_preserves_tables_styles_and_uploaded_images(): void
    {
        $sanitizer = app(ProductContentSanitizer::class);
        $html = '<p style="text-align: center"><span style="color: rgb(255, 0, 0); font-size: 24px">详情</span></p><table><tbody><tr><td>规格</td></tr></tbody></table><img src="/storage/products/example.png" style="width: 50%">';
        $result = $sanitizer->sanitize($html);
        $this->assertStringContainsString('text-align: center', $result);
        $this->assertStringContainsString('color: rgb(255, 0, 0); font-size: 24px', $result);
        $this->assertStringContainsString('<table><tbody><tr><td>规格</td></tr></tbody></table>', $result);
        $this->assertStringContainsString('src="/storage/products/example.png"', $result);
        $unsafe = $sanitizer->sanitize('<p style="color: expression(alert(1)); background-image: url(javascript:alert(1))">safe</p><img src="//evil.test/x"><img src="/storage/products/../secret.png">');
        $this->assertSame('<p>safe</p>', $unsafe);
    }
}

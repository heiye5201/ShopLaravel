<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Modules\Admin\Models\MarketingCampaign;
use Tests\TestCase;

class MarketingCampaignTest extends TestCase
{
    use RefreshDatabase;

    private function payload(Product $product): array
    {
        return [
            'name' => '日常好物推荐', 'headline' => '给日常多一点好声音',
            'body' => '发现生活中的好物。', 'cta' => '看看商品',
            'theme' => 'mint', 'position' => 'all', 'product_ids' => [$product->id],
            'starts_at' => null, 'ends_at' => null,
        ];
    }

    public function test_marketing_endpoints_are_private(): void
    {
        $this->getJson('/api/v1/admin/marketing/campaigns')->assertUnauthorized();
        $this->getJson('/api/v1/admin/marketing/config')->assertUnauthorized();
        $this->postJson('/api/v1/admin/marketing/campaigns', [])->assertUnauthorized();
        $this->postJson('/api/v1/admin/marketing/generate', [])->assertUnauthorized();
    }

    public function test_campaign_lifecycle_requires_explicit_publication_and_pause_before_editing(): void
    {
        $product = Product::factory()->create();
        $this->actingAs(User::factory()->create());
        $data = $this->payload($product);
        $id = $this->postJson('/api/v1/admin/marketing/campaigns', [...$data, 'status' => 'published'])
            ->assertCreated()->assertJsonPath('data.status', 'draft')->assertJsonPath('data.products.0.id', $product->id)->json('data.id');
        $this->get('/liquid/theme-demo')->assertOk()->assertDontSeeText($data['headline']);
        $this->patchJson('/api/v1/admin/marketing/campaigns/'.$id.'/status', ['status' => 'published'])
            ->assertOk()->assertJsonPath('data.status', 'published');
        $this->get('/liquid/theme-demo')->assertOk()->assertSeeText($data['headline']);
        $this->patchJson('/api/v1/admin/marketing/campaigns/'.$id, [...$data, 'headline' => 'Changed'])
            ->assertConflict();
        $this->patchJson('/api/v1/admin/marketing/campaigns/'.$id.'/status', ['status' => 'paused'])->assertOk();
        $this->get('/liquid/theme-demo')->assertOk()->assertDontSeeText($data['headline']);
        $this->patchJson('/api/v1/admin/marketing/campaigns/'.$id, [...$data, 'headline' => 'Changed'])
            ->assertOk()->assertJsonPath('data.headline', 'Changed');
    }

    public function test_validation_rejects_invalid_products_theme_and_time_range(): void
    {
        $this->actingAs(User::factory()->create());
        $this->postJson('/api/v1/admin/marketing/campaigns', [
            ...$this->payload(Product::factory()->create()), 'theme' => 'javascript:alert(1)',
            'product_ids' => [999999], 'starts_at' => '2026-10-20T10:00:00Z', 'ends_at' => '2026-10-19T10:00:00Z',
        ])->assertUnprocessable()->assertJsonValidationErrors(['theme', 'product_ids.0', 'ends_at']);
    }

    public function test_publication_rejects_unavailable_products_and_expired_campaigns(): void
    {
        $this->actingAs(User::factory()->create());
        $product = Product::factory()->unavailable()->create();
        $campaign = MarketingCampaign::factory()->create(['product_ids' => [$product->id]]);
        $this->patchJson('/api/v1/admin/marketing/campaigns/'.$campaign->id.'/status', ['status' => 'published'])
            ->assertUnprocessable()->assertJsonValidationErrors('product_ids');
        $product->update(['is_available' => true]);
        $campaign->update(['ends_at' => now()->subMinute()]);
        $this->patchJson('/api/v1/admin/marketing/campaigns/'.$campaign->id.'/status', ['status' => 'published'])
            ->assertUnprocessable()->assertJsonValidationErrors('ends_at');
    }

    public function test_display_respects_dates_position_availability_and_escapes_copy(): void
    {
        $product = Product::factory()->create(['slug' => 'selected-product']);
        $another = Product::factory()->create(['slug' => 'another-product']);
        $campaign = MarketingCampaign::factory()->create([
            'product_ids' => [$product->id, $another->id], 'status' => 'published', 'position' => 'home',
            'headline' => '<script>alert("unsafe")</script>',
        ]);
        $this->get('/liquid/theme-demo')->assertOk()->assertSee('&lt;script&gt;', false)
            ->assertDontSee('<script>alert', false)->assertSee('slug=selected-product');
        $this->get('/liquid/theme-demo/product?slug=selected-product')->assertOk()->assertDontSee('&lt;script&gt;', false);
        $campaign->update(['position' => 'all']);
        $this->get('/liquid/theme-demo/product?slug=selected-product')->assertOk()
            ->assertSee('slug=another-product')->assertSeeText($product->title);
        $product->update(['is_available' => false]);
        $another->update(['is_available' => false]);
        $this->get('/liquid/theme-demo')->assertOk()->assertDontSee('&lt;script&gt;', false);
        $product->update(['is_available' => true]);
        $campaign->update(['starts_at' => now()->addHour()]);
        $this->get('/liquid/theme-demo')->assertDontSee('&lt;script&gt;', false);
        $campaign->update(['starts_at' => null, 'ends_at' => now()->subMinute()]);
        $this->get('/liquid/theme-demo')->assertDontSee('&lt;script&gt;', false);
    }

    public function test_search_and_status_filter_campaigns(): void
    {
        $this->actingAs(User::factory()->create());
        MarketingCampaign::factory()->create(['name' => 'Summer', 'status' => 'published']);
        MarketingCampaign::factory()->create(['name' => 'Summer draft']);
        $this->getJson('/api/v1/admin/marketing/campaigns?search=Summer&status=published')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('meta.total', 1);
    }

    public function test_unconfigured_ai_reports_unavailability_without_fake_generation_or_saving(): void
    {
        config(['admin.ai.base_url' => null, 'admin.ai.key' => null, 'admin.ai.model' => null]);
        Http::preventStrayRequests();
        $this->actingAs(User::factory()->create());
        $this->getJson('/api/v1/admin/marketing/config')->assertOk()
            ->assertJsonPath('data.configured', false)->assertJsonMissingPath('data.key');
        $this->postJson('/api/v1/admin/marketing/generate', [
            'product_ids' => [Product::factory()->create()->id], 'goal' => '新品推荐',
        ])->assertStatus(503);
        $this->assertDatabaseCount('marketing_campaigns', 0);
        Http::assertNothingSent();
    }

    public function test_generic_marketing_environment_configures_deepseek_without_exposing_the_key(): void
    {
        $keys = ['MARKETING_AI_KEY', 'MARKETING_AI_BASE_URL', 'MARKETING_AI_MODEL', 'MARKETING_AI_TIMEOUT'];
        $originalEnv = $_ENV;
        $originalServer = $_SERVER;
        try {
            foreach ($keys as $key) {
                unset($_ENV[$key], $_SERVER[$key]);
            }
            $_ENV['MARKETING_AI_KEY'] = 'test-only-deepseek-key';
            $_SERVER['MARKETING_AI_KEY'] = 'test-only-deepseek-key';
            $_ENV['MARKETING_AI_BASE_URL'] = $_SERVER['MARKETING_AI_BASE_URL'] = 'https://api.deepseek.com';
            $_ENV['MARKETING_AI_MODEL'] = $_SERVER['MARKETING_AI_MODEL'] = 'deepseek-flash';
            $settings = require module_path('Admin', 'config/config.php');
            $this->assertSame('test-only-deepseek-key', $settings['ai']['key']);
            $this->assertSame('https://api.deepseek.com', $settings['ai']['base_url']);
            $this->assertSame('deepseek-flash', $settings['ai']['model']);
            config(['admin.ai' => $settings['ai']]);
            $this->actingAs(User::factory()->create())->getJson('/api/v1/admin/marketing/config')
                ->assertOk()->assertJsonPath('data.configured', true)
                ->assertJsonPath('data.model', 'deepseek-flash')->assertDontSee('test-only-deepseek-key');
        } finally {
            $_ENV = $originalEnv;
            $_SERVER = $originalServer;
        }
    }

    public function test_ai_http_integration_against_postman_simulation(): void
    {
        $baseUrl = getenv('MARKETING_SIMULATION_URL');
        if (! $baseUrl) {
            $this->markTestSkipped('设置 MARKETING_SIMULATION_URL 后运行真实 HTTP 模拟测试');
        }
        config(['admin.ai.base_url' => $baseUrl, 'admin.ai.key' => 'simulation-only', 'admin.ai.model' => 'deepseek-flash']);
        $this->actingAs(User::factory()->create());
        $payload = ['product_ids' => [Product::factory()->create()->id], 'goal' => '新品推荐'];
        $this->postJson('/api/v1/admin/marketing/generate', $payload)->assertOk()
            ->assertJsonStructure(['data' => ['name', 'headline', 'body', 'cta']]);
        $this->assertDatabaseCount('marketing_campaigns', 0);
        $this->postJson('/api/v1/admin/marketing/generate', [...$payload, 'goal' => 'simulate:invalid'])
            ->assertStatus(502);
        $this->postJson('/api/v1/admin/marketing/generate', [...$payload, 'goal' => 'simulate:unavailable'])
            ->assertStatus(502);
        $this->postJson('/api/v1/admin/marketing/generate', [...$payload, 'goal' => 'simulate:rate-limit'])
            ->assertStatus(502);
        $this->postJson('/api/v1/admin/marketing/generate', [...$payload, 'goal' => 'simulate:balance'])
            ->assertStatus(502)->assertJsonPath('message', 'AI 服务账户余额不足，请充值后重试，草稿未被修改。');
        $this->assertDatabaseCount('marketing_campaigns', 0);
        config(['admin.ai.timeout' => 1]);
        $this->postJson('/api/v1/admin/marketing/generate', [...$payload, 'goal' => 'simulate:timeout'])->assertStatus(502);
        config(['admin.ai.base_url' => 'http://127.0.0.1:1']);
        $this->postJson('/api/v1/admin/marketing/generate', $payload)->assertStatus(502);
    }
}

<?php

namespace Tests\Unit\Liquid\Filters;

use App\Liquid\Filters\ShopFilters;
use Tests\TestCase;

class ShopFiltersTest extends TestCase
{
    private ShopFilters $filters;

    protected function setUp(): void
    {
        parent::setUp();

        $this->filters = new ShopFilters;
    }

    public function test_formats_money(): void
    {
        $formatted = $this->filters->money(699.5, 'CNY', 'zh_CN');

        $this->assertStringContainsString('699.50', str_replace(',', '', $formatted));
        $this->assertTrue(str_contains($formatted, '¥') || str_contains($formatted, 'CN¥'));
        $this->assertSame('', $this->filters->money('not-a-number'));
    }

    public function test_formats_date_in_requested_timezone(): void
    {
        $this->assertSame(
            '2026-09-10 10:30',
            $this->filters->date_format('2026-09-10T02:30:00+00:00', 'Y-m-d H:i', 'Asia/Shanghai'),
        );
        $this->assertSame('', $this->filters->date_format('not-a-date'));
    }

    public function test_builds_a_resized_image_url(): void
    {
        $this->assertSame(
            asset('images/aurora-speaker.svg').'?width=720&height=420&fit=crop',
            $this->filters->image_url('/images/aurora-speaker.svg', 720, 420, 'crop'),
        );
    }

    public function test_translates_a_language_key(): void
    {
        $this->assertSame(
            '现货，可立即发出',
            $this->filters->translate('liquid_filters.stock.available', 'zh_CN'),
        );
        $this->assertSame(
            'In stock and ready to ship',
            $this->filters->translate('liquid_filters.stock.available', 'en'),
        );
    }
}

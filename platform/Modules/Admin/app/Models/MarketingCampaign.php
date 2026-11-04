<?php

namespace Modules\Admin\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Modules\Admin\Database\Factories\MarketingCampaignFactory;

class MarketingCampaign extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'headline', 'body', 'cta', 'theme', 'position', 'product_ids', 'starts_at', 'ends_at'];

    protected function casts(): array
    {
        return ['product_ids' => 'array', 'starts_at' => 'datetime', 'ends_at' => 'datetime'];
    }

    protected static function newFactory(): MarketingCampaignFactory
    {
        return MarketingCampaignFactory::new();
    }
}

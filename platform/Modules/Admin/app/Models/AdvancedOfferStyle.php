<?php

namespace Modules\Admin\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Modules\Admin\Database\Factories\AdvancedOfferStyleFactory;

class AdvancedOfferStyle extends Model
{
    use HasFactory;

    public const BADGES = ['circle', 'hexagon', 'star', 'wave', 'sunburst', 'bubble', 'rectangle', 'ribbon', 'capsule', 'flame', 'arrow', 'ticket', 'speed', 'soft-burst', 'sharp-burst', 'scallop', 'oval', 'fire'];

    protected $fillable = ['name', 'enabled', 'scope', 'product_ids', 'variant_ids', 'tiers', 'design'];

    protected function casts(): array
    {
        return ['enabled' => 'boolean', 'product_ids' => 'array', 'variant_ids' => 'array', 'tiers' => 'array', 'design' => 'array'];
    }

    public static function defaults(): array
    {
        return [
            'name' => '', 'enabled' => false, 'scope' => 'all', 'product_ids' => [], 'variant_ids' => [],
            'tiers' => [['quantity' => 1, 'title' => 'Buy {{$buyValue}}', 'subtitle' => '', 'label' => 'Popular', 'badge_text' => 'Save {{$saveValue}}']],
            'design' => [
                'title_mode' => 'default', 'title' => '', 'palette' => 'violet',
                'colors' => ['text' => '#712695', 'surface' => '#ffffff', 'active_surface' => '#f3eaf8', 'border' => '#dedede', 'active_border' => '#742d99'],
                'thumbnails' => false, 'marquee' => false, 'radius' => 4,
                'badge_shape' => 'rectangle', 'badge_mode' => 'dynamic', 'badge_text' => '推荐',
                'badge_animation' => false, 'badge_tilt' => false, 'hide_quantity' => false,
            ],
        ];
    }

    protected static function newFactory(): AdvancedOfferStyleFactory
    {
        return AdvancedOfferStyleFactory::new();
    }
}

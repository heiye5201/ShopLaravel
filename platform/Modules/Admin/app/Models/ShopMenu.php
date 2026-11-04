<?php

namespace Modules\Admin\Models;

use Illuminate\Database\Eloquent\Model;

class ShopMenu extends Model
{
    protected $fillable = ['name', 'placement', 'items'];

    protected function casts(): array
    {
        return ['items' => 'array'];
    }
}

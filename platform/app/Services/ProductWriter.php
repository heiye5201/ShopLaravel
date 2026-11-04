<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProductWriter
{
    public function create(array $data): Product
    {
        return DB::transaction(function () use ($data): Product {
            $product = Product::create(Arr::except($data, 'variants'))->refresh();
            $this->syncVariants($product, $data['variants'] ?? [$this->defaultVariant($product)]);

            $this->validateOptions($product);

            return $product->load('variants');
        });
    }

    public function update(Product $product, array $data): Product
    {
        return DB::transaction(function () use ($product, $data): Product {
            $product = Product::query()->lockForUpdate()->findOrFail($product->id);
            $product->update(Arr::except($data, 'variants'));
            if (array_key_exists('variants', $data)) {
                $this->syncVariants($product, $data['variants']);
            } elseif (! $product->variants()->exists()) {
                $this->syncVariants($product, [$this->defaultVariant($product)]);
            }

            $this->validateOptions($product);

            return $product->load('variants');
        });
    }

    private function validateOptions(Product $product): void
    {
        $options = $product->variant_options ?? [];
        $variants = $product->variants()->get();
        $expected = 1;
        foreach ($options as $index => $option) {
            if (count(array_unique($option['values'])) !== count($option['values'])) {
                throw ValidationException::withMessages(["variant_options.{$index}.values" => '同一规格的值不能重复。']);
            }
            $expected *= count($option['values']);
        }
        if ($options && ($expected > 100 || $variants->count() !== $expected)) {
            throw ValidationException::withMessages(['variants' => '请提交完整的规格组合，最多支持 100 个组合。']);
        }
        $seen = [];
        $skus = [];
        foreach ($variants as $index => $variant) {
            if ($variant->sku !== null && $variant->sku !== '') {
                if (in_array($variant->sku, $skus, true)) {
                    throw ValidationException::withMessages(["variants.{$index}.sku" => '同一商品的 SKU 不能重复。']);
                }
                $skus[] = $variant->sku;
            }
            $values = $variant->option_values ?? [];
            if (count($values) !== count($options)) {
                throw ValidationException::withMessages(["variants.{$index}.option_values" => '规格值与商品规格不匹配。']);
            }
            foreach ($options as $position => $option) {
                if (! in_array($values[$position], $option['values'], true)) {
                    throw ValidationException::withMessages(["variants.{$index}.option_values" => '规格值不属于当前商品。']);
                }
            }
            if ($options) {
                $key = json_encode($values);
                if (in_array($key, $seen, true)) {
                    throw ValidationException::withMessages(["variants.{$index}.option_values" => '规格组合不能重复。']);
                }
                $seen[] = $key;
            }
        }
    }

    private function defaultVariant(Product $product): array
    {
        return ['title' => '默认规格', 'price' => $product->price, 'is_available' => $product->is_available, 'position' => 0];
    }

    private function syncVariants(Product $product, array $variants): void
    {
        $existing = $product->variants()->lockForUpdate()->get()->keyBy('id');
        $retained = [];
        foreach ($variants as $index => $attributes) {
            $id = $attributes['id'] ?? null;
            $values = Arr::except($attributes, 'id');
            $values['position'] = $values['position'] ?? $index;
            if ($id !== null) {
                if (! $existing->has($id)) {
                    throw ValidationException::withMessages(["variants.{$index}.id" => '规格不存在或不属于当前商品，请刷新后重试。']);
                }
                $variant = $existing->get($id);
                $variant->update($values);
            } else {
                $variant = $product->variants()->create($values);
            }
            $retained[] = $variant->id;
        }
        $product->variants()->whereNotIn('id', $retained)->delete();
    }
}

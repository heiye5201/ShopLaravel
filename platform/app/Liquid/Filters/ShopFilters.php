<?php

namespace App\Liquid\Filters;

use DateTimeInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Lang;
use NumberFormatter;
use Throwable;

final class ShopFilters
{
    public function money(mixed $value, string $currency = 'CNY', ?string $locale = null): string
    {
        if (! is_numeric($value)) {
            return '';
        }

        $locale = str_replace('-', '_', $locale ?? app()->getLocale());
        $currency = strtoupper($currency);

        if (class_exists(NumberFormatter::class)) {
            $formatter = new NumberFormatter($locale, NumberFormatter::CURRENCY);
            $formatted = $formatter->formatCurrency((float) $value, $currency);

            if ($formatted !== false) {
                return $formatted;
            }
        }

        $symbol = match ($currency) {
            'CNY' => '¥',
            'USD' => '$',
            'EUR' => '€',
            default => $currency.' ',
        };

        return $symbol.number_format((float) $value, 2);
    }

    public function date_format(
        mixed $value,
        string $format = 'Y-m-d H:i',
        ?string $timezone = null,
    ): string {
        if ($value === null || $value === '') {
            return '';
        }

        try {
            $date = $value instanceof DateTimeInterface
                ? Carbon::instance($value)
                : Carbon::parse($value);

            return $date
                ->timezone($timezone ?? config('app.timezone'))
                ->format($format);
        } catch (Throwable) {
            return '';
        }
    }

    public function image_url(
        mixed $value,
        ?int $width = null,
        ?int $height = null,
        string $fit = 'cover',
    ): string {
        if (! is_string($value) || trim($value) === '') {
            return '';
        }

        $url = preg_match('/^https?:\/\//i', $value) === 1
            ? $value
            : asset(ltrim($value, '/'));

        $parameters = array_filter([
            'width' => $width,
            'height' => $height,
            'fit' => $width !== null || $height !== null ? $fit : null,
        ], static fn (mixed $item): bool => $item !== null);

        if ($parameters === []) {
            return $url;
        }

        return $url
            .(str_contains($url, '?') ? '&' : '?')
            .http_build_query($parameters);
    }

    public function translate(mixed $key, ?string $locale = null): string
    {
        if (! is_string($key) || $key === '') {
            return '';
        }

        return Lang::get($key, [], $locale);
    }
}

<?php

namespace App\Providers;

use App\Liquid\Filters\ShopFilters;
use App\Liquid\Tags\TagPanel;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use Liquid\Template;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(Template::class, function (Application $app): Template {
            $template = new Template(resource_path('liquid'));
            $template->registerFilter($app->make(ShopFilters::class));

            return $template;
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Template::registerTag('panel', TagPanel::class);
    }
}

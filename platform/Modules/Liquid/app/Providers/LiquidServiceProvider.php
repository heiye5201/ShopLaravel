<?php

namespace Modules\Liquid\Providers;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Foundation\Application;
use Liquid\Template;
use Modules\Liquid\Filters\ShopFilters;
use Modules\Liquid\Tags\TagPanel;
use Nwidart\Modules\Support\ModuleServiceProvider;

class LiquidServiceProvider extends ModuleServiceProvider
{
    /**
     * The name of the module.
     */
    protected string $name = 'Liquid';

    /**
     * The lowercase version of the module name.
     */
    protected string $nameLower = 'liquid';

    /**
     * Command classes to register.
     *
     * @var string[]
     */
    // protected array $commands = [];

    /**
     * Provider classes to register.
     *
     * @var string[]
     */
    protected array $providers = [
        EventServiceProvider::class,
        RouteServiceProvider::class,
    ];

    public function register(): void
    {
        parent::register();

        $this->app->bind(Template::class, function (Application $app): Template {
            $template = new Template(module_path($this->name, 'resources/liquid'));
            $template->registerFilter($app->make(ShopFilters::class));

            return $template;
        });
    }

    public function boot(): void
    {
        parent::boot();

        Template::registerTag('panel', TagPanel::class);
    }

    /**
     * Define module schedules.
     *
     * @param  $schedule
     */
    // protected function configureSchedules(Schedule $schedule): void
    // {
    //     $schedule->command('inspire')->hourly();
    // }
}

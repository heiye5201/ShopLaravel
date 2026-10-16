<?php

namespace Modules\Liquid\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Response;
use Liquid\Template;
use Modules\Liquid\Services\LiquidDemoPageService;

final class LiquidThemeDemoController extends Controller
{
    public function __construct(private readonly LiquidDemoPageService $pages) {}

    public function home(Template $template): Response
    {
        $html = $this->pages->renderThemeHome($template);

        return response($html)->header('Content-Type', 'text/html; charset=UTF-8');
    }

    public function product(Template $template): Response
    {
        $html = $this->pages->renderThemeProduct($template);

        return response($html)->header('Content-Type', 'text/html; charset=UTF-8');
    }
}

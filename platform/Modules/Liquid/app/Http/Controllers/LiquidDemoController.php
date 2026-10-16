<?php

namespace Modules\Liquid\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Response;
use Liquid\Template;
use Modules\Liquid\Services\LiquidDemoPageService;

class LiquidDemoController extends Controller
{
    public function __construct(private readonly LiquidDemoPageService $pages) {}

    public function __invoke(Template $template): Response
    {
        $html = $this->pages->renderProduct($template);

        return response($html)->header('Content-Type', 'text/html; charset=UTF-8');
    }
}

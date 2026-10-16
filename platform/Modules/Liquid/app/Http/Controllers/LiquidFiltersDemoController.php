<?php

namespace Modules\Liquid\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Liquid\Template;
use Modules\Liquid\Services\LiquidDemoPageService;

class LiquidFiltersDemoController extends Controller
{
    public function __construct(private readonly LiquidDemoPageService $pages) {}

    public function __invoke(Request $request, Template $template): Response
    {
        $html = $this->pages->renderFilters($template, $request->query('locale'));

        return response($html)->header('Content-Type', 'text/html; charset=UTF-8');
    }
}

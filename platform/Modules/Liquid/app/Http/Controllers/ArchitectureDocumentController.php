<?php

namespace Modules\Liquid\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Modules\Liquid\Services\ArchitectureDocumentService;

class ArchitectureDocumentController extends Controller
{
    public function __construct(private readonly ArchitectureDocumentService $documents) {}

    public function adminGraphqlDataAccess(): View
    {
        return view('liquid::architecture.document', [
            'title' => 'Admin GraphQL 数据获取底座设计思考',
            'content' => $this->documents->renderAdminGraphqlDataAccess(),
        ]);
    }
}

<?php

namespace Modules\Liquid\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class ArchitectureDocumentService
{
    public function renderAdminGraphqlDataAccess(): string
    {
        $markdown = File::get(module_path(
            'Liquid',
            'resources/docs/admin-graphql-data-access-foundation.md',
        ));

        return Str::markdown($markdown, [
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ]);
    }
}

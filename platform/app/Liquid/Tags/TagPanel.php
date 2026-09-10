<?php

namespace App\Liquid\Tags;

use Liquid\AbstractBlock;
use Liquid\Context;

final class TagPanel extends AbstractBlock
{
    public function render(Context $context): string
    {
        return '<section class="demo-panel">'.parent::render($context).'</section>';
    }
}

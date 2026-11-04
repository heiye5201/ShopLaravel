<?php

return [
    'name' => 'Admin',
    'ai' => [
        'base_url' => env('MARKETING_AI_BASE_URL'),
        'key' => env('MARKETING_AI_KEY'),
        'model' => env('MARKETING_AI_MODEL'),
        'timeout' => (int) env('MARKETING_AI_TIMEOUT', 30),
    ],
];

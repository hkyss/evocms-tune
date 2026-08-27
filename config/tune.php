<?php

return [
    'connection' => env('TUNE_CONNECTION'),

    'tier' => env('TUNE_TIER', 'core'),

    'prune' => [
        'event_log' => [
            'column' => 'createdon',
            'days' => (int) env('TUNE_PRUNE_EVENT_LOG_DAYS', 90),
        ],
        'manager_log' => [
            'column' => 'timestamp',
            'days' => (int) env('TUNE_PRUNE_MANAGER_LOG_DAYS', 180),
        ],
    ],

    'prune_batch' => (int) env('TUNE_PRUNE_BATCH', 5000),
];

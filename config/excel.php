<?php

return [
    'imports' => [
        'read_only' => true,
        'ignore_empty' => false,
        'heading_row' => [
            'formatter' => 'slug'
        ],
        'csv' => [
            'delimiter' => ',',
            'enclosure' => '"',
            'input_encoding' => 'UTF-8',
        ],
    ],
    'exports' => [
        'chunk_size' => 1000,
    ],
    'cache' => [
        'driver' => 'memory',
    ],
];

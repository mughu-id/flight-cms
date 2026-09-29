<?php

declare(strict_types=1);

return [
    'app' => [
        'name' => 'Flight CMS',
        'url' => 'http://localhost:1155',
        'debug' => false,
        'timezone' => 'UTC',
        'key' => '__APP_KEY__',
    ],
    'database' => [
        'path' => 'storage/database/cms.sqlite',
    ],
    'runway' => [
        'app_root' => 'app/',
        'public_root' => '',
        'index_root' => 'index.php',
    ],
];

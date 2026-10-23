<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Storefront tĩnh (store/) chạy trên một server khác (port khác) với
    | Laravel, nên các route API/storage phải cho phép gọi cross-origin.
    | allowed_origins '*' chỉ hợp lý cho môi trường dev/đồ án.
    |
    */

    'paths' => ['api/*', 'storage/*'],

    'allowed_methods' => ['*'],

    'allowed_origins' => ['*'],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => false,

];

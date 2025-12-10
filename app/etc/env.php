<?php
return [
    'backend' => [
        'frontName' => 'sysadminy'
    ],
    'remote_storage' => [
        'driver' => 'file'
    ],
    'queue' => [
        'consumers_wait_for_messages' => 1
    ],
    'crypt' => [
        'key' => '864d830f6b508b7ef6fedba974f707f0'
    ],
    'db' => [
        'table_prefix' => '',
        'connection' => [
            'default' => [
                'host' => '127.0.0.1:3307',
                'dbname' => 'technadminy7_dBT8x12y22',
                'username' => 'technadminy7_ntdbusr24',
                'password' => 'the-correct-password',
                'model' => 'mysql4',
                'engine' => 'innodb',
                'initStatements' => 'SET NAMES utf8;',
                'active' => '1',
                'driver_options' => [
                    1014 => false
                ]
            ]
        ]
    ],
    'resource' => [
        'default_setup' => [
            'connection' => 'default'
        ]
    ],
    'x-frame-options' => 'SAMEORIGIN',
    'MAGE_MODE' => 'production',
    'cache' => [
        'frontend' => [
            'default' => [
                'backend' => 'Cm_Cache_Backend_Redis',
                'backend_options' => [
                    'server' => '127.0.0.1',
                    'database' => '0',
                    'port' => '6379',
                    'password' => '',
                    'compress_data' => '1',
                    'compression_lib' => ''
                ]
            ],
            'page_cache' => [
                'backend' => 'Cm_Cache_Backend_Redis',
                'backend_options' => [
                    'server' => '127.0.0.1',
                    'database' => '1',
                    'port' => '6379',
                    'password' => '',
                    'compress_data' => '0',
                    'database_pattern' => '/^[0-9]+$/'
                ]
            ]
        ]
    ],
    'session' => [
        'save' => 'redis',
        'redis' => [
            'host' => '127.0.0.1',
            'port' => '6379',
            'password' => '',
            'timeout' => '2.5',
            'persistent_identifier' => '',
            'database' => '2',
            'compression_threshold' => '2048',
            'compression_library' => 'gzip',
            'log_level' => '1',
            'max_concurrency' => '6',
            'break_after_frontend' => '5',
            'break_after_adminhtml' => '30',
            'first_lifetime' => '600',
            'bot_first_lifetime' => '60',
            'bot_lifetime' => '7200',
            'disable_locking' => '0',
            'min_lifetime' => '60',
            'max_lifetime' => '2592000'
        ]
    ],
    'lock' => [
        'provider' => 'db'
    ],
    'directories' => [
        'document_root_is_pub' => false
    ],
    'downloadable_domains' => [
        'technostationery.com'
    ],
    'install' => [
        'date' => 'Mon, 10 Nov 2025 09:05:27 +0000'
    ],
    'cors' => [
        'allowed_origins' => [
            '*'
        ],
        'allowed_methods' => [
            'GET',
            'POST',
            'PUT',
            'DELETE',
            'OPTIONS'
        ],
        'allowed_headers' => [
            '*'
        ],
        'exposed_headers' => [
            '*'
        ],
        'max_age' => 3600,
        'supports_credentials' => true
    ],
    'cache_types' => [
        'compiled_config' => 1,
        'config' => 1,
        'layout' => 1,
        'block_html' => 1,
        'collections' => 1,
        'reflection' => 1,
        'db_ddl' => 1,
        'eav' => 1,
        'customer_notification' => 1,
        'config_integration' => 1,
        'config_integration_api' => 1,
        'full_page' => 1,
        'config_webservice' => 1,
        'translate' => 1,
        'amasty_report_builder_scheme' => 1,
        'mab_delivery' => 1,
        'data_layer' => 1,
        'amasty_blog' => 1,
        'elasticsuite' => 1,
        'checkout' => 1
    ],
    'system' => [
        'default' => [
            'system' => [
                'full_page_cache' => [
                    'caching_application' => '2'
                ]
            ]
        ]
    ]
];

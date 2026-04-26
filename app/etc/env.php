<?php
return [
    'backend' => [
        'frontName' => 'sysadminy'
    ],
    'remote_storage' => [
        'driver' => 'file'
    ],
    'queue' => [
        'consumers_wait_for_messages' => 0
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
                    'compression_lib' => 'gzip',
                    'force_standalone' => '0',
                    'connect_retries' => '5',
                    'read_timeout' => '2',
                    'automatic_cleaning_factor' => '0',
                    'compress_tags' => '1',
                    'compress_threshold' => '20480'
                ]
            ],
            'page_cache' => [
                'backend' => 'Cm_Cache_Backend_Redis',
                'backend_options' => [
                    'server' => '127.0.0.1',
                    'database' => '1',
                    'port' => '6379',
                    'password' => '',
                    'compress_data' => '1',
                    'compression_lib' => 'gzip',
                    'compress_tags' => '1',
                    'compress_threshold' => '20480',
                    'database_pattern' => '/^[0-9]+$/'
                ]
            ]
        ],
        'allow_parallel_generation' => true
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
            'disable_locking' => '1',
            'min_lifetime' => '60',
            'max_lifetime' => '2592000'
        ]
    ],
    'lock' => [
        'provider' => 'db'
    ],
    'directories' => [
        'document_root_is_pub' => true
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
            'admin' => [
                'url' => [
                    'custom' => null,
                    'custom_path' => null
                ]
            ],
            'web' => [
                'unsecure' => [
                    'base_url' => 'https://technostationery.com/',
                    'base_link_url' => 'http://technostationery.com/',
                    'base_static_url' => null,
                    'base_media_url' => null
                ],
                'secure' => [
                    'base_url' => 'https://technostationery.com/',
                    'base_link_url' => 'https://technostationery.com/',
                    'base_static_url' => null,
                    'base_media_url' => null
                ],
                'default' => [
                    'front' => 'cms'
                ],
                'cookie' => [
                    'cookie_path' => '/',
                    'cookie_domain' => 'technostationery.com'
                ]
            ],
            'catalog' => [
                'productalert_cron' => [
                    'error_email' => null
                ],
                'product_video' => [
                    'youtube_api_key' => null
                ],
                'search' => [
                    'elasticsearch7_server_hostname' => '127.0.0.1',
                    'elasticsearch7_server_port' => '9200',
                    'elasticsearch7_index_prefix' => 'techno_stationery',
                    'elasticsearch7_enable_auth' => '0',
                    'elasticsearch7_server_timeout' => '15',
                    'elasticsearch7_minimum_should_match' => '0',
                    'opensearch_server_port' => '9200',
                    'opensearch_index_prefix' => 'magento2',
                    'opensearch_enable_auth' => '0',
                    'opensearch_server_timeout' => '15',
                    'min_query_length' => '2',
                    'max_query_length' => '128',
                    'search_suggestion_enabled' => '1',
                    'search_suggestion_count' => '5',
                    'search_recommendations_enabled' => '1',
                    'search_recommendations_count' => '5'
                ]
            ],
            'cataloginventory' => [
                'source_selection_distance_based_google' => [
                    'api_key' => null
                ]
            ],
            'currency' => [
                'import' => [
                    'error_email' => null
                ]
            ],
            'sitemap' => [
                'generate' => [
                    'error_email' => null
                ]
            ],
            'trans_email' => [
                'ident_general' => [
                    'name' => 'contact',
                    'email' => 'contact@technostationery.com'
                ],
                'ident_sales' => [
                    'name' => 'Sales',
                    'email' => 'sales@technostationery.com'
                ],
                'ident_support' => [
                    'name' => 'Customer Support',
                    'email' => 'sales@technostationery.com'
                ],
                'ident_custom1' => [
                    'name' => 'Custom 1',
                    'email' => 'contact@technostationery.com'
                ],
                'ident_custom2' => [
                    'name' => 'Custom 2',
                    'email' => 'contact@technostationery.com'
                ]
            ],
            'contact' => [
                'email' => [
                    'recipient_email' => 'contact@technostationery.com.dz'
                ]
            ],
            'sales_email' => [
                'order' => [
                    'copy_to' => 'sales.ecommerce@techno-dz.com,socialbx@gmail.com,amine.tms2021@gmail.com,yacine.ho.tms@gmail.com'
                ],
                'order_comment' => [
                    'copy_to' => null
                ],
                'invoice' => [
                    'copy_to' => null
                ],
                'invoice_comment' => [
                    'copy_to' => null
                ],
                'shipment' => [
                    'copy_to' => null
                ],
                'shipment_comment' => [
                    'copy_to' => null
                ],
                'creditmemo' => [
                    'copy_to' => null
                ],
                'creditmemo_comment' => [
                    'copy_to' => null
                ]
            ],
            'checkout' => [
                'payment_failed' => [
                    'copy_to' => 'webmaster@technostationery.com'
                ]
            ],
            'google' => [
                'analytics' => [
                    'account' => 'UA-196342035-1'
                ],
                'gtag' => [
                    'analytics4' => [
                        'measurement_id' => 'G-72HCNG92F2'
                    ],
                    'adwords' => [
                        'conversion_id' => 'G-72HCNG92F2'
                    ]
                ]
            ],
            'payment' => [
                'checkmo' => [
                    'mailing_address' => null
                ]
            ],
            'recaptcha_backend' => [
                'type_recaptcha' => [
                    'public_key' => null,
                    'private_key' => null
                ],
                'type_invisible' => [
                    'public_key' => null,
                    'private_key' => null
                ],
                'type_recaptcha_v3' => [
                    'public_key' => '0:3:AYAvuPo2BWc6oSJetk6+JarEwQgt9BefnqcsT+pqcY0cWrP2t5enOkH46ooeNbmxKzbYynDqOqulwDUo1rBHb05ArwY=',
                    'private_key' => '0:3:EDEaqXsosmBzy0kezhxfA1mj6nSBr/XO3LGKA91ptlVncWgMEJAFq72OESQmfjW6ctoTXyGKGBF56h44i+qrNz7WqFs='
                ]
            ],
            'recaptcha_frontend' => [
                'type_recaptcha' => [
                    'public_key' => '0:3:aGQ1O73jIUssUSLCaUlbulGpYK2LX2G81+TLmT8O3A07tQi0pw3tpxZe9lpf5dyc8wrBrn0Smg48lmsXpD2piiGq5Es=',
                    'private_key' => '0:3:Lw019BjY/8SWvIyp3u/nNAk/rtivfAAyFEum1rQZEOqNqYBR1KjcF/E5RJTdYREqtg/DQLpTazgiu8SER9yS9ZZtJxQ='
                ],
                'type_invisible' => [
                    'public_key' => null,
                    'private_key' => null
                ],
                'type_recaptcha_v3' => [
                    'public_key' => '0:3:mV6Qd7BJUr2aRc687hCAYN69tSfkrVC1BWAMaepdU0x0HjJ3QVRs4BZEu+ReK1rOYB8WXi+vcryuRMnYeelAVrHF3nY=',
                    'private_key' => '0:3:zsBxYfBQV3KeFYrdEM+h03mUkU2/x0mFEeJpnZo46zwiVef8+iEk2/bWOCAqDuXhII5hjKFltzcQt8n9qUIR34qr0+M='
                ]
            ],
            'system' => [
                'smtp' => [
                    'host' => '127.0.0.1:3307',
                    'port' => '25'
                ],
                'full_page_cache' => [
                    'varnish' => [
                        'access_list' => 'localhost,127.0.0.1',
                        'backend_host' => '127.0.0.1',
                        'backend_port' => '8080'
                    ]
                ],
                'release_notification' => [
                    'content_url' => 'magento.com/release_notifications',
                    'use_https' => '1'
                ]
            ],
            'dev' => [
                'restrict' => [
                    'allow_ips' => null
                ],
                'js' => [
                    'session_storage_key' => 'collected_errors',
                    'merge_files' => '1',
                    'minify_files' => '1',
                    'enable_js_bundling' => '0',
                    'move_script_to_bottom' => '1'
                ],
                'css' => [
                    'merge_css_files' => '1',
                    'minify_files' => '1'
                ],
                'template' => [
                    'minify_html' => '1'
                ]
            ],
            'analytics' => [
                'general' => [
                    'token' => null
                ],
                'url' => [
                    'signup' => 'https://advancedreporting.rjmetrics.com/signup',
                    'update' => 'https://advancedreporting.rjmetrics.com/update',
                    'bi_essentials' => 'https://dashboard.rjmetrics.com/v2/magento/signup',
                    'otp' => 'https://advancedreporting.rjmetrics.com/otp',
                    'report' => 'https://advancedreporting.rjmetrics.com/report',
                    'notify_data_changed' => 'https://advancedreporting.rjmetrics.com/report'
                ]
            ],
            'crontab' => [
                'default' => [
                    'jobs' => [
                        'analytics_collect_data' => [
                            'schedule' => [
                                'cron_expr' => '00 02 * * *'
                            ]
                        ]
                    ]
                ]
            ]
        ]
    ]
];

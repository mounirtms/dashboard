<?php
return array (
  'backend' => 
  array (
    'frontName' => 'sysadminy',
  ),
  'remote_storage' => 
  array (
    'driver' => 'file',
  ),
  'queue' => 
  array (
    'consumers_wait_for_messages' => 1,
  ),
  'crypt' => 
  array (
    'key' => '864d830f6b508b7ef6fedba974f707f0',
  ),
  'db' => 
  array (
    'table_prefix' => '',
    'connection' => 
    array (
      'default' => 
      array (
        'host' => '127.0.0.1:3307',
        'dbname' => 'technadminy7_dBT8x12y22',
        'username' => 'technadminy7_ntdbusr24',
        'password' => 'the-correct-password',
        'model' => 'mysql4',
        'engine' => 'innodb',
        'initStatements' => 'SET NAMES utf8;',
        'active' => '1',
        'driver_options' => 
        array (
          1014 => false,
        ),
      ),
    ),
  ),
  'resource' => 
  array (
    'default_setup' => 
    array (
      'connection' => 'default',
    ),
  ),
  'x-frame-options' => 'SAMEORIGIN',
  'MAGE_MODE' => 'production',
  'cache' => 
  array (
    'frontend' => 
    array (
      'default' => 
      array (
        'backend' => 'Cm_Cache_Backend_Redis',
        'backend_options' => 
        array (
          'server' => '127.0.0.1',
          'database' => '0',
          'port' => '6379',
          'password' => '',
          'compress_data' => '1',
          'compression_lib' => '',
        ),
      ),
      'page_cache' => 
      array (
        'backend' => 'Cm_Cache_Backend_Redis',
        'backend_options' => 
        array (
          'server' => '127.0.0.1',
          'database' => '1',
          'port' => '6379',
          'password' => '',
          'compress_data' => '0',
          'database_pattern' => '/^[0-9]+$/',
        ),
      ),
    ),
  ),
  'session' => 
  array (
    'save' => 'redis',
    'redis' => 
    array (
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
      'max_lifetime' => '2592000',
    ),
  ),
  'lock' => 
  array (
    'provider' => 'db',
  ),
  'directories' => 
  array (
    'document_root_is_pub' => true,
  ),
  'downloadable_domains' => 
  array (
    0 => 'technostationery.com',
  ),
  'install' => 
  array (
    'date' => 'Mon, 10 Nov 2025 09:05:27 +0000',
  ),
  'cors' => 
  array (
    'allowed_origins' => 
    array (
      0 => '*',
    ),
    'allowed_methods' => 
    array (
      0 => 'GET',
      1 => 'POST',
      2 => 'PUT',
      3 => 'DELETE',
      4 => 'OPTIONS',
    ),
    'allowed_headers' => 
    array (
      0 => '*',
    ),
    'exposed_headers' => 
    array (
      0 => '*',
    ),
    'max_age' => 3600,
    'supports_credentials' => true,
  ),
  'cache_types' => 
  array (
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
    'checkout' => 1,
  ),
  'system' => 
  array (
    'default' => 
    array (
      'admin' => 
      array (
        'url' => 
        array (
          'custom' => NULL,
          'custom_path' => NULL,
        ),
      ),
      'web' => 
      array (
        'unsecure' => 
        array (
          'base_url' => 'https://technostationery.com/',
          'base_link_url' => 'http://technostationery.com/',
          'base_static_url' => NULL,
          'base_media_url' => NULL,
        ),
        'secure' => 
        array (
          'base_url' => 'https://technostationery.com/',
          'base_link_url' => 'https://technostationery.com/',
          'base_static_url' => NULL,
          'base_media_url' => NULL,
        ),
        'default' => 
        array (
          'front' => 'cms',
        ),
        'cookie' => 
        array (
          'cookie_path' => '/',
          'cookie_domain' => 'technostationery.com',
        ),
      ),
      'catalog' => 
      array (
        'productalert_cron' => 
        array (
          'error_email' => NULL,
        ),
        'product_video' => 
        array (
          'youtube_api_key' => NULL,
        ),
        'search' => 
        array (
          'elasticsearch5_server_hostname' => 'localhost',
          'elasticsearch7_server_hostname' => '127.0.0.1',
          'opensearch_server_hostname' => 'localhost',
          'elasticsearch5_server_port' => '9200',
          'elasticsearch7_server_port' => '9200',
          'opensearch_server_port' => '9200',
          'elasticsearch5_index_prefix' => 'magento2',
          'elasticsearch7_index_prefix' => 'techno_stationery',
          'opensearch_index_prefix' => 'magento2',
          'elasticsearch5_enable_auth' => '0',
          'elasticsearch7_enable_auth' => '0',
          'opensearch_enable_auth' => '0',
          'elasticsearch5_username' => NULL,
          'elasticsearch7_username' => NULL,
          'opensearch_username' => NULL,
          'elasticsearch5_password' => NULL,
          'elasticsearch7_password' => NULL,
          'opensearch_password' => NULL,
          'elasticsearch5_server_timeout' => '15',
          'elasticsearch7_server_timeout' => '15',
          'opensearch_server_timeout' => '15',
        ),
      ),
      'cataloginventory' => 
      array (
        'source_selection_distance_based_google' => 
        array (
          'api_key' => NULL,
        ),
      ),
      'currency' => 
      array (
        'import' => 
        array (
          'error_email' => NULL,
        ),
      ),
      'sitemap' => 
      array (
        'generate' => 
        array (
          'error_email' => NULL,
        ),
      ),
      'trans_email' => 
      array (
        'ident_general' => 
        array (
          'name' => 'contact',
          'email' => 'contact@technostationery.com',
        ),
        'ident_sales' => 
        array (
          'name' => 'Sales',
          'email' => 'sales@technostationery.com',
        ),
        'ident_support' => 
        array (
          'name' => 'Customer Support',
          'email' => 'sales@technostationery.com',
        ),
        'ident_custom1' => 
        array (
          'name' => 'Custom 1',
          'email' => 'contact@technostationery.com',
        ),
        'ident_custom2' => 
        array (
          'name' => 'Custom 2',
          'email' => 'contact@technostationery.com',
        ),
      ),
      'contact' => 
      array (
        'email' => 
        array (
          'recipient_email' => 'contact@technostationery.com.dz',
        ),
      ),
      'sales_email' => 
      array (
        'order' => 
        array (
          'copy_to' => 'sales.ecommerce@techno-dz.com,socialbx@gmail.com,amine.tms2021@gmail.com,yacine.ho.tms@gmail.com',
        ),
        'order_comment' => 
        array (
          'copy_to' => NULL,
        ),
        'invoice' => 
        array (
          'copy_to' => NULL,
        ),
        'invoice_comment' => 
        array (
          'copy_to' => NULL,
        ),
        'shipment' => 
        array (
          'copy_to' => NULL,
        ),
        'shipment_comment' => 
        array (
          'copy_to' => NULL,
        ),
        'creditmemo' => 
        array (
          'copy_to' => NULL,
        ),
        'creditmemo_comment' => 
        array (
          'copy_to' => NULL,
        ),
      ),
      'checkout' => 
      array (
        'payment_failed' => 
        array (
          'copy_to' => 'webmaster@technostationery.com',
        ),
      ),
      'google' => 
      array (
        'analytics' => 
        array (
          'account' => 'UA-196342035-1',
        ),
        'gtag' => 
        array (
          'analytics4' => 
          array (
            'measurement_id' => 'G-72HCNG92F2',
          ),
          'adwords' => 
          array (
            'conversion_id' => 'G-72HCNG92F2',
          ),
        ),
      ),
      'payment' => 
      array (
        'checkmo' => 
        array (
          'mailing_address' => NULL,
        ),
      ),
      'recaptcha_backend' => 
      array (
        'type_recaptcha' => 
        array (
          'public_key' => NULL,
          'private_key' => NULL,
        ),
        'type_invisible' => 
        array (
          'public_key' => NULL,
          'private_key' => NULL,
        ),
        'type_recaptcha_v3' => 
        array (
          'public_key' => '0:3:AYAvuPo2BWc6oSJetk6+JarEwQgt9BefnqcsT+pqcY0cWrP2t5enOkH46ooeNbmxKzbYynDqOqulwDUo1rBHb05ArwY=',
          'private_key' => '0:3:EDEaqXsosmBzy0kezhxfA1mj6nSBr/XO3LGKA91ptlVncWgMEJAFq72OESQmfjW6ctoTXyGKGBF56h44i+qrNz7WqFs=',
        ),
      ),
      'recaptcha_frontend' => 
      array (
        'type_recaptcha' => 
        array (
          'public_key' => '0:3:aGQ1O73jIUssUSLCaUlbulGpYK2LX2G81+TLmT8O3A07tQi0pw3tpxZe9lpf5dyc8wrBrn0Smg48lmsXpD2piiGq5Es=',
          'private_key' => '0:3:Lw019BjY/8SWvIyp3u/nNAk/rtivfAAyFEum1rQZEOqNqYBR1KjcF/E5RJTdYREqtg/DQLpTazgiu8SER9yS9ZZtJxQ=',
        ),
        'type_invisible' => 
        array (
          'public_key' => NULL,
          'private_key' => NULL,
        ),
        'type_recaptcha_v3' => 
        array (
          'public_key' => '0:3:mV6Qd7BJUr2aRc687hCAYN69tSfkrVC1BWAMaepdU0x0HjJ3QVRs4BZEu+ReK1rOYB8WXi+vcryuRMnYeelAVrHF3nY=',
          'private_key' => '0:3:zsBxYfBQV3KeFYrdEM+h03mUkU2/x0mFEeJpnZo46zwiVef8+iEk2/bWOCAqDuXhII5hjKFltzcQt8n9qUIR34qr0+M=',
        ),
      ),
      'system' => 
      array (
        'smtp' => 
        array (
          'host' => 'localhost',
          'port' => '25',
        ),
        'full_page_cache' => 
        array (
          'varnish' => 
          array (
            'access_list' => 'localhost,127.0.0.1',
            'backend_host' => '127.0.0.1',
            'backend_port' => '8080',
          ),
        ),
        'release_notification' => 
        array (
          'content_url' => 'magento.com/release_notifications',
          'use_https' => '1',
        ),
      ),
      'dev' => 
      array (
        'restrict' => 
        array (
          'allow_ips' => NULL,
        ),
        'js' => 
        array (
          'session_storage_key' => 'collected_errors',
          'merge_files' => '1',
          'minify_files' => '1',
          'enable_js_bundling' => '0',
          'move_script_to_bottom' => '1',
        ),
        'css' => 
        array (
          'merge_css_files' => '1',
          'minify_files' => '1',
        ),
        'template' => 
        array (
          'minify_html' => '1',
        ),
      ),
      'analytics' => 
      array (
        'general' => 
        array (
          'token' => NULL,
        ),
        'url' => 
        array (
          'signup' => 'https://advancedreporting.rjmetrics.com/signup',
          'update' => 'https://advancedreporting.rjmetrics.com/update',
          'bi_essentials' => 'https://dashboard.rjmetrics.com/v2/magento/signup',
          'otp' => 'https://advancedreporting.rjmetrics.com/otp',
          'report' => 'https://advancedreporting.rjmetrics.com/report',
          'notify_data_changed' => 'https://advancedreporting.rjmetrics.com/report',
        ),
      ),
      'crontab' => 
      array (
        'default' => 
        array (
          'jobs' => 
          array (
            'analytics_collect_data' => 
            array (
              'schedule' => 
              array (
                'cron_expr' => '00 02 * * *',
              ),
            ),
          ),
        ),
      ),
    ),
  ),
);

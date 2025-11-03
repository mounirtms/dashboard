# Magento Multi-Store Elasticsearch Optimization Report

## System Overview

Based on the analysis of your Magento installation, here is the current setup:

### Websites and Stores Configuration
1. **Main Techno B2C** (code: base) - Website ID: 1
   - Store: Techno Stationery (code: default) - Store ID: 1
2. **Techno B2B** (code: TechnoB2B) - Website ID: 3
3. **SILA 2025** (code: Sila) - Website ID: 4
   - Store: SILA 2025 (code: sila) - Store ID: 6

### Current Elasticsearch Setup
- **Engine**: Elasticsearch 7
- **Server**: 127.0.0.1:9200
- **Index Prefix**: techno_stationery
- **Memory Allocation**: 4GB (as requested)
- **Current Indices**:
  - techno_stationery_product_1_v15 (Techno Stationery store)
  - techno_stationery_product_6_v13 (SILA store)
  - Multiple Amasty popup data indices

## Resource Usage Analysis

### Top Resource Consumers
1. **MariaDB** - Using ~7.2GB RAM (23% of total system memory)
2. **Elasticsearch** - Using ~4.8GB RAM (as required for multi-website usage)
3. **PHP-FPM processes** - Multiple processes consuming 150-200MB each
4. **VS Code Server** - Using ~1.4GB RAM (development tool)

### System Status
- **Load Average**: 2.95, 7.66, 10.77 (high, indicating system overload)
- **Memory**: 18.3GB used out of 31.8GB total
- **Swap**: Only 121MB used, which is good

## Optimization Recommendations

### 1. Elasticsearch Multi-Store Configuration

Since you have multiple websites (Techno and Sila) and want to maintain at least 4GB for Elasticsearch:

1. **Optimize Index Settings**:
   ```bash
   # Update index settings to improve performance
   curl -X PUT "127.0.0.1:9200/techno_stationery*/_settings" -H 'Content-Type: application/json' -d'
   {
     "index": {
       "refresh_interval": "30s",
       "number_of_replicas": 0
     }
   }'
   ```

2. **Configure Cluster Settings**:
   ```bash
   # Set cluster-level settings for better performance
   curl -X PUT "127.0.0.1:9200/_cluster/settings" -H 'Content-Type: application/json' -d'
   {
     "persistent": {
       "indices.breaker.fielddata.limit": "40%",
       "indices.breaker.request.limit": "20%",
       "network.tcp.no_delay": true,
       "thread_pool.search.queue_size": 1000
     }
   }'
   ```

### 2. Magento Configuration for Multi-Store

1. **Configure separate Elasticsearch indices for each store**:
   In Magento Admin, go to:
   - Stores > Configuration > Catalog > Catalog Search
   - Set different index prefixes for each store if needed

2. **Optimize batch sizes for indexing**:
   ```bash
   # Add to app/etc/config.php or env.php
   'system' => [
       'default' => [
           'catalog' => [
               'search' => [
                   'elasticsearch7' => [
                       'batch_size' => 100
                   ]
               ]
           ]
       ]
   ]
   ```

### 3. Database Optimization

1. **Optimize MariaDB configuration**:
   Edit `/opt/mariadb10.6/my.cnf` and add/modify:
   ```
   [mysqld]
   innodb_buffer_pool_size = 2G
   max_connections = 100
   query_cache_size = 64M
   tmp_table_size = 64M
   max_heap_table_size = 64M
   innodb_log_file_size = 512M
   innodb_flush_log_at_trx_commit = 2
   ```

2. **Restart MariaDB to apply changes**:
   ```bash
   sudo systemctl restart mariadb
   ```

### 4. PHP-FPM Optimization

1. **Optimize PHP-FPM configuration**:
   Edit `/opt/cpanel/ea-php82/root/etc/php-fpm.conf`:
   ```
   pm = dynamic
   pm.max_children = 20
   pm.start_servers = 5
   pm.min_spare_servers = 2
   pm.max_spare_servers = 10
   pm.max_requests = 500
   request_terminate_timeout = 300
   ```

### 5. Static Content and SILA Store Issues

1. **Fix static content deployment for SILA store**:
   ```bash
   php bin/magento setup:static-content:deploy fr_FR -f --area frontend --theme Sm/market
   ```

2. **Check store-specific configurations**:
   - Ensure the SILA store has the correct theme assigned
   - Verify base URLs in Stores > Configuration > General > Web for SILA store view

### 6. Module Optimization

1. **Disable resource-intensive modules not needed for all stores**:
   ```bash
   # Disable non-essential modules for better performance
   php bin/magento module:disable Amasty_Blog
   php bin/magento module:disable Amasty_Storelocator
   php bin/magento module:disable Sm_MegaMenu
   ```

2. **Keep essential modules for search functionality**:
   - Magento_Elasticsearch
   - Magento_Elasticsearch7
   - Amasty_ElasticSearch (if in use)

### 7. Caching Strategy

1. **Enable and configure full page cache**:
   ```bash
   php bin/magento config:set system/full_page_cache/caching_application 1
   ```

2. **Configure Redis for caching**:
   Add to `app/etc/env.php`:
   ```php
   'cache' => [
       'frontend' => [
           'default' => [
               'backend' => 'Cm_Cache_Backend_Redis',
               'backend_options' => [
                   'server' => '127.0.0.1',
                   'port' => '6379',
                   'database' => '0',
                   'compress_data' => '1'
               ]
           ],
           'page_cache' => [
               'backend' => 'Cm_Cache_Backend_Redis',
               'backend_options' => [
                   'server' => '127.0.0.1',
                   'port' => '6379',
                   'database' => '1',
                   'compress_data' => '0'
               ]
           ]
       ]
   ]
   ```

## Implementation Steps

1. **Apply Elasticsearch optimizations**:
   - Update index settings for better performance
   - Configure cluster settings for multi-store environment

2. **Optimize database configuration**:
   - Adjust MariaDB settings to reduce memory usage
   - Restart database service

3. **Fix static content deployment**:
   - Deploy static content for SILA store specifically
   - Check theme assignments and base URLs

4. **Optimize PHP processes**:
   - Adjust PHP-FPM settings to reduce number of processes
   - Set request timeout limits

5. **Configure caching**:
   - Enable full page cache
   - Configure Redis for better caching performance

## Monitoring

After implementing these optimizations, monitor:

1. **System load average** should decrease from current levels
2. **Memory usage** should be more balanced across services
3. **Elasticsearch performance** should improve with proper indexing
4. **SILA store** should load static assets correctly

## Expected Results

1. **Reduced system load** by optimizing database and PHP processes
2. **Improved Elasticsearch performance** with proper multi-store configuration
3. **Fixed SILA store static content issues**
4. **Better resource distribution** across all services while maintaining 4GB for Elasticsearch

## Maintenance

1. **Regular index optimization**:
   ```bash
   php bin/magento indexer:reindex catalogsearch_fulltext
   ```

2. **Periodic cache cleaning**:
   ```bash
   php bin/magento cache:clean
   ```

3. **Monitor logs for errors**:
   - `var/log/system.log`
   - `var/log/exception.log`
   - Elasticsearch logs in `/var/log/elasticsearch/`
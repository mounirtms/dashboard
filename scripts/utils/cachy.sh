#!/bin/bash
rm -rf var/cache/*
rm -rf var/page_cache/*
rm -rf generated/*
php -d memory_limit=-1 bin/magento cache:flush
php -d memory_limit=-1 bin/magento cache:clean
php -d memory_limit=-1 bin/magento index:reindex

<?php
/**
 * Mab ElasticsearchFix Module Registration
 * 
 * This module provides a fixed XSD schema for Elasticsearch configuration
 * to resolve the non-deterministic content model error.
 */
\Magento\Framework\Component\ComponentRegistrar::register(
    \Magento\Framework\Component\ComponentRegistrar::MODULE,
    'Mab_ElasticsearchFix',
    __DIR__
);

<?php
namespace Mab\Core\Api;

interface CacheableInterface
{
    /**
     * Get cache tags
     *
     * @return array
     */
    public function getCacheTags();

    /**
     * Get cache key info
     *
     * @return array
     */
    public function getCacheKeyInfo();

    /**
     * Get cache lifetime
     *
     * @return int|bool
     */
    public function getCacheLifetime();
}

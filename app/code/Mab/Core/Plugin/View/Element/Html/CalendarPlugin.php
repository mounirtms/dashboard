<?php
/**
 * Copyright © MAB. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Mab\Core\Plugin\View\Element\Html;

use Magento\Framework\View\Element\Html\Calendar;
use Magento\Framework\Locale\Bundle\DataBundle;

/**
 * Plugin to fix null locale data issue in Calendar block
 * Optimized with caching to prevent repeated locale data fetching
 */
class CalendarPlugin
{
    /**
     * @var \Magento\Framework\Locale\ResolverInterface
     */
    private $localeResolver;

    /**
     * Cache for locale data to prevent repeated instantiation
     * 
     * @var array
     */
    private $localeDataCache = [];

    /**
     * @param \Magento\Framework\Locale\ResolverInterface $localeResolver
     */
    public function __construct(
        \Magento\Framework\Locale\ResolverInterface $localeResolver
    ) {
        $this->localeResolver = $localeResolver;
    }

    /**
     * Fix null locale data issue with caching optimization
     *
     * @param Calendar $subject
     * @param callable $proceed
     * @return string
     */
    public function aroundToHtml(Calendar $subject, callable $proceed)
    {
        try {
            return $proceed();
        } catch (\Exception $e) {
            if (strpos($e->getMessage(), 'Trying to access array offset on value of type null') !== false) {
                // Fix the locale data issue
                $locale = $this->localeResolver->getLocale();
                
                // Check cache first
                if (!isset($this->localeDataCache[$locale])) {
                    $localeData = (new DataBundle())->get($locale);
                    
                    if ($localeData === null) {
                        // Fallback to en_US locale data
                        $localeData = (new DataBundle())->get('en_US');
                    }
                    
                    // Cache the locale data
                    $this->localeDataCache[$locale] = $localeData;
                }
                
                // Set the locale data to avoid null access
                $subject->setData('locale_data', $this->localeDataCache[$locale]);
                
                // Retry the original method
                return $proceed();
            }
            throw $e;
        }
    }
}
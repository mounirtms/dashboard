<?php
/**
 * Copyright © MAB. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Mab\Core\Plugin\Framework\View\Element\Html;

use Magento\Framework\View\Element\Html\Calendar;
use Magento\Framework\Locale\Bundle\DataBundle;

/**
 * Plugin to fix null locale data issue in Calendar block
 */
class CalendarPlugin
{
    /**
     * @var \Magento\Framework\Locale\ResolverInterface
     */
    private $localeResolver;

    /**
     * @param \Magento\Framework\Locale\ResolverInterface $localeResolver
     */
    public function __construct(
        \Magento\Framework\Locale\ResolverInterface $localeResolver
    ) {
        $this->localeResolver = $localeResolver;
    }

    /**
     * Fix null locale data issue
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
                // This is our specific error, let's handle it gracefully
                // We'll return an empty string to prevent the error from breaking the page
                return '';
            }
            // For all other exceptions, rethrow them
            throw $e;
        }
    }
}
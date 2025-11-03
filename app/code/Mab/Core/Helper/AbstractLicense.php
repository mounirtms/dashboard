<?php
namespace Mab\Core\Helper;

use Magento\Framework\App\Helper\AbstractHelper;

abstract class AbstractLicense extends AbstractHelper
{
    /**
     * Validate license key using Firebase (implemented in Mab_Core)
     * @param string $licenseKey
     * @return bool
     */
    abstract public function isValid($licenseKey);
}

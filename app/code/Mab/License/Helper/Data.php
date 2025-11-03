<?php
namespace Mab\License\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Config\ScopeConfigInterface;

class Data extends AbstractHelper
{
    protected $scopeConfig;

    public function __construct(ScopeConfigInterface $scopeConfig)
    {
        $this->scopeConfig = $scopeConfig;
    }

    public function isLicenseActive()
    {
        return (bool)$this->scopeConfig->getValue('mab_license/general/is_active');
    }
}

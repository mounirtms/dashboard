<?php
namespace Mab\AdminLocale\Plugin;

use Magento\Framework\App\State;
use Magento\Framework\Locale\Resolver;
use Mab\License\Helper\Data as LicenseHelper;

class LocaleResolver
{
    /**
     * @var State
     */
    private $appState;

    /**
     * @var LicenseHelper
     */
    private $licenseHelper;

    /**
     * @param State $appState
     * @param LicenseHelper $licenseHelper
     */
    public function __construct(
        State $appState,
        LicenseHelper $licenseHelper
    ) {
        $this->appState = $appState;
        $this->licenseHelper = $licenseHelper;
    }

    /**
     * Force English locale for admin area
     *
     * @param Resolver $subject
     * @param string $result
     * @return string
     */
    public function afterGetLocale(Resolver $subject, $result)
    {
        if (!$this->licenseHelper->isLicenseActive()) {
            return $result;
        }

        try {
            $areaCode = $this->appState->getAreaCode();
            if ($areaCode === 'adminhtml') {
                return 'en_US';
            }
        } catch (\Exception $e) {
            // If area code is not set, check if we're in admin context
            if (php_sapi_name() !== 'cli' && strpos($_SERVER['REQUEST_URI'] ?? '', '/admin') !== false) {
                return 'en_US';
            }
        }
        
        return $result;
    }

    /**
     * Force English locale for admin area
     *
     * @param Resolver $subject
     * @param string $result
     * @return string
     */
    public function afterGetDefaultLocale(Resolver $subject, $result)
    {
        if (!$this->licenseHelper->isLicenseActive()) {
            return $result;
        }

        try {
            $areaCode = $this->appState->getAreaCode();
            if ($areaCode === 'adminhtml') {
                return 'en_US';
            }
        } catch (\Exception $e) {
            // If area code is not set, check if we're in admin context
            if (php_sapi_name() !== 'cli' && strpos($_SERVER['REQUEST_URI'] ?? '', '/admin') !== false) {
                return 'en_US';
            }
        }
        
        return $result;
    }
}

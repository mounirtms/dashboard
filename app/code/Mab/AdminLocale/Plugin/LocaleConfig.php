<?php
namespace Mab\AdminLocale\Plugin;

use Magento\Framework\App\State;
use Magento\Framework\Locale\Config;
use Magento\Framework\App\Request\Http;
use Mab\License\Helper\Data as LicenseHelper;

class LocaleConfig
{
    /**
     * @var State
     */
    private $appState;

    /**
     * @var Http
     */
    private $request;

    /**
     * @var LicenseHelper
     */
    private $licenseHelper;

    /**
     * @param State $appState
     * @param Http $request
     * @param LicenseHelper $licenseHelper
     */
    public function __construct(
        State $appState,
        Http $request,
        LicenseHelper $licenseHelper
    ) {
        $this->appState = $appState;
        $this->request = $request;
        $this->licenseHelper = $licenseHelper;
    }

    /**
     * Check if we're in admin context
     *
     * @return bool
     */
    private function isAdminArea()
    {
        try {
            $areaCode = $this->appState->getAreaCode();
            if ($areaCode === 'adminhtml') {
                return true;
            }
        } catch (\Exception $e) {
            // Fallback checks
            if (php_sapi_name() !== 'cli') {
                $requestUri = $this->request->getRequestUri() ?? $_SERVER['REQUEST_URI'] ?? '';
                if (strpos($requestUri, '/admin') !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Restrict available locales to English only in admin area
     *
     * @param Config $subject
     * @param array $result
     * @return array
     */
    public function afterGetAllowedLocales(Config $subject, $result)
    {
        if ($this->licenseHelper->isLicenseActive() && $this->isAdminArea()) {
            return ['en_US'];
        }

        return $result;
    }

    /**
     * Return only English options for admin area
     *
     * @param Config $subject
     * @param array $result
     * @return array
     */
    public function afterGetTranslatedOptionLocales(Config $subject, $result)
    {
        if ($this->licenseHelper->isLicenseActive() && $this->isAdminArea()) {
            return [
                [
                    'value' => 'en_US',
                    'label' => 'English (United States)'
                ]
            ];
        }

        return $result;
    }

    /**
     * Return only English options for admin area
     *
     * @param Config $subject
     * @param array $result
     * @return array
     */
    public function afterGetOptionLocales(Config $subject, $result)
    {
        if ($this->licenseHelper->isLicenseActive() && $this->isAdminArea()) {
            return [
                'en_US' => 'English (United States)'
            ];
        }

        return $result;
    }
}

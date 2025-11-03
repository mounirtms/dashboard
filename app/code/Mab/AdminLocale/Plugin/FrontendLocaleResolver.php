<?php
namespace Mab\AdminLocale\Plugin;

use Magento\Framework\Locale\Resolver;
use Mab\AdminLocale\Helper\FrontendLocale;
use Mab\License\Helper\Data as LicenseHelper;

class FrontendLocaleResolver
{
    /**
     * @var FrontendLocale
     */
    private $frontendLocaleHelper;

    /**
     * @var LicenseHelper
     */
    private $licenseHelper;

    /**
     * @param FrontendLocale $frontendLocaleHelper
     * @param LicenseHelper $licenseHelper
     */
    public function __construct(
        FrontendLocale $frontendLocaleHelper,
        LicenseHelper $licenseHelper
    ) {
        $this->frontendLocaleHelper = $frontendLocaleHelper;
        $this->licenseHelper = $licenseHelper;
    }

    /**
     * Enforce French locale for frontend
     *
     * @param Resolver $subject
     * @param string $result
     * @return string
     */
    public function afterGetLocale(Resolver $subject, $result)
    {
        if (!$this->licenseHelper->isLicenseActive() || !$this->frontendLocaleHelper->isLocaleEnforcementEnabled()) {
            return $result;
        }

        // Force French for frontend
        if ($this->frontendLocaleHelper->isFrontendArea()) {
            return $this->frontendLocaleHelper->getRequiredFrontendLocale();
        }

        return $result;
    }

    /**
     * Enforce locale setting
     *
     * @param Resolver $subject
     * @param string $locale
     * @return array
     */
    public function beforeSetLocale(Resolver $subject, $locale)
    {
        if (!$this->licenseHelper->isLicenseActive() || !$this->frontendLocaleHelper->isLocaleEnforcementEnabled()) {
            return [$locale];
        }

        // Force French for frontend
        if ($this->frontendLocaleHelper->isFrontendArea()) {
            return [$this->frontendLocaleHelper->getRequiredFrontendLocale()];
        }

        return [$locale];
    }
}

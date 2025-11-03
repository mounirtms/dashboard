<?php
namespace Mab\Core\Plugin\Framework\Module;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Module\ModuleListInterface;
use Mab\Core\Model\License\Validator;

class ModuleOutputEnablement
{
    /**
     * @var Validator
     */
    private $licenseValidator;

    /**
     * @var ModuleListInterface
     */
    private $moduleList;

    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;

    /**
     * Modules that require license validation
     *
     * @var array
     */
    private $modulesToValidate = [
        'Mab_VisualEffects' => 'visual_effect',
        // Add other modules here as needed
    ];

    /**
     * @param Validator $licenseValidator
     * @param ModuleListInterface $moduleList
     * @param ScopeConfigInterface $scopeConfig
     */
    public function __construct(
        Validator $licenseValidator,
        ModuleListInterface $moduleList,
        ScopeConfigInterface $scopeConfig
    ) {
        $this->licenseValidator = $licenseValidator;
        $this->moduleList = $moduleList;
        $this->scopeConfig = $scopeConfig;
    }

    /**
     * Check if module output is enabled and has valid license
     *
     * @param \Magento\Framework\Module\Manager $subject
     * @param \Closure $proceed
     * @param string $moduleName
     * @return bool
     */
    public function aroundIsEnabled(
        \Magento\Framework\Module\Manager $subject,
        \Closure $proceed,
        $moduleName
    ) {
        $result = $proceed($moduleName);

        // Only validate enabled modules that require license validation
        if ($result && isset($this->modulesToValidate[$moduleName])) {
            // Get module license information
            $licenseFeature = $this->modulesToValidate[$moduleName];
            
            // Validate license
            return $this->licenseValidator->validateLicense($licenseFeature);
        }

        return $result;
    }
}

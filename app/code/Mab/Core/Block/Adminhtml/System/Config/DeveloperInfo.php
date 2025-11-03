<?php
/**
 * MAB Core - Developer Info Block
 * 
 * @category    Mab
 * @package     Mab_Core
 * @author      Mounir Abderrahmani <mounir.webdev@gmail.com>
 * @copyright   Copyright (c) 2025 MAB Extensions
 * @license     https://opensource.org/licenses/MIT MIT License
 */

namespace Mab\Core\Block\Adminhtml\System\Config;

use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use Magento\Framework\Module\ModuleListInterface;

/**
 * Class DeveloperInfo
 * 
 * Displays developer information and MAB extensions overview
 */
class DeveloperInfo extends Template
{
    /**
     * @var ModuleListInterface
     */
    private $moduleList;

    /**
     * @param Context $context
     * @param ModuleListInterface $moduleList
     * @param array $data
     */
    public function __construct(
        Context $context,
        ModuleListInterface $moduleList,
        array $data = []
    ) {
        $this->moduleList = $moduleList;
        parent::__construct($context, $data);
    }

    /**
     * Get MAB modules
     *
     * @return array
     */
    public function getMabModules()
    {
        $modules = $this->moduleList->getAll();
        $mabModules = [];
        
        foreach ($modules as $moduleName => $moduleData) {
            if (strpos($moduleName, 'Mab_') === 0) {
                $mabModules[$moduleName] = $moduleData;
            }
        }
        
        return $mabModules;
    }

    /**
     * Get developer portfolio URL
     *
     * @return string
     */
    public function getPortfolioUrl()
    {
        return 'https://mounir1.github.io';
    }

    /**
     * Get MAB icon URL
     *
     * @return string
     */
    public function getMabIconUrl()
    {
        return $this->getViewFileUrl('Mab_Core::images/mab-icon.svg');
    }

    /**
     * Get MAB signature URL
     *
     * @return string
     */
    public function getMabSignatureUrl()
    {
        return $this->getViewFileUrl('Mab_Core::images/mab-signature.svg');
    }

    /**
     * Check if current page is MAB configuration
     *
     * @return bool
     */
    public function isMabConfigPage()
    {
        $section = $this->getRequest()->getParam('section');
        return $section && (strpos($section, 'mab_') === 0 || $section === 'carriers');
    }

    /**
     * Get module description
     *
     * @param string $moduleName
     * @return string
     */
    public function getModuleDescription($moduleName)
    {
        $descriptions = [
            'Mab_Core' => 'Foundation module providing shared functionality and configuration management',
            'Mab_DeliveryOptions' => 'Advanced shipping and delivery management with Yalidine integration',
            'Mab_CheckoutCustomization' => 'Enhanced checkout experience with custom fields and validation',
            'Mab_VisualEffects' => 'Interactive user experience with animations and visual feedback',
            'Mab_SocialLogin' => 'Social media authentication for faster customer registration',
            'Mab_GuestCheckout' => 'Streamlined guest purchasing experience',
            'Mab_AdminLocale' => 'Multi-language admin interface support',
            'Mab_Theme' => 'Frontend customization and responsive design components',
            'Mab_SourceSelector' => 'Multi-source inventory management',
            'Mab_License' => 'License validation and compliance monitoring'
        ];

        return $descriptions[$moduleName] ?? 'Professional Magento 2 extension';
    }
}
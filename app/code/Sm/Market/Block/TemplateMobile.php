<?php

namespace Sm\Market\Block;

class TemplateMobile extends \Magento\Framework\View\Element\Template
{
    /**
     * @var \Magento\Framework\Registry
     */
    public $_coreRegistry;

    /**
     * @var \Magento\Theme\Block\Html\Header\Logo
     */
    protected $_logo;

    /**
     * TemplateMobile constructor.
     * @param \Magento\Backend\Block\Template\Context $context
     * @param \Magento\Theme\Block\Html\Header\Logo $logo
     * @param \Magento\Framework\Registry $coreRegistry
     * @param array $data
     */
    public function __construct(
        \Magento\Backend\Block\Template\Context $context,
        \Magento\Theme\Block\Html\Header\Logo $logo,
        \Magento\Framework\Registry $coreRegistry,
        array $data = []
    )
    {
        $this->_coreRegistry = $coreRegistry;
        $this->_logo         = $logo;
        parent::__construct($context, $data);
    }

    /**
     * @return \Magento\Framework\View\Element\Template
     */

    public function _prepareLayout()
    {
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $page          = $objectManager->get('Magento\Framework\View\Page\Config');
        
        // Get theme configuration from scope config instead of non-existent helper method
        $productStyle  = $this->_scopeConfig->getValue('layout_product/product_style', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);
        $rtlLayout     = $this->_scopeConfig->getValue('direction_rtl/enabled', \Magento\Store\Model\ScopeInterface::SCOPE_STORE);

        $this->pageConfig->addBodyClass($productStyle . '-style');

        if ($rtlLayout) {
            $extRtl = "_rtl";
            $page->addPageAsset('css/bootstrap_rtl.css');
            $this->pageConfig->addBodyClass('rtl-layout');
        } else {
            $extRtl = "";
            $page->addPageAsset('css/bootstrap.css');
        }

        $headerCss    = 'css/header-mobile' . $extRtl . '.css';
        $productCss   = 'css/' . $productStyle . $extRtl . '.css';
        $homeCss      = 'css/home-mobile' . $extRtl . '.css';
        $footerCss    = 'css/footer-mobile' . $extRtl . '.css';
        $pageThemeCss = 'css/pages-theme' . $extRtl . '.css';

        $page->addPageAsset($headerCss);
        $page->addPageAsset($productCss);
        $page->addPageAsset($homeCss);
        $page->addPageAsset($footerCss);
        $page->addPageAsset($pageThemeCss);

        return parent::_prepareLayout();
    }

    /**
     * @return string
     */
    public function getLogoAlt()
    {
        return $this->_logo->getLogoAlt();
    }
}
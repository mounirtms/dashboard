<?php
/**
 * MAB Core - Documentation Controller
 * 
 * @category    Mab
 * @package     Mab_Core
 * @author      Mounir Abderrahmani <mounir.webdev@gmail.com>
 * @copyright   Copyright (c) 2025 MAB Extensions
 * @license     https://opensource.org/licenses/MIT MIT License
 */

namespace Mab\Core\Controller\Adminhtml\Documentation;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\View\Result\PageFactory;

class Readme extends Action
{
    /**
     * @var PageFactory
     */
    protected $resultPageFactory;

    /**
     * @param Context $context
     * @param PageFactory $resultPageFactory
     */
    public function __construct(
        Context $context,
        PageFactory $resultPageFactory
    ) {
        parent::__construct($context);
        $this->resultPageFactory = $resultPageFactory;
    }

    /**
     * Execute action
     *
     * @return \Magento\Framework\View\Result\Page
     */
    public function execute()
    {
        $module = $this->getRequest()->getParam('module', 'core');
        
        // Redirect to GitHub documentation for now
        $documentationUrls = [
            'core' => 'https://github.com/mounir1/mab-extensions/blob/main/Core/README.md',
            'deliveryoptions' => 'https://github.com/mounir1/mab-extensions/blob/main/DeliveryOptions/README.md',
            'checkoutcustomization' => 'https://github.com/mounir1/mab-extensions/blob/main/CheckoutCustomization/README.md',
            'guestcheckout' => 'https://github.com/mounir1/mab-extensions/blob/main/GuestCheckout/README.md',
            'adminlocale' => 'https://github.com/mounir1/mab-extensions/blob/main/AdminLocale/README.md',
            'sourceselector' => 'https://github.com/mounir1/mab-extensions/blob/main/SourceSelector/README.md'
        ];
        
        $url = $documentationUrls[$module] ?? 'https://mounir1.github.io';
        
        return $this->resultRedirectFactory->create()->setUrl($url);
    }

    /**
     * Check admin permissions
     *
     * @return bool
     */
    protected function _isAllowed()
    {
        return $this->_authorization->isAllowed('Mab_Core::mab_menu');
    }
}
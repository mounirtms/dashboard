<?php
/**
 * MAB Core - Documentation Overview Controller
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

class Overview extends Action
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
        // Redirect to main MAB Extensions documentation
        $url = 'https://github.com/mounir1/mab-extensions';
        
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
<?php
/**
 * Custom store switcher block that works with websites
 */
namespace Sm\Market\Block\Html;

class StoreSwitcher extends \Magento\Framework\View\Element\Template
{
    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    protected $_storeManager;

    /**
     * @var \Magento\Store\Model\ResourceModel\Website\CollectionFactory
     */
    protected $_websiteCollectionFactory;

    /**
     * @param \Magento\Framework\View\Element\Template\Context $context
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     * @param \Magento\Store\Model\ResourceModel\Website\CollectionFactory $websiteCollectionFactory
     * @param array $data
     */
    public function __construct(
        \Magento\Framework\View\Element\Template\Context $context,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Store\Model\ResourceModel\Website\CollectionFactory $websiteCollectionFactory,
        array $data = []
    ) {
        $this->_storeManager = $storeManager;
        $this->_websiteCollectionFactory = $websiteCollectionFactory;
        parent::__construct($context, $data);
    }

    /**
     * Prepare block data before rendering
     *
     * @return void
     */
    protected function _prepareLayout()
    {
        // Get websites excluding admin
        $collection = $this->_websiteCollectionFactory->create();
        $collection->addFieldToFilter('website_id', ['gt' => 0]);
        
        $websites = [];
        foreach ($collection as $website) {
            $websites[] = $website;
        }
        
        $this->setData('websites', $websites);
        $this->setData('current_website_id', $this->_storeManager->getWebsite()->getId());
        
        parent::_prepareLayout();
    }
}
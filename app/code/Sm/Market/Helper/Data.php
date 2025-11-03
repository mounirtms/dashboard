<?php
/**
 * Custom helper for website data
 */
namespace Sm\Market\Helper;

class Data extends \Magento\Framework\App\Helper\AbstractHelper
{
    /**
     * @var \Magento\Store\Model\ResourceModel\Website\CollectionFactory
     */
    protected $_websiteCollectionFactory;

    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    protected $_storeManager;

    /**
     * @param \Magento\Framework\App\Helper\Context $context
     * @param \Magento\Store\Model\ResourceModel\Website\CollectionFactory $websiteCollectionFactory
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     */
    public function __construct(
        \Magento\Framework\App\Helper\Context $context,
        \Magento\Store\Model\ResourceModel\Website\CollectionFactory $websiteCollectionFactory,
        \Magento\Store\Model\StoreManagerInterface $storeManager
    ) {
        $this->_websiteCollectionFactory = $websiteCollectionFactory;
        $this->_storeManager = $storeManager;
        parent::__construct($context);
    }

    /**
     * Get websites excluding admin
     *
     * @return array
     */
    public function getWebsites()
    {
        $collection = $this->_websiteCollectionFactory->create();
        $collection->addFieldToFilter('website_id', ['gt' => 0]);
        
        $websites = [];
        foreach ($collection as $website) {
            $websites[] = $website;
        }
        
        return $websites;
    }

    /**
     * Get current website ID
     *
     * @return int
     */
    public function getCurrentWebsiteId()
    {
        return $this->_storeManager->getWebsite()->getId();
    }
}
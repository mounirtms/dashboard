<?php
namespace Mab\Core\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Store\Model\ScopeInterface;

class Firebase extends AbstractHelper
{
    const XML_PATH_FIREBASE_ENABLED = 'mab_core/firebase/enabled';
    const XML_PATH_FIREBASE_API_KEY = 'mab_core/firebase/api_key';
    const XML_PATH_FIREBASE_PROJECT_ID = 'mab_core/firebase/project_id';

    /**
     * @var Data
     */
    protected $coreHelper;

    /**
     * @param Context $context
     * @param Data $coreHelper
     */
    public function __construct(
        Context $context,
        Data $coreHelper
    ) {
        parent::__construct($context);
        $this->coreHelper = $coreHelper;
    }

    /**
     * Check if Firebase integration is enabled
     *
     * @param int|null $storeId
     * @return bool
     */
    public function isEnabled($storeId = null)
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_FIREBASE_ENABLED,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * Get Firebase API key
     *
     * @param int|null $storeId
     * @return string
     */
    public function getApiKey($storeId = null)
    {
        return (string)$this->scopeConfig->getValue(
            self::XML_PATH_FIREBASE_API_KEY,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * Get Firebase Project ID
     *
     * @param int|null $storeId
     * @return string
     */
    public function getProjectId($storeId = null)
    {
        return (string)$this->scopeConfig->getValue(
            self::XML_PATH_FIREBASE_PROJECT_ID,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    /**
     * Initialize Firebase configuration
     *
     * @param int|null $storeId
     * @return array
     */
    public function getFirebaseConfig($storeId = null)
    {
        if (!$this->isEnabled($storeId)) {
            return [];
        }

        return [
            'apiKey' => $this->getApiKey($storeId),
            'projectId' => $this->getProjectId($storeId),
            'authDomain' => $this->getProjectId($storeId) . '.firebaseapp.com',
            'databaseURL' => 'https://' . $this->getProjectId($storeId) . '.firebaseio.com',
            'storageBucket' => $this->getProjectId($storeId) . '.appspot.com'
        ];
    }
}

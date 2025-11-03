<?php
namespace Mab\SocialLogin\Controller\Account;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Customer\Model\CustomerFactory;
use Magento\Customer\Model\Session;
use Magento\Store\Model\StoreManagerInterface;
use Mab\SocialLogin\Helper\Data as HelperData;

class Login extends Action
{
    protected $resultJsonFactory;
    protected $customerFactory;
    protected $session;
    protected $storeManager;
    protected $helperData;

    public function __construct(
        Context $context,
        JsonFactory $resultJsonFactory,
        CustomerFactory $customerFactory,
        Session $session,
        StoreManagerInterface $storeManager,
        HelperData $helperData
    ) {
        parent::__construct($context);
        $this->resultJsonFactory = $resultJsonFactory;
        $this->customerFactory = $customerFactory;
        $this->session = $session;
        $this->storeManager = $storeManager;
        $this->helperData = $helperData;
    }

    public function execute()
    {
        $result = $this->resultJsonFactory->create();
        $firebaseUser = $this->getRequest()->getParam('firebaseUser');

        if (!$firebaseUser || !$this->helperData->isEnabled()) {
            return $result->setData(['success' => false, 'message' => __('Invalid request.')]);
        }

        try {
            $firebaseUser = json_decode($firebaseUser, true);
            $email = $firebaseUser['email'];
            $websiteId = $this->storeManager->getWebsite()->getId();

            $customer = $this->customerFactory->create()->setWebsiteId($websiteId)->loadByEmail($email);

            if ($customer->getId()) {
                // Customer exists, so we log them in
                $this->session->setCustomerAsLoggedIn($customer);
            } else {
                // Customer does not exist, so we create a new account
                $customer = $this->customerFactory->create();
                $customer->setWebsiteId($websiteId);
                $customer->setEmail($email);
                $customer->setFirstname($firebaseUser['displayName']);
                $customer->setLastname(' '); // Firebase doesn't provide a last name
                $customer->setPassword($this->generatePassword());
                $customer->save();

                $this->session->setCustomerAsLoggedIn($customer);
            }

            return $result->setData(['success' => true]);
        } catch (\Exception $e) {
            return $result->setData(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    private function generatePassword($length = 12)
    {
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $password = '';
        for ($i = 0; $i < $length; $i++) {
            $password .= $chars[rand(0, strlen($chars) - 1)];
        }
        return $password;
    }
}

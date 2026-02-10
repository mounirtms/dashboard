<?php
/**
 * Plugin to ensure customer ID is properly assigned during quote to order conversion
 */

namespace Mab\GuestFix\Plugin\Quote\Model;

use Magento\Quote\Model\Quote;
use Magento\Customer\Model\Session as CustomerSession;

class QuotePlugin
{
    protected $customerSession;

    public function __construct(CustomerSession $customerSession)
    {
        $this->customerSession = $customerSession;
    }

    /**
     * Before converting quote to order, ensure customer ID is properly set
     */
    public function beforeConvertToOrder(Quote $subject)
    {
        // Ensure customer ID is properly assigned when converting quote to order
        if ($this->customerSession->isLoggedIn()) {
            $customerId = $this->customerSession->getCustomerId();
            $customer = $this->customerSession->getCustomer();
            
            $subject->setCustomerId($customerId);
            $subject->setCustomerIsGuest(false);
            $subject->setCustomerEmail($customer->getEmail());
            $subject->setCustomerFirstname($customer->getFirstname());
            $subject->setCustomerLastname($customer->getLastname());
            $subject->setCustomerGroupId($customer->getGroupId());
        }
        
        return $subject;
    }
}
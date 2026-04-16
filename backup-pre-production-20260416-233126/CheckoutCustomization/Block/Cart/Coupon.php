<?php
namespace Mab\CheckoutCustomization\Block\Cart;

use Magento\Checkout\Model\Session;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\View\Element\Template\Context;
use Magento\SalesRule\Model\ResourceModel\Rule\CollectionFactory as RuleCollectionFactory;

class Coupon extends \Magento\Checkout\Block\Cart\Coupon
{
    /**
     * @var Session
     */
    protected $checkoutSession;

    /**
     * @var RuleCollectionFactory
     */
    protected $ruleCollectionFactory;

    /**
     * @param Context $context
     * @param CustomerSession $customerSession
     * @param Session $checkoutSession
     * @param RuleCollectionFactory $ruleCollectionFactory
     * @param array $data
     */
    public function __construct(
        Context $context,
        CustomerSession $customerSession,
        Session $checkoutSession,
        RuleCollectionFactory $ruleCollectionFactory,
        array $data = []
    ) {
        $this->checkoutSession = $checkoutSession;
        $this->ruleCollectionFactory = $ruleCollectionFactory;
        parent::__construct($context, $customerSession, $checkoutSession, $data);
    }

    /**
     * Get custom message to display when discount is disabled
     *
     * @return string
     */
    public function getCustomMessage()
    {
        return $this->scopeConfig->getValue(
            'mab_checkout/discount_settings/custom_message',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * Get cart totals
     *
     * @return array
     */
    public function getTotals()
    {
        $quote = $this->checkoutSession->getQuote();
        if ($quote->getId()) {
            return $quote->getTotals();
        }
        return [];
    }

    /**
     * Get discount rule label
     *
     * @return string
     */
    public function getDiscountLabel()
    {
        $quote = $this->checkoutSession->getQuote();
        if ($quote->getId() && $quote->getCouponCode()) {
            $rules = $this->ruleCollectionFactory->create()
                ->addFieldToFilter('code', $quote->getCouponCode())
                ->setPageSize(1)
                ->getFirstItem();
            
            if ($rules->getId()) {
                return $rules->getName();
            }
        }
        return __('Discount');
    }
}
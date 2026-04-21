<?php
/**
 * Mab_CheckoutCustomization
 * Cart Configuration Block
 */
namespace Mab\CheckoutCustomization\Block\Cart;

use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Store\Model\StoreManagerInterface;

class CheckoutConfig extends Template
{
    /**
     * @var CheckoutSession
     */
    protected $checkoutSession;

    /**
     * @var CustomerSession
     */
    protected $customerSession;

    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @param Context $context
     * @param CheckoutSession $checkoutSession
     * @param CustomerSession $customerSession
     * @param StoreManagerInterface $storeManager
     * @param array $data
     */
    public function __construct(
        Context $context,
        CheckoutSession $checkoutSession,
        CustomerSession $customerSession,
        StoreManagerInterface $storeManager,
        array $data = []
    ) {
        $this->checkoutSession = $checkoutSession;
        $this->customerSession = $customerSession;
        $this->storeManager = $storeManager;
        parent::__construct($context, $data);
    }

    /**
     * Get quote
     *
     * @return \Magento\Quote\Model\Quote
     */
    public function getQuote()
    {
        return $this->checkoutSession->getQuote();
    }

    /**
     * Check if customer is logged in
     *
     * @return bool
     */
    public function isCustomerLoggedIn()
    {
        return $this->customerSession->isLoggedIn();
    }

    /**
     * Get customer email
     *
     * @return string
     */
    public function getCustomerEmail()
    {
        if ($this->isCustomerLoggedIn()) {
            return $this->customerSession->getCustomer()->getEmail();
        }
        return '';
    }

    /**
     * Get customer firstname
     *
     * @return string
     */
    public function getCustomerFirstname()
    {
        if ($this->isCustomerLoggedIn()) {
            return $this->customerSession->getCustomer()->getFirstname();
        }
        return '';
    }

    /**
     * Get customer lastname
     *
     * @return string
     */
    public function getCustomerLastname()
    {
        if ($this->isCustomerLoggedIn()) {
            return $this->customerSession->getCustomer()->getLastname();
        }
        return '';
    }

    /**
     * Get store code
     *
     * @return string
     */
    public function getStoreCode()
    {
        return $this->storeManager->getStore()->getCode();
    }

    /**
     * Get base currency code
     *
     * @return string
     */
    public function getBaseCurrencyCode()
    {
        return $this->storeManager->getStore()->getBaseCurrencyCode();
    }

    /**
     * Get quote currency code
     *
     * @return string
     */
    public function getQuoteCurrencyCode()
    {
        return $this->getQuote()->getQuoteCurrencyCode();
    }

    /**
     * Get total segments for KO totals rendering
     * Includes gift card segment if applied
     *
     * @return array
     */
    public function getTotalSegments()
    {
        $segments = [];
        $quote = $this->getQuote();
        $shippingAddress = $quote->getShippingAddress();

        // Subtotal
        if ($quote->getSubtotal()) {
            $segments[] = [
                'code' => 'subtotal',
                'title' => __('Subtotal'),
                'value' => $quote->getSubtotal()
            ];
        }

        // Shipping
        if ($shippingAddress && $shippingAddress->getShippingAmount()) {
            $segments[] = [
                'code' => 'shipping',
                'title' => __('Shipping'),
                'value' => $shippingAddress->getShippingAmount()
            ];
        }

        // Discount
        if ($shippingAddress && $shippingAddress->getDiscountAmount()) {
            $segments[] = [
                'code' => 'discount',
                'title' => __('Discount'),
                'value' => -$shippingAddress->getDiscountAmount()
            ];
        }

        // Tax
        if ($shippingAddress && $shippingAddress->getTaxAmount()) {
            $segments[] = [
                'code' => 'tax',
                'title' => __('Tax'),
                'value' => $shippingAddress->getTaxAmount()
            ];

            $segments[] = [
                'code' => 'wee',
                'title' => __('Tax'),
                'value' => $shippingAddress->getTaxAmount()
            ];
        }

        // Gift Card (Amasty)
        if ($quote->getExtensionAttributes() && $quote->getExtensionAttributes()->getAmGiftcardQuote()) {
            $giftCardQuote = $quote->getExtensionAttributes()->getAmGiftcardQuote();
            $giftAmountUsed = $giftCardQuote->getGiftAmountUsed();
            $giftCards = $giftCardQuote->getGiftCards();

            if ($giftAmountUsed > 0 && $giftCards) {
                $codes = [];
                foreach ($giftCards as $card) {
                    if (isset($card['code'])) {
                        $codes[] = $card['code'];
                    }
                }

                $segments[] = [
                    'code' => 'amgiftcard',
                    'title' => implode(', ', $codes),
                    'value' => -$giftAmountUsed
                ];
            }
        }

        // Grand Total
        $segments[] = [
            'code' => 'grand_total',
            'title' => __('Order Total'),
            'value' => $quote->getGrandTotal()
        ];

        return $segments;
    }

    /**
     * Get gift card info for inline display
     *
     * @return array|null
     */
    public function getGiftCardInfo()
    {
        $quote = $this->getQuote();

        if (!$quote->getExtensionAttributes() || !$quote->getExtensionAttributes()->getAmGiftcardQuote()) {
            return null;
        }

        $giftCardQuote = $quote->getExtensionAttributes()->getAmGiftcardQuote();
        $giftAmountUsed = $giftCardQuote->getGiftAmountUsed();
        $giftCards = $giftCardQuote->getGiftCards();

        if ($giftAmountUsed <= 0 || !$giftCards) {
            return null;
        }

        $codes = [];
        foreach ($giftCards as $card) {
            if (isset($card['code'])) {
                $codes[] = $card['code'];
            }
        }

        return [
            'codes' => $codes,
            'amount_used' => $giftAmountUsed,
            'formatted_amount' => -$giftAmountUsed
        ];
    }
}

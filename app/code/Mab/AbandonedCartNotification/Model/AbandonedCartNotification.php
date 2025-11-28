<?php
namespace Mab\AbandonedCartNotification\Model;

use Magento\Framework\Mail\Template\TransportBuilder;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Framework\UrlInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Psr\Log\LoggerInterface;
use Magento\Framework\Pricing\PriceCurrencyInterface;

class AbandonedCartNotification
{
    const XML_PATH_ENABLED = 'abandoned_cart/general/enabled';
    const XML_PATH_TIME_THRESHOLD = 'abandoned_cart/general/time_threshold';
    const XML_PATH_SENDER_EMAIL_IDENTITY = 'abandoned_cart/general/sender_email_identity';
    const XML_PATH_EMAIL_TEMPLATE = 'abandoned_cart/general/email_template';

    protected $transportBuilder;
    protected $scopeConfig;
    protected $urlBuilder;
    protected $cartRepository;
    protected $customerRepository;
    protected $logger;
    protected $priceCurrency;
    protected $storeManager;

    public function __construct(
        TransportBuilder $transportBuilder,
        ScopeConfigInterface $scopeConfig,
        UrlInterface $urlBuilder,
        CartRepositoryInterface $cartRepository,
        CustomerRepositoryInterface $customerRepository,
        LoggerInterface $logger,
        PriceCurrencyInterface $priceCurrency
    ) {
        $this->transportBuilder = $transportBuilder;
        $this->scopeConfig = $scopeConfig;
        $this->urlBuilder = $urlBuilder;
        $this->cartRepository = $cartRepository;
        $this->customerRepository = $customerRepository;
        $this->logger = $logger;
        $this->priceCurrency = $priceCurrency;
    }

    public function isEnabled($storeId = null)
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_ENABLED,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    public function getTimeThreshold($storeId = null)
    {
        return (int)$this->scopeConfig->getValue(
            self::XML_PATH_TIME_THRESHOLD,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    public function getSenderEmailIdentity($storeId = null)
    {
        return $this->scopeConfig->getValue(
            self::XML_PATH_SENDER_EMAIL_IDENTITY,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    public function getEmailTemplate($storeId = null)
    {
        return $this->scopeConfig->getValue(
            self::XML_PATH_EMAIL_TEMPLATE,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    public function sendNotification($quote)
    {
        if (!$this->isEnabled($quote->getStoreId())) {
            return false;
        }

        if (!$quote->getCustomerEmail()) {
            return false;
        }

        try {
            $customer = $this->getCustomer($quote);
            $itemsHtml = $this->getCartItemsHtml($quote);
            $total = $this->getCartTotal($quote);
            $checkoutUrl = $this->urlBuilder->getUrl('checkout', ['_secure' => true]);

            $transport = $this->transportBuilder
                ->setTemplateIdentifier($this->getEmailTemplate($quote->getStoreId()))
                ->setTemplateOptions([
                    'area' => \Magento\Framework\App\Area::AREA_FRONTEND,
                    'store' => $quote->getStoreId()
                ])
                ->setTemplateVars([
                    'customer' => $customer,
                    'cart' => [
                        'items' => $itemsHtml,
                        'total' => $total
                    ],
                    'checkout_url' => $checkoutUrl
                ])
                ->setFromByScope($this->getSenderEmailIdentity($quote->getStoreId()), $quote->getStoreId())
                ->addTo($quote->getCustomerEmail(), $customer->getName())
                ->getTransport();

            $transport->sendMessage();
            return true;
        } catch (\Exception $e) {
            $this->logger->error('Abandoned Cart Notification Error: ' . $e->getMessage());
            return false;
        }
    }

    protected function getCustomer($quote)
    {
        if ($quote->getCustomerId()) {
            return $this->customerRepository->getById($quote->getCustomerId());
        }

        $customer = new \Magento\Framework\DataObject();
        $customer->setName($quote->getCustomerFirstname() . ' ' . $quote->getCustomerLastname());
        return $customer;
    }

    protected function getCartItemsHtml($quote)
    {
        $itemsHtml = '';
        foreach ($quote->getAllVisibleItems() as $item) {
            $itemsHtml .= '<tr>';
            $itemsHtml .= '<td>' . $item->getName() . '</td>';
            $itemsHtml .= '<td>' . $item->getQty() . '</td>';
            $itemsHtml .= '<td>' . $this->formatPrice($item->getPrice(), $quote->getStoreId()) . '</td>';
            $itemsHtml .= '</tr>';
        }
        return $itemsHtml;
    }

    protected function getCartTotal($quote)
    {
        return $this->formatPrice($quote->getGrandTotal(), $quote->getStoreId());
    }
    
    /**
     * Format price using PriceCurrencyInterface
     *
     * @param float $amount
     * @param int $storeId
     * @return string
     */
    protected function formatPrice($amount, $storeId)
    {
        $store = $this->getStoreManager()->getStore($storeId);
        return $this->priceCurrency->format($amount, false, 2, $store);
    }
    
    /**
     * Get store manager
     *
     * @return \Magento\Store\Model\StoreManagerInterface
     */
    protected function getStoreManager()
    {
        if (!$this->storeManager) {
            $this->storeManager = \Magento\Framework\App\ObjectManager::getInstance()
                ->get(\Magento\Store\Model\StoreManagerInterface::class);
        }
        return $this->storeManager;
    }
}
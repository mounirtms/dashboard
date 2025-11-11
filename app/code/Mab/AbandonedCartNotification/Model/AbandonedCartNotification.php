<?php
namespace Mab\AbandonedCartNotification\Model;

use Magento\Framework\Mail\Template\TransportBuilder;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Framework\UrlInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Psr\Log\LoggerInterface;

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

    public function __construct(
        TransportBuilder $transportBuilder,
        ScopeConfigInterface $scopeConfig,
        UrlInterface $urlBuilder,
        CartRepositoryInterface $cartRepository,
        CustomerRepositoryInterface $customerRepository,
        LoggerInterface $logger
    ) {
        $this->transportBuilder = $transportBuilder;
        $this->scopeConfig = $scopeConfig;
        $this->urlBuilder = $urlBuilder;
        $this->cartRepository = $cartRepository;
        $this->customerRepository = $customerRepository;
        $this->logger = $logger;
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
            $itemsHtml .= '<td>' . $quote->getStore()->convertPrice($item->getPrice(), true) . '</td>';
            $itemsHtml .= '</tr>';
        }
        return $itemsHtml;
    }

    protected function getCartTotal($quote)
    {
        return $quote->getStore()->convertPrice($quote->getGrandTotal(), true);
    }
}
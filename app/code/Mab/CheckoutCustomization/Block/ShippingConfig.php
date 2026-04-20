<?php
/**
 * Mab_CheckoutCustomization
 * Shipping Configuration ViewModel
 *
 * Provides dynamic strings and media URL to the checkout frontend
 * instead of hardcoding them in JavaScript.
 */
namespace Mab\CheckoutCustomization\Block;

use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

class ShippingConfig implements ArgumentInterface
{
    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;

    /**
     * @param StoreManagerInterface $storeManager
     * @param ScopeConfigInterface $scopeConfig
     */
    public function __construct(
        StoreManagerInterface $storeManager,
        ScopeConfigInterface $scopeConfig
    ) {
        $this->storeManager = $storeManager;
        $this->scopeConfig = $scopeConfig;
    }

    /**
     * Get shipping method configuration
     *
     * @return array
     */
    public function getShippingConfig(): array
    {
        $mediaUrl = rtrim($this->storeManager->getStore()->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_MEDIA), '/');

        return [
            'shippingMethodCards' => [
                'mediaUrl' => $mediaUrl,
                'defaultTechnoLogo' => $mediaUrl . '/mageplaza/tablerate/techno.png',
                'defaultYalidineLogo' => $mediaUrl . '/mageplaza/tablerate/yalidine-logo.jpg',
                'messages' => [
                    'noMethodsAvailable' => 'Aucune méthode de livraison disponible',
                    'selectShippingForRegion' => 'Sélectionnez votre mode de livraison pour la région de ',
                    'yourRegion' => 'votre région',
                    'retraitImmediat' => 'Retrait immédiat',
                    'delivery2to3Days' => '2-3 jours',
                    'delivery3to5Days' => '3-5 jours',
                    'freeShipping' => 'Gratuit',
                ],
                'deliveryTypeMapping' => [
                    'pickup' => 'retrait',
                    'agency' => 'agence',
                    'homeDelivery' => 'livraison'
                ]
            ]
        ];
    }
}

<?php
/**
 * Mab_CheckoutCustomization
 * Shipping Configuration ViewModel
 *
 * Provides dynamic strings, media URLs, and method logo mappings
 * to the checkout frontend instead of hardcoding them in JavaScript.
 *
 * Logo paths are read from mageplaza_tablerate_method.image column.
 * Update the database and the frontend reflects changes automatically.
 */
namespace Mab\CheckoutCustomization\Block;

use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\App\ResourceConnection;

class ShippingConfig implements ArgumentInterface
{
    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var ResourceConnection
     */
    private $resource;

    /**
     * @param StoreManagerInterface $storeManager
     * @param ResourceConnection $resource
     */
    public function __construct(
        StoreManagerInterface $storeManager,
        ResourceConnection $resource
    ) {
        $this->storeManager = $storeManager;
        $this->resource = $resource;
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
                'methodLogoMap' => $this->getMethodLogoMap(),
                'messages' => [
                    'noMethodsAvailable' => 'Aucune méthode de livraison disponible',
                    'selectShippingForRegion' => 'Sélectionnez votre mode de livraison pour la région de ',
                    'yourRegion' => 'votre région',
                    'retraitImmediat' => 'Retrait aujourd\'hui possible',
                    'delivery2to5Days' => '2-5 jours (préavis par téléphone)',
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

    /**
     * Build logo URL map from database method data
     *
     * Key: method_id (string), Value: full media URL to logo
     *
     * @return array
     */
    private function getMethodLogoMap(): array
    {
        $logoMap = [];
        $mediaUrl = rtrim($this->storeManager->getStore()->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_MEDIA), '/');

        try {
            $connection = $this->resource->getConnection(AdapterInterface::class);
            $tableName = $this->resource->getTableName('mageplaza_tablerate_method');

            $results = $connection->fetchAssoc(
                $connection->select()
                    ->from($tableName, ['method_id', 'image'])
                    ->where('status = ?', 1)
            );

            foreach ($results as $row) {
                $methodId = (string)$row['method_id'];
                $imagePath = $row['image'] ?? '';

                if ($imagePath) {
                    // Build full URL from relative path
                    $logoUrl = $mediaUrl . '/' . ltrim($imagePath, '/');
                    $logoMap[$methodId] = $logoUrl;
                }
            }
        } catch (\Exception $e) {
            // Fall back to empty map - JS will use keyword-based fallback
        }

        return $logoMap;
    }
}

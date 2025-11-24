<?php
namespace Mab\AlgeriaProducts\Block\Product;

use Magento\Catalog\Block\Product\Context;
use Magento\Catalog\Model\CategoryFactory;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory as ProductCollectionFactory;
use Magento\Framework\View\Element\Template;

class Tabs extends Template
{
    /**
     * @var CategoryFactory
     */
    protected $categoryFactory;

    /**
     * @var ProductCollectionFactory
     */
    protected $productCollectionFactory;

    /**
     * @var int
     */
    protected $categoryId = 2172; // Made in Algeria category ID

    /**
     * @param Context $context
     * @param CategoryFactory $categoryFactory
     * @param ProductCollectionFactory $productCollectionFactory
     * @param array $data
     */
    public function __construct(
        Context $context,
        CategoryFactory $categoryFactory,
        ProductCollectionFactory $productCollectionFactory,
        array $data = []
    ) {
        $this->categoryFactory = $categoryFactory;
        $this->productCollectionFactory = $productCollectionFactory;
        parent::__construct($context, $data);
    }

    /**
     * Get product groups for tabs
     *
     * @return array
     */
    public function getProductGroups()
    {
        $category = $this->categoryFactory->create()->load($this->categoryId);
        if (!$category->getId()) {
            return [];
        }

        $productCollection = $category->getProductCollection()
            ->addAttributeToSelect(['name', 'image', 'price', 'url_key', 'sku', 'manufacturer'])
            ->setPageSize(200); // Increased page size to accommodate all products

        $groups = [
            'notebooks' => [
                'title' => __('Notebooks & Journals'),
                'description' => __('Cahiers, carnets et notebooks fabriqués en Algérie'),
                'products' => []
            ],
            'art_supplies' => [
                'title' => __('Art Supplies'),
                'description' => __('Fournitures artistiques et de dessin'),
                'products' => []
            ],
            'stationery' => [
                'title' => __('Stationery'),
                'description' => __('Articles de papeterie générale'),
                'products' => []
            ],
            'office_supplies' => [
                'title' => __('Office Supplies'),
                'description' => __('Fournitures de bureau professionnelles'),
                'products' => []
            ],
            'school_supplies' => [
                'title' => __('School Supplies'),
                'description' => __('Fournitures scolaires pour tous les niveaux'),
                'products' => []
            ],
            'uncategorized' => [
                'title' => __('Other Products'),
                'description' => __('Autres produits fabriqués en Algérie'),
                'products' => []
            ]
        ];

        // Define improved tab categories with more comprehensive keywords
        $tabCategories = [
            'notebooks' => ['cahier', 'notebook', 'carnet', 'journal', 'agenda', 'block', 'memo', 'ecriture', 'writing', 'copy', 'carnet', 'registre'],
            'art_supplies' => ['peinture', 'couleur', 'aquarelle', 'gouache', 'crayon', 'feutre', 'marqueur', 'pastel', 'encre', 'pinceau', 'papier', 'toile', 'chevalet', 'dessin', 'color', 'paint', 'ardoise'],
            'stationery' => ['stylo', 'bic', 'roller', 'mine', 'porte', 'correcteur', 'gomme', 'taille', 'crayon', 'surligneur', 'effaceur', 'pen', 'pencil', 'ink', 'marker'],
            'office_supplies' => ['classeur', 'dossier', 'chemise', 'pochette', 'boite', 'archive', 'porte', 'document', 'bureau', 'agrafe', 'perfore', 'règle', 'compas', 'scissors', 'cutter', 'tape', 'ruban', 'adhesive', 'agrafeuse', 'corbeille'],
            'school_supplies' => ['trousse', 'sacoche', 'cartable', 'sac', 'étui', 'fourre', 'tout', 'pochette', 'gaine', 'pencil', 'case', 'school', 'backpack', 'bag', 'tablier']
        ];

        foreach ($productCollection as $product) {
            $productName = strtolower($product->getName());
            $assigned = false;
            
            // Check each category for matching keywords
            foreach ($tabCategories as $tabKey => $keywords) {
                foreach ($keywords as $keyword) {
                    if (strpos($productName, strtolower($keyword)) !== false) {
                        $groups[$tabKey]['products'][] = $product;
                        $assigned = true;
                        break 2; // Break out of both loops
                    }
                }
            }
            
            // If not assigned to any category, put in uncategorized
            if (!$assigned) {
                $groups['uncategorized']['products'][] = $product;
            }
        }

        // Remove empty groups
        foreach ($groups as $key => $group) {
            if (empty($group['products'])) {
                unset($groups[$key]);
            }
        }

        return $groups;
    }

    /**
     * Get JSON-LD structured data for SEO
     *
     * @return string
     */
    public function getJsonLdStructuredData()
    {
        $data = [
            '@context' => 'https://schema.org',
            '@type' => 'WebPage',
            'name' => __('Made in Algeria - Quality Algerian Products'),
            'description' => __('Discover our wide range of products manufactured in Algeria, showcasing the quality and craftsmanship of Algerian industry.'),
            'url' => $this->getUrl('made-in-algeria'),
            'mainEntity' => [
                '@type' => 'ItemList',
                'itemListElement' => []
            ]
        ];

        $groups = $this->getProductGroups();
        $position = 1;
        foreach ($groups as $group) {
            foreach ($group['products'] as $product) {
                $data['mainEntity']['itemListElement'][] = [
                    '@type' => 'Product',
                    'position' => $position++,
                    'name' => $product->getName(),
                    'url' => $product->getProductUrl(),
                    'sku' => $product->getSku(),
                    'offers' => [
                        '@type' => 'Offer',
                        'price' => $product->getPrice(),
                        'priceCurrency' => 'DZD'
                    ]
                ];
            }
        }

        return json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    }
}
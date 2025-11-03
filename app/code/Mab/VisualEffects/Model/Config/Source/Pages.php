<?php
namespace Mab\VisualEffects\Model\Config\Source;

use Magento\Framework\Option\ArrayInterface;

class Pages implements ArrayInterface
{
    /**
     * Options getter
     *
     * @return array
     */
    public function toOptionArray()
    {
        return [
            ['value' => 'cms_index_index', 'label' => __('Home Page')],
            ['value' => 'catalog_category_view', 'label' => __('Category Pages')],
            ['value' => 'catalog_product_view', 'label' => __('Product Pages')],
            ['value' => 'checkout_cart_index', 'label' => __('Shopping Cart')],
            ['value' => 'checkout_index_index', 'label' => __('Checkout')],
            ['value' => 'cms_page_view', 'label' => __('CMS Pages')],
            ['value' => 'customer_account_index', 'label' => __('Customer Account')],
            ['value' => 'catalogsearch_result_index', 'label' => __('Search Results')],
        ];
    }

    /**
     * Get options in "key-value" format
     *
     * @return array
     */
    public function toArray()
    {
        return [
            'cms_index_index' => __('Home Page'),
            'catalog_category_view' => __('Category Pages'),
            'catalog_product_view' => __('Product Pages'),
            'checkout_cart_index' => __('Shopping Cart'),
            'checkout_index_index' => __('Checkout'),
            'cms_page_view' => __('CMS Pages'),
            'customer_account_index' => __('Customer Account'),
            'catalogsearch_result_index' => __('Search Results'),
        ];
    }
}
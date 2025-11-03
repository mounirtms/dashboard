<?php
/**
 * Copyright © MageWorx. All rights reserved.
 * See LICENSE.txt for license details.
 */
declare(strict_types=1);

namespace MageWorx\OrdersGrid\Ui\Component\Listing\Columns\GridAdditionalDataListing;

use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Ui\Component\Listing\Columns\Column;
use MageWorx\OrdersGrid\Helper\Image as Helper;

class IconImage extends Column
{
    const ALT_FIELD = 'name';

    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @var UrlInterface
     */
    protected $urlBuilder;

    /**
     * @var Helper
     */
    protected $helper;

    /**
     * @param ContextInterface $context
     * @param UiComponentFactory $uiComponentFactory
     * @param UrlInterface $urlBuilder
     * @param StoreManagerInterface $storeManager
     * @param Helper $helper
     * @param array $components
     * @param array $data
     */
    public function __construct(
        ContextInterface      $context,
        UiComponentFactory    $uiComponentFactory,
        UrlInterface          $urlBuilder,
        StoreManagerInterface $storeManager,
        Helper                $helper,
        array                 $components = [],
        array                 $data = []
    ) {
        $this->storeManager = $storeManager;
        $this->urlBuilder   = $urlBuilder;
        $this->helper       = $helper;
        parent::__construct($context, $uiComponentFactory, $components, $data);
    }

    /**
     * Prepare Data Source
     *
     * @param array $dataSource
     * @return array
     */
    public function prepareDataSource(array $dataSource): array
    {
        if (isset($dataSource['data']['items'])) {
            $fieldName = $this->getData('name');
            foreach ($dataSource['data']['items'] as &$item) {
                if (empty($item['entity_id'])) {
                    continue;
                }

                $link = $this->getEditLink((int)$item['entity_id']);

                if (!empty($item[$fieldName])) {
                    $url                            = $this->helper->getImageUrl($item[$fieldName]);
                    $thumbnailUrl                   = $this->helper->getImageUrl(
                        $item[$fieldName],
                        Helper::IMAGE_TYPE_THUMBNAIL
                    );
                    $item[$fieldName . '_src']      = $thumbnailUrl;
                    $item[$fieldName . '_alt']      = $this->getAlt($item) ?: '';
                    $item[$fieldName . '_link']     = $link;
                    $item[$fieldName . '_orig_src'] = $url;
                }
            }
        }

        return $dataSource;
    }

    /**
     * @param array $row
     *
     * @return null|string
     */
    protected function getAlt(array $row): ?string
    {
        return $row[static::ALT_FIELD] ?? null;
    }

    /**
     * @param int $id
     * @return string
     */
    protected function getEditLink(int $id): string
    {
        return $this->urlBuilder->getUrl(
            'mageworx_ordersgrid/gridAdditionalData/edit',
            ['id' => $id]
        );
    }
}

<?php
/**
 * Copyright © MageWorx. All rights reserved.
 * See LICENSE.txt for license details.
 */
declare(strict_types=1);

namespace MageWorx\OrdersGrid\Ui\Component\MassAction;

use Magento\Framework\UrlInterface;
use MageWorx\OrdersGrid\Helper\Data as Helper;

class Additional implements \JsonSerializable
{
    const URL_PATH = 'mageworx_ordersgrid/order_grid/';
    const TYPE_COMPLETE = 'complete';
    const TYPE_DELETE_COMPLETELY = 'deleteCompletely';
    const TYPE_SYNCHRONIZE = 'synchronize';

    /**
     * Additional options params
     *
     * @var array
     */
    protected $data;

    /**
     * @var UrlInterface
     */
    protected $urlBuilder;

    /**
     * Sub-actions Base URL
     *
     * @var string
     */
    protected $urlPath;

    /**
     * @var Helper
     */
    protected $helper;

    /**
     * Sub-actions additional params
     *
     * @var array
     */
    protected $additionalData = [];

    /**
     * Options
     *
     * @var array
     */
    protected $options = [];

    /**
     * @param UrlInterface $urlBuilder
     * @param Helper $helper
     * @param array $data
     */
    public function __construct(
        UrlInterface $urlBuilder,
        Helper       $helper,
        array        $data = []
    ) {
        $this->data       = $data;
        $this->helper     = $helper;
        $this->urlBuilder = $urlBuilder;
    }

    /**
     * Get options
     *
     * @return array
     */
    #[\ReturnTypeWillChange]
    public function jsonSerialize()
    {
        if (empty($this->options)) {
            $this->prepareOptionsData();

            $this->options[static::TYPE_COMPLETE] = array_merge_recursive(
                $this->options[static::TYPE_COMPLETE] = [
                    'type' => static::TYPE_COMPLETE,
                    'label' => __('Complete'),
                    'url' => $this->urlBuilder->getUrl(static::URL_PATH . static::TYPE_COMPLETE, []),
                    'confirm' => [
                        'title' => __('Complete'),
                        'message' => __('Are you sure you want to complete selected items?')
                    ]
                ],
                $this->additionalData
            );

            $this->options[static::TYPE_DELETE_COMPLETELY] = array_merge_recursive(
                $this->options[static::TYPE_DELETE_COMPLETELY] = [
                    'type' => static::TYPE_DELETE_COMPLETELY,
                    'label' => __('Delete Completely'),
                    'url' => $this->urlBuilder->getUrl(static::URL_PATH . static::TYPE_DELETE_COMPLETELY, []),
                    'confirm' => [
                        'title' => __('Delete Completely'),
                        'message' => __('Are you sure you want to Delete Completely selected items?')
                    ]
                ],
                $this->additionalData
            );

            $this->options[static::TYPE_SYNCHRONIZE] = array_merge_recursive(
                $this->options[static::TYPE_SYNCHRONIZE] = [
                    'type' => static::TYPE_SYNCHRONIZE,
                    'label' => __('Synchronize'),
                    'url' => $this->urlBuilder->getUrl(static::URL_PATH . static::TYPE_SYNCHRONIZE, []),
                    'confirm' => [
                        'title' => __('Synchronize'),
                        'message' => __('Are you sure you want to synchronize selected orders?')
                    ]
                ],
                $this->additionalData
            );

            $this->filterOptions();
            $this->options = array_values($this->options);
        }

        return $this->options;
    }

    /**
     * Prepare sub-actions addition data
     *
     * @return void
     */
    protected function prepareOptionsData()
    {
        foreach ($this->data as $dataKey => $dataValue) {
            switch ($dataKey) {
                case 'urlPath':
                    $this->urlPath = $dataValue;
                    break;
                default:
                    $this->additionalData[$dataKey] = $dataValue;
                    break;
            }
        }
    }

    /**
     * Remove disabled mass-actions
     *
     * @return void
     */
    protected function filterOptions(): void
    {
        $disabledOptions = $this->helper->getDisabledMassActions();
        foreach ($this->options as $key => $value) {
            if (in_array($key, $disabledOptions)) {
                unset($this->options[$key]);
            }
        }
    }

    /**
     * @return array
     */
    public function getOptions(): array
    {
        return $this->options;
    }
}

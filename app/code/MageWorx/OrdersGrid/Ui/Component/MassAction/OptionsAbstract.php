<?php
/**
 * Copyright © MageWorx. All rights reserved.
 * See LICENSE.txt for license details.
 */
declare(strict_types=1);

namespace MageWorx\OrdersGrid\Ui\Component\MassAction;

use Magento\Framework\UrlInterface;
use MageWorx\OrdersGrid\Helper\Data as Helper;

abstract class OptionsAbstract
{
    const PATH_RESEND_EMAIL = 'mageworx_ordersgrid/order_grid/resendEmail';
    const SEND_EMAIL = null;

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
     * @return string
     */
    abstract protected function getUrlPath(): string;

    /**
     * Render options similar for all classes
     */
    protected function getMatchingOptions()
    {
        /**
         * Capture
         * Invoice
         * Invoice → Print
         * Ship
         * Ship → Print
         * Invoice → Capture
         * Invoice → Capture → Ship
         * Invoice → Capture → Ship → Print
         */
        $this->options['capture_' . static::SEND_EMAIL] = array_merge_recursive(
            $this->options['capture_' . static::SEND_EMAIL] = [
                'type' => 'capture_' . static::SEND_EMAIL,
                'label' => __('Capture'),
                'url' => $this->urlBuilder->getUrl(
                    $this->urlPath,
                    [
                        'email' => static::SEND_EMAIL,
                        'capture' => 1,
                        'invoice' => 0,
                        'ship' => 0,
                        'print' => 0
                    ]
                ),
                'confirm' => [
                    'title' => __('Capture'),
                    'message' => __('Are you sure you want to Capture selected items?')
                ]
            ],
            $this->additionalData
        );

        $this->options['invoice_' . static::SEND_EMAIL] = array_merge_recursive(
            $this->options['invoice_' . static::SEND_EMAIL] = [
                'type' => 'invoice_' . static::SEND_EMAIL,
                'label' => __('Invoice'),
                'url' => $this->urlBuilder->getUrl(
                    $this->urlPath,
                    [
                        'email' => static::SEND_EMAIL,
                        'capture' => 0,
                        'invoice' => 1,
                        'ship' => 0,
                        'print' => 0
                    ]
                ),
                'confirm' => [
                    'title' => __('Invoice'),
                    'message' => __('Are you sure you want to Invoice selected items?')
                ]
            ],
            $this->additionalData
        );

        $this->options['invoice_print_' . static::SEND_EMAIL] = array_merge_recursive(
            $this->options['invoice_print_' . static::SEND_EMAIL] = [
                'type' => 'invoice_print_' . static::SEND_EMAIL,
                'label' => __('Invoice + Print'),
                'url' => $this->urlBuilder->getUrl(
                    $this->urlPath,
                    [
                        'email' => static::SEND_EMAIL,
                        'capture' => 0,
                        'invoice' => 1,
                        'ship' => 0,
                        'print' => 1
                    ]
                ),
                'confirm' => [
                    'title' => __('Invoice + Print'),
                    'message' => __('Are you sure you want to Invoice + Print selected items?')
                ]
            ],
            $this->additionalData
        );

        $this->options['ship_' . static::SEND_EMAIL] = array_merge_recursive(
            $this->options['ship_' . static::SEND_EMAIL] = [
                'type' => 'ship_' . static::SEND_EMAIL,
                'label' => __('Ship'),
                'url' => $this->urlBuilder->getUrl(
                    $this->urlPath,
                    [
                        'email' => static::SEND_EMAIL,
                        'capture' => 0,
                        'invoice' => 0,
                        'ship' => 1,
                        'print' => 0
                    ]
                ),
                'confirm' => [
                    'title' => __('Ship'),
                    'message' => __('Are you sure you want to Ship selected items?')
                ]
            ],
            $this->additionalData
        );

        $this->options['ship_print_' . static::SEND_EMAIL] = array_merge_recursive(
            $this->options['ship_print_' . static::SEND_EMAIL] = [
                'type' => 'ship_print_' . static::SEND_EMAIL,
                'label' => __('Ship + Print'),
                'url' => $this->urlBuilder->getUrl(
                    $this->urlPath,
                    [
                        'email' => static::SEND_EMAIL,
                        'capture' => 0,
                        'invoice' => 0,
                        'ship' => 1,
                        'print' => 1
                    ]
                ),
                'confirm' => [
                    'title' => __('Ship + Print'),
                    'message' => __('Are you sure you want to Ship + Print selected items?')
                ]
            ],
            $this->additionalData
        );

        $this->options['invoice_capture_' . static::SEND_EMAIL] = array_merge_recursive(
            $this->options['invoice_capture_' . static::SEND_EMAIL] = [
                'type' => 'invoice_capture_' . static::SEND_EMAIL,
                'label' => __('Invoice + Capture'),
                'url' => $this->urlBuilder->getUrl(
                    $this->urlPath,
                    [
                        'email' => static::SEND_EMAIL,
                        'capture' => 1,
                        'invoice' => 1,
                        'ship' => 0,
                        'print' => 0
                    ]
                ),
                'confirm' => [
                    'title' => __('Invoice + Capture'),
                    'message' => __('Are you sure you want to Invoice + Capture selected items?')
                ]
            ],
            $this->additionalData
        );

        $this->options['invoice_capture_ship_' . static::SEND_EMAIL] = array_merge_recursive(
            $this->options['invoice_capture_ship_' . static::SEND_EMAIL] = [
                'type' => 'invoice_capture_ship_' . static::SEND_EMAIL,
                'label' => __('Invoice + Capture + Ship'),
                'url' => $this->urlBuilder->getUrl(
                    $this->urlPath,
                    [
                        'email' => static::SEND_EMAIL,
                        'capture' => 1,
                        'invoice' => 1,
                        'ship' => 1,
                        'print' => 0
                    ]
                ),
                'confirm' => [
                    'title' => __('Invoice + Capture + Ship'),
                    'message' => __('Are you sure you want to Invoice + Capture + Ship selected items?')
                ]
            ],
            $this->additionalData
        );

        $this->options['invoice_capture_ship_print_' . static::SEND_EMAIL] = array_merge_recursive(
            $this->options['invoice_capture_ship_print_' . static::SEND_EMAIL] = [
                'type' => 'invoice_capture_ship_print_' . static::SEND_EMAIL,
                'label' => __('Invoice + Capture + Ship + Print'),
                'url' => $this->urlBuilder->getUrl(
                    $this->urlPath,
                    [
                        'email' => static::SEND_EMAIL,
                        'capture' => 1,
                        'invoice' => 1,
                        'ship' => 1,
                        'print' => 1
                    ]
                ),
                'confirm' => [
                    'title' => __('Invoice + Capture + Ship + Print'),
                    'message' => __('Are you sure you want to Invoice + Capture + Ship + Print selected items?')
                ]
            ],
            $this->additionalData
        );

        $this->options['capture_ship_' . static::SEND_EMAIL] = array_merge_recursive(
            $this->options['capture_ship_' . static::SEND_EMAIL] = [
                'type' => 'capture_ship_' . static::SEND_EMAIL,
                'label' => __('Capture + Ship'),
                'url' => $this->urlBuilder->getUrl(
                    $this->urlPath,
                    [
                        'email' => static::SEND_EMAIL,
                        'capture' => 1,
                        'invoice' => 0,
                        'ship' => 1,
                        'print' => 0
                    ]
                ),
                'confirm' => [
                    'title' => __('Capture + Ship'),
                    'message' => __('Are you sure you want to Capture + Ship selected items?')
                ]
            ],
            $this->additionalData
        );
    }

    /**
     * Prepare sub-actions addition data
     *
     * @return void
     */
    protected function prepareOptionsData()
    {
        $this->urlPath = $this->getUrlPath();

        foreach ($this->data as $dataKey => $dataValue) {
            $this->additionalData[$dataKey] = $dataValue;
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

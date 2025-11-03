<?php
/**
 * Copyright © MageWorx. All rights reserved.
 * See LICENSE.txt for license details.
 */
declare(strict_types=1);

namespace MageWorx\OrdersGrid\Model\Config\Source;

class MassActions implements \Magento\Framework\Data\OptionSourceInterface
{
    /**
     * @inheritDoc
     */
    public function toOptionArray()
    {
        return [
            [
                'label' => __('None'),
                'value' => ''
            ],
            [
                'label' => __('Additional'),
                'value' => [
                    [
                        'value' => \MageWorx\OrdersGrid\Ui\Component\MassAction\Additional::TYPE_COMPLETE,
                        'label' => __('Complete')
                    ],
                    [
                        'value' => \MageWorx\OrdersGrid\Ui\Component\MassAction\Additional::TYPE_DELETE_COMPLETELY,
                        'label' => __('Delete Completely')
                    ],
                    [
                        'value' => \MageWorx\OrdersGrid\Ui\Component\MassAction\Additional::TYPE_SYNCHRONIZE,
                        'label' => __('Synchronize')
                    ],
                ]
            ],
            [
                'label' => __('Notify Customer'),
                'value' => [
                    [
                        'value' => 'resend_order_email',
                        'label' => __('Re-send Order Email')
                    ],
                    [
                        'value' => 'resend_invoice_email',
                        'label' => __('Re-send Invoice Email')
                    ],
                    [
                        'value' => 'resend_shipment_email',
                        'label' => __('Re-send Shipment Email')
                    ],
                    [
                        'value' => 'invoice_1',
                        'label' => __('Invoice')
                    ],
                    [
                        'value' => 'capture_1',
                        'label' => __('Capture')
                    ],
                    [
                        'value' => 'invoice_print_1',
                        'label' => __('Invoice + Print')
                    ],
                    [
                        'value' => 'ship_1',
                        'label' => __('Ship')
                    ],
                    [
                        'value' => 'ship_print_1',
                        'label' => __('Ship + Print')
                    ],
                    [
                        'value' => 'invoice_capture_1',
                        'label' => __('Invoice + Capture')
                    ],
                    [
                        'value' => 'invoice_capture_ship_1',
                        'label' => __('Invoice + Capture + Ship')
                    ],
                    [
                        'value' => 'invoice_capture_ship_print_1',
                        'label' => __('Invoice + Capture + Ship + Print')
                    ],
                    [
                        'value' => 'capture_ship_1',
                        'label' => __('Capture + Ship')
                    ]
                ]
            ],
            [
                'label' => __('Do Not Notify Customer'),
                'value' => [
                    [
                        'value' => 'invoice_0',
                        'label' => __('Invoice')
                    ],
                    [
                        'value' => 'capture_0',
                        'label' => __('Capture')
                    ],
                    [
                        'value' => 'invoice_print_0',
                        'label' => __('Invoice + Print')
                    ],
                    [
                        'value' => 'ship_0',
                        'label' => __('Ship')
                    ],
                    [
                        'value' => 'ship_print_0',
                        'label' => __('Ship + Print')
                    ],
                    [
                        'value' => 'invoice_capture_0',
                        'label' => __('Invoice + Capture')
                    ],
                    [
                        'value' => 'invoice_capture_ship_0',
                        'label' => __('Invoice + Capture + Ship')
                    ],
                    [
                        'value' => 'invoice_capture_ship_print_0',
                        'label' => __('Invoice + Capture + Ship + Print')
                    ],
                    [
                        'value' => 'capture_ship_0',
                        'label' => __('Capture + Ship')
                    ]
                ]
            ]
        ];
    }
}

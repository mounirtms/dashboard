<?php
/**
 * Copyright © MageWorx. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace MageWorx\OrderEditor\Model\Order;

use Magento\Framework\ObjectManagerInterface;
use MageWorx\OrderEditor\Api\SalesProcessorInterface;
use MageWorx\OrderEditor\Helper\Data as Helper;

class SalesProcessorFactory
{
    /**
     * @var ObjectManagerInterface
     */
    protected $objectManager;

    /**
     * @var Helper
     */
    protected $helper;

    /**
     * @var array
     */
    private $salesProcessors;

    /**
     * @var string
     */
    private $defaultSalesProcessorInstance;

    /**
     * @param string $defaultSalesProcessorInstance
     * @param array $salesProcessors
     */
    public function __construct(
        ObjectManagerInterface $objectManager,
        Helper $helper,
        string $defaultSalesProcessorInstance = '\MageWorx\OrderEditor\Model\Order\SalesProcessor\KeepUntouchedSalesProcessor',
        array $salesProcessors = []
    ) {
        $this->objectManager = $objectManager;
        $this->helper = $helper;
        $this->defaultSalesProcessorInstance = $defaultSalesProcessorInstance;
        $this->salesProcessors = $salesProcessors;
    }

    public function create(): SalesProcessorInterface
    {
        $salesProcessorCode = $this->helper->getSalesProcessorCode();
        if ($salesProcessorCode && isset($this->salesProcessors[$salesProcessorCode])) {
            return $this->objectManager->create($this->salesProcessors[$salesProcessorCode]);
        } else {
            return $this->objectManager->create($this->defaultSalesProcessorInstance);
        }
    }
}

<?php
/**
 * Copyright © MageWorx. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace MageWorx\OrderEditor\Model\Invoice;

use Magento\Framework\ObjectManagerInterface as ObjectManager;
use MageWorx\OrderEditor\Api\PaymentMethodProcessorInterface;

class PaymentMethodProcessorFactory
{
    /**
     * Object Manager instance
     *
     * @var \Magento\Framework\ObjectManagerInterface
     */
    protected $objectManager;

    /**
     * @var string
     */
    protected $instanceName;

    /**
     * @var array
     */
    private $map;

    /**
     * @param ObjectManager $objectManager
     * @param string $instanceName
     * @param array $map
     */
    public function __construct(
        ObjectManager $objectManager,
        string $instanceName = 'MageWorx\OrderEditor\Api\PaymentMethodProcessorInterface',
        array $map = []
    ) {
        $this->objectManager = $objectManager;
        $this->instanceName  = $instanceName;
        $this->map           = $map;
    }

    /**
     * Create class instance with specified parameters
     *
     * @param array $data
     * @return PaymentMethodProcessorInterface
     */
    public function create(array $data = [])
    {
        if (!empty($data['payment'])) {
            $instanceName = $this->getClassNameFromMap($data['payment']);
        } else {
            $instanceName = $this->instanceName;
        }

        return $this->objectManager->create($instanceName, $data);
    }

    /**
     * @param string $paymentMethodCode
     * @return string
     */
    protected function getClassNameFromMap(string $paymentMethodCode): string
    {
        if (isset($this->map[$paymentMethodCode])) {
            $instanceName = $this->map[$paymentMethodCode];
        } else {
            $instanceName = $this->instanceName;
        }

        return $instanceName;
    }
}

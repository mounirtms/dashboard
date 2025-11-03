<?php
/**
 * Copyright © MageWorx. All rights reserved.
 * See LICENSE.txt for license details.
 */

namespace MageWorx\OrderEditor\Block;

use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\Checkout\Model\ConfigProviderInterface;
use MageWorx\OrderEditor\Model\Ui\ConfigProvider;
use Magento\Framework\Serialize\Serializer\Json as SerializerJson;

/**
 * Class Payment
 *
 */
class Payment extends Template
{
    /**
     * @var ConfigProviderInterface
     */
    private $config;

    /**
     * @var SerializerJson
     */
    private $serializer;

    /**
     * Payment constructor.
     *
     * @param ConfigProviderInterface $config
     * @param Context $context
     * @param SerializerJson $serializer
     * @param array $data
     */
    public function __construct(
        ConfigProviderInterface $config,
        Context $context,
        SerializerJson $serializer,
        array $data = []
    ) {
        $this->serializer = $serializer;
        parent::__construct($context, $data);
        $this->config = $config;
    }

    /**
     * Get code
     *
     * @return string
     */
    public function getCode()
    {
        return ConfigProvider::CODE;
    }

    /**
     * Payment config
     *
     * @return string
     */
    public function getPaymentConfig()
    {
        $paymentConfig  = $this->config->getConfig()['payment'];
        $config         = $paymentConfig[$this->getCode()];
        $config['code'] = $this->getCode();

        return $this->serializer->serialize($config);
    }
}

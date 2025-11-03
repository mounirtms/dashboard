<?php
/**
 * Copyright © MageWorx. All rights reserved.
 * See LICENSE.txt for license details.
 */

namespace MageWorx\OrderEditor\Block\Adminhtml\Sales\Order\Edit;

use Magento\Backend\Block\Template;
use Magento\Framework\Serialize\Serializer\Json as SerializerJson;
use MageWorx\OrderEditor\Helper\Data as Helper;

class Wrapper extends Template
{
    /**
     * @var \Magento\Framework\AuthorizationInterface
     */
    protected $authorization;

    /**
     * @var SerializerJson
     */
    protected $serializer;

    /**
     * @var Helper
     */
    protected $helper;

    /**
     * @param Template\Context $context
     * @param \Magento\Framework\AuthorizationInterface $authorization
     * @param SerializerJson $serializer
     * @param array $data
     */
    public function __construct(
        Template\Context                          $context,
        \Magento\Framework\AuthorizationInterface $authorization,
        SerializerJson                            $serializer,
        Helper                                    $helper,
        array                                     $data = []
    ) {
        $this->authorization = $authorization;
        $this->serializer    = $serializer;
        $this->helper        = $helper;
        parent::__construct($context, $data);
    }

    /**
     * @return string
     */
    public function getJsonParamsItems()
    {
        $data = [
            'loadFormUrl'       => $this->getUrl('ordereditor/form/load'),
            'updateUrl'         => $this->getUrl('ordereditor/edit/items'),
            'discardChangesUrl' => $this->getUrl('ordereditor/edit/restoreQuote'),
            'isAllowed'         => $this->authorization->isAllowed('MageWorx_OrderEditor::edit_items')
        ];
        $this->addGeneralParams($data);

        return $this->serializer->serialize($data);
    }

    /**
     * @return string
     */
    public function getJsonParamsAddress()
    {
        $data = [
            'loadFormUrl' => $this->getUrl('ordereditor/form/load'),
            'updateUrl'   => $this->getUrl('ordereditor/edit/address'),
            'isAllowed'   => $this->authorization->isAllowed('MageWorx_OrderEditor::edit_address')
        ];
        $this->addGeneralParams($data);

        return $this->serializer->serialize($data);
    }

    /**
     * @return string
     */
    public function getJsonParamsShipping()
    {
        $data = [
            'loadFormUrl' => $this->getUrl('ordereditor/form/load'),
            'updateUrl'   => $this->getUrl('ordereditor/edit/shipping'),
            'isAllowed'   => $this->authorization->isAllowed('MageWorx_OrderEditor::edit_shipping')
        ];
        $this->addGeneralParams($data);

        return $this->serializer->serialize($data);
    }

    /**
     * @return string
     */
    public function getJsonParamsPayment()
    {
        $data = [
            'loadFormUrl' => $this->getUrl('ordereditor/form/load'),
            'updateUrl'   => $this->getUrl('ordereditor/edit/payment'),
            'isAllowed'   => $this->authorization->isAllowed('MageWorx_OrderEditor::edit_payment')
        ];
        $this->addGeneralParams($data);

        return $this->serializer->serialize($data);
    }

    /**
     * @return string
     */
    public function getJsonParamsAccount()
    {
        $data = [
            'loadFormUrl'   => $this->getUrl('ordereditor/form/load'),
            'updateUrl'     => $this->getUrl('ordereditor/edit/account'),
            'renderGridUrl' => $this->getUrl('ordereditor/edit_account_widget/chooser'),
            'isAllowed'     => $this->authorization->isAllowed('MageWorx_OrderEditor::edit_account')
        ];
        $this->addGeneralParams($data);

        return $this->serializer->serialize($data);
    }

    /**
     * @return string
     */
    public function getJsonParamsInfo()
    {
        $data = [
            'loadFormUrl' => $this->getUrl('ordereditor/form/load'),
            'updateUrl'   => $this->getUrl('ordereditor/edit/info'),
            'isAllowed'   => $this->authorization->isAllowed('MageWorx_OrderEditor::edit_info')
        ];
        $this->addGeneralParams($data);

        return $this->serializer->serialize($data);
    }

    /**
     * @return string
     */
    public function getJsonParamsComments()
    {
        $data = [
            'loadFormUrl' => $this->getUrl('ordereditor/form/load'),
            'updateUrl'   => $this->getUrl('ordereditor/edit/comments'),
            'isAllowed'   => $this->authorization->isAllowed('MageWorx_OrderEditor::edit_info')
        ];
        $this->addGeneralParams($data);

        return $this->serializer->serialize($data);
    }

    /**
     * @return bool
     */
    public function isEditAllowed(): bool
    {
        return $this->_authorization->isAllowed('MageWorx_OrderEditor::edit_order');
    }

    /**
     * Add params similar to all configurations (all blocks)
     *
     * @param array $data
     * @return void
     */
    private function addGeneralParams(array &$data): void
    {
        $data['sales_post_processor'] = $this->helper->getSalesProcessorCode();
    }
}

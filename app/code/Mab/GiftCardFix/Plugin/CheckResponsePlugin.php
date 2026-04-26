<?php

declare(strict_types=1);

namespace Mab\GiftCardFix\Plugin;

use Amasty\GiftCardAccount\Controller\Cart\Check;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\ResultFactory;

class CheckResponsePlugin
{
    /**
     * @var ResultFactory
     */
    private $resultFactory;

    /**
     * @var \Magento\Framework\Serialize\Serializer\Json
     */
    private $serializer;

    public function __construct(
        ResultFactory $resultFactory,
        \Magento\Framework\Serialize\Serializer\Json $serializer
    ) {
        $this->resultFactory = $resultFactory;
        $this->serializer = $serializer;
    }

    /**
     * After plugin to ensure response is never empty
     */
    public function afterExecute(Check $subject, $result)
    {
        // If result is not a Json result, wrap it
        if (!$result instanceof Json) {
            $resultJson = $this->resultFactory->create(ResultFactory::TYPE_JSON);
            return $resultJson->setData($result);
        }

        // Check if the response data is empty
        $responseData = $result->getData();
        if (empty($responseData) || $responseData === '' || $responseData === '""') {
            $errorResponse = $this->serializer->serialize([
                'error' => true,
                'message' => 'Code invalide ou carte expirée'
            ]);
            return $result->setData($errorResponse);
        }

        return $result;
    }
}

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
     * After plugin to fix check response format
     */
    public function afterExecute(
        Check $subject,
        $result
    ) {
        try {
            // If result is already Json, just return it
            if ($result instanceof Json) {
                return $result;
            }

            // Handle non-Json results
            if (is_array($result)) {
                $resultJson = $this->resultFactory->create(ResultFactory::TYPE_JSON);
                return $resultJson->setData($result);
            }

            // Handle string results (double-encoded JSON)
            if (is_string($result) && !empty($result)) {
                $resultJson = $this->resultFactory->create(ResultFactory::TYPE_JSON);
                // Try to decode to verify it's valid JSON
                $decoded = $this->serializer->unserialize($result);
                return $resultJson->setData($decoded);
            }

            // Empty result - return error
            $resultJson = $this->resultFactory->create(ResultFactory::TYPE_JSON);
            return $resultJson->setData([
                'error' => true,
                'message' => 'Code invalide ou carte expirée'
            ]);
        } catch (\Exception $e) {
            error_log("[Mab GiftCardFix] Exception: " . $e->getMessage());
            $resultJson = $this->resultFactory->create(ResultFactory::TYPE_JSON);
            return $resultJson->setData([
                'error' => true,
                'message' => $e->getMessage() ?: 'An error occurred'
            ]);
        }
    }
}

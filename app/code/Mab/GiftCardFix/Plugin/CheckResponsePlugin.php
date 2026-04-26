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
     * Around plugin to completely control the response
     */
    public function aroundExecute(
        Check $subject,
        \Closure $proceed
    ) {
        // Log that plugin is being called
        error_log("[Mab GiftCardFix] aroundExecute called");
        
        try {
            $result = $proceed();
            
            error_log("[Mab GiftCardFix] Result type: " . get_class($result));
            error_log("[Mab GiftCardFix] Result data: " . var_export($result->getData(), true));
            
            // Ensure result is Json
            if (!$result instanceof Json) {
                $resultJson = $this->resultFactory->create(ResultFactory::TYPE_JSON);
                $result = $resultJson->setData($result);
            }
            
            // Check for empty response
            $data = $result->getData();
            if (empty($data) || $data === '' || $data === '""') {
                error_log("[Mab GiftCardFix] Empty response detected, returning error");
                return $result->setData($this->serializer->serialize([
                    'error' => true,
                    'message' => 'Code invalide ou carte expirée'
                ]));
            }
            
            return $result;
        } catch (\Exception $e) {
            // Catch any exception and return proper JSON
            error_log("[Mab GiftCardFix] Exception caught: " . $e->getMessage());
            $resultJson = $this->resultFactory->create(ResultFactory::TYPE_JSON);
            return $resultJson->setData($this->serializer->serialize([
                'error' => true,
                'message' => $e->getMessage() ?: 'An error occurred'
            ]));
        }
    }
}

<?php

declare(strict_types=1);

namespace Mab\CheckoutCustomization\Controller\GiftCard;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;

class Apply extends Action implements CsrfAwareActionInterface
{
    /**
     * @var \Magento\Checkout\Model\Session
     */
    private $checkoutSession;

    /**
     * @var \Amasty\GiftCardAccount\Api\GiftCardAccountManagementInterface
     */
    private $accountManagement;

    /**
     * @var JsonFactory
     */
    private $resultJsonFactory;

    public function __construct(
        Context $context,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Amasty\GiftCardAccount\Api\GiftCardAccountManagementInterface $accountManagement,
        JsonFactory $resultJsonFactory
    ) {
        parent::__construct($context);
        $this->checkoutSession = $checkoutSession;
        $this->accountManagement = $accountManagement;
        $this->resultJsonFactory = $resultJsonFactory;
    }

    /**
     * @inheritDoc
     */
    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException
    {
        return null;
    }

    /**
     * @inheritDoc
     */
    public function validateForCsrf(RequestInterface $request): ?bool
    {
        return true;
    }

    /**
     * Apply gift card to cart - returns JSON response
     */
    public function execute()
    {
        $result = $this->resultJsonFactory->create();

        try {
            $requestBody = $this->getRequest()->getContent();
            $data = json_decode($requestBody, true);

            $code = '';
            if (is_array($data) && isset($data['giftcard_code'])) {
                $code = trim($data['giftcard_code']);
            } elseif ($this->getRequest()->getParam('giftcard_code')) {
                $code = trim($this->getRequest()->getParam('giftcard_code'));
            }

            if (empty($code)) {
                return $result->setData([
                    'success' => false,
                    'error' => true,
                    'message' => __('Gift card code is required')
                ]);
            }

            $quote = $this->checkoutSession->getQuote();
            $quoteId = (int)$quote->getId();

            if (!$quoteId) {
                return $result->setData([
                    'success' => false,
                    'error' => true,
                    'message' => __('Cart is empty or session expired')
                ]);
            }

            $this->accountManagement->applyGiftCardToCart($quoteId, $code);

            return $result->setData([
                'success' => true,
                'error' => false,
                'message' => __('Carte cadeau "%1" appliquée avec succès', $code),
                'code' => $code
            ]);

        } catch (\Exception $e) {
            $errorMessage = $e->getMessage();
            
            // Handle "already in quote" case as success
            if (stripos($errorMessage, 'already in the quote') !== false || 
                stripos($errorMessage, 'déjà') !== false) {
                return $result->setData([
                    'success' => true,
                    'error' => false,
                    'message' => __('La carte cadeau "%1" est déjà appliquée au panier', $code),
                    'code' => $code
                ]);
            }
            
            return $result->setData([
                'success' => false,
                'error' => true,
                'message' => $errorMessage
            ]);
        }
    }
}

<?php
namespace Mab\VisualEffects\Controller\Ajax;

use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Checkout\Model\Session as CheckoutSession;
use Mab\DeliveryOptions\Helper\ShippingConditions;
use Mab\Core\Helper\ErrorHandler;
use Psr\Log\LoggerInterface;

class ShippingProgress implements HttpGetActionInterface
{
    /**
     * @var JsonFactory
     */
    private $resultJsonFactory;

    /**
     * @var RequestInterface
     */
    private $request;

    /**
     * @var CheckoutSession
     */
    private $checkoutSession;

    /**
     * @var ShippingConditions
     */
    private $shippingConditionsHelper;

    /**
     * @var ErrorHandler
     */
    private $errorHandler;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @param JsonFactory $resultJsonFactory
     * @param RequestInterface $request
     * @param CheckoutSession $checkoutSession
     * @param ShippingConditions $shippingConditionsHelper
     * @param ErrorHandler $errorHandler
     * @param LoggerInterface $logger
     */
    public function __construct(
        JsonFactory $resultJsonFactory,
        RequestInterface $request,
        CheckoutSession $checkoutSession,
        ShippingConditions $shippingConditionsHelper,
        ErrorHandler $errorHandler,
        LoggerInterface $logger
    ) {
        $this->resultJsonFactory = $resultJsonFactory;
        $this->request = $request;
        $this->checkoutSession = $checkoutSession;
        $this->shippingConditionsHelper = $shippingConditionsHelper;
        $this->errorHandler = $errorHandler;
        $this->logger = $logger;
    }

    /**
     * Execute shipping progress check
     *
     * @return ResultInterface
     */
    public function execute(): ResultInterface
    {
        $result = $this->resultJsonFactory->create();

        try {
            $quote = $this->checkoutSession->getQuote();
            
            if (!$quote || !$quote->getId()) {
                return $result->setData([
                    'success' => false,
                    'message' => __('No active cart found')
                ]);
            }

            $cartTotal = $quote->getSubtotal();
            $cartItems = $quote->getAllItems();
            $destinationCountry = $quote->getShippingAddress()->getCountryId() ?: 'DZ';

            $conditions = $this->shippingConditionsHelper->checkFreeShippingConditions(
                $cartTotal,
                $cartItems,
                $destinationCountry
            );

            // Calculate progress percentage
            $freeShippingMinimum = $this->shippingConditionsHelper->getFreeShippingMinimum();
            $progressPercentage = 0;
            
            if ($freeShippingMinimum > 0) {
                $progressPercentage = min(100, ($cartTotal / $freeShippingMinimum) * 100);
            }

            // Prepare response data
            $responseData = [
                'success' => true,
                'eligible' => $conditions['eligible'],
                'progress_percentage' => round($progressPercentage, 1),
                'cart_total' => $cartTotal,
                'free_shipping_minimum' => $freeShippingMinimum,
                'amount_needed' => max(0, $freeShippingMinimum - $cartTotal),
                'visual_effects' => $conditions['visual_effects'] ?? [],
                'notifications' => $this->getProgressNotifications($progressPercentage),
                'reasons' => $conditions['reasons'] ?? []
            ];

            return $result->setData($responseData);

        } catch (\Exception $e) {
            $this->logger->error('[MAB Visual Effects] Shipping progress error: ' . $e->getMessage());
            
            return $result->setData([
                'success' => false,
                'message' => __('Unable to check shipping progress'),
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Get progress notifications
     *
     * @param float $progressPercentage
     * @return array
     */
    private function getProgressNotifications(float $progressPercentage): array
    {
        $notifications = [];
        $thresholds = [50, 75, 90];

        foreach ($thresholds as $threshold) {
            if ($progressPercentage >= $threshold && $progressPercentage < 100) {
                $notifications[] = [
                    'type' => 'info',
                    'message' => __('You\'re %1% towards free shipping!', round($progressPercentage)),
                    'threshold' => $threshold
                ];
                break; // Only show one notification at a time
            }
        }

        return $notifications;
    }
}
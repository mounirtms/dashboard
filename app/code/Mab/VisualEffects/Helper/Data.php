<?php
namespace Mab\VisualEffects\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Store\Model\ScopeInterface;
use Mab\Core\Helper\Data as CoreHelper;
use Mab\Core\Helper\ErrorHandler;
use Psr\Log\LoggerInterface;

class Data extends AbstractHelper
{
    const XML_PATH_ENABLED = 'mab_visual_effects/general/enabled';
    const XML_PATH_DEBUG_MODE = 'mab_visual_effects/general/debug_mode';
    const XML_PATH_PERFORMANCE_MODE = 'mab_visual_effects/general/performance_mode';
    
    const XML_PATH_FREE_SHIPPING_CELEBRATION = 'mab_visual_effects/shipping_effects/free_shipping_celebration';
    const XML_PATH_PROGRESS_BAR_ENABLED = 'mab_visual_effects/shipping_effects/progress_bar_enabled';
    const XML_PATH_PROGRESS_BAR_STYLE = 'mab_visual_effects/shipping_effects/progress_bar_style';
    const XML_PATH_THRESHOLD_NOTIFICATION = 'mab_visual_effects/shipping_effects/threshold_notification';
    const XML_PATH_NOTIFICATION_THRESHOLDS = 'mab_visual_effects/shipping_effects/notification_thresholds';
    
    const XML_PATH_ADD_TO_CART_EFFECT = 'mab_visual_effects/cart_effects/add_to_cart_effect';
    const XML_PATH_CART_UPDATE_EFFECT = 'mab_visual_effects/cart_effects/cart_update_effect';
    const XML_PATH_CART_MILESTONE_EFFECTS = 'mab_visual_effects/cart_effects/cart_milestone_effects';
    const XML_PATH_MILESTONE_AMOUNTS = 'mab_visual_effects/cart_effects/milestone_amounts';
    
    const XML_PATH_STEP_COMPLETION_EFFECT = 'mab_visual_effects/checkout_effects/step_completion_effect';
    const XML_PATH_ORDER_SUCCESS_EFFECT = 'mab_visual_effects/checkout_effects/order_success_effect';
    const XML_PATH_LOADING_ANIMATIONS = 'mab_visual_effects/checkout_effects/loading_animations';
    
    const XML_PATH_ANIMATION_DURATION = 'mab_visual_effects/advanced_settings/animation_duration';
    const XML_PATH_EFFECT_INTENSITY = 'mab_visual_effects/advanced_settings/effect_intensity';
    const XML_PATH_MOBILE_OPTIMIZED = 'mab_visual_effects/advanced_settings/mobile_optimized';
    const XML_PATH_CUSTOM_CSS = 'mab_visual_effects/advanced_settings/custom_css';

    /**
     * @var CoreHelper
     */
    private $coreHelper;

    /**
     * @var ErrorHandler
     */
    private $errorHandler;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @param Context $context
     * @param CoreHelper $coreHelper
     * @param ErrorHandler $errorHandler
     * @param LoggerInterface $logger
     */
    public function __construct(
        Context $context,
        CoreHelper $coreHelper,
        ErrorHandler $errorHandler,
        LoggerInterface $logger
    ) {
        parent::__construct($context);
        $this->coreHelper = $coreHelper;
        $this->errorHandler = $errorHandler;
        $this->logger = $logger;
    }

    /**
     * Check if visual effects module is enabled
     *
     * @param int|null $storeId
     * @return bool
     */
    public function isEnabled($storeId = null): bool
    {
        return $this->errorHandler->executeWithErrorHandling(
            function () use ($storeId) {
                return $this->scopeConfig->isSetFlag(
                    self::XML_PATH_ENABLED,
                    ScopeInterface::SCOPE_STORE,
                    $storeId
                ) && $this->coreHelper->isVisualEffectsEnabled($storeId);
            },
            false,
            'Checking if visual effects is enabled'
        );
    }

    /**
     * Check if debug mode is enabled
     *
     * @param int|null $storeId
     * @return bool
     */
    public function isDebugModeEnabled($storeId = null): bool
    {
        return $this->isEnabled($storeId) && $this->errorHandler->executeWithErrorHandling(
            function () use ($storeId) {
                return $this->scopeConfig->isSetFlag(
                    self::XML_PATH_DEBUG_MODE,
                    ScopeInterface::SCOPE_STORE,
                    $storeId
                );
            },
            false,
            'Checking debug mode status'
        );
    }

    /**
     * Check if performance mode is enabled
     *
     * @param int|null $storeId
     * @return bool
     */
    public function isPerformanceModeEnabled($storeId = null): bool
    {
        return $this->errorHandler->executeWithErrorHandling(
            function () use ($storeId) {
                return $this->scopeConfig->isSetFlag(
                    self::XML_PATH_PERFORMANCE_MODE,
                    ScopeInterface::SCOPE_STORE,
                    $storeId
                );
            },
            false,
            'Checking performance mode status'
        );
    }

    /**
     * Get free shipping celebration effect
     *
     * @param int|null $storeId
     * @return string
     */
    public function getFreeShippingCelebrationEffect($storeId = null): string
    {
        return $this->errorHandler->executeWithErrorHandling(
            function () use ($storeId) {
                return (string)$this->scopeConfig->getValue(
                    self::XML_PATH_FREE_SHIPPING_CELEBRATION,
                    ScopeInterface::SCOPE_STORE,
                    $storeId
                );
            },
            'confetti',
            'Getting free shipping celebration effect'
        );
    }

    /**
     * Check if progress bar is enabled
     *
     * @param int|null $storeId
     * @return bool
     */
    public function isProgressBarEnabled($storeId = null): bool
    {
        return $this->isEnabled($storeId) && $this->errorHandler->executeWithErrorHandling(
            function () use ($storeId) {
                return $this->scopeConfig->isSetFlag(
                    self::XML_PATH_PROGRESS_BAR_ENABLED,
                    ScopeInterface::SCOPE_STORE,
                    $storeId
                );
            },
            false,
            'Checking progress bar status'
        );
    }

    /**
     * Get progress bar style
     *
     * @param int|null $storeId
     * @return string
     */
    public function getProgressBarStyle($storeId = null): string
    {
        return $this->errorHandler->executeWithErrorHandling(
            function () use ($storeId) {
                return (string)$this->scopeConfig->getValue(
                    self::XML_PATH_PROGRESS_BAR_STYLE,
                    ScopeInterface::SCOPE_STORE,
                    $storeId
                );
            },
            'modern',
            'Getting progress bar style'
        );
    }

    /**
     * Check if threshold notifications are enabled
     *
     * @param int|null $storeId
     * @return bool
     */
    public function isThresholdNotificationEnabled($storeId = null): bool
    {
        return $this->isEnabled($storeId) && $this->errorHandler->executeWithErrorHandling(
            function () use ($storeId) {
                return $this->scopeConfig->isSetFlag(
                    self::XML_PATH_THRESHOLD_NOTIFICATION,
                    ScopeInterface::SCOPE_STORE,
                    $storeId
                );
            },
            false,
            'Checking threshold notification status'
        );
    }

    /**
     * Get notification thresholds
     *
     * @param int|null $storeId
     * @return array
     */
    public function getNotificationThresholds($storeId = null): array
    {
        return $this->errorHandler->executeWithErrorHandling(
            function () use ($storeId) {
                $thresholds = $this->scopeConfig->getValue(
                    self::XML_PATH_NOTIFICATION_THRESHOLDS,
                    ScopeInterface::SCOPE_STORE,
                    $storeId
                );
                
                if (empty($thresholds)) {
                    return [50, 75, 90];
                }
                
                $thresholdArray = array_map('trim', explode(',', $thresholds));
                $thresholdArray = array_filter($thresholdArray, function($value) {
                    return is_numeric($value) && $value >= 0 && $value <= 100;
                });
                
                return array_map('intval', $thresholdArray);
            },
            [50, 75, 90],
            'Getting notification thresholds'
        );
    }

    /**
     * Get add to cart effect
     *
     * @param int|null $storeId
     * @return string
     */
    public function getAddToCartEffect($storeId = null): string
    {
        return $this->errorHandler->executeWithErrorHandling(
            function () use ($storeId) {
                return (string)$this->scopeConfig->getValue(
                    self::XML_PATH_ADD_TO_CART_EFFECT,
                    ScopeInterface::SCOPE_STORE,
                    $storeId
                );
            },
            'bounce',
            'Getting add to cart effect'
        );
    }

    /**
     * Get cart update effect
     *
     * @param int|null $storeId
     * @return string
     */
    public function getCartUpdateEffect($storeId = null): string
    {
        return $this->errorHandler->executeWithErrorHandling(
            function () use ($storeId) {
                return (string)$this->scopeConfig->getValue(
                    self::XML_PATH_CART_UPDATE_EFFECT,
                    ScopeInterface::SCOPE_STORE,
                    $storeId
                );
            },
            'pulse',
            'Getting cart update effect'
        );
    }

    /**
     * Check if cart milestone effects are enabled
     *
     * @param int|null $storeId
     * @return bool
     */
    public function isCartMilestoneEffectsEnabled($storeId = null): bool
    {
        return $this->isEnabled($storeId) && $this->errorHandler->executeWithErrorHandling(
            function () use ($storeId) {
                return $this->scopeConfig->isSetFlag(
                    self::XML_PATH_CART_MILESTONE_EFFECTS,
                    ScopeInterface::SCOPE_STORE,
                    $storeId
                );
            },
            false,
            'Checking cart milestone effects status'
        );
    }

    /**
     * Get milestone amounts configuration
     *
     * @param int|null $storeId
     * @return array
     */
    public function getMilestoneAmounts($storeId = null): array
    {
        return $this->errorHandler->executeWithErrorHandling(
            function () use ($storeId) {
                $milestones = $this->scopeConfig->getValue(
                    self::XML_PATH_MILESTONE_AMOUNTS,
                    ScopeInterface::SCOPE_STORE,
                    $storeId
                );
                
                if (empty($milestones)) {
                    return [
                        ['amount' => 100, 'effect' => 'sparkles'],
                        ['amount' => 500, 'effect' => 'confetti'],
                        ['amount' => 1000, 'effect' => 'fireworks']
                    ];
                }
                
                return $this->errorHandler->safeJsonDecode($milestones, true, [], 'milestone amounts');
            },
            [
                ['amount' => 100, 'effect' => 'sparkles'],
                ['amount' => 500, 'effect' => 'confetti'],
                ['amount' => 1000, 'effect' => 'fireworks']
            ],
            'Getting milestone amounts'
        );
    }

    /**
     * Get step completion effect
     *
     * @param int|null $storeId
     * @return string
     */
    public function getStepCompletionEffect($storeId = null): string
    {
        return $this->errorHandler->executeWithErrorHandling(
            function () use ($storeId) {
                return (string)$this->scopeConfig->getValue(
                    self::XML_PATH_STEP_COMPLETION_EFFECT,
                    ScopeInterface::SCOPE_STORE,
                    $storeId
                );
            },
            'glow',
            'Getting step completion effect'
        );
    }

    /**
     * Get order success effect
     *
     * @param int|null $storeId
     * @return string
     */
    public function getOrderSuccessEffect($storeId = null): string
    {
        return $this->errorHandler->executeWithErrorHandling(
            function () use ($storeId) {
                return (string)$this->scopeConfig->getValue(
                    self::XML_PATH_ORDER_SUCCESS_EFFECT,
                    ScopeInterface::SCOPE_STORE,
                    $storeId
                );
            },
            'celebration',
            'Getting order success effect'
        );
    }

    /**
     * Check if loading animations are enabled
     *
     * @param int|null $storeId
     * @return bool
     */
    public function isLoadingAnimationsEnabled($storeId = null): bool
    {
        return $this->isEnabled($storeId) && $this->errorHandler->executeWithErrorHandling(
            function () use ($storeId) {
                return $this->scopeConfig->isSetFlag(
                    self::XML_PATH_LOADING_ANIMATIONS,
                    ScopeInterface::SCOPE_STORE,
                    $storeId
                );
            },
            false,
            'Checking loading animations status'
        );
    }

    /**
     * Get animation duration
     *
     * @param int|null $storeId
     * @return int
     */
    public function getAnimationDuration($storeId = null): int
    {
        return $this->errorHandler->executeWithErrorHandling(
            function () use ($storeId) {
                $duration = $this->scopeConfig->getValue(
                    self::XML_PATH_ANIMATION_DURATION,
                    ScopeInterface::SCOPE_STORE,
                    $storeId
                );
                return max(100, (int)$duration);
            },
            1000,
            'Getting animation duration'
        );
    }

    /**
     * Get effect intensity
     *
     * @param int|null $storeId
     * @return string
     */
    public function getEffectIntensity($storeId = null): string
    {
        return $this->errorHandler->executeWithErrorHandling(
            function () use ($storeId) {
                return (string)$this->scopeConfig->getValue(
                    self::XML_PATH_EFFECT_INTENSITY,
                    ScopeInterface::SCOPE_STORE,
                    $storeId
                );
            },
            'moderate',
            'Getting effect intensity'
        );
    }

    /**
     * Check if mobile optimized effects are enabled
     *
     * @param int|null $storeId
     * @return bool
     */
    public function isMobileOptimized($storeId = null): bool
    {
        return $this->errorHandler->executeWithErrorHandling(
            function () use ($storeId) {
                return $this->scopeConfig->isSetFlag(
                    self::XML_PATH_MOBILE_OPTIMIZED,
                    ScopeInterface::SCOPE_STORE,
                    $storeId
                );
            },
            true,
            'Checking mobile optimization status'
        );
    }

    /**
     * Get custom CSS
     *
     * @param int|null $storeId
     * @return string
     */
    public function getCustomCSS($storeId = null): string
    {
        return $this->errorHandler->executeWithErrorHandling(
            function () use ($storeId) {
                return (string)$this->scopeConfig->getValue(
                    self::XML_PATH_CUSTOM_CSS,
                    ScopeInterface::SCOPE_STORE,
                    $storeId
                );
            },
            '',
            'Getting custom CSS'
        );
    }

    /**
     * Get effect configuration for JavaScript
     *
     * @param int|null $storeId
     * @return array
     */
    public function getEffectConfiguration($storeId = null): array
    {
        if (!$this->isEnabled($storeId)) {
            return ['enabled' => false];
        }

        return [
            'enabled' => true,
            'debug' => $this->isDebugModeEnabled($storeId),
            'performance_mode' => $this->isPerformanceModeEnabled($storeId),
            'mobile_optimized' => $this->isMobileOptimized($storeId),
            'animation_duration' => $this->getAnimationDuration($storeId),
            'effect_intensity' => $this->getEffectIntensity($storeId),
            'shipping_effects' => [
                'free_shipping_celebration' => $this->getFreeShippingCelebrationEffect($storeId),
                'progress_bar_enabled' => $this->isProgressBarEnabled($storeId),
                'progress_bar_style' => $this->getProgressBarStyle($storeId),
                'threshold_notification' => $this->isThresholdNotificationEnabled($storeId),
                'notification_thresholds' => $this->getNotificationThresholds($storeId)
            ],
            'cart_effects' => [
                'add_to_cart' => $this->getAddToCartEffect($storeId),
                'cart_update' => $this->getCartUpdateEffect($storeId),
                'milestone_effects' => $this->isCartMilestoneEffectsEnabled($storeId),
                'milestones' => $this->getMilestoneAmounts($storeId)
            ],
            'checkout_effects' => [
                'step_completion' => $this->getStepCompletionEffect($storeId),
                'order_success' => $this->getOrderSuccessEffect($storeId),
                'loading_animations' => $this->isLoadingAnimationsEnabled($storeId)
            ]
        ];
    }

    /**
     * Log debug message
     *
     * @param string $message
     * @param array $context
     * @return void
     */
    public function debugLog(string $message, array $context = []): void
    {
        if ($this->isDebugModeEnabled()) {
            $this->logger->debug('[MAB Visual Effects] ' . $message, $context);
        }
    }
}
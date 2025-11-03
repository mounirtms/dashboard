<?php
namespace Mab\VisualEffects\Block;

use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Mab\VisualEffects\Helper\Data as VisualEffectsHelper;
use Mab\Core\Helper\ErrorHandler;

class VisualEffects extends Template
{
    /**
     * @var VisualEffectsHelper
     */
    private $visualEffectsHelper;

    /**
     * @var ErrorHandler
     */
    private $errorHandler;

    /**
     * @param Context $context
     * @param VisualEffectsHelper $visualEffectsHelper
     * @param ErrorHandler $errorHandler
     * @param array $data
     */
    public function __construct(
        Context $context,
        VisualEffectsHelper $visualEffectsHelper,
        ErrorHandler $errorHandler,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->visualEffectsHelper = $visualEffectsHelper;
        $this->errorHandler = $errorHandler;
    }

    /**
     * Check if visual effects are enabled
     *
     * @return bool
     */
    public function isEnabled(): bool
    {
        return $this->visualEffectsHelper->isEnabled();
    }

    /**
     * Get visual effects configuration for JavaScript
     *
     * @return string JSON encoded configuration
     */
    public function getEffectConfigJson(): string
    {
        return $this->errorHandler->executeWithErrorHandling(
            function () {
                $config = $this->visualEffectsHelper->getEffectConfiguration();
                return $this->errorHandler->safeJsonEncode($config, '{}', 'visual effects configuration');
            },
            '{}',
            'Getting visual effects configuration JSON'
        );
    }

    /**
     * Get custom CSS
     *
     * @return string
     */
    public function getCustomCSS(): string
    {
        return $this->visualEffectsHelper->getCustomCSS();
    }

    /**
     * Check if performance mode is enabled
     *
     * @return bool
     */
    public function isPerformanceModeEnabled(): bool
    {
        return $this->visualEffectsHelper->isPerformanceModeEnabled();
    }

    /**
     * Get helper instance for template access
     *
     * @return VisualEffectsHelper
     */
    public function getHelper(): VisualEffectsHelper
    {
        return $this->visualEffectsHelper;
    }

    /**
     * Get cache key info
     *
     * @return array
     */
    public function getCacheKeyInfo()
    {
        return [
            'MAB_VISUAL_EFFECTS',
            $this->_storeManager->getStore()->getId(),
            $this->_design->getDesignTheme()->getId(),
            $this->visualEffectsHelper->isEnabled() ? '1' : '0'
        ];
    }
}
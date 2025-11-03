<?php
namespace Mab\Core\Block;

use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;

class AbstractBlock extends Template
{
    /**
     * @var int Cache lifetime in seconds (24 hours)
     */
    protected $cacheLifetime = 86400;

    /**
     * Constructor
     *
     * @param Context $context
     * @param array $data
     */
    public function __construct(
        Context $context,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->initializeCache();
    }

    /**
     * Initialize cache settings
     *
     * @return void
     */
    protected function initializeCache()
    {
        $this->addData([
            'cache_lifetime' => $this->cacheLifetime,
            'cache_tags' => $this->getCacheTags()
        ]);
    }

    /**
     * Get cache tags
     *
     * @return array
     */
    protected function getCacheTags()
    {
        return ['MAB_BLOCK', 'MAB_' . strtoupper($this->getNameInLayout())];
    }

    /**
     * Get cache key info
     *
     * @return array
     */
    public function getCacheKeyInfo()
    {
        return [
            'MAB_BLOCK',
            $this->getNameInLayout(),
            $this->getTemplate(),
            $this->_storeManager->getStore()->getId(),
            $this->_design->getDesignTheme()->getId(),
            $this->httpContext->getValue(\Magento\Customer\Model\Context::CONTEXT_GROUP),
            $this->serialize($this->getRequest()->getParams())
        ];
    }

    /**
     * Serialize data
     *
     * @param mixed $data
     * @return string
     */
    public function serialize($keys = [], $valueSeparator = '=', $fieldSeparator = ' ', $quote = '"')
    {
        if (is_array($keys)) {
            return json_encode($keys);
        }
        return parent::serialize($keys, $valueSeparator, $fieldSeparator, $quote);
    }
}

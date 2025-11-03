<?php
namespace Mab\Core\Plugin\View\Element;

use Magento\Framework\View\Element\Template;

class TemplateCaching
{
    /**
     * Add cache tags and lifetime to MAB blocks
     *
     * @param Template $subject
     * @param array $result
     * @return array
     */
    public function afterGetCacheKeyInfo(Template $subject, array $result)
    {
        if (strpos(get_class($subject), 'Mab\\') === 0) {
            $result[] = 'MAB_BLOCK';
            $result[] = $subject->getNameInLayout();
        }
        return $result;
    }

    /**
     * Set default cache lifetime for MAB blocks
     *
     * @param Template $subject
     * @param int|bool|null $result
     * @return int|bool|null
     */
    public function afterGetCacheLifetime(Template $subject, $result)
    {
        if (strpos(get_class($subject), 'Mab\\') === 0 && $result === null) {
            return 86400; // 24 hours default cache lifetime for MAB blocks
        }
        return $result;
    }
}

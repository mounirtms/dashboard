<?php
namespace Mab\SourceSelector\Block;

use Magento\Framework\View\Element\Template;
use Mab\License\Helper\Data as LicenseHelper;

class Selector extends Template
{
    protected $licenseHelper;

    public function __construct(
        Template\Context $context,
        LicenseHelper $licenseHelper,
        array $data = []
    ) {
        $this->licenseHelper = $licenseHelper;
        parent::__construct($context, $data);
    }

    protected function _toHtml()
    {
        if (!$this->licenseHelper->isLicenseActive()) {
            return '';
        }
        return parent::_toHtml();
    }
}

<?php
namespace Mab\SocialLogin\Block;

use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Mab\SocialLogin\Helper\Data as HelperData;

class Login extends Template
{
    protected $helperData;

    public function __construct(
        Context $context,
        HelperData $helperData,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->helperData = $helperData;
    }

    public function getFirebaseConfig()
    {
        return $this->helperData->getFirebaseConfig();
    }

    public function isGoogleEnabled()
    {
        return $this->helperData->isGoogleEnabled();
    }

    public function isFacebookEnabled()
    {
        return $this->helperData->isFacebookEnabled();
    }
}

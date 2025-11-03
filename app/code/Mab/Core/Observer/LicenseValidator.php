<?php
namespace Mab\Core\Observer;

use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Event\Observer;
use Mab\Core\Model\License\Validator;
use Magento\Framework\Message\ManagerInterface;

class LicenseValidator implements ObserverInterface
{
    protected $validator;
    protected $messageManager;

    public function __construct(
        Validator $validator,
        ManagerInterface $messageManager
    ) {
        $this->validator = $validator;
        $this->messageManager = $messageManager;
    }

    public function execute(Observer $observer)
    {
        $isValid = $this->validator->validateLicense('Mab_Core');

        if (!$isValid) {
            $this->messageManager->addError('MAB Solutions license is invalid. Please check your license key.');
        }
    }
}

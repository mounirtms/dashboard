<?php
namespace Mab\Core\Block\Adminhtml\Menu;

use Magento\Backend\Block\Template;

class Logo extends Template
{
    /**
     * @return string|null
     */
    public function getLogoUrl()
    {
        $viewModel = $this->getData('logo_view_model');
        if ($viewModel) {
            return $viewModel->getLogoUrl();
        }
        return null;
    }

    /**
     * @return bool
     */
    public function isLogoEnabled()
    {
        $viewModel = $this->getData('logo_view_model');
        if ($viewModel) {
            return $viewModel->isLogoEnabled();
        }
        return false;
    }
}

<?php
/**
 * MAB Checkout Customization - Module Registration
 * 
 * @category    Mab
 * @package     Mab_CheckoutCustomization
 * @author      Mounir Abderrahmani <mounir.webdev@gmail.com>
 * @copyright   Copyright (c) 2025 MAB Extensions
 * @license     https://opensource.org/licenses/MIT MIT License
 */

use Magento\Framework\Component\ComponentRegistrar;

ComponentRegistrar::register(
    ComponentRegistrar::MODULE,
    'Mab_CheckoutCustomization',
    __DIR__
);

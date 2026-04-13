# Mab_CheckoutCustomizations Module

## Description

The Mab_CheckoutCustomizations module provides a set of customizations for the checkout process on your Magento store.

## Installation

To install the Mab_CheckoutCustomizations module, follow these steps:

1. Download the module from the Magento Marketplace or GitHub.
2. Extract the module files to the `app/code/Mab/CheckoutCustomizations` directory.
3. Run the following command to enable the module: `php bin/magento module:enable Mab_CheckoutCustomizations`
4. Run the following command to update the database schema: `php bin/magento setup:upgrade`

## Configuration

To configure the Mab_CheckoutCustomizations module, follow these steps:

1. Go to the Magento admin panel and navigate to `Stores > Configuration > Mab Extensions > Checkout Customizations`.
2. Set the `Enabled` option to `Yes` to enable the module.
3. Configure the checkout customizations settings as desired.

## Checkout Customizations Options

The following checkout customizations options are available:

* `Checkout Layout`: Select the layout for the checkout process.
* `Checkout Steps`: Set the number of steps for the checkout process.
* `Checkout Button Text`: Set the text for the checkout button.

## Implementation

The Mab_CheckoutCustomizations module uses the following implementation:

* The `Mab_CheckoutCustomizations` class is responsible for rendering the checkout customizations on the frontend.
* The `Mab_CheckoutCustomizations_Helper_Data` class provides helper methods for the module.

## Admin Menu

The Mab_CheckoutCustomizations module is added to the admin menu under `Stores > Configuration > Mab Extensions > Checkout Customizations`.

## Configuration Fields

The following configuration fields are available:

* `enabled`: Enables or disables the module.
* `checkout_layout`: Sets the layout for the checkout process.
* `checkout_steps`: Sets the number of steps for the checkout process.
* `checkout_button_text`: Sets the text for the checkout button.

## Known Issues

* None

## Change Log

* 1.0.0: Initial release

## Licensing

The Mab_CheckoutCustomizations module is licensed under the Open Software License (OSL) version 3.0.
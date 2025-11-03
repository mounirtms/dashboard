/**
 * Copyright © 2017 MageWorx. All rights reserved.
 * See LICENSE.txt for license details.
 */
define([
    'Magento_Ui/js/grid/columns/column',
    'mage/translate'
], function (Column, $t) {
    'use strict';

    return Column.extend({
        defaults: {
            bodyTmpl: 'MageWorx_OrdersGrid/grid/cells/icon',
            fieldClass: {
                'data-grid-mw-icon-cell': true
            }
        },

        getSrc: function (row) {
            return row['icon_image']
        },

        getAlt: function () {
            return $t('Icon');
        },

        isIconExist: function (row) {
            return Boolean(row['icon_image']);
        }
    });
});

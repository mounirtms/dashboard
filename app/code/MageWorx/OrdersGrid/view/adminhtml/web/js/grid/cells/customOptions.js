/**
 * Copyright © 2017 MageWorx. All rights reserved.
 * See LICENSE.txt for license details.
 */
define([
    'Magento_Ui/js/grid/columns/column',
    'underscore'
], function (Column, _) {
    'use strict';

    return Column.extend({
        defaults: {
            bodyTmpl: 'MageWorx_OrdersGrid/grid/cells/customOptions',
            itemTemplate: 'MageWorx_OrdersGrid/grid/cells/custom-options/general',
            noOptionsTemplate: 'MageWorx_OrdersGrid/grid/cells/custom-options/empty'
        },

        isEmpty: function (data) {
            return _.isEmpty(data);
        },

        getSrc: function (row) {
            if (!row || !row[this.index]) {
                return [];
            }

            var jsonData = JSON.parse(row[this.index]),
                optionsData = [];

            for (var index in jsonData) {
                if (typeof jsonData[index] !== 'undefined') {
                    var preparedOptions = _.isArray(jsonData[index]) ? jsonData[index] : _.toArray(jsonData[index]);
                    optionsData.push({
                        'item_id': index,
                        'options': preparedOptions
                    });
                }
            }

            return optionsData;
        }
    });
});

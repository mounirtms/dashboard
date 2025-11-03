/**
 * Copyright © MageWorx. All rights reserved.
 * See LICENSE.txt for license details.
 */
define(
    [
        'jquery',
        'mage/translate',
        'MageWorx_OrderEditor/js/order/edit/form/base',
        'MageWorx_OrderEditor/js/order/edit/form/items/form',
        'jquery/ui'
    ],
    function ($, $t, base, form) {
        'use strict';

        $.widget('mage.mageworxOrderEditorItems', $.mage.mageworxOrderEditorBase, {
            params: {
                updateUrl: '',
                loadFormUrl: '',
                discardChangesUrl: '',

                cancelButtonId: '#order-items-cancel',
                submitButtonId: '#order-items-submit',
                discardChangesButtonId: '#order-items-discard-changes',
                editLinkId: '#ordereditor-order-items-link',

                blockId: 'order_items',
                formId: '#order_items_edit_form',
                linkContainerId: '.admin__page-section-title',
                blockContainerId: '.admin__table-wrapper',
                formContainerId: '.admin__table-wrapper'
            },

            init: function (params) {
                this.params = this._mergeParams(this.params, params);
                this._initParams(params);
                if (params.isAllowed) {
                    this._initActions();
                }
            },

            _initActions: function() {
                this._super();
                this._initDiscardChanges();
            },


            _initDiscardChanges: function () {
                var self = this;
                $(document).off('click touchstart', this.params.discardChangesButtonId);
                $(document).on('click touchstart', this.params.discardChangesButtonId, (function (e) {
                    e.preventDefault();
                    self.discardChanges();
                }));
            },

            _initEditLink: function () {
                var linkTemplate = this.editLinkTemplate;
                var editLink = linkTemplate.replace('%block_id%', this.params.editLinkId.substring(1));
                $(editLink).appendTo($(this.params.formContainerId).parent().children(this.params.linkContainerId));
            },

            getLoadFormParams: function () {
                var orderId = this.getCurrentOrderId();
                var blockId = this.params.blockId;
                return {'form_key':FORM_KEY, 'order_id':orderId, 'block_id':blockId};
            },

            getConfirmUpdateData: function () {
                var data = this.getLoadFormParams();
                var formData = this.getFormData(this.params.formId);
                return this._mergeParams(data, formData);
            },

            onClickAction: function (actionClass, action) {
                var self = this;

                $(document).off('click touchstart', actionClass);
                $(document).on('click touchstart', actionClass, (function (e) {
                    e.preventDefault();
                    eval("self." + action);
                }));
            },

            getOrderIdFromUrl: function () {
                var url = location.pathname;
                var VRegExp = new RegExp(/order_id\/([0-9]+)/);
                var VResult = url.match(VRegExp);
                return VResult[1];
            },

            validateForm: function () {
                var removedItems = $('.ordered_item_remove input.remove_ordered_item, .remove_quote_item').length;
                if (removedItems != 0 && removedItems == $('.ordered_item_remove input.remove_ordered_item:checked').length) {
                    alert($t("Sorry, but you can not delete all items in order. Maybe, better remove this order?"));
                    return false;
                }

                if (window.qtyWarning > 0) {
                    window.qtyWarning = 0;
                    alert($t("Warning! Requested quantity for some items are not available. Please, recheck or update changes."));
                    return false;
                }

                var validator = $(this.params.formId).validate();
                return validator.form();
            },

            discardChanges: function () {
                var self = this;

                $.ajax({
                    url: self.params.discardChangesUrl,
                    data: {'form_key':FORM_KEY, 'order_id': self.getCurrentOrderId()},
                    type: 'post',
                    dataType: 'json',
                    context: this,
                    beforeSend: function () {
                        self.showPreLoader();
                    }
                }).done(function (response) {
                    if (response.error || response.status == false) {
                        this.throwError(
                            {
                                "error": true,
                                "message": $.mage.__('Something went wrong. Please, reload the page and try again.')
                            }
                        );
                        this.hidePreLoader();
                    } else {
                        $('body').notification('clear')
                            .notification('add', {
                                error: false,
                                message: $.mage.__('All ok.'),

                                /**
                                 * @param {*} message
                                 */
                                insertMethod: function (message) {
                                    $('.page-main-actions:first').html(message);
                                }
                            });
                        this.hidePreLoader();
                        this.editAction({'data': {'context': self}});
                    }
                }).fail(function (error) {
                    this.throwError(
                        {
                            "error": true,
                            "message": error.message
                        }
                    );
                    this.hidePreLoader();
                });
            }
        });

        return $.mage.mageworxOrderEditorItems;
    }
);

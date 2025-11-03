define([
    'jquery',
    'mage/translate',
    'Magento_Ui/js/modal/alert',
    'underscore',
    'Magento_Ui/js/modal/modal',
    'jquery/ui',
    'prototype'
], function (jQuery, $t, alert, _) {
    window.PdfCustomizerVariables = {
        textareaElementId: null,
        variablesContent: null,
        dialogWindow: null,
        dialogWindowId: 'variables-chooser',
        overlayShowEffectOptions: null,
        overlayHideEffectOptions: null,
        insertFunction: 'PdfCustomizerVariables.insertVariable',
        init: function (textareaElementId, insertFunction) {
            if ($(textareaElementId)) {
                this.textareaElementId = textareaElementId;
            }
            if (insertFunction) {
                this.insertFunction = insertFunction;
            }
        },

        resetData: function () {
            this.variablesContent = null;
            this.dialogWindow = null;
        },

        openVariableChooser: function (variables) {
            if (this.variablesContent == null && variables) {
                this.variablesContent = '<ul class="insert-variable">';
                variables.each(function (variableGroup) {
                    if (variableGroup.label && variableGroup.value) {
                        this.variablesContent += '<li><b>' + variableGroup.label + '</b></li>';
                        (variableGroup.value).each(function (variable) {
                            if (variable.value && variable.label) {
                                this.variablesContent += '<li>' +
                                    this.prepareVariableRow(variable.value, variable.label) + '</li>';
                            }
                        }.bind(this));
                    }
                }.bind(this));
                this.variablesContent += '</ul>';
            }
            if (this.variablesContent) {
                this.openDialogWindow(this.variablesContent);
            }
            this.resetData();
        },
        openDialogWindow: function (variablesContent) {
            var windowId = this.dialogWindowId;
            var emptyNote = $t('<strong>Empty fields/nodes:</strong> If certain fields/nodes are empty, that is because the field you are looking for is empty in the database. If the "customer" variables are empty for example, this could be because you are looking at an order placed by a guest, so there is no customer.<br/><br/>');
            var barcodeNote = $t('<strong>Barcodes:</strong> To output a barcode, simply prefix the variable with "barcode_TYPE_" (see wiki for <a href="https://support.xtento.com/wiki/Magento_2_Extensions:PDF_Customizer#Barcodes" target="_blank">types</a>). Example: {{var <strong>barcode_qr_</strong>order.entity_id}}<br/><br/>');
            var ifNote = $t('<strong>Depends:</strong> To use one of the below variables with {{depends}} (see <a href="https://support.xtento.com/wiki/Magento_2_Extensions:PDF_Customizer#.7B.7Bdepend.7D.7D.2C_.7B.7Bif.7D.7D.2C_.7B.7Belse.7D.7D" target="_blank">wiki</a>), simply add <strong>_if</strong> after the type. Example: <strong>order</strong>.custom_field becomes <strong>order_if</strong>.custom_field <br/><br/>');
            jQuery('<div id="' + windowId + '">' + emptyNote + barcodeNote + ifNote + PdfCustomizerVariables.variablesContent + '</div>').modal({
                title: $t('List of variables (click to insert)'),
                type: 'slide',
                buttons: [],
                closed: function (e, modal) {
                    modal.modal.remove();
                }
            });

            jQuery('#' + windowId).modal('openModal');

            variablesContent.evalScripts.bind(variablesContent).defer();
        },
        closeDialogWindow: function () {
            jQuery('#' + this.dialogWindowId).modal('closeModal');
        },
        prepareVariableRow: function (varValue, varLabel) {
            var value = (varValue).replace(/"/g, '&quot;').replace(/'/g, '\\&#39;');
            var content = '<a href="#" onclick="' + this.insertFunction + '(\'' + value + '\');return false;">' + varLabel + '</a>';
            return content;
        },
        insertVariable: function (value) {
            var windowId = this.dialogWindowId;
            jQuery('#' + windowId).modal('closeModal');
            var doc = window.codeMirrorTemplate.getDoc();
            var cursor = doc.getCursor();
            doc.replaceRange(value, cursor);
        }
    };

    window.PdfCustomizerVariablePlugin = {
        editor: null,
        variables: null,
        textareaId: null,
        setEditor: function (editor) {
            this.editor = editor;
        },
        testPdf: function (url) {
            url = url.replace(/\/+$/, '') + '/entity_id/' + jQuery('#template_source').val();
            window.open(url);
        },
        loadChooser: function (url, textareaId) {
            var fieldVal = jQuery('input[name=source]').val();
            if (fieldVal == 0 || !fieldVal) {
                alert({
                    content: jQuery.mage.__(
                        'Please enter a valid object ID for testing. For example 000000001 for sales (orders, invoices, shipments, credit memos) and 1 or more for products.'
                    )
                });
                return;
            }

            this.textareaId = textareaId;

            new Ajax.Request(url, {
                parameters: {
                    'variables_entity_id': fieldVal,
                    'type_id': jQuery('input[name=type_id]').val()
                },
                onComplete: function (transport) {
                    if (transport.responseText.isJSON()) {
                        PdfCustomizerVariables.init(null, 'PdfCustomizerVariablePlugin.insertVariable');
                        this.variables = transport.responseText.evalJSON();
                        this.openChooser(this.variables);
                    } else {
                        alert({
                            content: jQuery.mage.__(
                                'Please enter a valid object ID for testing. For example 000000001 for sales (orders, invoices, shipments, credit memos) and 1 or more for products. If you tried to access "Customer" variables, make sure the customer in this order/invoice really exists in Magento, and that this is not a guest order!'
                            )
                        });
                    }
                }.bind(this)
            });
        },
        openChooser: function (variables) {
            PdfCustomizerVariables.openVariableChooser(variables);
        },
        insertVariable: function (value) {
            /*var prefix = this.textareaId.replace('template_', ''),
                editorIdPrefix = prefix;
                PdfCustomizerVariables.init(editorIdPrefix + '_template_' + editorIdPrefix);*/
            PdfCustomizerVariables.insertVariable(value);
        }
    };

    jQuery(function () {
        var field = jQuery('input[name=source]'),
            url = jQuery('input[name=ajax_search]').val();

        field.on('focus', _.debounce(function () {
                var examples = jQuery('.source-examples');
                if (examples.length > 0) {
                    return;
                }
                new Ajax.Request(url, {
                    parameters: {
                        'variables_entity_id': jQuery(this).val()
                    },
                    onComplete: function (transport) {
                        var json = transport.responseText, message;
                        if (json.isJSON()) {
                            jQuery('.source-examples').remove();
                            message = jQuery.mage.__(
                                'Use one of the following example IDs: '
                            );
                            field.parent().append('<div class="source-examples">' + message + json.evalJSON()[0].join(', ') + '</div>');
                        } else {
                            alert({
                                content: jQuery.mage.__(
                                    'You need to have at least one sales entity (invoice, order and so on)')
                            });
                        }
                    }
                });
            }, 500)
        )
    });

});
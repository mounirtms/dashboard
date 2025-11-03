define([
    'jquery',
    'Magento_Ui/js/modal/modal',
    'jquery/ui',
    'prototype'
], function ($) {
    window.previewPopupModal = {
        open: function () {
            $.ajaxSetup({
                showLoader: true
            });
            $.getJSON(window.loadDefaultTemplateUrl, {
                'template_type': $('#template_template_type').val()
            }).done(function (response) {
                $('#default_template_id')
                    .find('option')
                    .remove()
                    .end();
                window.defaultPdfTemplates = response.templates;
                $.each(window.defaultPdfTemplates, function (i, template) {
                    $('#default_template_id').append($('<option>', {
                        value: i,
                        text: template.template_name
                    })).trigger('change');
                });
            }).fail(function (response) {
                alert('Could not load default templates from server');
            });

            $('#load_default_template_window').modal('openModal');
        },
        close: function () {
            $('#load_default_template_window').modal('closeModal');
        },
        loadTemplate: function () {
            // Load template
            var templateId = $('#default_template_id').val();
            $.each(window.defaultPdfTemplates[templateId], function (field, value) {
                $('#template_' + field).val(value);
            });
            window.codeMirrorTemplate.setValue(window.defaultPdfTemplates[templateId].template_html);
            window.codeMirrorCss.setValue(window.defaultPdfTemplates[templateId].template_css);
            // Load test order/invoice/... ID
            if ($('input[name=source]').val() === '') {
                new Ajax.Request($('input[name=ajax_search]').val(), {
                    parameters: {
                        'variables_entity_id': '',
                        'get_one': true
                    },
                    onComplete: function (transport) {
                        var json = transport.responseText, message;
                        if (json.isJSON()) {
                            $('input[name=source]').val(json.evalJSON());
                        }
                    }
                });
            }
            // Close modal
            this.close();
        }
    };

    $(document).ready(function () {
        $('#load_default_template_window').modal({
            title: '',
            type: 'slide',
            buttons: []
        });
        $('#load_default_template_window').show();

        $('#default_template_id').change(function () {
            // New template selected, show preview image
            var templateId = $(this).val();
            $('#template-preview-img').attr('src', 'data:image/jpeg;base64,' + window.defaultPdfTemplates[templateId].thumbnail);
        });

        if (typeof $('#template_template_id')[0] === 'undefined' && $('#template_template_type').val() < 6) { // Not for attachments
            // New template, open onboarding popup
            window.previewPopupModal.open();
        }
    });
});
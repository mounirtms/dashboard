define([
    'jquery',
    'Xtento_PdfCustomizer/js/lib/codemirror/codemirror',
    'Xtento_PdfCustomizer/js/lib/codemirror/addon/codemirror-colorpicker',
    'Xtento_PdfCustomizer/js/lib/codemirror/mode/xml/xml',
    'Xtento_PdfCustomizer/js/lib/codemirror/mode/css/css',
    'Xtento_PdfCustomizer/js/lib/codemirror/addon/display/autorefresh'
], function ($, CM) {
    $(document).ready(function () {
        initTemplateEditor();
        initCssEditor();
        $('#pdfcustomizer_tabs_preview_section').on('click', function () {
            window.initPdfPreview()
        });
        $(document).on("beforeSubmit", function (e) {
            $('#preview-form').remove();
        });
    });

    // Template Editor
    function initTemplateEditor() {
        $("label[for='template_template_html']").hide();
        $("#template_template_html").parent().width('98%');
        window.codeMirrorTemplate = CM.fromTextArea($('#template_template_html')[0], {
            lineNumbers: true,
            mode: 'xml',
            htmlMode: true,
            autofocus: true,
            autoRefresh: true,
            colorpicker: {
                mode: 'edit'
            }
        });
        $('.field-template_html .CodeMirror.cm-s-default').height('680px');
        window.codeMirrorTemplate.on('change', function (codeMirror) {
            $("#template_template_html").val(codeMirror.getValue());
        });
    }

    // CSS Editor
    function initCssEditor() {
        $("label[for='template_template_css']").hide();
        $("#template_template_css").parent().width('98%');
        window.codeMirrorCss = CM.fromTextArea($('#template_template_css')[0], {
            lineNumbers: true,
            mode: 'css',
            autoRefresh: true,
            colorpicker: {
                mode: 'edit'
            }
        });
        $('.field-template_template_css .CodeMirror.cm-s-default').height('375px');
        window.codeMirrorCss.on('change', function (codeMirror) {
            $("#template_template_css").val(codeMirror.getValue());
        });
    }

    // PDF Preview functionality
    window.initPdfPreview = function () {
        $('#manual-preview').remove();
        $('#pdf-preview').remove();
        $('#preview-form').after('<iframe id="pdf-preview" name="pdf-preview" style="width: 99%; height: 1200px; border: 1px solid #eee; display: none;"></iframe>');
        $("#pdf-preview").contents().find('html').html('<html>\n' +
            '<head>\n' +
            '<link rel="stylesheet" type="text/css" href="//maxcdn.bootstrapcdn.com/font-awesome/4.4.0/css/font-awesome.min.css">\n' +
            '  <style type="text/css">\n' +
            '    .center-parent {\n' +
            '    position: absolute;\n' +
            '    top: 0;\n' +
            '    bottom: 0;\n' +
            '    left: 0;\n' +
            '    right: 0;\n' +
            '    width: 100%;\n' +
            '    height: 100%;\n' +
            '    z-index: -1;\n' +
            '}\n' +
            '.center-container {\n' +
            '    width: 60px;\n' +
            '    height: 60px;\n' +
            '    font-size: 60px;\n' +
            '    position: absolute;\n' +
            '    top: 50%;\n' +
            '    left: 50%;\n' +
            '    margin-top: -30px;\n' +
            '    margin-right: 0;\n' +
            '    margin-bottom: 0;\n' +
            '    margin-left: -30px;\n' +
            '    z-index: -1;\n' +
            '}\n' +
            '</style>\n' +
            '</head>\n' +
            '<body><br/>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<i>Loading preview, please wait...</i>\n' +
            '<div class="center-parent">\n' +
            '    <div class="center-container"> \n' +
            '        <i id="mo-spin-icon" class="fa fa-spinner fa-spin"></i>\n' +
            '    </div>\n' +
            '</div>\n' +
            '</body>\n' +
            '</html>');
        $('#preview-form #form-key').val(window.FORM_KEY);
        $('#preview-form #entity-id').val($('#template_source').val());
        $('#preview-form #template-html').val(window.codeMirrorTemplate.getValue());
        $('#preview-form #template-css').val(window.codeMirrorCss.getValue());
        $('#preview-form #template-paper-ori').val($('#template_template_paper_ori').val());
        $('#preview-form #template-custom-t').val($('#template_template_custom_t').val());
        $('#preview-form #template-custom-b').val($('#template_template_custom_b').val());
        $('#preview-form #template-custom-l').val($('#template_template_custom_l').val());
        $('#preview-form #template-custom-r').val($('#template_template_custom_r').val());
        $('#preview-form #submit-form').trigger('click');
        $("#pdf-preview").show();
    }
});
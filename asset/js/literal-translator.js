/**
 * Literal Translator: a translate button on every literal value of the
 * resource form.
 *
 * The button opens a sidebar with the value, its language and a choice of
 * target languages. "Add translation" asks the server for a DeepL translation
 * and adds it to the same property as a new value tagged with that language.
 *
 * `literalTranslator` ({url, languages}) is set by Module::prepareResourceForm().
 */
(function($) {

    var sidebar, translatingValue, request;

    /**
     * Put the button directly below the annotate button of a value.
     *
     * Anchoring on the annotate button rather than on a fixed place in the
     * list keeps this working both with core's "more actions" menu and with
     * modules that flatten that menu into a column of buttons.
     */
    var addButton = function(value) {
        if ('literal' !== value.data('dataType') || value.find('.literal-translator-translate').length) {
            return;
        }
        var item = $('<li>').append(
            $('<a>', {
                'href': '#',
                'class': 'o-icon-translate literal-translator-translate',
                'title': Omeka.jsTranslate('Translate value'),
                'aria-label': Omeka.jsTranslate('Translate value')
            })
        );
        var annotate = value.find('.input-footer .value-annotation-annotate').first().closest('li');
        if (annotate.length) {
            annotate.after(item);
        } else {
            value.find('.input-footer ul.actions').first().append(item);
        }
    };

    var addButtons = function() {
        $('#properties .value').not('.template').each(function() {
            addButton($(this));
        });
    };

    /**
     * "en-GB" and "en_gb" both become "en".
     */
    var primarySubtag = function(tag) {
        return $.trim(tag || '').toLowerCase().replace(/_/g, '-').split('-')[0];
    };

    var showError = function(message) {
        sidebar.find('.literal-translator-error').text(message).prop('hidden', !message);
    };

    var setBusy = function(busy) {
        $('#literal-translator-add')
            .prop('disabled', busy)
            .text(Omeka.jsTranslate(busy ? 'Translating…' : 'Add translation'));
        $('#literal-translator-target').prop('disabled', busy);
    };

    var openSidebar = function(value) {
        translatingValue = value;
        var text = value.find('textarea.input-value').val();
        var language = $.trim(value.find('input.value-language').val());
        var source = primarySubtag(language);

        sidebar.find('.literal-translator-text').text(text);
        sidebar.find('.literal-translator-source').text(
            language
                ? (literalTranslator.languages[source] ? literalTranslator.languages[source] + ' (' + language + ')' : language)
                : Omeka.jsTranslate('Unknown (detected automatically)')
        );

        // Offer every configured language except the one the value is in.
        var select = $('#literal-translator-target').empty();
        $.each(literalTranslator.languages, function(code, label) {
            if (primarySubtag(code) !== source) {
                select.append($('<option>', {value: code, text: label + ' (' + code + ')'}));
            }
        });

        setBusy(false);
        showError('');
        if ('' === $.trim(text)) {
            showError(Omeka.jsTranslate('There is no text to translate.'));
            $('#literal-translator-add').prop('disabled', true);
        } else if (!select.children().length) {
            showError(Omeka.jsTranslate('There is no other language to translate into.'));
            $('#literal-translator-add').prop('disabled', true);
        }

        Omeka.openSidebar(sidebar);
    };

    /**
     * Add a literal value below the translated one.
     *
     * The function that builds a value is private to resource-form.js, so the
     * property's own "add value" button is used: that way the value is made
     * exactly as if an editor had added it, with every module's
     * o:prepare-value handler applied.
     */
    var addTranslation = function(source, text, language) {
        var field = source.closest('.resource-property');
        var values = field.find('.values').first();
        var count = values.children('.value').length;

        field.find('.add-values .add-value').filter(function() {
            return 'literal' === $(this).data('type');
        }).first().trigger('click');

        var value = values.children('.value').last();
        if (values.children('.value').length === count) {
            // No button for literals on this property (a template may allow
            // them only through a custom selector): build the value by hand.
            value = $('.value.template[data-data-type="literal"]').clone(true).removeClass('template');
            value.find('input.is_public').val(1);
            $(document).trigger('o:prepare-value', ['literal', value, null]);
            values.append(value);
        }

        value.find('textarea.input-value').val(text).trigger('change');
        value.find('.language-wrapper').addClass('active');
        value.find('input.value-language').val(language);
        value.insertAfter(source);

        value.addClass('literal-translator-added');
        setTimeout(function() {
            value.removeClass('literal-translator-added');
        }, 2000);
    };

    var translate = function() {
        var source = translatingValue;
        if (!source || request) {
            return;
        }
        setBusy(true);
        showError('');

        request = $.ajax({
            url: literalTranslator.url,
            method: 'POST',
            dataType: 'json',
            headers: {'X-Csrf': $('form.resource-form input[name="csrf"]').val() || $('#content form input[name="csrf"]').first().val()},
            data: {
                text: source.find('textarea.input-value').val(),
                source: $.trim(source.find('input.value-language').val()),
                target: $('#literal-translator-target').val()
            }
        }).done(function(data) {
            addTranslation(source, data.text, data.lang);
            Omeka.closeSidebar(sidebar);
        }).fail(function(jqXHR, status) {
            if ('abort' === status) {
                return;
            }
            var data = jqXHR.responseJSON;
            showError(data && data.error ? data.error : Omeka.jsTranslate('The translation failed.'));
        }).always(function() {
            request = null;
            setBusy(false);
        });
    };

    $(document).on('o:prepare-value', function(e, dataType, value) {
        addButton(value);
    });

    $(document).ready(function() {
        sidebar = $('#literal-translator-sidebar');
        if (!sidebar.length || 'undefined' === typeof literalTranslator) {
            return;
        }

        // Values present when the script loads, and those of forms rebuilt
        // after a resource template was applied.
        addButtons();
        $(document).on('o:form-loaded o:template-applied', addButtons);

        $('#content').on('click', '.literal-translator-translate', function(e) {
            e.preventDefault();
            openSidebar($(this).closest('.value'));
        });

        $('#literal-translator-add').on('click', translate);

        // Closing the sidebar abandons a translation still on its way.
        sidebar.on('o:sidebar-closed', function() {
            if (request) {
                request.abort();
            }
            translatingValue = null;
        });
    });

})(jQuery);

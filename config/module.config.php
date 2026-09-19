<?php

namespace LiteralTranslator;

return [
    'controllers' => [
        'factories' => [
            'LiteralTranslator\Controller\Admin\Index' => Service\Controller\Admin\IndexControllerFactory::class,
        ],
    ],
    'service_manager' => [
        'factories' => [
            'LiteralTranslator\DeepL' => Service\Stdlib\DeepLFactory::class,
        ],
    ],
    'form_elements' => [
        'invokables' => [
            Form\ConfigForm::class => Form\ConfigForm::class,
        ],
    ],
    'router' => [
        'routes' => [
            'admin' => [
                'child_routes' => [
                    'literal-translator' => [
                        'type' => 'Literal',
                        'options' => [
                            'route' => '/literal-translator/translate',
                            'defaults' => [
                                '__NAMESPACE__' => 'LiteralTranslator\Controller\Admin',
                                'controller' => 'Index',
                                'action' => 'translate',
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ],
    'view_manager' => [
        'template_path_stack' => [
            dirname(__DIR__) . '/view',
        ],
    ],
    'translator' => [
        'translation_file_patterns' => [
            [
                'type' => 'gettext',
                'base_dir' => dirname(__DIR__) . '/language',
                'pattern' => '%s.mo',
                'text_domain' => null,
            ],
        ],
    ],
    // Strings used by asset/js/literal-translator.js through
    // Omeka.jsTranslate().
    'js_translate_strings' => [
        'Translate value', // @translate
        'Unknown (detected automatically)', // @translate
        'Translating…', // @translate
        'Add translation', // @translate
        'There is no text to translate.', // @translate
        'There is no other language to translate into.', // @translate
        'The translation failed.', // @translate
    ],

    'literaltranslator' => [
        // The languages offered in the sidebar, as language code => label.
        //
        // The code is what ends up in the language field of the new value, so
        // keep it to what you use elsewhere in your metadata (usually the
        // two-letter code). It must be a language DeepL can translate into:
        // https://developers.deepl.com/docs/getting-started/supported-languages
        // Where DeepL insists on a regional variant ("en", "pt", "zh") the
        // module picks one; see Stdlib\DeepL::TARGET_VARIANTS.
        //
        // The language of the value being translated is left out of the list
        // automatically. Labels are passed through the translator, so add new
        // ones to the catalogues in language/ if you want them localised.
        'target_languages' => [
            'nl' => 'Dutch', // @translate
            'en' => 'English', // @translate
            'de' => 'German', // @translate
            'fr' => 'French', // @translate
        ],
    ],
];

# Literal Translator

Omeka S module adding a **translate button** to every literal value on the resource form. Pick a
target language, and the [DeepL](https://www.deepl.com/) translation is added as a new value of
the same property, tagged with that language.

## Why

Multilingual metadata in Omeka S is a matter of repeating a value once per language, each with its
own language tag. Doing that by hand means copying the text, translating it somewhere else, adding
a value, pasting, and typing the language code — for every value, in every language.

This module does the same thing in two clicks, without leaving the form. It translates one value
at a time, when an editor asks for it, and the result is an ordinary value that can be corrected
before the resource is saved. It does not translate in bulk or in the background, and it never
touches a value you did not click on.

## On the resource form

Open an item, item set or media for editing. Every text value has a new button, directly below
the **Annotate value** button:

1. Click the translate button. A sidebar opens with the **value**, its **source language** — taken
   from the value's language field — and a list of **target languages**. The source language is
   left out of that list, since there is nothing to translate a Dutch text into Dutch for.
2. Choose a target language and click **Add translation**.
3. The translation appears as a new value directly below the original, with the language field set
   to the language you chose, and the sidebar closes.

Nothing is stored until you save the resource, exactly as with a value you typed yourself. So read
the translation, fix what needs fixing, and remove it again if it is no use.

A value without a language is fine: DeepL detects what it is written in. If that turns out to be
the language you asked for, you are told so and no value is added.

DeepL is asked for plain text. Left to itself it sets the titles of publications in Markdown
italics — `an editor of the *Winkler Prins*` — and in a metadata value those asterisks are just
asterisks.

Only values of the data type *Text* (`literal`) get the button. URIs and linked resources have
nothing to translate, and value annotations are left alone.

The module works with the core resource form and with
[Advanced Resource Template](https://gitlab.com/Daniel-KM/Omeka-S-module-AdvancedResourceTemplate),
including its option to show the value buttons as a column instead of a **More actions** menu.

## Languages

Out of the box the sidebar offers Dutch (`nl`), English (`en`), German (`de`) and French (`fr`).
The list is an array in `config/module.config.php`:

```php
'literaltranslator' => [
    'target_languages' => [
        'nl' => 'Dutch', // @translate
        'en' => 'English', // @translate
        'de' => 'German', // @translate
        'fr' => 'French', // @translate
    ],
],
```

The key is the code written into the language field of the new value; the label is what the
sidebar shows. Add any language
[DeepL can translate into](https://developers.deepl.com/docs/getting-started/supported-languages).

For English, Portuguese and Chinese, DeepL wants to know which variant to write. The module keeps
the short code in your metadata and asks DeepL for British English (`EN-GB`), European Portuguese
(`PT-PT`) and simplified Chinese (`ZH-HANS`). If you would rather have another variant, use its
full code as the key — `'en-us' => 'English (US)'` — and that is what DeepL is asked for and what
the value is tagged with.

## Installation

Download the release zip, unzip it into Omeka's `modules/` directory as `LiteralTranslator`, and
install it from **Modules** in the admin. Or from source:

```bash
cd /path/to/omeka-s/modules
git clone https://github.com/coret/Omeka-S-module-LiteralTranslator.git LiteralTranslator
```

There is nothing to build and no `composer install` to run: the module has no dependencies of its
own.

Requires Omeka S 4.0 or later and PHP 8.1 or later.

## Configuration

The module needs a **DeepL API key**. Create an account for the
[DeepL API](https://www.deepl.com/pro-api) — the free plan translates 500,000 characters a month —
and copy the authentication key from your account page. Then click **Configure** next to the
module in the admin and paste it in.

Keys of the free plan end in `:fx`; the module sees that and uses DeepL's free endpoint. There is
nothing else to choose.

The key is stored in Omeka's settings and is never sent back to the browser: the configuration
form only says whether one is stored. Leave the field empty to keep the current key, or tick
**Remove the stored key** to delete it. Uninstalling the module removes it as well.

## Who can translate

Anyone who can edit resources: global and site administrators, editors, reviewers and authors.
Researchers cannot.

The translate endpoint only answers a signed-in user with one of those roles, and only a request
carrying the CSRF token of the resource form it was made from — every translation counts against
your DeepL quota, so another website must not be able to trigger one.

## Privacy

The text of a value is sent to DeepL when, and only when, an editor clicks **Add translation** for
it. Nothing else leaves your server: no resource identifiers, no user details. Whether DeepL keeps
the text depends on your plan; see [DeepL's privacy policy](https://www.deepl.com/privacy).

## What it depends on

Nothing beyond Omeka S itself.

- The DeepL API is called with the HTTP client Omeka already ships, not with an SDK.
- The button's icon comes from the Font Awesome that Omeka's admin theme already loads.
- The sidebar is the admin theme's own sidebar component.

## Development

```bash
composer install                                   # only php-cs-fixer
vendor/bin/php-cs-fixer fix --dry-run --diff       # code style, rules copied from Omeka core
```

Translations live in `language/`; see `language/README.md` for how to update them. The module
ships with English and Dutch.

## Licence

GPL-3.0-or-later.

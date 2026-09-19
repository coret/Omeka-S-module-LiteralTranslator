# Translations

Shipped catalogues:

| File | Locale | Notes |
|---|---|---|
| `template.pot` | — | Extracted source strings (34). |
| `en.po` / `en.mo` | `en` | Identity catalogue. English is the source language, but an explicit `en` locale then resolves instead of falling through to the raw msgid. |
| `nl_NL.po` / `nl_NL.mo` | `nl_NL` | Dutch. Matches the filename Omeka core uses (`application/language/nl_NL.po`). |
| `nl.po` / `nl.mo` | `nl` | Same Dutch content, so a plain `nl` locale resolves too. |

The strings are the button and the sidebar on the resource form, the
configuration form, the error messages of the translate endpoint, the labels of
the target languages in `config/module.config.php`, and the name and
description from `config/module.ini` — Omeka runs the last two through the
translator in the module list.

The strings the script uses are listed under `js_translate_strings` in
`config/module.config.php`; Omeka translates them on the server and hands them
to `Omeka.jsTranslate()`.

## Regenerating after changing a string

The catalogues are small enough to maintain by hand. After adding or changing a
translatable string:

1. Add the new `msgid` to `template.pot`, with a `#:` reference to its source.
2. Add the matching entry to `nl_NL.po`.
3. Regenerate the English identity catalogue and the plain-`nl` copy, and
   recompile:

```sh
cd modules/LiteralTranslator/language
msgen template.pot -o en.po          # then fix the Language: header to "en"
sed 's/^"Language: nl_NL/"Language: nl/' nl_NL.po > nl.po
for f in en nl nl_NL; do msgfmt --check -o "$f.mo" "$f.po"; done
```

4. Confirm every catalogue still covers the template:

```sh
for f in en nl_NL nl; do msgcmp "$f.po" template.pot; done
```

Register and terminology follow Omeka S's own Dutch catalogue: informal *je*.

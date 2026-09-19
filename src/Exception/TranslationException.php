<?php

namespace LiteralTranslator\Exception;

/**
 * A translation that could not be made.
 *
 * The message is an untranslated source string meant for the person at the
 * form; the HTTP status to answer with travels as the exception code.
 */
class TranslationException extends \RuntimeException
{
}

<?php

namespace LiteralTranslator\Stdlib;

use Laminas\Http\Client;
use Laminas\Http\Request;
use Laminas\Http\Response;
use Laminas\Log\LoggerInterface;
use LiteralTranslator\Exception\TranslationException;

/**
 * Translates one text with the DeepL API.
 *
 * The module translates a single value at a time, on request, so this talks to
 * the REST endpoint directly with Omeka's own HTTP client rather than pulling
 * in the DeepL SDK and the HTTP stack that comes with it.
 *
 * @see https://developers.deepl.com/api-reference/translate
 */
class DeepL
{
    const ENDPOINT_PRO = 'https://api.deepl.com/v2/translate';
    const ENDPOINT_FREE = 'https://api-free.deepl.com/v2/translate';

    /**
     * Target languages DeepL only accepts with a regional variant. The
     * metadata keeps the short code; only the request uses the variant.
     */
    const TARGET_VARIANTS = [
        'en' => 'EN-GB',
        'pt' => 'PT-PT',
        'zh' => 'ZH-HANS',
    ];

    /**
     * DeepL's models like to set the titles of publications in italics and
     * write that as Markdown: "an editor of the *Winkler Prins*". A metadata
     * value is plain text, where those asterisks are just asterisks, and no
     * formatting option turns them off. An instruction does.
     */
    const INSTRUCTIONS = [
        'Output plain text only. Never use Markdown or asterisks for emphasis or titles.',
    ];

    /**
     * @var Client
     */
    protected $httpClient;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var string
     */
    protected $apiKey;

    public function __construct(Client $httpClient, LoggerInterface $logger, string $apiKey)
    {
        $this->httpClient = $httpClient;
        $this->logger = $logger;
        $this->apiKey = trim($apiKey);
    }

    public function hasApiKey(): bool
    {
        return '' !== $this->apiKey;
    }

    /**
     * @param string $text
     * @param string|null $source Language tag of the text; empty to let DeepL
     *   detect it.
     * @param string $target Language tag to translate into.
     * @throws TranslationException
     * @return array The translation under "text" and the lowercase code of
     *   the language DeepL took the text to be in under "detected".
     */
    public function translate(string $text, ?string $source, string $target): array
    {
        if (!$this->hasApiKey()) {
            throw new TranslationException(
                'No DeepL API key is configured. Add one in the configuration of the Literal Translator module.', // @translate
                503
            );
        }

        $source = $this->primarySubtag((string) $source);
        if ('' !== $source && $source === $this->primarySubtag($target)) {
            throw new TranslationException(
                'The text is already in the chosen language.', // @translate
                422
            );
        }

        $body = [
            'text' => [$text],
            'target_lang' => $this->targetCode($target),
            'custom_instructions' => self::INSTRUCTIONS,
        ];
        // DeepL names source languages without a region. Anything that does
        // not look like such a code is left for DeepL to detect, which beats
        // failing on a tag like "x-unknown".
        if (preg_match('/^[a-z]{2,3}$/', $source)) {
            $body['source_lang'] = strtoupper($source);
        }

        $response = $this->send($body);
        if (400 === $response->getStatusCode()) {
            // Instructions are not available for every language pair and
            // plan. A translation with the odd asterisk beats none at all.
            unset($body['custom_instructions']);
            $response = $this->send($body);
        }

        $status = $response->getStatusCode();
        if (200 !== $status) {
            $this->logger->err(sprintf('LiteralTranslator: DeepL answered %d: %s', $status, $response->getBody()));
            throw $this->exceptionForStatus($status);
        }

        $data = json_decode($response->getBody(), true);
        $translation = $data['translations'][0] ?? null;
        if (!is_array($translation) || !isset($translation['text'])) {
            $this->logger->err(sprintf('LiteralTranslator: unexpected answer from DeepL: %s', $response->getBody()));
            throw new TranslationException(
                'DeepL returned an answer that could not be read.', // @translate
                502
            );
        }

        $detected = $this->primarySubtag((string) ($translation['detected_source_language'] ?? ''));
        // Without a source language the check above had nothing to compare,
        // so make it now that DeepL has said what the text is written in.
        if ('' !== $detected && $detected === $this->primarySubtag($target)) {
            throw new TranslationException(
                'The text is already in the chosen language.', // @translate
                422
            );
        }

        return [
            'text' => (string) $translation['text'],
            'detected' => $detected,
        ];
    }

    /**
     * @throws TranslationException
     */
    protected function send(array $body): Response
    {
        // Keys of the free plan carry this suffix and are only valid on the
        // free endpoint.
        $endpoint = ':fx' === substr($this->apiKey, -3) ? self::ENDPOINT_FREE : self::ENDPOINT_PRO;

        $client = $this->httpClient;
        $client->resetParameters(true);
        $client->setUri($endpoint);
        $client->setMethod(Request::METHOD_POST);
        $client->setOptions(['timeout' => 30]);
        $client->setHeaders([
            'Authorization' => 'DeepL-Auth-Key ' . $this->apiKey,
            'Content-Type' => 'application/json',
        ]);
        $client->setRawBody(json_encode($body, JSON_INVALID_UTF8_SUBSTITUTE));

        try {
            return $client->send();
        } catch (\Exception $e) {
            $this->logger->err(sprintf('LiteralTranslator: DeepL could not be reached: %s', $e->getMessage()));
            throw new TranslationException(
                'DeepL could not be reached. Try again later.', // @translate
                502
            );
        }
    }

    protected function exceptionForStatus(int $status): TranslationException
    {
        switch ($status) {
            case 401:
            case 403:
                return new TranslationException(
                    'DeepL did not accept the API key. Check it in the configuration of the Literal Translator module.', // @translate
                    502
                );
            case 456:
                return new TranslationException(
                    'The translation quota of the DeepL account is used up.', // @translate
                    502
                );
            case 429:
            case 529:
                return new TranslationException(
                    'DeepL is receiving too many requests. Try again in a moment.', // @translate
                    502
                );
            case 400:
            case 404:
                return new TranslationException(
                    'DeepL rejected the request. The language may not be supported.', // @translate
                    502
                );
            default:
                return new TranslationException(
                    'DeepL could not translate the text. Try again later.', // @translate
                    502
                );
        }
    }

    /**
     * "en-GB" and "en_gb" both become "en".
     */
    protected function primarySubtag(string $tag): string
    {
        $tag = strtolower(trim($tag));
        return (string) strtok(str_replace('_', '-', $tag), '-');
    }

    /**
     * The code DeepL wants for a target language: the tag as given when it
     * names a region, otherwise the variant chosen in TARGET_VARIANTS.
     */
    protected function targetCode(string $tag): string
    {
        $tag = str_replace('_', '-', strtolower(trim($tag)));
        if (false === strpos($tag, '-') && isset(self::TARGET_VARIANTS[$tag])) {
            return self::TARGET_VARIANTS[$tag];
        }
        return strtoupper($tag);
    }
}

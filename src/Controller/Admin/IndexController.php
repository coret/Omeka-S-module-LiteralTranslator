<?php

namespace LiteralTranslator\Controller\Admin;

use Laminas\Mvc\Controller\AbstractActionController;
use LiteralTranslator\Exception\TranslationException;
use LiteralTranslator\Stdlib\DeepL;
use Omeka\Form\ResourceForm;

/**
 * Translates one value for the sidebar on the resource form.
 */
class IndexController extends AbstractActionController
{
    /**
     * The longest text the endpoint will send to DeepL, in bytes. DeepL's own
     * limit for a request is 128 KiB; a metadata value is nowhere near it.
     */
    const MAX_LENGTH = 102400;

    /**
     * @var DeepL
     */
    protected $deepL;

    /**
     * @var string[]
     */
    protected $targetLanguages;

    public function __construct(DeepL $deepL, array $targetLanguages)
    {
        $this->deepL = $deepL;
        $this->targetLanguages = array_map('strval', $targetLanguages);
    }

    public function translateAction()
    {
        $request = $this->getRequest();
        if (!$request->isPost()) {
            return $this->jsonResponse(['error' => $this->translate('The request must be a POST.')], 405); // @translate
        }

        // Every call costs characters from the DeepL quota, so unlike a
        // read-only endpoint this one must not be triggerable from another
        // site. The resource form the sidebar lives on already carries a CSRF
        // token; the script sends it along and it is checked against the same
        // form here.
        $csrf = $request->getHeaders()->get('X-Csrf');
        $form = $this->getForm(ResourceForm::class);
        $form->setData(['csrf' => $csrf ? $csrf->getFieldValue() : null]);
        if (!$form->isValid()) {
            return $this->jsonResponse([
                'error' => $this->translate('The form has expired. Save your changes and reload the page.'), // @translate
            ], 403);
        }

        $text = (string) $this->params()->fromPost('text', '');
        $source = (string) $this->params()->fromPost('source', '');
        $target = (string) $this->params()->fromPost('target', '');

        if ('' === trim($text)) {
            return $this->jsonResponse(['error' => $this->translate('There is no text to translate.')], 422); // @translate
        }
        if (strlen($text) > self::MAX_LENGTH) {
            return $this->jsonResponse(['error' => $this->translate('This text is too long to translate.')], 413); // @translate
        }
        if (!in_array($target, $this->targetLanguages, true)) {
            return $this->jsonResponse(['error' => $this->translate('This target language is not available.')], 422); // @translate
        }

        try {
            $translation = $this->deepL->translate($text, $source, $target);
        } catch (TranslationException $e) {
            return $this->jsonResponse(
                ['error' => $this->translate($e->getMessage())],
                $e->getCode() ?: 500
            );
        }

        return $this->jsonResponse([
            'text' => $translation['text'],
            'lang' => $target,
            'detected' => $translation['detected'],
        ]);
    }

    /**
     * @param array $payload
     * @param int $status
     * @return \Laminas\Http\Response
     */
    protected function jsonResponse(array $payload, $status = 200)
    {
        $response = $this->getResponse();
        $response->getHeaders()->addHeaderLine('Content-Type', 'application/json; charset=utf-8');
        $response->setStatusCode($status);
        return $response->setContent(json_encode($payload, JSON_INVALID_UTF8_SUBSTITUTE));
    }
}

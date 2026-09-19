<?php

namespace LiteralTranslator\Service\Stdlib;

use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;
use LiteralTranslator\Stdlib\DeepL;

class DeepLFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, ?array $options = null)
    {
        return new DeepL(
            $services->get('Omeka\HttpClient'),
            $services->get('Omeka\Logger'),
            (string) $services->get('Omeka\Settings')->get('literaltranslator_deepl_api_key', '')
        );
    }
}

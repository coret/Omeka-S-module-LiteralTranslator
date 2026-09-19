<?php

namespace LiteralTranslator\Service\Controller\Admin;

use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;
use LiteralTranslator\Controller\Admin\IndexController;

class IndexControllerFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, ?array $options = null)
    {
        $config = $services->get('Config');
        return new IndexController(
            $services->get('LiteralTranslator\DeepL'),
            array_keys($config['literaltranslator']['target_languages'] ?? [])
        );
    }
}

<?php

namespace LiteralTranslator;

use Laminas\EventManager\Event;
use Laminas\EventManager\SharedEventManagerInterface;
use Laminas\Mvc\Controller\AbstractController;
use Laminas\Mvc\MvcEvent;
use Laminas\ServiceManager\ServiceLocatorInterface;
use Laminas\View\Renderer\PhpRenderer;
use LiteralTranslator\Form\ConfigForm;
use Omeka\Module\AbstractModule;
use Omeka\Permissions\Acl;

/**
 * Adds a translate button to every literal value on the resource form.
 *
 * The button opens a sidebar showing the value and its language, with a choice
 * of languages to translate into. The translation is made by DeepL and added
 * to the form as a new value of the same property, tagged with the chosen
 * language. Nothing is stored until the form itself is saved.
 *
 * @copyright Bob Coret, 2026
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL-3.0-or-later
 */
class Module extends AbstractModule
{
    const SETTING_API_KEY = 'literaltranslator_deepl_api_key';

    public function getConfig()
    {
        return include sprintf('%s/config/module.config.php', __DIR__);
    }

    public function onBootstrap(MvcEvent $event)
    {
        parent::onBootstrap($event);

        // Translating is part of editing a resource. Global and site
        // administrators already have it; these are the other roles that can
        // reach the resource form.
        $acl = $this->getServiceLocator()->get('Omeka\Acl');
        $acl->allow(
            [Acl::ROLE_EDITOR, Acl::ROLE_REVIEWER, Acl::ROLE_AUTHOR],
            'LiteralTranslator\Controller\Admin\Index'
        );
    }

    public function uninstall(ServiceLocatorInterface $services)
    {
        $services->get('Omeka\Settings')->delete(self::SETTING_API_KEY);
    }

    public function attachListeners(SharedEventManagerInterface $sharedEventManager)
    {
        $controllers = [
            'Omeka\Controller\Admin\Item',
            'Omeka\Controller\Admin\ItemSet',
            'Omeka\Controller\Admin\Media',
        ];
        foreach ($controllers as $controller) {
            $sharedEventManager->attach($controller, 'view.add.after', [$this, 'prepareResourceForm']);
            $sharedEventManager->attach($controller, 'view.edit.after', [$this, 'prepareResourceForm']);
        }
    }

    /**
     * Add the script, the stylesheet and the sidebar to the resource form.
     *
     * The events used fire after the form, inside #content, which is where a
     * sidebar has to be for the admin theme's close button to work.
     */
    public function prepareResourceForm(Event $event)
    {
        $view = $event->getTarget();
        $config = $this->getServiceLocator()->get('Config');

        $languages = [];
        foreach ($config['literaltranslator']['target_languages'] ?? [] as $code => $label) {
            $languages[(string) $code] = $view->translate($label);
        }

        $view->headLink()->appendStylesheet($view->assetUrl('css/literal-translator.css', 'LiteralTranslator'));
        $view->headScript()
            ->appendScript(sprintf(
                'var literalTranslator = %s;',
                json_encode([
                    'url' => $view->url('admin/literal-translator'),
                    'languages' => $languages,
                ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT)
            ))
            ->appendFile($view->assetUrl('js/literal-translator.js', 'LiteralTranslator'));

        echo $view->partial('common/literal-translator-sidebar');
    }

    public function getConfigForm(PhpRenderer $renderer)
    {
        $services = $this->getServiceLocator();
        $hasKey = '' !== (string) $services->get('Omeka\Settings')->get(self::SETTING_API_KEY, '');

        $form = $services->get('FormElementManager')->get(ConfigForm::class);
        // The key itself is never sent back to the browser; say whether there
        // is one instead.
        $form->get(self::SETTING_API_KEY)->setAttribute(
            'placeholder',
            $hasKey
                ? $renderer->translate('A key is stored. Leave empty to keep it.') // @translate
                : $renderer->translate('No key is stored yet.') // @translate
        );
        if (!$hasKey) {
            $form->remove(self::SETTING_API_KEY . '_remove');
        }

        return $renderer->formCollection($form, false);
    }

    public function handleConfigForm(AbstractController $controller)
    {
        $services = $this->getServiceLocator();
        $settings = $services->get('Omeka\Settings');

        $form = $services->get('FormElementManager')->get(ConfigForm::class);
        $form->setData($controller->params()->fromPost());
        if (!$form->isValid()) {
            $controller->messenger()->addFormErrors($form);
            return false;
        }

        $data = $form->getData();
        $key = (string) ($data[self::SETTING_API_KEY] ?? '');
        if ('' !== $key) {
            $settings->set(self::SETTING_API_KEY, $key);
        } elseif (!empty($data[self::SETTING_API_KEY . '_remove'])) {
            $settings->delete(self::SETTING_API_KEY);
        }
        // An empty field without the checkbox leaves the stored key alone.

        return true;
    }
}

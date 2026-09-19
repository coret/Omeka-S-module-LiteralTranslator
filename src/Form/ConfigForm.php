<?php

namespace LiteralTranslator\Form;

use Laminas\Form\Element\Password;
use Laminas\Form\Form;

class ConfigForm extends Form
{
    public function init()
    {
        // A password element never renders its value, so the key is not
        // echoed back into the page once it is saved. Module::handleConfigForm()
        // treats an empty submission as "keep the current key".
        $this->add([
            'name' => 'literaltranslator_deepl_api_key',
            'type' => Password::class,
            'options' => [
                'label' => 'DeepL API key', // @translate
                'info' => 'The authentication key of your DeepL API account. Keys of the free plan end in ":fx" and are sent to the free endpoint automatically. Leave empty to keep the current key.', // @translate
            ],
            'attributes' => [
                'id' => 'literaltranslator_deepl_api_key',
                'autocomplete' => 'new-password',
            ],
        ]);

        $this->add([
            'name' => 'literaltranslator_deepl_api_key_remove',
            'type' => 'Checkbox',
            'options' => [
                'label' => 'Remove the stored key', // @translate
            ],
            'attributes' => [
                'id' => 'literaltranslator_deepl_api_key_remove',
            ],
        ]);

        $inputFilter = $this->getInputFilter();
        $inputFilter->add([
            'name' => 'literaltranslator_deepl_api_key',
            'required' => false,
            'filters' => [
                ['name' => 'StringTrim'],
            ],
        ]);
        $inputFilter->add([
            'name' => 'literaltranslator_deepl_api_key_remove',
            'required' => false,
        ]);
    }
}

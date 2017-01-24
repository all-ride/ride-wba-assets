<?php

namespace ride\web\base\form;

use ride\library\form\component\AbstractComponent;
use ride\library\form\FormBuilder;
use ride\library\system\file\File;
use ride\library\validation\constraint\ConditionalConstraint;
use ride\library\validation\factory\ValidationFactory;

/**
 * Form component for an asset
 */
class AssetComponent extends AbstractComponent {

    /**
     * Directory for the uploads
     * @var \ride\library\system\file\File
     */
    protected $directory;

    /**
     * @var \ride\library\validation\factory\ValidationFactory
     */
    protected $validationFactory;

    /**
     * Constructs a new AssetComponent
     * @param \ride\library\system\file\File $directory
     * @param \ride\library\validation\factory\ValidationFactory $validationFactory
     * @return null
     */
    public function __construct(File $directory, ValidationFactory $validationFactory) {
        $this->directory = $directory;
        $this->validationFactory = $validationFactory;
    }

    /**
     * Gets the upload directory
     * @return \ride\library\system\file\File
     */
    public function getDirectory() {
        return $this->directory;
    }

    /**
     * Gets the data type for the data of this form component
     * @return string|null A string for a data class, null for an array
     */
    public function getDataType() {
        return 'ride\application\orm\asset\entry\AssetEntry';
    }

    /**
     * Parse the data to form values for the component rows
     * @param mixed $data
     * @return array $data
     */
    public function parseSetData($data) {
        $this->data = $data;

        $value = $data->getValue();
        $isUrl = $data->isUrl();

        $data = array(
            'name' => $data->getName(),
            'alt' => $data->getAlt(),
            'description' => $data->getDescription(),
            'copyright' => $data->getCopyright(),
            'resource' => $isUrl || !$value ? 'url' : 'file',
            'file' => !$isUrl ? $value : '',
            'url' => $isUrl ? $value : '',
        );

        return $data;
    }

    /**
     * Parses the form values to the entry of the component
     * @param array $data Submitted data
     * @return mixed $data Instance of an asset
     */
    public function parseGetData(array $data) {
        $asset = $this->data;

        $asset->setName($data['name']);
        $asset->setAlt($data['alt']);
        $asset->setDescription($data['description']);
        $asset->setCopyright($data['copyright']);

        if ($data['resource'] == 'url' && isset($data['url'])) {
            $asset->setValue($data['url']);
        } elseif ($data['resource'] == 'file' && isset($data['file'])) {
            $asset->setValue($data['file']);
        }

        // workaround for conditional validators
        $asset->resource = $data['resource'];
        $asset->file = $data['file'];
        $asset->url = $data['url'];

        return $asset;
    }

    /**
     * Prepares the form
     * @param \ride\library\form\FormBuilder $builder
     * @param array $options
     * @return null
     */
    public function prepareForm(FormBuilder $builder, array $options) {
        $translator = $options['translator'];
        $asset = $options['data'];

        $builder->addRow('resource', 'option', array(
            'label' => $translator->translate('label.resource'),
            'attributes' => array(
                'data-toggle-dependant' => 'option-resource',
            ),
            'options' => array(
                'url' => $translator->translate('label.url'),
                'file' => $translator->translate('label.file'),
            ),
            'default' => 'url',
        ));
        $builder->addRow('url', 'website', array(
            'label' => $translator->translate('label.url'),
            'attributes' => array(
                'class' => 'option-resource option-resource-url',
            ),
        ));
        $builder->addRow('file', 'file', array(
            'label' => $translator->translate('label.file'),
            'attributes' => array(
                'class' => 'option-resource option-resource-file',
            ),
            'path' => $this->directory,
            'allow_delete' => false,
        ));
        $builder->addRow('name', 'string', array(
            'label' => $translator->translate('label.name'),
            'filters' => array(
                'trim' => array(),
            )
        ));
        $builder->addRow('alt', 'string', array(
            'label' => $translator->translate('label.alt'),
            'filters' => array(
                'trim' => array(),
            )
        ));
        $builder->addRow('description', 'wysiwyg', array(
            'label' => $translator->translate('label.description'),
        ));
        $builder->addRow('copyright', 'string', array(
            'label' => $translator->translate('label.copyright'),
            'filters' => array(
                'trim' => array(),
            )
        ));

        $requiredValidator = $this->validationFactory->createValidator('required', array());

        $urlRequired = new ConditionalConstraint();
        $urlRequired->addValueCondition('resource', 'url');
        $urlRequired->addValidator($requiredValidator, 'url');

        $fileRequired = new ConditionalConstraint();
        $fileRequired->addValueCondition('resource', 'file');
        $fileRequired->addValidator($requiredValidator, 'file');

        $builder->addValidationConstraint($urlRequired);
        $builder->addValidationConstraint($fileRequired);
    }

}

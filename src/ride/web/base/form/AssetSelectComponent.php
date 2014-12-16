<?php

namespace ride\web\base\form;

use ride\library\form\component\AbstractComponent;
use ride\library\form\FormBuilder;
use ride\library\orm\OrmManager;

use ride\web\base\asset\AssetModel;

/**
 * Form component to select an asset
 */
class AssetSelectComponent extends AbstractComponent {

    /**
     * Code of the locale
     * @var string
     */
    protected $locale;

    /**
     * Constructs a new component
     * @param \ride\library\orm\OrmManager $orm
     * @return null
     */
    public function __construct(OrmManager $orm) {
        $this->assetModel = $orm->getAssetModel();
    }

    /**
     * Sets the locale for the asset query
     * @param string $locale
     * @return null
     */
    public function setLocale($locale) {
        $this->locale = $locale;
    }

    /**
     * Gets the data type for the data of this form component
     * @return string|null A string for a data class, null for an array
     */
    public function getDataType() {
        return 'ride\web\base\asset\AssetEntry';
    }

    /**
     * Parse the data to form values for the component rows
     * @param mixed $data
     * @return array $data
     */
    public function parseSetData($data) {
        return array('asset' => $data);
    }

    /**
     * Parses the form values to the entry of the component
     * @param array $data Submitted data
     * @return mixed $data Instance of an asset
     */
    public function parseGetData(array $data) {
        return $data['asset'];
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

        $builder->addRow('asset', 'object', array(
            'label' => $translator->translate('label.asset'),
            'options' => $this->assetModel->find(null, $this->locale),
            'value' => 'id',
            'property' => 'name',
            'widget' => 'select',
        ));
    }

}

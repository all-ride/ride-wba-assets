<?php

namespace ride\web\cms\form;

use ride\library\form\component\AbstractComponent;
use ride\library\form\FormBuilder;

class EntryFolderComponent extends AbstractComponent {

    /**
     * Gets the data type for the data of this form component
     * @return string|null A string for a data class, null for an array
     */
    public function getDataType()
    {
        return 'ride\web\cms\asset\AssetFolderEntry';
    }

    /**
     * Parse the data to form values for the component rows
     * @param mixed $data
     * @return array $data
     */
    public function parseSetData($data) {
        $this->data = $data;
        $data = array(
            'name' => $data->name,
            'description' => $data->description,
        );

        return $data;
    }

    /**
     * Parse the form values to data of the component
     * If no thumbnail is provided and this is a media asset, find and save the thumbnail.
     * @param array $data
     * @return mixed $data
     */
    public function parseGetData(array $data)
    {
        $folder = $this->data;
        $folder->setName($data['name']);
        $folder->setDescription($data['description']);
        return $folder;
    }

    function prepareForm(FormBuilder $builder, array $options) {
        $translator = $options['translator'];
        $builder->addRow('name', 'string', array(
            'label' => $translator->translate('label.name'),
        ));
        $builder->addRow('description', 'wysiwyg', array(
            'label' => $translator->translate('label.description'),
        ));
    }
}
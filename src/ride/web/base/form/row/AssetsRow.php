<?php

namespace ride\web\base\form\row;

use ride\library\form\row\AbstractRow;
use ride\library\form\row\HtmlRow;
use ride\library\form\widget\GenericWidget;
use ride\library\orm\OrmManager;
use ride\library\validation\factory\ValidationFactory;

use ride\web\base\form\widget\AssetsWidget;

/**
 * Assets row
 */
class AssetsRow extends AbstractRow implements HtmlRow {

    /**
     * Name of the row type
     * @var string
     */
    const TYPE = 'assets';

    /**
     * Name of the option for the id of the folder to start browsing
     * @var string
     */
    const OPTION_FOLDER = 'folder';

    /**
     * Name for the option of the locale
     * @var string
     */
    const OPTION_LOCALE = 'locale';

    /**
     * Base URL of the request
     * @var string
     */
    protected $orm;

    /**
     * Sets the instance of the ORM to this row
     */
    public function setOrmManager(OrmManager $orm) {
        $this->orm = $orm;
    }

    /**
     * Gets the locale
     * @return string|null
     */
    protected function getLocale() {
        return $this->getOption(self::OPTION_LOCALE, null);
    }

    /**
     * Processes the request and updates the data of this row
     * @param array $values Submitted values
     * @return null
     */
    public function processData(array $values) {
        if (!isset($values[$this->name])) {
            return;
        }

        $locale = $this->getLocale();
        $assetModel = $this->orm->getAssetModel();

        if ($this->isMultiple()) {
            $this->data = array();

            $ids = explode(',', $values[$this->name]);
            foreach ($ids as $id) {
                $this->data[$id] = $assetModel->createProxy($id, $locale);
            }
        } else {
            $this->data = $assetModel->createProxy($id, $locale);
        }
    }

    /**
     * Sets the data to this row
     * @param mixed $data
     * @return null
     */
    public function setData($data) {
        $this->data = $data;

        if (!$this->widget) {
            return;
        }

        $this->setWidgetValue();
    }

    protected function setWidgetValue() {
        $assetModel = $this->orm->getAssetModel();
        $locale = $this->getLocale();

        $value = array();
        $assets = array();

        if ($this->data) {
            if ($this->isMultiple()) {
                foreach ($this->data as $asset) {
                    $assetId = $asset->getId();

                    $value[$assetId] = $assetId;
                    $assets[$assetId] = $assetModel->createProxy($assetId, $locale);
                }
            } else {
                $assetId = $this->data->getId();

                $value[] = $assetId;
                $assets[$assetId] = $assetModel->createProxy($assetId, $locale);
            }
        }

        $this->widget->setValue(implode(',', $value));
        $this->widget->setAssets($assets);
    }

    /**
     * Performs necessairy build actions for this row
     * @param string $namePrefix Prefix for the row name
     * @param string $idPrefix Prefix for the field id
     * @param \ride\library\validation\factory\ValidationFactory $validationFactory
     * @return null
     */
    public function buildRow($namePrefix, $idPrefix, ValidationFactory $validationFactory) {
        $folder = $this->getOption(self::OPTION_FOLDER);
        if ($folder) {
            $attributes = $this->getOption(self::OPTION_ATTRIBUTES, array());
            $attributes['data-folder'] = $folder;

            $this->setOption(self::OPTION_ATTRIBUTES, $attributes);
        }

        parent::buildRow($namePrefix, $idPrefix, $validationFactory);

        $this->widget->setIsMultiple(false);
        if ($folder) {
            if (!is_numeric($folder)) {
                $folder = $folder->getId();
            }

            $this->widget->setFolderId($folder);
        }

        $this->setWidgetValue();
    }

    /**
     * Creates the widget for this row
     * @param string $name
     * @param mixed $default
     * @param array $attributes
     * @return \ride\library\form\widget\Widget
     */
    protected function createWidget($name, $default, array $attributes) {
        return new AssetsWidget($this->type, $name, $default, $attributes);
    }

    /**
     * Prepares the row for a serializable form view
     * @return null
     */
    public function prepareForView() {
        $this->orm = null;
    }

    /**
     * Gets all the javascript files which are needed for this row
     * @return array
     */
    public function getJavascripts() {
        return array();
    }

    /**
     * Gets all the inline javascripts which are needed for this row
     * @return array
    */
    public function getInlineJavascripts() {
        return array();
    }

    /**
     * Gets all the stylesheets which are needed for this row
     * @return array
     */
    public function getStyles() {
        return array();
    }

}

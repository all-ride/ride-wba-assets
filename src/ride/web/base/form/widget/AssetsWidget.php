<?php

namespace ride\web\base\form\widget;

use ride\library\form\widget\GenericWidget;

/**
 * Form widget for the assets row
 */
class AssetsWidget extends GenericWidget {

    /**
     * Id of the starting folder
     * @var integer
     */
    protected $folderId;

    /**
     * Array with the selected assets
     * @var array
     */
    protected $assets;

    /**
     * Sets the starting folder
     * @param integer $folderId Id of the folder
     * @return null
     */
    public function setFolderId($folderId) {
        $this->folderId = $folderId;
    }

    /**
     * Gets the starting folder
     * @return integer
     */
    public function getFolderId() {
        return $this->folderId;
    }

    /**
     * Sets the selected assets
     * @param array
     * @return null
     */
    public function setAssets(array $assets) {
        $this->assets = $assets;
    }

    /**
     * Gets the selected assets
     * @return null
     */
    public function getAssets() {
        return $this->assets;
    }

}

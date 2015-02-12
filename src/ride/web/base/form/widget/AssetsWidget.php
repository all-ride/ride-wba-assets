<?php

namespace ride\web\base\form\widget;

use ride\library\form\widget\GenericWidget;

class AssetsWidget extends GenericWidget {

    protected $folderId;

    protected $assets;

    public function setFolderId($folderId) {
        $this->folderId = $folderId;
    }

    public function getFolderId() {
        return $folderId;
    }

    public function setAssets(array $assets) {
        $this->assets = $assets;
    }

    public function getAssets() {
        return $this->assets;
    }

}

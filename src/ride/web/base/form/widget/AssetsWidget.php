<?php

namespace ride\web\base\form\widget;

use ride\library\form\widget\GenericWidget;

class AssetsWidget extends GenericWidget {

    protected $assets;

    public function setAssets(array $assets) {
        $this->assets = $assets;
    }

    public function getAssets() {
        return $this->assets;
    }

}

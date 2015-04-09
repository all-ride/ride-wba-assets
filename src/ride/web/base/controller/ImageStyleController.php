<?php

namespace ride\web\base\controller;

use ride\web\orm\controller\ScaffoldController;

/**
 * Scaffold controller of the image styles
 */
class ImageStyleController extends ScaffoldController {

    /**
     * Hook after the constructor
     * @return null
     */
    protected function initialize() {
        $this->translationAdd = 'button.add.image.style';
    }

    /**
     * Hook to add extra actions in the overview
     * @param string $locale Code of the locale
     * @return array Array with the URL of the action as key and the label as
     * value
     */
    protected function getIndexActions($locale) {
        return array(
            $this->getUrl('system.orm.scaffold.index', array('model' => 'ImageTransformation', 'locale' => $locale)) => $this->getTranslator()->translate('button.image.transformations'),
        );
    }

}

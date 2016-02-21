<?php

namespace ride\web\base\controller;

use ride\web\orm\controller\ScaffoldController;

/**
 * Scaffold controller of the image transformations
 */
class ImageTransformationController extends ScaffoldController {

    /**
     * Hook after the constructor
     * @return null
     */
    protected function initialize() {
        $this->translationAdd = 'button.add.image.transformation';
    }

    /**
     * Hook to add extra actions in the overview
     * @param string $locale Code of the locale
     * @return array Array with the URL of the action as key and the label as
     * value
     */
    protected function getIndexActions($locale) {
        return array(
            (string) $this->getUrl('system.orm.scaffold.index', array('model' => 'ImageStyle', 'locale' => $locale)) => $this->getTranslator()->translate('button.image.styles'),
        );
    }

}

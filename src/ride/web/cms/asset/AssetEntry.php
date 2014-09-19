<?php

namespace ride\web\cms\asset;

use ride\application\orm\entry\AssetEntry as OrmAssetEntry;

/**
 * Data container for a asset object
 */
class AssetEntry extends OrmAssetEntry {

    /**
     * Gets the image of this asset item
     * @return string
     */
    public function getImage() {
        switch ($this->type) {
            case 'image':
                return $this->value;

                break;
            case 'youtube':
                return 'http://img.youtube.com/vi/' . $this->value . '/0.jpg';

                break;
        }

        return null;
    }

}

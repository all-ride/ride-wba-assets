<?php

namespace ride\web\cms\asset;

use ride\library\orm\model\GenericModel;

/**
 * Model for the asset items
 */
class AssetModel extends GenericModel {

    public function getAssetsForAlbum($album, $locale = null) {
        $query = $this->createQuery($locale);
        if (is_array($album)) {
            $query->addCondition('{album} IN %1%', $album);
        } else {
            $query->addCondition('{album} = %1%', $album);
        }
        $query->addOrderBy('{orderIndex} ASC');

        return $query->query();
    }

}

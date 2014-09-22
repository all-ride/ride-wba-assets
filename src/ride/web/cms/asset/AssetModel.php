<?php

namespace ride\web\cms\asset;

use ride\library\orm\model\GenericModel;

/**
 * Model for the asset items
 */
class AssetModel extends GenericModel {

    public function getAssetsForFolder($asset, $locale = null) {
        $query = $this->createQuery($locale);
        if (is_array($asset)) {
            $query->addCondition('{asset} IN %1%', $asset);
        } else {
            $query->addCondition('{asset} = %1%', $asset);
        }
        $query->addOrderBy('{orderIndex} ASC');

        return $query->query();
    }
}

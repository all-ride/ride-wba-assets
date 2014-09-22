<?php

namespace ride\web\cms\asset;

use ride\library\orm\model\GenericModel;

/**
 * Model for the asset items
 */
class AssetModel extends GenericModel {

    public function getAssetsForFolder($folder, $locale = null) {
        $query = $this->createQuery($locale);
        if (is_array($folder)) {
            $query->addCondition('{folder} IN %1%', $folder);
        } else {
            $query->addCondition('{folder} = %1%', $folder);
        }
        $query->addOrderBy('{orderIndex} ASC');

        return $query->query();
    }
}

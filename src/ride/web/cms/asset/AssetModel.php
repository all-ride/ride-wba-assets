<?php

namespace ride\web\cms\asset;

use ride\library\orm\model\GenericModel;

/**
 * Model for the asset items
 */
class AssetModel extends GenericModel {

    public function getAssetsForFolder($folder, $locale = null) {
        if ($folder == 0) {
            $folder = NULL;
        }
        $query = $this->createQuery($locale);
        if (is_array($folder)) {
            $query->addCondition('{folder} IN %1%', $folder);
        } else {
            if ($folder != NULL) {
                $query->addCondition('{folder} = %1%', $folder);
            }
            else {
                $query->addCondition('{folder} IS NULL');
            }
        }
        $query->addOrderBy('{orderIndex} ASC');
        return $query->query();
    }
}

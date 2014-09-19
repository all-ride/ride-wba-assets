<?php

namespace ride\web\cms\media;

use ride\library\orm\model\GenericModel;

/**
 * Model for the media items
 */
class MediaModel extends GenericModel {

    public function getMediaForAlbum($album, $locale = null) {
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

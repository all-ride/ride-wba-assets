<?php

namespace ride\web\cms\media;

use ride\application\orm\entry\MediaEntry as OrmMediaEntry;

/**
 * Data container for a media object
 */
class MediaEntry extends OrmMediaEntry {

    /**
     * Gets the image of this media item
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

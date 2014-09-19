<?php

namespace ride\web\cms\media;

use ride\application\orm\entry\MediaAlbumEntry as OrmMediaAlbumEntry;

/**
 * Data container for a media album
 */
class MediaAlbumEntry extends OrmMediaAlbumEntry {

    /**
     * Variable to attach the children of this album to
     * @var array
     */
    public $children;

    /**
     * Get a string representation of the album
     * @return string
     */
    public function __toString() {
        return $this->getPath() . ': ' . $this->name;
    }

    /**
     * Get the full path of the album. The path is used for the parent field of a album.
     * @return string
     */
    public function getPath() {
        if (!$this->parent) {
            return $this->id;
        }

        return $this->parent . MediaAlbumModel::PATH_SEPARATOR . $this->id;
    }

    /**
     * Get the album id of the root of this album
     * @return integer
     */
    public function getRootAlbumId() {
        if (!$this->parent) {
            return $this->id;
        }

        $tokens = explode(MediaAlbumModel::PATH_SEPARATOR, $this->parent);

        return array_shift($tokens);
    }

    /**
     * Get the album id of the parent
     * @return integer
     */
    public function getParentAlbumId() {
        if (!$this->parent) {
            return null;
        }

        $ids = explode(MediaAlbumModel::PATH_SEPARATOR, $this->parent);

        return array_pop($ids);
    }

    /**
     * Checks if the provided album is a parent album of this album
     * @param MediaElbumEntry $album The album to check as a parent
     * @return boolean True if the provided album is a parent, false otherwise
     */
    public function hasParentAlbum(MediaAlbumEntry $album) {
        $ids = explode(MediaAlbumModel::PATH_SEPARATOR, $this->parent);

        return in_array($album->id, $ids);
    }

    /**
     * Gets the level of this album
     * @return integer
     */
    public function getLevel() {
        if (!$this->parent) {
            return 0;
        }

        return substr_count($this->parent, MediaAlbumModel::PATH_SEPARATOR) + 1;
    }

}

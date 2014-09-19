<?php

namespace ride\web\cms\asset;

use ride\application\orm\entry\AssetFolderEntry as OrmAssetFolderEntry;

/**
 * Data container for a asset folder
 */
class AssetFolderEntry extends OrmAssetFolderEntry {

    /**
     * Variable to attach the children of this folder to
     * @var array
     */
    public $children;

    /**
     * Get a string representation of the folder
     * @return string
     */
    public function __toString() {
        return $this->getPath() . ': ' . $this->name;
    }

    /**
     * Get the full path of the folder. The path is used for the parent field of a folder.
     * @return string
     */
    public function getPath() {
        if (!$this->parent) {
            return $this->id;
        }

        return $this->parent . AssetFolderModel::PATH_SEPARATOR . $this->id;
    }

    /**
     * Get the folder id of the root of this folder
     * @return integer
     */
    public function getRootFolderId() {
        if (!$this->parent) {
            return $this->id;
        }

        $tokens = explode(AssetFolderModel::PATH_SEPARATOR, $this->parent);

        return array_shift($tokens);
    }

    /**
     * Get the folder id of the parent
     * @return integer
     */
    public function getParentFolderId() {
        if (!$this->parent) {
            return null;
        }

        $ids = explode(AssetFolderModel::PATH_SEPARATOR, $this->parent);

        return array_pop($ids);
    }

    /**
     * Checks if the provided folder is a parent folder of this folder
     * @param AssetFolderEntry $folder The folder to check as a parent
     * @return boolean True if the provided folder is a parent, false otherwise
     */
    public function hasParentFolder(AssetFolderEntry $folder) {
        $ids = explode(AssetFolderModel::PATH_SEPARATOR, $this->parent);

        return in_array($folder->id, $ids);
    }

    /**
     * Gets the level of this folder
     * @return integer
     */
    public function getLevel() {
        if (!$this->parent) {
            return 0;
        }

        return substr_count($this->parent, AssetFolderModel::PATH_SEPARATOR) + 1;
    }

}

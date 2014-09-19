<?php

namespace ride\web\cms\asset;

use ride\library\orm\model\GenericModel;
use ride\library\orm\query\ModelQuery;
use ride\library\validation\exception\ValidationException;
use ride\library\validation\ValidationError;

use \Exception;
use ride\web\cms\asset\AssetFolderEntry;

/**
 * Model for the folders of asset items
 */
class AssetFolderModel extends GenericModel {

    /**
     * Separator for the node path
     * @var string
     */
    const PATH_SEPARATOR = '-';

    public function getDataList(array $options = null) {
        $locale = isset($options['locale']) ? $options['locale'] : null;

        $tree = $this->getFolderTree(null, null, null, $locale);

        return $this->createListFromFolderTree($tree);
    }

    /**
     * Get a folder by it's id
     * @param int $id id of the node
     * @param integer $recursiveDepth set to false to skip the loading of the node settings
     * @param string $locale code of the locale
     * @return Folder
     */
    public function getFolder($id, $recursiveDepth = 1, $locale = null, $fetchUnlocalized = null) {
        $query = $this->createQuery($locale);
        $query->setRecursiveDepth(0);
        if ($fetchUnlocalized) {
            $query->setWillFetchUnlocalized(true);
        }

        if (is_numeric($id)) {
            $query->addCondition('{id} = %1%', $id);
        } elseif (is_string($id)) {
            $query->addCondition('{slug} = %1%', $id);
        } else {
            throw new Exception('Could not get folder: invalid id provided (' . gettype($id) . ')');
        }

        $folder = $query->queryFirst();

        if (!$folder) {
            throw new Exception('Could not find folder with id ' . $id);
        }

        if ($recursiveDepth != 0) {
            $folder->children = $this->getFolderTree($folder, null, $recursiveDepth, $locale, $fetchUnlocalized);
        }

        return $folder;
    }

    /**
     * Get the root node of a node
     * @param int|Folder $folder id of the node or an instance of a Folder
     * @param integer $recursiveDepth
     * @param string $locale code of the locale
     * @return Folder
     */
    public function getRootFolder($folder, $recursiveDepth = 1, $locale = null) {
        if (is_numeric($folder)) {
            $query = $this->createQuery($locale);
            $query->setFields('{id}, {parent}');
            $query->addCondition('{id} = %1%', $folder);
            $folder = $query->queryFirst();

            if (!$folder) {
                throw new Exception('Could not find folder id ' . $folder);
            }
        }

        $rootFolderId = $folder->getRootFolderId();

        return $this->getFolder($rootFolderId, $recursiveDepth, $locale);
    }

    /**
     * Create an array with the node hierarchy. Usefull for an options field.
     * @param array $tree array with Album objects
     * @param string $prefix prefix for the node names
     * @return array Array with the node id as key and the node name as value
     */
    public function createListFromFolderTree(array $tree, $separator = '/', $prefix = '') {
        $list = array();

        foreach ($tree as $folder) {
            $newPrefix = $prefix . $separator . $folder->name;

            $list[$folder->id] = $newPrefix;

            if ($folder->children) {
                $children = $this->createListFromFolderTree($folder->children, $separator, $newPrefix);

                $list = $list + $children;
            }
        }

        return $list;
    }

    /**
     * Get an array with the nodes and specify the number of levels for fetching the children of the nodes.
     * @param int|Album $parent the parent node
     * @param string|array $excludes id's of nodes which are not to be included in the result
     * @param int $maxDepth maximum number of nested levels will be looked for
     * @param string $locale Locale code
     * @param boolean $loadSettings set to true to load the AlbumSettings object of the node
     * @param boolean $isFrontend Set to true to get only the nodes available in the frontend*
     * @return array Array with the node id as key and the node as value
     */
    public function getFolderTree($parent = null, $excludes = null, $maxDepth = null, $locale = null, $includeUnlocalized = null) {
        if ($excludes) {
            if (!is_array($excludes)) {
                $excludes = array($excludes);
            }
        } else {
            $excludes = array();
        }

        if ($parent && is_numeric($parent)) {
            $parent = $this->getAlbum($parent, 0, $locale);
        }

        $tree = $this->getAlbums($parent, $excludes, $maxDepth, $locale, $includeUnlocalized);

        return $tree;
    }

    /**
     * Get the nodes with their nested children for a parent node
     * @param AssetFolderEntry $parent
     * @param string|array $excludes
     * @param integer $maxDepth
     * @param string $locale
     * @param boolean $includeUnlocalized
     * @return array Array with the node id as key and the Folder object with nested children as value
     */
    public function getFolder(AssetFolderEntry $parent = null, $excludes = null, $maxDepth = null, $locale = null, $includeUnlocalized = null) {
        $query = $this->createQuery($locale);
        $query->setRecursiveDepth(0);
        $query->setFetchUnlocalized($includeUnlocalized);

        $path = "";
        if ($parent) {
            $path = $parent->getPath();
            $query->addCondition('{parent} = %1% OR {parent} LIKE %2%', $path, $path . self::PATH_SEPARATOR . '%');
        }

        if ($parent && $maxDepth !== null) {
            if ($parent) {
                $maxDepth = $parent->getLevel() + $maxDepth;
            }

            $query->addCondition('(LENGTH({parent}) - LENGTH(REPLACE({parent}, %1%, %2%))) <= %3%', self::PATH_SEPARATOR, '', $maxDepth);
        }

        if ($excludes) {
            if (!is_array($excludes)) {
                $excludes = array($excludes);
            }

            $query->addCondition('{id} NOT IN (%1%)', implode(', ', $excludes));
        }

        $query->addOrderBy('{parent} ASC, {orderIndex} ASC');
        $folders = $query->query();

        // create an array by path
        $foldersByParent = array();
        foreach ($folders as $folder) {
            if (!array_key_exists($folder->parent, $foldersByParent)) {
                $foldersByParent[$folder->parent] = array();
            }

            $folderParent = $folder->parent;
            if (!$folder->parent) {
                $folderParent = 0;
            }

            $foldersByParent[$folderParent][$folder->id] = $folder;
        }

        // link the nested nodes
        $folders = array();
        foreach ($foldersByParent as $folderPath => $pathFolders) {
            if ($parent && $folderPath == $path) {
                $folders = $pathFolders;
            }

            foreach ($pathFolders as $pathFolder) {
                $pathFolderPath = $pathFolder->getPath();
                if (!array_key_exists($pathFolderPath, $foldersByParent)) {
                    continue;
                }

                $pathFolder->children = $foldersByParent[$pathFolderPath];

                unset($foldersByParent[$pathFolderPath]);
            }
        }

        if ($parent) {
            return $folders;
        } elseif ($foldersByParent) {
            return $foldersByParent[0];
        } else {
            return array();
        }
    }

    /**
     * Gets the number of children levels for the provided node
     * @param Folder $folder
     * @return integer
     */
    public function getChildrenLevelsForAlbum(AssetFolderEntry $folder) {
        $path = $folder->getPath();

        $query = $this->createQuery();
        $query->setRecursiveDepth(0);
        $query->setFields('MAX(LENGTH({parent}) - LENGTH(REPLACE({parent}, %1%, %2%))) + 1 AS levels', self::PATH_SEPARATOR, '');
        $query->addCondition('{parent} LIKE %1%', $path . '%');

        $result = $query->queryFirst();

        return $result->levels - $folder->getLevel();
    }

    /**
     * Reorder the folders
     * @param integer $parent Id of the parent node
     * @param array $folderOrder Array with the node id as key and the number of children as value
     * @return null
     */
    public function orderFolders($parent, array $folderOrder) {
        $query = $this->createQuery();
        $query->setRecursiveDepth(0);
        $query->setFields('{id}, {parent}');
        $query->addCondition('{id} = %1%', $parent);

        $parent = $query->queryFirst();
        if (!$parent) {
            throw new Exception('Could not find folder id ' . $parent->id);
        }

        $path = $parent->getPath();
        $orderIndex = 1;
        $child = 0;

        $paths = array();
        $orderIndexes = array();
        $children = array();

        $query = $this->createQuery();
        $query->setRecursiveDepth(0);
        $query->setFields('{id}, {parent}, {orderIndex}');
        $query->addCondition('{parent} = %1% OR {parent} LIKE %2%', $path, $path . self::PATH_SEPARATOR . '%');
        $folders = $query->query();

        $transactionStarted = $this->startTransaction();
        try {
            foreach ($folderOrder as $folderId => $numChildren) {
                if (!array_key_exists($folderId, $folders)) {
                    throw new Exception('Folder with id ' . $folderId . ' is not a child of node ' . $parent->id);
                }

                $folders[$folderId]->parent = $path;
                $folders[]->orderIndex = $orderIndex;

                $this->save($folders[$folderId]);

                $orderIndex++;

                if ($child) {
                    $child--;

                    if (!$child) {
                        $orderIndex = array_pop($orderIndexes);
                        $path = array_pop($paths);
                        $child = array_pop($children);
                    }
                }

                if ($numChildren) {
                    array_push($orderIndexes, $orderIndex);
                    array_push($paths, $path);
                    array_push($children, $child);

                    $orderIndex = 1;
                    $path = $folders[$folderId]->getPath();
                    $child = $numChildren;
                }

                unset($folders[$folderId]);
            }

            if ($folders) {
                throw new Exception('Not all folders of the provided parent are provided in the order array: missing folders ' . implode(', ', array_keys($folders)));
            }

            $this->commitTransaction($transactionStarted);
        } catch (Exception $exception) {
            $this->rollbackTransaction($transactionStarted);

            throw $exception;
        }
    }

    /**
     * Save a node to the model
     * @param Folder $folder
     * @return null
     */
    protected function saveEntry($folder) {
        if (!$folder->id) {
            if (!$folder->orderIndex) {
                $folder->orderIndex = $this->getNewOrderIndex($folder->parent);
            }
        }

        parent::saveEntry($folder);
    }

    /**
     * Get a order index for a new node
     * @param string $parent path of the parent of the new node
     * @return int new order index
     */
    private function getNewOrderIndex($parent) {
        $query = $this->createQuery();
        $query->setRecursiveDepth(0);
        $query->setFields('MAX({orderIndex}) AS maxOrderIndex');

        if ($parent) {
            $query->addCondition('{parent} = %1%', $parent);
        } else {
            $query->addCondition('{parent} IS NULL');
        }

        $data = $query->queryFirst();

        return $data->maxOrderIndex + 1;
    }

    /**
     * Deletes the data from the database
     * @param Folder $data
     * @return folder
     */
    protected function deleteData($data) {
        $data = parent::deleteData($data);

        if (!$data) {
            return $data;
        }

        $path = $data->getPath();

        $query = $this->createQuery();
        $query->setRecursiveDepth(0);
        $query->setFetchUnlocalizedData(true);
        $query->setFields('{id}');
        $query->addCondition('{parent} = %1% OR {parent} LIKE %2%', $path, $path . self::PATH_SEPARATOR . '%');
        $children = $query->query();

        $this->delete($children);

        return $data;
    }

}

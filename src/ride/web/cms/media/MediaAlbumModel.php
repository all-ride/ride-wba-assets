<?php

namespace ride\web\cms\media;

use ride\library\orm\model\GenericModel;
use ride\library\orm\query\ModelQuery;
use ride\library\validation\exception\ValidationException;
use ride\library\validation\ValidationError;

use \Exception;

/**
 * Model for the albums of media items
 */
class MediaAlbumModel extends GenericModel {

    /**
     * Separator for the node path
     * @var string
     */
    const PATH_SEPARATOR = '-';

    public function getDataList(array $options = null) {
        $locale = isset($options['locale']) ? $options['locale'] : null;

        $tree = $this->getAlbumTree(null, null, null, $locale);

        return $this->createListFromAlbumTree($tree);
    }

    /**
     * Get a album by it's id
     * @param int $id id of the node
     * @param integer $recursiveDepth set to false to skip the loading of the node settings
     * @param string $locale code of the locale
     * @return Album
     */
    public function getAlbum($id, $recursiveDepth = 1, $locale = null, $fetchUnlocalized = null) {
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
            throw new Exception('Could not get album: invalid id provided (' . gettype($id) . ')');
        }

        $album = $query->queryFirst();

        if (!$album) {
            throw new Exception('Could not find album with id ' . $id);
        }

        if ($recursiveDepth != 0) {
            $album->children = $this->getAlbumTree($album, null, $recursiveDepth, $locale, $fetchUnlocalized);
        }

        return $album;
    }

    /**
     * Get the root node of a node
     * @param int|Album $album id of the node or an instance of a Album
     * @param integer $recursiveDepth
     * @param string $locale code of the locale
     * @return Album
     */
    public function getRootAlbum($album, $recursiveDepth = 1, $locale = null) {
        if (is_numeric($album)) {
            $query = $this->createQuery($locale);
            $query->setFields('{id}, {parent}');
            $query->addCondition('{id} = %1%', $album);
            $album = $query->queryFirst();

            if (!$album) {
                throw new Exception('Could not find album id ' . $id);
            }
        }

        $rootAlbumId = $album->getRootAlbumId();

        return $this->getAlbum($rootAlbumId, $recursiveDepth, $locale);
    }

    /**
     * Create an array with the node hierarchy. Usefull for an options field.
     * @param array $tree array with Album objects
     * @param string $prefix prefix for the node names
     * @return array Array with the node id as key and the node name as value
     */
    public function createListFromAlbumTree(array $tree, $separator = '/', $prefix = '') {
        $list = array();

        foreach ($tree as $album) {
            $newPrefix = $prefix . $separator . $album->name;

            $list[$album->id] = $newPrefix;

            if ($album->children) {
                $children = $this->createListFromAlbumTree($album->children, $separator, $newPrefix);

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
    public function getAlbumTree($parent = null, $excludes = null, $maxDepth = null, $locale = null, $includeUnlocalized = null) {
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
     * @param MediaAlbumEntry $parent
     * @param string|array $excludes
     * @param integer $maxDepth
     * @param string $locale
     * @param boolean $includeUnlocalized
     * @return array Array with the node id as key and the Album object with nested children as value
     */
    public function getAlbums(MediaAlbumEntry $parent = null, $excludes = null, $maxDepth = null, $locale = null, $includeUnlocalized = null) {
        $query = $this->createQuery($locale);
        $query->setRecursiveDepth(0);
        $query->setFetchUnlocalized($includeUnlocalized);

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
        $albums = $query->query();

        // create an array by path
        $albumsByParent = array();
        foreach ($albums as $album) {
            if (!array_key_exists($album->parent, $albumsByParent)) {
                $albumsByParent[$album->parent] = array();
            }

            $albumParent = $album->parent;
            if (!$album->parent) {
                $albumParent = 0;
            }

            $albumsByParent[$albumParent][$album->id] = $album;
        }

        // link the nested nodes
        $albums = array();
        foreach ($albumsByParent as $albumPath => $pathAlbums) {
            if ($parent && $albumPath == $path) {
                $albums = $pathAlbums;
            }

            foreach ($pathAlbums as $pathAlbum) {
                $pathAlbumPath = $pathAlbum->getPath();
                if (!array_key_exists($pathAlbumPath, $albumsByParent)) {
                    continue;
                }

                $pathAlbum->children = $albumsByParent[$pathAlbumPath];

                unset($albumsByParent[$pathAlbumPath]);
            }
        }

        if ($parent) {
            return $albums;
        } elseif ($albumsByParent) {
            return $albumsByParent[0];
        } else {
            return array();
        }
    }

    /**
     * Gets the number of children levels for the provided node
     * @param Album $album
     * @return integer
     */
    public function getChildrenLevelsForAlbum(MediaAlbumEntry $album) {
        $path = $album->getPath();

        $query = $this->createQuery();
        $query->setRecursiveDepth(0);
        $query->setFields('MAX(LENGTH({parent}) - LENGTH(REPLACE({parent}, %1%, %2%))) + 1 AS levels', self::PATH_SEPARATOR, '');
        $query->addCondition('{parent} LIKE %1%', $path . '%');

        $result = $query->queryFirst();

        return $result->levels - $album->getLevel();
    }

    /**
     * Reorder the albums
     * @param integer $parent Id of the parent node
     * @param array $albumOrder Array with the node id as key and the number of children as value
     * @return null
     */
    public function orderAlbums($parent, array $albumOrder) {
        $query = $this->createQuery();
        $query->setRecursiveDepth(0);
        $query->setFields('{id}, {parent}');
        $query->addCondition('{id} = %1%', $parent);

        $parent = $query->queryFirst();
        if (!$parent) {
            throw new Exception('Could not find album id ' . $id);
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
        $albums = $query->query();

        $transactionStarted = $this->startTransaction();
        try {
            foreach ($albumOrder as $albumId => $numChildren) {
                if (!array_key_exists($albumId, $albums)) {
                    throw new Exception('Album with id ' . $albumId . ' is not a child of node ' . $parent->id);
                }

                $albums[$albumId]->parent = $path;
                $albums[$albumId]->orderIndex = $orderIndex;

                $this->save($albums[$albumId]);

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
                    $path = $albums[$albumId]->getPath();
                    $child = $numChildren;
                }

                unset($albums[$albumId]);
            }

            if ($albums) {
                throw new Exception('Not all albums of the provided parent are provided in the order array: missing albums ' . implode(', ', array_keys($albums)));
            }

            $this->commitTransaction($transactionStarted);
        } catch (Exception $exception) {
            $this->rollbackTransaction($transactionStarted);

            throw $exception;
        }
    }

    /**
     * Save a node to the model
     * @param Album $album
     * @return null
     */
    protected function saveEntry($album) {
        if (!$album->id) {
            if (!$album->orderIndex) {
                $album->orderIndex = $this->getNewOrderIndex($album->parent);
            }
        }

        parent::saveEntry($album);
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
     * @param Album $data
     * @return Album
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

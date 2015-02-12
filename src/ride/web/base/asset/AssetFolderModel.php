<?php

namespace ride\web\base\asset;

use ride\library\orm\model\GenericModel;

use \Exception;

/**
 * Model for the folders of asset items
 */
class AssetFolderModel extends GenericModel {

    /**
     * Separator for the node path
     * @var string
     */
    const PATH_SEPARATOR = '-';

    public function getOptionList($locale = null, $fetchUnlocalized = null) {
        $query = $this->createQuery($locale);
        if ($fetchUnlocalized != null) {
            $query->setFetchUnlocalized($fetchUnlocalized);
        }

        $query->addOrderBy('{parent} ASC, {orderIndex} ASC');

        $folders = $query->query();

        return $this->createTree($folders, array());
    }

    protected function createTree($folders, $options, $path = 0) {
        foreach ($folders as $folderId => $folder) {
            $parent = $folder->getParent();
            if ($parent != $path) {
                continue;
            }

            $prefix = '';
            if ($path != null) {
                $prefix = str_repeat('-- ', substr_count($path, self::PATH_SEPARATOR) + 1);
            }

            $options[$folderId] = $prefix . $folder->getName();

            $options = $this->createTree($folders, $options, $folder->getPath());
        }

        return $options;
    }

    /**
     * Get a folder by it's id
     * @param integer|string $id Id or slug of the folder
     * @param string $locale Code of the locale
     * @param boolean $fetchUnlocalized
     * @return Folder|null
     */
    public function getFolder($id, $locale = null, $fetchUnlocalized = null) {
        if (!$id) {
            $folder = $this->createEntry();
            $folder->setId(0);

            return $folder;
        }

        $query = $this->createQuery($locale);
        $query->setRecursiveDepth(0);
        if ($fetchUnlocalized !== null) {
            $query->setFetchUnlocalized($fetchUnlocalized);
        }

        if (is_numeric($id)) {
            $query->addCondition('{id} = %1%', $id);
        } elseif (is_string($id)) {
            $query->addCondition('{slug} = %1%', $id);
        } else {
            throw new Exception('Could not get folder: invalid id provided (' . gettype($id) . ')');
        }

        return $query->queryFirst();
    }

    /**
     * Get the folders with their nested children for a parent node
     * @param AssetFolderEntry $parent
     * @param string $locale
     * @param boolean $includeUnlocalized
     * @param integer $maxDepth
     * @param string|array $excludes
     * @return array Array with the folder id as key and the Folder instance
     * with requested children as value
     */
    public function getFolders(AssetFolderEntry $parent = null, $locale = null, $includeUnlocalized = null, array $filter = null, $limit = 0, $page = 1, $maxDepth = 1, $excludes = null) {
        if (isset($filter['type']) && $filter['type'] != 'all' && $filter['type'] != 'folder') {
            return array();
        }

        if ($parent && !$parent->getId()) {
            $parent = null;
        }

        $query = $this->createFoldersQuery($parent, $locale, $includeUnlocalized, $filter, $maxDepth, $excludes);

        if ($limit) {
            $query->setLimit($limit, ($page - 1) * $limit);
        }

        $query->addOrderBy('{parent} ASC, {orderIndex} ASC');
        $folders = $query->query();

        // order folders by path
        $foldersByParent = array();
        foreach ($folders as $folder) {
            $folderParent = $folder->getParent();
            if (!$folderParent) {
                $folderParent = 0;
            }

            if (!array_key_exists($folderParent, $foldersByParent)) {
                $foldersByParent[$folderParent] = array();
            }

            $foldersByParent[$folderParent][$folder->getId()] = $folder;
        }

        $path = null;
        if ($parent && $parent->getId())  {
            $path = $parent->getPath();
        }

        // restore the tree hierrarchy of the folders
        $folders = array();
        foreach ($foldersByParent as $folderPath => $pathFolders) {
            if ($path) {
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
     * Counts the folders for a parent node
     * @param AssetFolderEntry $parent
     * @param string $locale
     * @param boolean $includeUnlocalized
     * @param integer $maxDepth
     * @param string|array $excludes
     * @return integer Number of folders
     */
    public function countFolders(AssetFolderEntry $parent = null, $locale = null, $includeUnlocalized = null, array $filter = null, $limit = 0, $page = 1, $maxDepth = 1, $excludes = null) {
        if (isset($filter['type']) && $filter['type'] != 'all' && $filter['type'] != 'folder') {
            return 0;
        }

        $filter['count'] = true;

        $query = $this->createFoldersQuery($parent, $locale, $includeUnlocalized, $filter, $maxDepth, $excludes);

        return $query->count();
    }

    /**
     * Creates a query to get or count the folders for a parent node
     * @param AssetFolderEntry $parent
     * @param string $locale
     * @param boolean $includeUnlocalized
     * @param integer $maxDepth
     * @param string|array $excludes
     * @return \ride\library\orm\query\ModelQuery
     */
    protected function createFoldersQuery(AssetFolderEntry $parent = null, $locale = null, $includeUnlocalized = null, array $filter = null, $maxDepth = null, $excludes = null) {
        if ($parent && !$parent->getId()) {
            $parent = null;
        }

        // fetch the subfolders
        $query = $this->createQuery($locale);
        $query->setFetchUnlocalized($includeUnlocalized);

        if ($parent) {
            $path = $parent->getPath();

            if (isset($filter['count'])) {
                $query->addCondition('{parent} = %1%', $path, $path . self::PATH_SEPARATOR . '%');
            } else {
                $query->addCondition('{parent} = %1% OR {parent} LIKE %2%', $path, $path . self::PATH_SEPARATOR . '%');
            }
        } else {
            if (isset($filter['count'])) {
                $query->addCondition('{parent} = %1% OR {parent} IS NULL', 0);
            } else {
                $path = '';
            }
        }

        if ($maxDepth !== null) {
            if ($parent) {
                $maxDepth = $parent->getLevel() + $maxDepth;
            }

            $query->addCondition('(LENGTH({parent}) - LENGTH(REPLACE({parent}, %1%, %2%))) <= %3%', self::PATH_SEPARATOR, '', $maxDepth);
        }

        if (isset($filter['date']) && $filter['date'] != 'all') {
            if ($filter['date'] == 'today') {
                $filter['date'] = date('Y-m-d');
            }

            $tokens = explode('-', $filter['date']);
            $numTokens = count($tokens);
            if ($numTokens == 1) {
                // year
                $from = mktime(0, 0, 0, 1, 1, $tokens[0]);
                $till = mktime(23, 59, 59, 12, 31, $tokens[0]);
            } elseif ($numTokens == 2) {
                // month
                $from = mktime(0, 0, 0, $tokens[1], 1, $tokens[0]);
                $till = mktime(23, 59, 59, $tokens[1], date('t', $from), $tokens[0]);
            } else {
                $from = mktime(0, 0, 0, $tokens[1], $tokens[2], $tokens[0]);
                $till = mktime(23, 59, 59, $tokens[1], $tokens[2], $tokens[0]);
            }

            $query->addCondition('%1% <= {dateAdded} AND {dateAdded} <= %2%', $from, $till);
        }

        if ($excludes) {
            if (!is_array($excludes)) {
                $excludes = array($excludes);
            }

            $query->addCondition('{id} NOT IN (%1%)', implode(', ', $excludes));
        }

        return $query;
    }

    /**
     * Counts the items in the provided folder
     * @param AssetFolderEntry $folder
     * @param string $locale Code of the locale
     * @param boolean $fetchUnlocalized
     * @param array $filter
     * @param boolean $flatten Set to true to ignore folders and get all child
     * assets
     * @return integer Number of items
     */
    public function countItems(AssetFolderEntry $folder, $locale = null, $fetchUnlocalized = null, array $filter = null, $flatten = false) {
        $numItems = 0;

        // get the folders
        if ($flatten) {
            $flattenFilter = $filter;
            $flattenFilter['type'] = 'folder';

            $children = $this->getFolders($folder, $locale, $fetchUnlocalized, $flattenFilter);
            foreach ($children as $child) {
                $numItems += $this->countItems($child, $locale, $fetchUnlocalized, $filter, true);
            }
        } else {
            $numItems = $this->countFolders($folder, $locale, $fetchUnlocalized, $filter);
        }

        // get the assets
        $assetModel = $this->orm->getAssetModel();

        $numItems += $assetModel->countByFolder($folder->getId(), $locale, $fetchUnlocalized, $filter);

        return $numItems;
    }

    /**
     * Gets the items in the provided folder
     * @param AssetFolderEntry $folder
     * @param string $locale Code of the locale
     * @param boolean $fetchUnlocalized
     * @param array $filter
     * @param boolean $flatten Set to true to ignore folders and get all child
     * assets
     * @param array $items Current item result, needed for recursive calls
     * @return array Array with folders and assets ordered by their order index.
     * When flattening, array with all the child assets and uhm quite random...
     */
    public function getItems(AssetFolderEntry $folder, $locale = null, $fetchUnlocalized = null, array $filter = null, $flatten = false, $limit = 0, $page = 1, array $items = array()) {
        $numItems = count($items);

        // get the folders
        if ($flatten) {
            $flattenFilter = $filter;
            $flattenFilter['type'] = 'folder';

            $children = $this->getFolders($folder, $locale, $fetchUnlocalized, $flattenFilter, $limit, $page);
            foreach ($children as $child) {
                $items = $this->getItems($child, $locale, $fetchUnlocalized, $filter, true, $limit, $page, $items);
            }
        } else {
            $children = $this->getFolders($folder, $locale, $fetchUnlocalized, $filter, $limit, $page);
            foreach ($children as $child) {
                $items[$child->getOrderIndex()] = $child;

                $numItems++;
                if ($limit && $numItems == $limit) {
                    break;
                }
            }
        }

        // get the assets
        if (!$limit || ($limit && $numItems < $limit)) {
            $assetModel = $this->orm->getAssetModel();

            $assets = $assetModel->getByFolder($folder->getId(), $locale, $fetchUnlocalized, $filter, $limit, $page);
            foreach ($assets as $asset) {
                if ($flatten) {
                    $items[$asset->getId()] = $asset;
                } else {
                    $items[$asset->getOrderIndex()] = $asset;
                }

                $numItems++;
                if ($limit && $numItems == $limit) {
                    break;
                }
            }
        }

        if (!$flatten) {
            ksort($items);
        }

        return $items;
    }

    /**
     * Get the root node of a node
     * @param int|Folder $folder id of the node or an instance of a Folder
     * @param string $locale code of the locale
     * @return Folder
     */
    public function getRootFolder($folder, $locale = null) {
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

        return $this->getFolder($rootFolderId, $locale);
    }

    /**
     * Gets the number of children levels for the provided node
     * @param Folder $folder
     * @return integer
     */
    public function getChildrenLevelsForFolder(AssetFolderEntry $folder) {
        $path = $folder->getPath();

        $query = $this->createQuery();
        $query->setRecursiveDepth(0);
        $query->setFields('MAX(LENGTH({parent}) - LENGTH(REPLACE({parent}, %1%, %2%))) + 1 AS levels', self::PATH_SEPARATOR, '');
        $query->addCondition('{parent} LIKE %1%', $path . '%');

        $result = $query->queryFirst();

        return $result->levels - $folder->getLevel();
    }

    /**
     * Save a node to the model
     * @param Folder $folder
     * @return null
     */
    protected function saveEntry($folder) {
        if (!$folder->getId() && !$folder->getOrderIndex()) {
            $parentFolderId = $folder->getParentFolderId();
            if ($parentFolderId) {
                $orderIndex = $this->getNewOrderIndex($this->createProxy($parentFolderId));
            } else {
                $orderIndex = $this->getNewOrderIndex(null);
            }

            $folder->setOrderIndex($orderIndex);
        }

        parent::saveEntry($folder);
    }

    /**
     * Deletes the data from the database
     * @param AssetFolderEntry $folder
     * @return folder
     */
    protected function deleteEntry($folder) {
        $folder = parent::deleteEntry($folder);
        if (!$folder) {
            return $folder;
        }

        $path = $folder->getPath();

        $query = $this->createQuery();
        $query->setFetchUnlocalized(true);
        $query->setFields('{id}');
        $query->addCondition('{parent} = %1% OR {parent} LIKE %2%', $path, $path . self::PATH_SEPARATOR . '%');
        $children = $query->query();

        $this->delete($children);

        return $folder;
    }

    /**
     * Get an order index for a new item in a folder
     * @param string $parent path of the parent of the new node
     * @return int new order index
     */
    public function getNewOrderIndex(AssetFolderEntry $parent = null) {
        $assetModel = $this->orm->getAssetModel();

        $folderQuery = $this->createQuery();
        $folderQuery->setFields('MAX({orderIndex}) AS maxOrderIndex');

        $assetQuery = $assetModel->createQuery();
        $assetQuery->setFields('MAX({orderIndex}) AS maxOrderIndex');

        if ($parent) {
            $folderQuery->addCondition('{parent} = %1%', $parent->getPath());
            $assetQuery->addCondition('{folder} = %1%', $parent->getId());
        } else {
            $folderQuery->addCondition('{parent} IS NULL OR {parent} = %1%', '0');
            $assetQuery->addCondition('{folder} IS NULL');
        }

        $result = $folderQuery->queryFirst();
        $folderWeight = $result->maxOrderIndex + 1;

        $result = $assetQuery->queryFirst();
        $assetWeight = $result->maxOrderIndex + 1;

        return max($folderWeight, $assetWeight);
    }

    /**
     * Returns the full breadcrumb for this folder.
     * @param AssetFolderEntry $folder The folder to render the breadcrumbs for.
     * @return Breadcrumb array
     */
    public function getBreadcrumbs(AssetFolderEntry $folder = null) {
        $folders = array();

        while ($folder && $folder->getId()) {
            $folders[$folder->getId()] = $folder->getName();

            $parentFolderId = $folder->getParentFolderId();
            if ($parentFolderId) {
                $folder = $this->getFolder($parentFolderId, $folder->getLocale(), true);
            } else {
                $folder = null;
            }
        }

        return array_reverse($folders, true);
    }

}

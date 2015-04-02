<?php

namespace ride\web\base\asset;

use ride\library\http\Header;
use ride\library\http\Response;
use ride\library\image\exception\ImageException;
use ride\library\i18n\translator\Translator;
use ride\library\orm\model\GenericModel;

/**
 * Model for the asset items
 */
class AssetModel extends GenericModel {

    /**
     * Gets an entry list as a flat list
     * @param string $locale Code of the locale
     * @return array Array with the id of the entry as key and the title format
     * as value
     */
    public function getEntryList($locale = null) {
        $locale = $this->getLocale($locale);

        if (isset($this->list[$locale])) {
            return $this->list[$locale];
        }

        $page = 1;
        $limit = 1000;
        $this->list[$locale] = array();

        do {
            $query = $this->createFindQuery(null, $locale, true);
            $query->setFields('{id}, {name}');
            $query->setLimit($limit, ($page - 1) * $limit);
            $entries = $query->query();

            $this->list[$locale] += $this->getOptionsFromEntries($entries);

            $page++;
        } while (count($entries) == $limit);

        return $this->list[$locale];
    }

    /**
     * Gets the assets for a folder
     * @param string $folder Id of the folder
     * @param string $locale Code of the locale
     * @return array
     */
    public function getByFolder($folder, $locale = null, $fetchUnlocalized = null, array $filter = null, $limit = 0, $page = 1, $offset = 0) {
        $query = $this->createByFolderQuery($folder, $locale, $fetchUnlocalized, $filter);
        if ($limit) {
            $query->setLimit($limit, (($page - 1) * $limit) + $offset);
        }

        return $query->query();
    }

    /**
     * Gets the assets for a folder
     * @param string $folder Id of the folder
     * @param string $locale Code of the locale
     * @return array
     */
    public function countByFolder($folder, $locale = null, $fetchUnlocalized = null, array $filter = null) {
        $query = $this->createByFolderQuery($folder, $locale, $fetchUnlocalized, $filter);

        return $query->count();
    }

    /**
     * Gets the assets for a folder
     * @param string $folder Id of the folder
     * @param string $locale Code of the locale
     * @return array
     */
    protected function createByFolderQuery($folder, $locale = null, $fetchUnlocalized = null, array $filter = null) {
        $query = $this->createQuery($locale);
        $query->setFetchUnlocalized($fetchUnlocalized);
        $query->addOrderBy('{orderIndex} ASC');

        if (is_array($folder)) {
            $query->addCondition('{folder} IN %1%', $folder);
        } elseif (!$folder || !$folder->getId()) {
            $query->addCondition('{folder} IS NULL');
        } else {
            $query->addCondition('{folder} = %1%', $folder);
        }

        if (isset($filter['query'])) {
            $query->addCondition('{name} LIKE %1% OR {description} LIKE %1%', '%' . $filter['query'] . '%');
        }

        if (isset($filter['type']) && $filter['type'] != 'all') {
            $query->addCondition('{type} = %1%', $filter['type']);
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

        return $query;
    }

    /**
     * Gets all the used types
     * @param \ride\library\i18n\translator\Translator $translator
     * @return array Array with the type as value
     */
    public function getTypes(Translator $translator) {
        $query = $this->createQuery();
        $query->setFields('{type}');
        $query->setDistinct(TRUE);

        $types = $query->query('type');
        foreach ($types as $type => $null) {
            if (!$type) {
                continue;
            }

            $types[$type] = $translator->translate('label.type.' . $type);
        }

        asort($types);

        return $types;
    }

    /**
     * Gets all the dates of upload
     * @return array Array with the numeric year and month as key and formatted
     * as value
     */
    public function getMonths() {
        $query = $this->createQuery();
        $query->setFields('FROM_UNIXTIME({dateAdded}, "%Y-%m") AS month');
        $query->setDistinct(TRUE);
        $query->addOrderBy('month DESC');

        $months = $query->query('month');
        foreach ($months as $key => $null) {
            list($year, $month) = explode('-', $key);

            $months[$key] = date('F Y', mktime(12, 0, 0, $month, 1, $year));
        }

        return $months;
    }

    /**
     * Save a node to the model
     * @param Folder $folder
     * @return null
     */
    protected function saveEntry($asset) {
        if (!$asset->getId() && !$asset->getOrderIndex()) {
            $asset->setOrderIndex($this->getNewOrderIndex($asset->getFolder()));
        }

        if (!$asset->isParsed()) {
            $this->parseAsset($asset);
        }

        parent::saveEntry($asset);
    }

    /**
     * Get an order index for a new item in a folder
     * @param string $parent path of the parent of the new node
     * @return int new order index
     */
    protected function getNewOrderIndex(AssetFolderEntry $parent = null) {
        $query = $this->createQuery();
        $query->setFields('MAX({orderIndex}) AS maxOrderIndex');

        if ($parent) {
            $query->addCondition('{folder} = %1%', $parent->getId());
        } else {
            $query->addCondition('{folder} IS NULL');
        }

        $result = $query->queryFirst();

        return $result->maxOrderIndex + 1;
    }

    /**
     * Parses an asset
     * @param AssetEntry $asset
     * @return null
     */
    public function parseAsset(AssetEntry $asset) {
        if (!$asset->getValue()) {
            return;
        }

        if ($asset->isUrl()) {
            $this->parseUrl($asset);
        } else {
            $this->parseFile($asset);
        }

        $asset->setIsParsed(true);
    }

    /**
     * Parses a URL into the provided asset
     * @param string $url URL to parse
     * @param AssetEntry $asset Asset to parse the URL into
     * @return null
     */
    protected function parseUrl($asset) {
        $mediaFactory = $this->getMediaFactory();
        $media = $mediaFactory->createMediaItem($asset->getValue());

        $asset->setSource($media->getType());
        $asset->setEmbedUrl($media->getEmbedUrl());
        if ($media->isVideo()) {
            $asset->setType(AssetEntry::TYPE_VIDEO);
        } elseif ($media->isAudio()) {
            $asset->setType(AssetEntry::TYPE_AUDIO);
        }

        if (!$asset->getName()) {
            $asset->setName($media->getTitle());
        }
        if (!$asset->getDescription()) {
            $asset->setDescription($media->getDescription());
        }
        if (!$asset->getThumbnail()) {
            $client = $mediaFactory->getHttpClient();

            $response = $client->get($media->getThumbnailUrl());
            if ($response->getStatusCode() == Response::STATUS_CODE_OK) {
                $contentType = $response->getHeader(Header::HEADER_CONTENT_TYPE);
                $extension = str_replace('image/', '', $contentType);

                $directory = $this->getDirectory();
                $file = $directory->getChild($media->getId() . '.' . $extension);
                $file->write($response->getBody());

                $file = $this->getFileBrowser()->getRelativeFile($file, true);
                $asset->setThumbnail($file->getPath());
            }
        }
    }

    /**
     * Parses a uploaded file into the provided asset
     * @param string $file Path of the file
     * @param AssetEntry $asset Asset to parse the URL into
     * @return null
     */
    protected function parseFile($asset) {
        $asset->setSource('file');

        $file = $this->getFileBrowser()->getFile($asset->getValue());

        if (!$asset->getName()) {
            $asset->setName($file->getName(true));
        }

        $image = $this->getImageFactory()->createImage();
        try {
            $image->read($file);
            $asset->setType(AssetEntry::TYPE_IMAGE);

            if (!$asset->getThumbnail()) {
                $asset->setThumbnail($asset->getValue());
            }
        } catch (ImageException $exception) {
            switch ($file->getExtension()) {
                case 'flac':
                case 'mp3':
                case 'ogg':
                case 'wav':
                    $asset->setType(AssetEntry::TYPE_AUDIO);

                    break;
                case 'pdf':
                    $asset->setType(AssetEntry::TYPE_PDF);

                    break;
                default:
                    $asset->setType(AssetEntry::TYPE_UNKNOWN);

                    break;
            }
        }
    }

    /**
     * Deletes the data from the database
     * @param AssetFolderEntry $folder
     * @return folder
     */
    protected function deleteEntry($asset) {
        // delete the asset
        $asset = parent::deleteEntry($asset);
        if (!$asset) {
            return $asset;
        }

        // reorder the siblings
        $this->orderFolder($asset->getFolder());

        return $asset;
    }

    /**
     * Orders the provided items in the order they are provided
     * @param array $items
     * @param integer $startIndex
     * @return null
     */
    public function order(array $assets, $startIndex = 1) {
        $isTransactionStarted = $this->beginTransaction();
        try {
            $index = $startIndex;
            foreach ($assets as $asset) {
                $asset->setOrderIndex($index);

                $this->save($asset);

                $index++;
            }

            $this->commitTransaction($isTransactionStarted);
        } catch (Exception $exception) {
            $this->rollbackTransaction($isTransactionStarted);

            throw $exception;
        }
    }

    /**
     * Orders the items in the provided parent with the provided algorithm
     * @param AssetFolderEntry $parent Parent of the items to order
     * @param string $order Name of the order algorithm
     * @return null
     */
    public function orderFolder(AssetFolderEntry $parent = null, $order = self::ORDER_RESYNC) {
        $index = 1;
        $ordered = array();

        $assets = $this->getByFolder($parent);
        switch ($order) {
            case AssetFolderModel::ORDER_ASC:
            case AssetFolderModel::ORDER_DESC:
                foreach ($assets as $asset) {
                    $base = $asset->getName();
                    $name = $base;
                    $index = 1;

                    while (isset($ordered[$name])) {
                        $name = $base . '-' . $index;
                        $index++;
                    }

                    $ordered[$name] = $asset;
                }

                break;
            case AssetFolderModel::ORDER_NEWEST:
            case AssetFolderModel::ORDER_OLDEST:
                foreach ($assets as $asset) {
                    $ordered[$asset->getDateAdded()] = $asset;
                }

                break;
            case AssetFolderModel::ORDER_RESYNC:
                foreach ($assets as $asset) {
                    $ordered[] = $asset;
                }

                break;
            default:
                throw new Exception('Could not order the assets: invalid order method provided');
        }

        ksort($ordered);
        if ($order == AssetFolderModel::ORDER_DESC || $order == AssetFolderModel::ORDER_OLDEST) {
            $ordered = array_reverse($ordered);
        }

        $this->order($ordered);
    }

    /**
     * Gets the directory of the assets
     * @return \ride\library\system\file\File
     */
    public function getDirectory() {
        return $this->orm->getDependencyInjector()->get('ride\\library\\system\\file\\File', 'assets');
    }

    /**
     * Gets the image factory
     * @return \ride\library\image\ImageFactory
     */
    public function getImageFactory() {
        return $this->orm->getDependencyInjector()->get('ride\\library\\image\\ImageFactory');
    }

    /**
     * Gets the media factory
     * @return \ride\library\system\file\browser\FileBrowser
     */
    public function getMediaFactory() {
        return $this->orm->getDependencyInjector()->get('ride\\library\\media\\MediaFactory');
    }

    /**
     * Gets the file browser
     * @return \ride\library\system\file\browser\FileBrowser
     */
    public function getFileBrowser() {
        return $this->orm->getDependencyInjector()->get('ride\\library\\system\\file\\browser\\FileBrowser');
    }

}

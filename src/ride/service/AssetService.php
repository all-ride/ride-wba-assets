<?php

namespace ride\service;

use ride\application\orm\asset\entry\AssetEntry;
use ride\application\orm\asset\model\AssetModel;
use ride\application\orm\asset\parser\AssetParser;

use ride\library\orm\model\GenericModel;

use ride\web\image\ImageUrlGenerator;

use \Exception;

/**
 * Service to work with assets
 */
class AssetService {

    /**
     * Model of the assets
     * @var \ride\application\orm\asset\model\AssetModel
     */
    private $assetModel;

    /**
     * Model of the image styles
     * @var \ride\application\orm\model\ImageStyleModel
     */
    private $imageStyleModel;

    /**
     * URL generator for images
     * @var \ride\web\image\ImageUrlGenerator
     */
    private $imageUrlGenerator;

    /**
     * Loaded image styles
     * @var array
     */
    private $imageStyles;

    /**
     * Loaded asset parsers
     * @var array
     */
    private $assetParsers;

    /**
     * Default class to retrieve the asset parser without class
     * @var string
     */
    private $defaultClass;

    /**
     * Constructs a new asset service
     * @param \ride\application\orm\asset\model\AssetModel $assetModel
     * @param \ride\library\orm\model\GenericModel $imageStyleModel
     * @param \ride\web\image\ImageUrlGenerator $imageUrlGenerator
     * @return null
     */
    public function __construct(AssetModel $assetModel, GenericModel $imageStyleModel, ImageUrlGenerator $imageUrlGenerator) {
        $this->assetModel = $assetModel;
        $this->imageStyleModel = $imageStyleModel;
        $this->imageUrlGenerator = $imageUrlGenerator;
        $this->imageStyles = array();
        $this->assetParsers = array();
        $this->defaultClass = null;
    }

    /**
     * Sets the default class to retrieve the asset parser without class
     * @param string $defaultClass Class name of the asset parser
     * @return null
     */
    public function setDefaultClass($defaultClass) {
        $this->defaultClass = $defaultClass;
    }

    /**
     * Sets asset parsers to this service
     * @param array $assetParsers Array with the class as key and the asset
     * parser as value
     * @return null
     */
    public function setAssetParsers(array $assetParsers) {
        foreach ($assetParsers as $class => $assetParser) {
            $this->setAssetParser($class, $assetParser);
        }
    }

    /**
     * Sets an asset class parser
     * @param string $class Name of the class
     * @param \ride\application\orm\asset\parser\AssetParser $assetParser
     * Instance of the asset parser
     * @return null
     */
    public function setAssetParser($class, AssetParser $assetParser) {
        $this->assetParsers[$class] = $assetParser;

        if ($this->defaultClass === null) {
            $this->defaultClass = $class;
        }
    }

    /**
     * Gets a asset parser
     * @param string $class Name of the class
     * @return \ride\application\orm\asset\parser\AssetParser Instance of the
     * asset parser
     * @throws \Exception when no class parser set for the provided class
     */
    public function getAssetParser($class = null) {
        if ($class === null) {
            $class = $this->defaultClass;
        }

        if (!isset($this->assetParsers[$class])) {
            throw new Exception('Could not get class parser: no parser set for ' . $class);
        }

        return $this->assetParsers[$class];
    }

    /**
     * Gets the image style with the provided name
     * @param string $style Name of the style
     * @return \ride\application\orm\asset\entry\ImageStyleEntry
     */
    public function getImageStyle($style) {
        if (isset($this->imageStyles[$style])) {
            return $this->imageStyles[$style];
        }

        $this->imageStyles[$style] = $this->imageStyleModel->getBy(array('filter' => array('slug' => $style)));
        if (!$this->imageStyles[$style]) {
            throw new Exception('Could not load style ' . $style . ': style does not exist');
        }

        return $this->imageStyles[$style];
    }

    /**
     * Gets an asset
     * @param string $id Id or slug of the asset
     * @param string $locale Locale to load
     * @return \ride\application\orm\asset\entry\AssetEntry|null
     */
    public function getAsset($id, $locale = null) {
        if (is_numeric($id)) {
            return $this->assetModel->getById($id, $locale);
        } else {
            return $this->assetModel->getBy(array('filter' => array('slug' => $id)), $locale);
        }
    }

    /**
     * Gets the URL for an asset
     * @param string|\ride\application\orm\asset\entry\AssetEntry $asset
     * @param string $style Name of the style to apply
     * @return string|null
     */
    public function getAssetUrl($asset, $style = null) {
        if (!$asset instanceof AssetEntry) {
            $asset = $this->getAsset($asset);
            if (!$asset) {
                return null;
            }
        }

        if ($asset->isUrl()) {
            return $asset->getValue();
        } elseif (!$asset->isImage()) {
            return null;
        }

        // check for overriden style image
        if ($style) {
            $image = $asset->getStyleImage($style);
            if ($image) {
                // transform to the correct size
                $transformations = $this->getImageStyle($style)->getSizeTransformationArray();

                // get url for the provided image
                return $this->imageUrlGenerator->generateUrl($image, $transformations);
            }
        }

        // no style image set
        $image = $asset->getImage();
        if (!$image) {
            return null;
        }

        $transformations = null;
        if ($style) {
            $transformations = $this->getImageStyle($style)->getTransformationArray();
        }

        // get url for the provided image
        return $this->imageUrlGenerator->generateUrl($image, $transformations);
    }

    /**
     * Gets the HTML for an asset
     * @param string|\ride\application\orm\asset\entry\AssetEntry $asset
     * @param string $style Name of the style to apply
     * @param string $class Class of the asset parser
     * @return string|null
     */
    public function getAssetHtml($asset, $style = null, $class = null) {
        if (!$asset instanceof AssetEntry) {
            $asset = $this->getAsset($asset);
            if (!$asset) {
                return null;
            }
        }

        $assetParser = $this->getAssetParser($class);

        return $assetParser->getAssetHtml($this, $asset, $style);
    }

}

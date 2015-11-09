<?php

namespace ride\service;

use ride\application\orm\asset\entry\AssetEntry;
use ride\application\orm\asset\model\AssetModel;

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
     * Loaded styles
     * @var array
     */
    private $styles;

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
        $this->styles = array();
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

        // check for overriden style image
        if ($style) {
            $image = $asset->getStyleImage($style);
            if ($image) {
                // get url for the provided image
                return $this->imageUrlGenerator->generateUrl($image);
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
     * Gets the image style with the provided name
     * @param string $style Name of the style
     * @return \ride\application\orm\asset\entry\ImageStyleEntry
     */
    public function getImageStyle($style) {
        if (isset($this->styles[$style])) {
            return $this->styles[$style];
        }

        $this->styles[$style] = $this->imageStyleModel->getBy(array('filter' => array('slug' => $style)));
        if (!$this->styles[$style]) {
            throw new Exception('Could not load style ' . $style . ': style does not exist');
        }

        return $this->styles[$style];
    }

}

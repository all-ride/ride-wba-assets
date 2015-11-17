<?php

namespace ride\application\orm\asset\parser;

use ride\application\orm\asset\entry\AssetEntry;

use ride\service\AssetService;

/**
 * Generic implementation to parse an asset into HTML
 */
class GenericAssetParser implements AssetParser {

    /**
     * Style class for the image tag
     * @var string
     */
    protected $imageClass;

    /**
     * Sets the style class for the image tag
     * @param string $imageClass Style class for the image tag
     * @return null
     */
    public function setImageClass($imageClass) {
        $this->imageClass = $imageClass;
    }

    /**
     * Gets the HTML for the provided asset
     * @param \ride\service\AssetService $assetService
     * @param \ride\application\orm\asset\entry\AssetEntry $asset
     * @param string $style Name of the style
     * @return string HTML for the provided asset
     */
    public function getAssetHtml(AssetService $assetService, AssetEntry $asset, $style = null) {
        $result = '<img src="' . $assetService->getAssetUrl($asset, $style) . '" title="' . htmlentities($asset->getName()) . '"';
        if ($this->imageClass) {
            $result .= ' class="' . $this->imageClass . '"';
        }
        $result .= '>';

        return $result;
    }

}

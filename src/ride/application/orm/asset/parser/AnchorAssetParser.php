<?php

namespace ride\application\orm\asset\parser;

use ride\application\orm\asset\entry\AssetEntry;

use ride\service\AssetService;

/**
 * Implementation to link an asset to the original resource
 */
class AnchorAssetParser extends GenericAssetParser {

    /**
     * Style class for the anchor tag
     * @var string
     */
    protected $anchorClass;

    /**
     * Relationship for the anchor tag
     * @var string
     */
    protected $anchorRelationship;

    /**
     * Sets the style class for the anchor tag
     * @param string $anchorClass Style class for the anchor tag
     * @return null
     */
    public function setAnchorClass($anchorClass) {
        $this->anchorClass = $anchorClass;
    }

    /**
     * Sets the relationship for the anchor tag
     * @param string $anchorRelationship Relationship for the anchor tag (rel)
     * @return null
     */
    public function setAnchorRelationship($anchorRelationship) {
        $this->anchorRelationship = $anchorRelationship;
    }

    /**
     * Gets the HTML for the provided asset
     * @param \ride\service\AssetService $assetService
     * @param \ride\application\orm\asset\entry\AssetEntry $asset
     * @param string $style Name of the style
     * @return string HTML for the provided asset
     */
    public function getAssetHtml(AssetService $assetService, AssetEntry $asset, $style = null) {
        if (!$asset->isImage()) {
            return parent::getAssetHtml($assetService, $asset, $style);
        }

        $anchor = '<a href="' . $assetService->getAssetUrl($asset) . '"';
        if ($this->anchorClass) {
            $anchor .= ' class="' . $this->anchorClass . '"';
        }
        if ($this->anchorRelationship) {
            $anchor .= ' rel="' . $this->anchorRelationship . '"';
        }
        $anchor .= '>';
        $anchor .= parent::getAssetHtml($assetService, $asset, $style);
        $anchor .= '</a>';

        return $anchor;
    }

}

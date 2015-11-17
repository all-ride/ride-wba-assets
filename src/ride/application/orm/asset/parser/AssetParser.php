<?php

namespace ride\application\orm\asset\parser;

use ride\application\orm\asset\entry\AssetEntry;

use ride\service\AssetService;

/**
 * Interface to parse an asset into HTML
 */
interface AssetParser {

    /**
     * Gets the HTML for the provided asset
     * @param \ride\service\AssetService $assetService
     * @param \ride\application\orm\asset\entry\AssetEntry $asset
     * @param string $style Name of the style
     * @return string HTML for the provided asset
     */
    public function getAssetHtml(AssetService $assetService, AssetEntry $asset, $style = null);

}

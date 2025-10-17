<?php

declare(strict_types=1);

namespace DWT\LocalFonts\Services;

use WP_Error;

/**
 * Asset Resolver Service
 *
 * Resolves ZIP assets from GitHub releases using naming convention pattern.
 * Pattern: {plugin-slug}-{version}.zip (e.g., dwt-localfonts-1.2.3.zip)
 *
 * @package DWT\LocalFonts\Services
 */
final class AssetResolver
{
    /**
     * Resolve ZIP asset URL from release assets
     *
     * Searches for asset matching {pluginSlug}-{version}.zip pattern.
     * Returns download URL if found, WP_Error if pattern not matched.
     *
     * @param array<array{name: string, browser_download_url: string}> $assets Release assets
     * @param string $pluginSlug Plugin slug (e.g., 'dwt-localfonts')
     * @param string $version Semantic version (e.g., '1.2.3' or '1.2.3-beta')
     * @return string|WP_Error Download URL on success, WP_Error on failure
     */
    public function resolveZipAsset(array $assets, string $pluginSlug, string $version): string|WP_Error
    {
        // Validate inputs
        if (empty($assets)) {
            return new WP_Error(
                'no_assets',
                'No assets found in release',
                ['plugin_slug' => $pluginSlug, 'version' => $version]
            );
        }

        // Build expected asset name pattern
        $expectedAssetName = sprintf('%s-%s.zip', $pluginSlug, $version);

        // Search for matching asset
        foreach ($assets as $asset) {
            // Validate asset structure
            if (!isset($asset['name']) || !isset($asset['browser_download_url'])) {
                continue;
            }

            // Check if asset name matches pattern (case-sensitive)
            if ($asset['name'] === $expectedAssetName) {
                return $asset['browser_download_url'];
            }
        }

        // No matching asset found
        return new WP_Error(
            'asset_not_found',
            sprintf(
                'Expected ZIP asset "%s" not found in release. Available assets: %s',
                $expectedAssetName,
                implode(', ', array_column($assets, 'name'))
            ),
            [
                'expected_asset' => $expectedAssetName,
                'available_assets' => array_column($assets, 'name'),
                'plugin_slug' => $pluginSlug,
                'version' => $version,
            ]
        );
    }
}

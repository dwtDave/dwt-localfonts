<?php
/**
 * GitHubRelease Value Object
 *
 * Immutable representation of a GitHub release with downloadable assets.
 *
 * @package DWT\LocalFonts
 * @since 1.1.0
 */

declare(strict_types=1);

namespace DWT\LocalFonts\ValueObjects;

use DateTimeImmutable;
use Exception;
use InvalidArgumentException;
use WP_Error;

/**
 * Value object representing a GitHub release
 *
 * @since 1.1.0
 */
final class GitHubRelease
{
    /**
     * Semantic version string
     *
     * @var string
     */
    public readonly string $version;

    /**
     * GitHub release page URL
     *
     * @var string
     */
    public readonly string $releaseUrl;

    /**
     * Release notes/changelog in Markdown format
     *
     * @var string
     */
    public readonly string $releaseNotes;

    /**
     * Release publication timestamp
     *
     * @var DateTimeImmutable
     */
    public readonly DateTimeImmutable $publishedAt;

    /**
     * Array of release assets
     *
     * @var array<array{name: string, browser_download_url: string, size: int}>
     */
    public readonly array $assets;

    /**
     * Resolved ZIP asset download URL (nullable if not found)
     *
     * @var string|null
     */
    public readonly ?string $zipAssetUrl;

    /**
     * Expected ZIP file size in bytes
     *
     * @var int
     */
    public readonly int $zipAssetSize;

    /**
     * Construct GitHubRelease
     *
     * @param string $version Semantic version (e.g., "1.2.3" or "1.2.3-beta")
     * @param string $releaseUrl GitHub release page URL (HTTPS)
     * @param string $releaseNotes Release notes in Markdown format
     * @param DateTimeImmutable $publishedAt Publication timestamp
     * @param array<array{name: string, browser_download_url: string, size: int}> $assets Release assets
     * @param string|null $zipAssetUrl Resolved ZIP download URL (nullable)
     * @param int $zipAssetSize Expected ZIP size in bytes
     *
     * @throws InvalidArgumentException If validation fails
     */
    public function __construct(
        string $version,
        string $releaseUrl,
        string $releaseNotes,
        DateTimeImmutable $publishedAt,
        array $assets,
        ?string $zipAssetUrl,
        int $zipAssetSize
    ) {
        // Validate version format (semantic versioning)
        if (!preg_match('/^[0-9]+\.[0-9]+\.[0-9]+(-[a-zA-Z0-9.]+)?$/', $version)) {
            throw new InvalidArgumentException('Invalid version format (must match semantic versioning: X.Y.Z or X.Y.Z-suffix)');
        }

        // Validate release URL
        if (!filter_var($releaseUrl, FILTER_VALIDATE_URL)) {
            throw new InvalidArgumentException('Invalid release URL');
        }

        if (!str_starts_with($releaseUrl, 'https://')) {
            throw new InvalidArgumentException('Release URL must be HTTPS');
        }

        // Validate assets array
        if (empty($assets)) {
            throw new InvalidArgumentException('Assets array cannot be empty');
        }

        foreach ($assets as $asset) {
            if (!isset($asset['name'], $asset['browser_download_url'], $asset['size'])) {
                throw new InvalidArgumentException('Invalid asset structure (must have name, browser_download_url, size)');
            }
        }

        // Validate ZIP asset URL if provided
        if ($zipAssetUrl !== null) {
            if (!filter_var($zipAssetUrl, FILTER_VALIDATE_URL)) {
                throw new InvalidArgumentException('Invalid ZIP asset URL');
            }

            if (!str_starts_with($zipAssetUrl, 'https://')) {
                throw new InvalidArgumentException('ZIP asset URL must be HTTPS');
            }
        }

        // Validate ZIP asset size
        if ($zipAssetUrl !== null && $zipAssetSize <= 0) {
            throw new InvalidArgumentException('ZIP asset size must be positive when ZIP URL is provided');
        }

        $this->version = $version;
        $this->releaseUrl = $releaseUrl;
        $this->releaseNotes = $releaseNotes;
        $this->publishedAt = $publishedAt;
        $this->assets = $assets;
        $this->zipAssetUrl = $zipAssetUrl;
        $this->zipAssetSize = $zipAssetSize;
    }

    /**
     * Create from GitHub API response
     *
     * @param array<string, mixed> $apiResponse Raw JSON-decoded GitHub API response
     * @param string $pluginSlug Plugin slug for asset resolution
     *
     * @return self|WP_Error Release instance or error
     */
    public static function fromGitHubApiResponse(array $apiResponse, string $pluginSlug): self|WP_Error
    {
        // Check required fields
        $requiredFields = ['tag_name', 'html_url', 'body', 'published_at', 'assets'];
        foreach ($requiredFields as $field) {
            if (!isset($apiResponse[$field])) {
                return new WP_Error(
                    'missing_required_field',
                    sprintf('Missing required field in GitHub API response: %s', $field),
                    ['field' => $field]
                );
            }
        }

        // Extract and clean version (strip leading 'v' if present)
        $version = $apiResponse['tag_name'];
        if (str_starts_with($version, 'v')) {
            $version = substr($version, 1);
        }

        // Validate version format
        if (!preg_match('/^[0-9]+\.[0-9]+\.[0-9]+(-[a-zA-Z0-9.]+)?$/', $version)) {
            return new WP_Error(
                'invalid_version_format',
                sprintf('Invalid version format: %s', $apiResponse['tag_name']),
                ['tag_name' => $apiResponse['tag_name']]
            );
        }

        // Parse published_at datetime
        try {
            $publishedAt = new DateTimeImmutable($apiResponse['published_at']);
        } catch (Exception $e) {
            return new WP_Error(
                'invalid_published_at',
                sprintf('Invalid published_at datetime: %s', $apiResponse['published_at']),
                ['exception' => $e->getMessage()]
            );
        }

        // Validate assets array
        if (!is_array($apiResponse['assets']) || empty($apiResponse['assets'])) {
            return new WP_Error(
                'no_assets',
                'Release has no assets',
                ['tag_name' => $apiResponse['tag_name']]
            );
        }

        // Resolve ZIP asset using naming convention: {plugin-slug}-{version}.zip
        $zipAssetUrl = null;
        $zipAssetSize = 0;
        $expectedAssetName = sprintf('%s-%s.zip', $pluginSlug, $version);

        foreach ($apiResponse['assets'] as $asset) {
            if (!isset($asset['name'], $asset['browser_download_url'], $asset['size'])) {
                continue;
            }

            if ($asset['name'] === $expectedAssetName) {
                $zipAssetUrl = $asset['browser_download_url'];
                $zipAssetSize = (int) $asset['size'];
                break;
            }
        }

        // Construct and return
        try {
            return new self(
                version: $version,
                releaseUrl: $apiResponse['html_url'],
                releaseNotes: $apiResponse['body'] ?? '',
                publishedAt: $publishedAt,
                assets: $apiResponse['assets'],
                zipAssetUrl: $zipAssetUrl,
                zipAssetSize: $zipAssetSize
            );
        } catch (InvalidArgumentException $e) {
            return new WP_Error(
                'invalid_release_data',
                sprintf('Invalid release data: %s', $e->getMessage()),
                ['exception' => $e->getMessage()]
            );
        }
    }
}

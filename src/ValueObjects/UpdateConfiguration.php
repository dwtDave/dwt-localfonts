<?php
/**
 * UpdateConfiguration Value Object
 *
 * Immutable configuration for GitHub update settings.
 *
 * @package DWT\LocalFonts
 * @since 1.1.0
 */

declare(strict_types=1);

namespace DWT\LocalFonts\ValueObjects;

use InvalidArgumentException;
use WP_Error;

/**
 * Value object representing plugin update configuration
 *
 * @since 1.1.0
 */
final class UpdateConfiguration
{
    /**
     * GitHub repository owner (username or organization)
     *
     * @var string
     */
    public readonly string $repositoryOwner;

    /**
     * GitHub repository name
     *
     * @var string
     */
    public readonly string $repositoryName;

    /**
     * Plugin slug for asset naming convention
     *
     * @var string
     */
    public readonly string $pluginSlug;

    /**
     * Cache lifetime in seconds (minimum 1 hour)
     *
     * @var int
     */
    public readonly int $cacheLifetime;

    /**
     * Update channel: "stable" or "all"
     *
     * @var string
     */
    public readonly string $updateChannel;

    /**
     * Enable automatic updates
     *
     * @var bool
     */
    public readonly bool $autoUpdateEnabled;

    /**
     * Construct UpdateConfiguration
     *
     * @param string $repositoryOwner GitHub repository owner
     * @param string $repositoryName GitHub repository name
     * @param string $pluginSlug Plugin slug (lowercase, hyphens, numbers)
     * @param int $cacheLifetime Cache lifetime in seconds (default: 12 hours)
     * @param string $updateChannel Update channel: "stable" or "all" (default: "stable")
     * @param bool $autoUpdateEnabled Enable automatic updates (default: false)
     *
     * @throws InvalidArgumentException If validation fails
     */
    public function __construct(
        string $repositoryOwner,
        string $repositoryName,
        string $pluginSlug,
        int $cacheLifetime = 43200,
        string $updateChannel = 'stable',
        bool $autoUpdateEnabled = false
    ) {
        // Validate repository owner
        if (!preg_match('/^[a-zA-Z0-9](?:[a-zA-Z0-9]|-(?=[a-zA-Z0-9])){0,38}$/', $repositoryOwner)) {
            throw new InvalidArgumentException('Invalid repository owner format');
        }

        // Validate repository name
        if (!preg_match('/^[a-zA-Z0-9._-]+$/', $repositoryName)) {
            throw new InvalidArgumentException('Invalid repository name format');
        }

        // Validate plugin slug
        if (!preg_match('/^[a-z0-9-]+$/', $pluginSlug)) {
            throw new InvalidArgumentException('Invalid plugin slug format (must be lowercase, hyphens, numbers only)');
        }

        // Validate cache lifetime
        if ($cacheLifetime < 3600) {
            throw new InvalidArgumentException('Cache lifetime must be at least 3600 seconds (1 hour)');
        }

        // Validate update channel
        if (!in_array($updateChannel, ['stable', 'all'], true)) {
            throw new InvalidArgumentException('Update channel must be "stable" or "all"');
        }

        $this->repositoryOwner = $repositoryOwner;
        $this->repositoryName = $repositoryName;
        $this->pluginSlug = $pluginSlug;
        $this->cacheLifetime = $cacheLifetime;
        $this->updateChannel = $updateChannel;
        $this->autoUpdateEnabled = $autoUpdateEnabled;
    }

    /**
     * Create from WordPress Options API data
     *
     * @param array<string, mixed> $optionData Decoded JSON from Options API
     *
     * @return self|WP_Error Configuration instance or error
     */
    public static function fromOptionData(array $optionData): self|WP_Error
    {
        // Check required fields
        $requiredFields = ['repository_owner', 'repository_name', 'plugin_slug'];
        foreach ($requiredFields as $field) {
            if (!isset($optionData[$field]) || !is_string($optionData[$field]) || $optionData[$field] === '') {
                return new WP_Error(
                    'missing_required_field',
                    sprintf('Missing required field: %s', $field),
                    ['field' => $field]
                );
            }
        }

        // Apply defaults
        $cacheLifetime = isset($optionData['cache_lifetime']) && is_int($optionData['cache_lifetime'])
            ? $optionData['cache_lifetime']
            : 43200;

        $updateChannel = isset($optionData['update_channel']) && is_string($optionData['update_channel'])
            ? $optionData['update_channel']
            : 'stable';

        $autoUpdateEnabled = isset($optionData['auto_update_enabled']) && is_bool($optionData['auto_update_enabled'])
            ? $optionData['auto_update_enabled']
            : false;

        // Attempt construction with validation
        try {
            return new self(
                repositoryOwner: $optionData['repository_owner'],
                repositoryName: $optionData['repository_name'],
                pluginSlug: $optionData['plugin_slug'],
                cacheLifetime: $cacheLifetime,
                updateChannel: $updateChannel,
                autoUpdateEnabled: $autoUpdateEnabled
            );
        } catch (InvalidArgumentException $e) {
            return new WP_Error(
                'invalid_configuration',
                sprintf('Invalid configuration data: %s', $e->getMessage()),
                ['exception' => $e->getMessage()]
            );
        }
    }

    /**
     * Convert to Options API storage format
     *
     * @return array<string, mixed> Associative array for JSON encoding
     */
    public function toOptionData(): array
    {
        return [
            'repository_owner' => $this->repositoryOwner,
            'repository_name' => $this->repositoryName,
            'plugin_slug' => $this->pluginSlug,
            'cache_lifetime' => $this->cacheLifetime,
            'update_channel' => $this->updateChannel,
            'auto_update_enabled' => $this->autoUpdateEnabled,
        ];
    }
}

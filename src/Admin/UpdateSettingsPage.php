<?php

declare(strict_types=1);

namespace DWT\LocalFonts\Admin;

use DWT\LocalFonts\ValueObjects\UpdateConfiguration;

/**
 * Update Settings Page
 *
 * Provides WordPress admin interface for configuring GitHub update settings.
 * Integrates with WordPress Settings API for proper settings management.
 *
 * @package DWT\LocalFonts\Admin
 */
final class UpdateSettingsPage
{
    private const OPTION_NAME = 'dwt_localfonts_github_config';
    private const OPTION_GROUP = 'dwt_localfonts_github_updates';
    private const PAGE_SLUG = 'dwt-localfonts-github-updates';
    private const SETTINGS_SECTION = 'dwt_localfonts_github_repository';

    /**
     * Register admin menu
     *
     * @return void
     */
    public function registerMenu(): void
    {
        add_options_page(
            'GitHub Updates', // page_title
            'GitHub Updates', // menu_title
            'manage_options', // capability
            self::PAGE_SLUG, // menu_slug
            [$this, 'renderPage'] // callback
        );
    }

    /**
     * Register settings with WordPress Settings API
     *
     * @return void
     */
    public function registerSettings(): void
    {
        // Register setting
        register_setting(
            self::OPTION_GROUP,
            self::OPTION_NAME,
            [
                'sanitize_callback' => [$this, 'sanitizeSettings'],
                'default' => $this->getDefaultSettings(),
            ]
        );

        // Add settings section
        add_settings_section(
            self::SETTINGS_SECTION,
            'GitHub Repository Configuration',
            [$this, 'renderSectionDescription'],
            self::PAGE_SLUG
        );

        // Add settings fields
        add_settings_field(
            'repository_owner',
            'Repository Owner',
            [$this, 'renderRepositoryOwnerField'],
            self::PAGE_SLUG,
            self::SETTINGS_SECTION
        );

        add_settings_field(
            'repository_name',
            'Repository Name',
            [$this, 'renderRepositoryNameField'],
            self::PAGE_SLUG,
            self::SETTINGS_SECTION
        );

        add_settings_field(
            'update_channel',
            'Update Channel',
            [$this, 'renderUpdateChannelField'],
            self::PAGE_SLUG,
            self::SETTINGS_SECTION
        );

        add_settings_field(
            'auto_update_enabled',
            'Auto-Update',
            [$this, 'renderAutoUpdateField'],
            self::PAGE_SLUG,
            self::SETTINGS_SECTION
        );

        add_settings_field(
            'cache_lifetime',
            'Cache Lifetime (seconds)',
            [$this, 'renderCacheLifetimeField'],
            self::PAGE_SLUG,
            self::SETTINGS_SECTION
        );
    }

    /**
     * Render settings page
     *
     * @return void
     */
    public function renderPage(): void
    {
        // Check user capabilities
        if (!current_user_can('manage_options')) {
            wp_die('You do not have sufficient permissions to access this page.');
        }

        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('GitHub Updates Settings', 'dwt-localfonts'); ?></h1>
            <form method="post" action="options.php">
                <?php
                settings_errors(self::OPTION_NAME);
                settings_fields(self::OPTION_GROUP);
                do_settings_sections(self::PAGE_SLUG);
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }

    /**
     * Render section description
     *
     * @return void
     */
    public function renderSectionDescription(): void
    {
        echo '<p>' . esc_html__('Configure the GitHub repository to check for plugin updates.', 'dwt-localfonts') . '</p>';
    }

    /**
     * Render repository owner field
     *
     * @return void
     */
    public function renderRepositoryOwnerField(): void
    {
        $options = get_option(self::OPTION_NAME, $this->getDefaultSettings());
        $value = $options['repository_owner'] ?? '';
        ?>
        <input type="text"
               name="<?php echo esc_attr(self::OPTION_NAME); ?>[repository_owner]"
               value="<?php echo esc_attr($value); ?>"
               class="regular-text"
               placeholder="e.g., octocat" />
        <p class="description">
            <?php echo esc_html__('The GitHub username or organization name.', 'dwt-localfonts'); ?>
        </p>
        <?php
    }

    /**
     * Render repository name field
     *
     * @return void
     */
    public function renderRepositoryNameField(): void
    {
        $options = get_option(self::OPTION_NAME, $this->getDefaultSettings());
        $value = $options['repository_name'] ?? '';
        ?>
        <input type="text"
               name="<?php echo esc_attr(self::OPTION_NAME); ?>[repository_name]"
               value="<?php echo esc_attr($value); ?>"
               class="regular-text"
               placeholder="e.g., my-plugin" />
        <p class="description">
            <?php echo esc_html__('The GitHub repository name.', 'dwt-localfonts'); ?>
        </p>
        <?php
    }

    /**
     * Render update channel field
     *
     * @return void
     */
    public function renderUpdateChannelField(): void
    {
        $options = get_option(self::OPTION_NAME, $this->getDefaultSettings());
        $value = $options['update_channel'] ?? 'stable';
        ?>
        <select name="<?php echo esc_attr(self::OPTION_NAME); ?>[update_channel]">
            <option value="stable" <?php selected($value, 'stable'); ?>>
                <?php echo esc_html__('Stable releases only', 'dwt-localfonts'); ?>
            </option>
            <option value="all" <?php selected($value, 'all'); ?>>
                <?php echo esc_html__('All releases (including pre-releases)', 'dwt-localfonts'); ?>
            </option>
        </select>
        <p class="description">
            <?php echo esc_html__('Choose which types of releases to include in update checks.', 'dwt-localfonts'); ?>
        </p>
        <?php
    }

    /**
     * Render auto-update field
     *
     * @return void
     */
    public function renderAutoUpdateField(): void
    {
        $options = get_option(self::OPTION_NAME, $this->getDefaultSettings());
        $value = $options['auto_update_enabled'] ?? false;
        ?>
        <label>
            <input type="checkbox"
                   name="<?php echo esc_attr(self::OPTION_NAME); ?>[auto_update_enabled]"
                   value="1"
                   <?php checked($value, true); ?> />
            <?php echo esc_html__('Enable automatic updates', 'dwt-localfonts'); ?>
        </label>
        <p class="description">
            <?php echo esc_html__('Automatically install updates when they become available.', 'dwt-localfonts'); ?>
        </p>
        <?php
    }

    /**
     * Render cache lifetime field
     *
     * @return void
     */
    public function renderCacheLifetimeField(): void
    {
        $options = get_option(self::OPTION_NAME, $this->getDefaultSettings());
        $value = $options['cache_lifetime'] ?? 43200;
        ?>
        <input type="number"
               name="<?php echo esc_attr(self::OPTION_NAME); ?>[cache_lifetime]"
               value="<?php echo esc_attr((string) $value); ?>"
               class="small-text"
               min="3600"
               max="86400"
               step="3600" />
        <p class="description">
            <?php echo esc_html__('How long to cache GitHub API responses (3600-86400 seconds, default 43200 = 12 hours).', 'dwt-localfonts'); ?>
        </p>
        <?php
    }

    /**
     * Sanitize settings before saving
     *
     * @param array<string, mixed> $input Raw input from form
     * @return array<string, mixed> Sanitized settings
     */
    public function sanitizeSettings(array $input): array
    {
        $sanitized = [];
        $hasErrors = false;

        // Sanitize repository owner
        $sanitized['repository_owner'] = sanitize_text_field($input['repository_owner'] ?? '');
        if (empty($sanitized['repository_owner'])) {
            add_settings_error(
                self::OPTION_NAME,
                'missing_repository_owner',
                'Repository owner is required.',
                'error'
            );
            $hasErrors = true;
        }

        // Sanitize repository name
        $sanitized['repository_name'] = sanitize_text_field($input['repository_name'] ?? '');
        if (empty($sanitized['repository_name'])) {
            add_settings_error(
                self::OPTION_NAME,
                'missing_repository_name',
                'Repository name is required.',
                'error'
            );
            $hasErrors = true;
        }

        // Sanitize plugin slug (always dwt-localfonts for this plugin)
        $sanitized['plugin_slug'] = sanitize_text_field($input['plugin_slug'] ?? 'dwt-localfonts');

        // Validate update channel
        $channel = sanitize_key($input['update_channel'] ?? 'stable');
        if (!in_array($channel, ['stable', 'all'], true)) {
            add_settings_error(
                self::OPTION_NAME,
                'invalid_update_channel',
                'Update channel must be either "stable" or "all".',
                'error'
            );
            $hasErrors = true;
        }
        $sanitized['update_channel'] = $channel;

        // Sanitize auto-update enabled
        $sanitized['auto_update_enabled'] = isset($input['auto_update_enabled']) && $input['auto_update_enabled'] === '1';

        // Validate cache lifetime
        $cacheLifetime = absint($input['cache_lifetime'] ?? 43200);
        if ($cacheLifetime < 3600 || $cacheLifetime > 86400) {
            add_settings_error(
                self::OPTION_NAME,
                'invalid_cache_lifetime',
                'Cache lifetime must be between 3600 and 86400 seconds (1-24 hours).',
                'error'
            );
            $hasErrors = true;
        }
        $sanitized['cache_lifetime'] = $cacheLifetime;

        // If validation failed, return previous valid settings
        if ($hasErrors) {
            $previousSettings = get_option(self::OPTION_NAME, $this->getDefaultSettings());
            return is_array($previousSettings) ? $previousSettings : $this->getDefaultSettings();
        }

        return $sanitized;
    }

    /**
     * Get UpdateConfiguration from stored settings
     *
     * @return UpdateConfiguration
     */
    public function getConfiguration(): UpdateConfiguration
    {
        $settings = get_option(self::OPTION_NAME, $this->getDefaultSettings());

        if (!is_array($settings)) {
            $settings = $this->getDefaultSettings();
        }

        return new UpdateConfiguration(
            repositoryOwner: $settings['repository_owner'] ?? '',
            repositoryName: $settings['repository_name'] ?? '',
            pluginSlug: $settings['plugin_slug'] ?? 'dwt-localfonts',
            cacheLifetime: $settings['cache_lifetime'] ?? 43200,
            updateChannel: $settings['update_channel'] ?? 'stable',
            autoUpdateEnabled: $settings['auto_update_enabled'] ?? false
        );
    }

    /**
     * Get default settings
     *
     * @return array<string, mixed>
     */
    private function getDefaultSettings(): array
    {
        return [
            'repository_owner' => '',
            'repository_name' => '',
            'plugin_slug' => 'dwt-localfonts',
            'update_channel' => 'stable',
            'auto_update_enabled' => false,
            'cache_lifetime' => 43200,
        ];
    }
}

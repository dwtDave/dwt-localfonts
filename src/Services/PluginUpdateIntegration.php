<?php

declare(strict_types=1);

namespace DWT\LocalFonts\Services;

use DWT\LocalFonts\ValueObjects\GitHubRelease;
use stdClass;

/**
 * Plugin Update Integration Service
 *
 * Integrates with WordPress plugin update infrastructure.
 * Hooks into `pre_set_site_transient_update_plugins` filter to inject
 * GitHub release information into native WordPress update checker.
 *
 * @package DWT\LocalFonts\Services
 */
final class PluginUpdateIntegration
{
    /**
     * Constructor
     *
     * @param GitHubUpdateService $updateService GitHub update service
     * @param string $pluginBasename Plugin basename (e.g., 'dwt-localfonts/dwt-localfonts.php')
     * @param UpdateInstaller|null $installer Update installer service (optional)
     * @param bool $autoUpdateEnabled Whether auto-updates are enabled
     */
    public function __construct(
        private readonly GitHubUpdateService $updateService,
        private readonly string $pluginBasename,
        private readonly ?UpdateInstaller $installer = null,
        private readonly bool $autoUpdateEnabled = false
    ) {
    }

    /**
     * Register WordPress hooks
     *
     * @return void
     */
    public function registerHooks(): void
    {
        add_filter('pre_set_site_transient_update_plugins', [$this, 'filterUpdateTransient'], 10, 1);
        add_filter('plugins_api', [$this, 'filterPluginsApi'], 10, 3);
        add_filter('upgrader_pre_download', [$this, 'handleUpdateDownload'], 10, 3);

        // Register auto-update filter if enabled
        if ($this->autoUpdateEnabled) {
            add_filter('auto_update_plugin', [$this, 'filterAutoUpdate'], 10, 2);
        }
    }

    /**
     * Filter update_plugins transient to inject GitHub release info
     *
     * This is the main integration point with WordPress update infrastructure.
     * Called when WordPress checks for plugin updates (twice daily automatic checks
     * and manual "Check for updates" clicks).
     *
     * @param mixed $transient Transient object or false
     * @return mixed Modified transient
     */
    public function filterUpdateTransient(mixed $transient): mixed
    {
        // Validate transient structure
        if (!is_object($transient)) {
            return $transient;
        }

        // Check for updates from GitHub
        $release = $this->updateService->checkForUpdates();

        // Handle errors silently (don't break admin)
        if (is_wp_error($release)) {
            error_log(sprintf(
                'GitHub update check failed: %s',
                $release->get_error_message()
            ));
            return $transient;
        }

        // No update available
        if ($release === null) {
            return $transient;
        }

        // Add update information to transient
        $updateInfo = $this->buildUpdateInfo($release);
        $transient->response[$this->pluginBasename] = $updateInfo;

        return $transient;
    }

    /**
     * Filter plugins_api to provide plugin information modal
     *
     * Called when user clicks "View version X.X.X details" in WordPress admin.
     * Displays release notes and changelog in modal.
     *
     * @param mixed $result Current result
     * @param string $action Action being performed
     * @param stdClass $args Action arguments
     * @return mixed Plugin info object or false
     */
    public function filterPluginsApi(mixed $result, string $action, stdClass $args): mixed
    {
        // Only handle plugin_information requests for our plugin
        if ($action !== 'plugin_information') {
            return $result;
        }

        $pluginSlug = $this->getPluginSlug();
        if (!isset($args->slug) || $args->slug !== $pluginSlug) {
            return $result;
        }

        // Get release information
        $release = $this->updateService->checkForUpdates();

        if (is_wp_error($release) || $release === null) {
            return $result;
        }

        // Build plugin info object
        return $this->buildPluginInfo($release);
    }

    /**
     * Build update info object for WordPress transient
     *
     * @param GitHubRelease $release GitHub release data
     * @return stdClass Update info object
     */
    private function buildUpdateInfo(GitHubRelease $release): stdClass
    {
        $updateInfo = new stdClass();
        $updateInfo->slug = $this->getPluginSlug();
        $updateInfo->plugin = $this->pluginBasename;
        $updateInfo->new_version = $release->version;
        $updateInfo->url = $release->releaseUrl;
        $updateInfo->package = $release->zipAssetUrl ?? '';
        $updateInfo->tested = '6.7'; // WordPress version tested up to
        $updateInfo->requires_php = '8.2';

        return $updateInfo;
    }

    /**
     * Build plugin info object for WordPress modal
     *
     * @param GitHubRelease $release GitHub release data
     * @return stdClass Plugin info object
     */
    private function buildPluginInfo(GitHubRelease $release): stdClass
    {
        $info = new stdClass();
        $info->name = 'DWT LocalFonts';
        $info->slug = $this->getPluginSlug();
        $info->version = $release->version;
        $info->author = 'DWT';
        $info->homepage = $release->releaseUrl;
        $info->requires = '6.0';
        $info->tested = '6.7';
        $info->requires_php = '8.2';
        $info->last_updated = $release->publishedAt->format('Y-m-d H:i:s');

        // Render release notes as HTML
        $info->sections = [
            'changelog' => $this->renderReleaseNotes($release->releaseNotes),
        ];

        return $info;
    }

    /**
     * Render markdown release notes as sanitized HTML
     *
     * @param string $markdown Markdown release notes
     * @return string Sanitized HTML
     */
    private function renderReleaseNotes(string $markdown): string
    {
        // Simple markdown to HTML conversion
        // In production, this could use a markdown parser library
        $html = $markdown;

        // Convert headers
        $html = preg_replace('/^### (.+)$/m', '<h3>$1</h3>', $html);
        $html = preg_replace('/^## (.+)$/m', '<h2>$1</h2>', $html);
        $html = preg_replace('/^# (.+)$/m', '<h1>$1</h1>', $html);

        // Convert lists
        $html = preg_replace('/^\- (.+)$/m', '<li>$1</li>', $html);
        $html = preg_replace('/(<li>.*<\/li>)/s', '<ul>$1</ul>', $html);

        // Convert bold
        $html = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $html);

        // Convert italic
        $html = preg_replace('/\*(.+?)\*/', '<em>$1</em>', $html);

        // Convert links
        $html = preg_replace('/\[(.+?)\]\((.+?)\)/', '<a href="$2">$1</a>', $html);

        // Convert newlines to paragraphs
        $html = wpautop($html);

        // Sanitize HTML for safe display
        return wp_kses_post($html);
    }

    /**
     * Handle update download
     *
     * Intercepts WordPress's plugin update process and uses our custom installer
     * instead of the default WordPress upgrader.
     *
     * @param bool $reply Whether to return the package
     * @param string $package Package URL
     * @param object $upgrader WP_Upgrader instance
     * @return bool|WP_Error
     */
    public function handleUpdateDownload(bool $reply, string $package, object $upgrader): bool|\WP_Error
    {
        // Only handle our plugin's updates
        if (!isset($upgrader->skin->plugin) || $upgrader->skin->plugin !== $this->pluginBasename) {
            return $reply;
        }

        // If no installer configured, use default WordPress upgrader
        if ($this->installer === null) {
            return $reply;
        }

        // Get release information
        $release = $this->updateService->checkForUpdates();

        if (is_wp_error($release) || $release === null) {
            return $reply; // Fall back to default
        }

        // Use our custom installer
        $result = $this->installer->installUpdate($release);

        if (is_wp_error($result)) {
            return $result;
        }

        // Return true to indicate package was handled
        return true;
    }

    /**
     * Filter auto-update for plugin
     *
     * Called by WordPress to determine if a plugin should auto-update.
     * Only enables auto-update for our plugin, preserves original value for others.
     *
     * @param bool $update Whether to enable auto-update
     * @param object $item Update item object
     * @return bool Whether to enable auto-update
     */
    public function filterAutoUpdate(bool $update, object $item): bool
    {
        // Only enable auto-update for our plugin
        if (isset($item->plugin) && $item->plugin === $this->pluginBasename) {
            return true;
        }

        // Preserve original value for other plugins
        return $update;
    }

    /**
     * Extract plugin slug from basename
     *
     * @return string Plugin slug
     */
    private function getPluginSlug(): string
    {
        // Extract slug from basename (e.g., 'dwt-localfonts/dwt-localfonts.php' -> 'dwt-localfonts')
        return dirname($this->pluginBasename);
    }
}

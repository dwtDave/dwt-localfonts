<?php

declare(strict_types=1);

namespace DWT\LocalFonts\Tests\Unit\Services;

use Brain\Monkey\Functions;
use DWT\LocalFonts\Services\AssetResolver;
use PHPUnit\Framework\TestCase;
use WP_Error;

/**
 * Test AssetResolver service
 *
 * Tests ZIP asset resolution using naming convention pattern
 */
final class AssetResolverTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        \Brain\Monkey\setUp();
    }

    protected function tearDown(): void
    {
        \Brain\Monkey\tearDown();
        parent::tearDown();
    }

    public function test_it_resolves_zip_asset_with_correct_naming_pattern(): void
    {
        // Arrange
        $resolver = new AssetResolver();
        $assets = [
            [
                'name' => 'source-code.zip',
                'browser_download_url' => 'https://example.com/source-code.zip',
            ],
            [
                'name' => 'dwt-localfonts-1.2.3.zip',
                'browser_download_url' => 'https://github.com/owner/repo/releases/download/1.2.3/dwt-localfonts-1.2.3.zip',
            ],
            [
                'name' => 'README.md',
                'browser_download_url' => 'https://example.com/README.md',
            ],
        ];

        // Act
        $result = $resolver->resolveZipAsset($assets, 'dwt-localfonts', '1.2.3');

        // Assert
        $this->assertIsString($result);
        $this->assertSame('https://github.com/owner/repo/releases/download/1.2.3/dwt-localfonts-1.2.3.zip', $result);
    }

    public function test_it_returns_wp_error_when_pattern_not_matched(): void
    {
        // Arrange
        $resolver = new AssetResolver();
        $assets = [
            [
                'name' => 'source-code.zip',
                'browser_download_url' => 'https://example.com/source-code.zip',
            ],
            [
                'name' => 'other-plugin-1.0.0.zip',
                'browser_download_url' => 'https://example.com/other-plugin-1.0.0.zip',
            ],
        ];

        // Act
        $result = $resolver->resolveZipAsset($assets, 'dwt-localfonts', '1.2.3');

        // Assert
        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertSame('asset_not_found', $result->get_error_code());
    }

    public function test_it_handles_empty_assets_array(): void
    {
        // Arrange
        $resolver = new AssetResolver();
        $assets = [];

        // Act
        $result = $resolver->resolveZipAsset($assets, 'dwt-localfonts', '1.2.3');

        // Assert
        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertSame('no_assets', $result->get_error_code());
    }

    public function test_it_matches_version_with_prerelease_suffix(): void
    {
        // Arrange
        $resolver = new AssetResolver();
        $assets = [
            [
                'name' => 'dwt-localfonts-1.2.3-beta.zip',
                'browser_download_url' => 'https://github.com/owner/repo/releases/download/1.2.3-beta/dwt-localfonts-1.2.3-beta.zip',
            ],
        ];

        // Act
        $result = $resolver->resolveZipAsset($assets, 'dwt-localfonts', '1.2.3-beta');

        // Assert
        $this->assertIsString($result);
        $this->assertStringContainsString('dwt-localfonts-1.2.3-beta.zip', $result);
    }

    public function test_it_is_case_sensitive_for_plugin_slug(): void
    {
        // Arrange
        $resolver = new AssetResolver();
        $assets = [
            [
                'name' => 'DWT-LocalFonts-1.2.3.zip',
                'browser_download_url' => 'https://example.com/DWT-LocalFonts-1.2.3.zip',
            ],
        ];

        // Act - Looking for lowercase slug
        $result = $resolver->resolveZipAsset($assets, 'dwt-localfonts', '1.2.3');

        // Assert - Should not match due to case difference
        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertSame('asset_not_found', $result->get_error_code());
    }

    public function test_it_validates_asset_structure(): void
    {
        // Arrange
        $resolver = new AssetResolver();
        $assets = [
            [
                'name' => 'dwt-localfonts-1.2.3.zip',
                // Missing browser_download_url
            ],
        ];

        // Act
        $result = $resolver->resolveZipAsset($assets, 'dwt-localfonts', '1.2.3');

        // Assert
        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertSame('asset_not_found', $result->get_error_code());
    }
}

<?php
/**
 * Unit tests for GitHubRelease value object
 *
 * Tests GitHub API response parsing, version validation, and asset resolution.
 *
 * @package DWT\LocalFonts
 * @since 1.1.0
 */

declare(strict_types=1);

namespace DWT\LocalFonts\Tests\Unit\ValueObjects;

use Brain\Monkey\Functions;
use DateTimeImmutable;
use DWT\LocalFonts\ValueObjects\GitHubRelease;
use PHPUnit\Framework\TestCase;
use WP_Error;

final class GitHubReleaseTest extends TestCase
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

    public function test_it_constructs_with_valid_parameters(): void
    {
        $publishedAt = new DateTimeImmutable('2025-10-15 10:00:00');
        $assets = [
            [
                'name' => 'dwt-localfonts-1.2.3.zip',
                'browser_download_url' => 'https://github.com/owner/repo/releases/download/1.2.3/dwt-localfonts-1.2.3.zip',
                'size' => 1048576,
            ],
        ];

        $release = new GitHubRelease(
            version: '1.2.3',
            releaseUrl: 'https://github.com/owner/repo/releases/tag/1.2.3',
            releaseNotes: '## Changes\n- Feature A\n- Bug fix B',
            publishedAt: $publishedAt,
            assets: $assets,
            zipAssetUrl: 'https://github.com/owner/repo/releases/download/1.2.3/dwt-localfonts-1.2.3.zip',
            zipAssetSize: 1048576
        );

        $this->assertSame('1.2.3', $release->version);
        $this->assertSame('https://github.com/owner/repo/releases/tag/1.2.3', $release->releaseUrl);
        $this->assertSame('## Changes\n- Feature A\n- Bug fix B', $release->releaseNotes);
        $this->assertSame($publishedAt, $release->publishedAt);
        $this->assertSame($assets, $release->assets);
        $this->assertSame('https://github.com/owner/repo/releases/download/1.2.3/dwt-localfonts-1.2.3.zip', $release->zipAssetUrl);
        $this->assertSame(1048576, $release->zipAssetSize);
    }

    public function test_it_accepts_valid_semantic_versions(): void
    {
        $validVersions = ['1.0.0', '2.3.15', '10.20.30', '1.0.0-beta', '2.0.0-rc.1', '1.2.3-alpha.10'];

        foreach ($validVersions as $version) {
            $release = new GitHubRelease(
                version: $version,
                releaseUrl: 'https://github.com/owner/repo/releases/tag/' . $version,
                releaseNotes: 'Test',
                publishedAt: new DateTimeImmutable(),
                assets: [
                    ['name' => 'test.zip', 'browser_download_url' => 'https://example.com/test.zip', 'size' => 100],
                ],
                zipAssetUrl: 'https://example.com/test.zip',
                zipAssetSize: 100
            );

            $this->assertSame($version, $release->version);
        }
    }

    public function test_it_rejects_invalid_version_format(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid version format');

        new GitHubRelease(
            version: 'v1.0.0', // Leading 'v' not allowed
            releaseUrl: 'https://github.com/owner/repo/releases/tag/v1.0.0',
            releaseNotes: 'Test',
            publishedAt: new DateTimeImmutable(),
            assets: [
                ['name' => 'test.zip', 'browser_download_url' => 'https://example.com/test.zip', 'size' => 100],
            ],
            zipAssetUrl: 'https://example.com/test.zip',
            zipAssetSize: 100
        );
    }

    public function test_it_rejects_incomplete_version(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid version format');

        new GitHubRelease(
            version: '1.0', // Missing patch version
            releaseUrl: 'https://github.com/owner/repo/releases/tag/1.0',
            releaseNotes: 'Test',
            publishedAt: new DateTimeImmutable(),
            assets: [
                ['name' => 'test.zip', 'browser_download_url' => 'https://example.com/test.zip', 'size' => 100],
            ],
            zipAssetUrl: 'https://example.com/test.zip',
            zipAssetSize: 100
        );
    }

    public function test_it_rejects_non_https_release_url(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Release URL must be HTTPS');

        new GitHubRelease(
            version: '1.0.0',
            releaseUrl: 'http://github.com/owner/repo/releases/tag/1.0.0', // HTTP not HTTPS
            releaseNotes: 'Test',
            publishedAt: new DateTimeImmutable(),
            assets: [
                ['name' => 'test.zip', 'browser_download_url' => 'https://example.com/test.zip', 'size' => 100],
            ],
            zipAssetUrl: 'https://example.com/test.zip',
            zipAssetSize: 100
        );
    }

    public function test_it_rejects_invalid_release_url(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid release URL');

        new GitHubRelease(
            version: '1.0.0',
            releaseUrl: 'not-a-url',
            releaseNotes: 'Test',
            publishedAt: new DateTimeImmutable(),
            assets: [
                ['name' => 'test.zip', 'browser_download_url' => 'https://example.com/test.zip', 'size' => 100],
            ],
            zipAssetUrl: 'https://example.com/test.zip',
            zipAssetSize: 100
        );
    }

    public function test_it_rejects_empty_assets_array(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Assets array cannot be empty');

        new GitHubRelease(
            version: '1.0.0',
            releaseUrl: 'https://github.com/owner/repo/releases/tag/1.0.0',
            releaseNotes: 'Test',
            publishedAt: new DateTimeImmutable(),
            assets: [], // Empty
            zipAssetUrl: null,
            zipAssetSize: 0
        );
    }

    public function test_it_rejects_malformed_assets(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid asset structure');

        new GitHubRelease(
            version: '1.0.0',
            releaseUrl: 'https://github.com/owner/repo/releases/tag/1.0.0',
            releaseNotes: 'Test',
            publishedAt: new DateTimeImmutable(),
            assets: [
                ['name' => 'test.zip'], // Missing browser_download_url and size
            ],
            zipAssetUrl: null,
            zipAssetSize: 0
        );
    }

    public function test_it_accepts_null_zip_asset_url(): void
    {
        $release = new GitHubRelease(
            version: '1.0.0',
            releaseUrl: 'https://github.com/owner/repo/releases/tag/1.0.0',
            releaseNotes: 'Test',
            publishedAt: new DateTimeImmutable(),
            assets: [
                ['name' => 'source.tar.gz', 'browser_download_url' => 'https://example.com/source.tar.gz', 'size' => 100],
            ],
            zipAssetUrl: null, // No ZIP found
            zipAssetSize: 0
        );

        $this->assertNull($release->zipAssetUrl);
        $this->assertSame(0, $release->zipAssetSize);
    }

    public function test_it_rejects_non_https_zip_asset_url(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('ZIP asset URL must be HTTPS');

        new GitHubRelease(
            version: '1.0.0',
            releaseUrl: 'https://github.com/owner/repo/releases/tag/1.0.0',
            releaseNotes: 'Test',
            publishedAt: new DateTimeImmutable(),
            assets: [
                ['name' => 'test.zip', 'browser_download_url' => 'http://example.com/test.zip', 'size' => 100],
            ],
            zipAssetUrl: 'http://example.com/test.zip', // HTTP not HTTPS
            zipAssetSize: 100
        );
    }

    public function test_it_rejects_negative_zip_asset_size(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('ZIP asset size must be positive');

        new GitHubRelease(
            version: '1.0.0',
            releaseUrl: 'https://github.com/owner/repo/releases/tag/1.0.0',
            releaseNotes: 'Test',
            publishedAt: new DateTimeImmutable(),
            assets: [
                ['name' => 'test.zip', 'browser_download_url' => 'https://example.com/test.zip', 'size' => 100],
            ],
            zipAssetUrl: 'https://example.com/test.zip',
            zipAssetSize: -1 // Negative
        );
    }

    public function test_it_creates_from_github_api_response(): void
    {
        $apiResponse = [
            'tag_name' => '1.2.3',
            'html_url' => 'https://github.com/owner/repo/releases/tag/1.2.3',
            'body' => '## Changes\n- Feature A\n- Bug fix B',
            'published_at' => '2025-10-15T10:00:00Z',
            'assets' => [
                [
                    'name' => 'dwt-localfonts-1.2.3.zip',
                    'browser_download_url' => 'https://github.com/owner/repo/releases/download/1.2.3/dwt-localfonts-1.2.3.zip',
                    'size' => 1048576,
                ],
            ],
        ];

        $release = GitHubRelease::fromGitHubApiResponse($apiResponse, 'dwt-localfonts');

        $this->assertInstanceOf(GitHubRelease::class, $release);
        $this->assertSame('1.2.3', $release->version);
        $this->assertSame('https://github.com/owner/repo/releases/tag/1.2.3', $release->releaseUrl);
        $this->assertSame('## Changes\n- Feature A\n- Bug fix B', $release->releaseNotes);
        $this->assertInstanceOf(DateTimeImmutable::class, $release->publishedAt);
        $this->assertSame('https://github.com/owner/repo/releases/download/1.2.3/dwt-localfonts-1.2.3.zip', $release->zipAssetUrl);
        $this->assertSame(1048576, $release->zipAssetSize);
    }

    public function test_it_strips_leading_v_from_tag_name(): void
    {
        $apiResponse = [
            'tag_name' => 'v1.2.3', // Leading 'v'
            'html_url' => 'https://github.com/owner/repo/releases/tag/v1.2.3',
            'body' => 'Test',
            'published_at' => '2025-10-15T10:00:00Z',
            'assets' => [
                [
                    'name' => 'dwt-localfonts-1.2.3.zip',
                    'browser_download_url' => 'https://github.com/owner/repo/releases/download/v1.2.3/dwt-localfonts-1.2.3.zip',
                    'size' => 100,
                ],
            ],
        ];

        $release = GitHubRelease::fromGitHubApiResponse($apiResponse, 'dwt-localfonts');

        $this->assertSame('1.2.3', $release->version); // 'v' stripped
    }

    public function test_it_returns_wp_error_for_missing_required_fields(): void
    {
        $apiResponse = [
            'tag_name' => '1.2.3',
            // Missing html_url, body, published_at, assets
        ];

        $result = GitHubRelease::fromGitHubApiResponse($apiResponse, 'dwt-localfonts');

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertSame('missing_required_field', $result->get_error_code());
    }

    public function test_it_returns_wp_error_for_invalid_version(): void
    {
        $apiResponse = [
            'tag_name' => 'invalid-version',
            'html_url' => 'https://github.com/owner/repo/releases/tag/invalid',
            'body' => 'Test',
            'published_at' => '2025-10-15T10:00:00Z',
            'assets' => [
                ['name' => 'test.zip', 'browser_download_url' => 'https://example.com/test.zip', 'size' => 100],
            ],
        ];

        $result = GitHubRelease::fromGitHubApiResponse($apiResponse, 'dwt-localfonts');

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertSame('invalid_version_format', $result->get_error_code());
    }

    public function test_it_returns_wp_error_for_invalid_datetime(): void
    {
        $apiResponse = [
            'tag_name' => '1.2.3',
            'html_url' => 'https://github.com/owner/repo/releases/tag/1.2.3',
            'body' => 'Test',
            'published_at' => 'invalid-datetime',
            'assets' => [
                ['name' => 'test.zip', 'browser_download_url' => 'https://example.com/test.zip', 'size' => 100],
            ],
        ];

        $result = GitHubRelease::fromGitHubApiResponse($apiResponse, 'dwt-localfonts');

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertSame('invalid_published_at', $result->get_error_code());
    }

    public function test_it_handles_empty_release_notes(): void
    {
        $apiResponse = [
            'tag_name' => '1.2.3',
            'html_url' => 'https://github.com/owner/repo/releases/tag/1.2.3',
            'body' => '', // Empty
            'published_at' => '2025-10-15T10:00:00Z',
            'assets' => [
                [
                    'name' => 'dwt-localfonts-1.2.3.zip',
                    'browser_download_url' => 'https://github.com/owner/repo/releases/download/1.2.3/dwt-localfonts-1.2.3.zip',
                    'size' => 100,
                ],
            ],
        ];

        $release = GitHubRelease::fromGitHubApiResponse($apiResponse, 'dwt-localfonts');

        $this->assertInstanceOf(GitHubRelease::class, $release);
        $this->assertSame('', $release->releaseNotes);
    }

    public function test_it_handles_missing_zip_asset(): void
    {
        $apiResponse = [
            'tag_name' => '1.2.3',
            'html_url' => 'https://github.com/owner/repo/releases/tag/1.2.3',
            'body' => 'Test',
            'published_at' => '2025-10-15T10:00:00Z',
            'assets' => [
                [
                    'name' => 'source.tar.gz', // Not a ZIP matching pattern
                    'browser_download_url' => 'https://github.com/owner/repo/releases/download/1.2.3/source.tar.gz',
                    'size' => 100,
                ],
            ],
        ];

        $release = GitHubRelease::fromGitHubApiResponse($apiResponse, 'dwt-localfonts');

        $this->assertInstanceOf(GitHubRelease::class, $release);
        $this->assertNull($release->zipAssetUrl);
        $this->assertSame(0, $release->zipAssetSize);
    }
}

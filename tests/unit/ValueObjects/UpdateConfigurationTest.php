<?php
/**
 * Unit tests for UpdateConfiguration value object
 *
 * Tests validation rules, defaults, and Options API serialization.
 *
 * @package DWT\LocalFonts
 * @since 1.1.0
 */

declare(strict_types=1);

namespace DWT\LocalFonts\Tests\Unit\ValueObjects;

use Brain\Monkey\Functions;
use DWT\LocalFonts\ValueObjects\UpdateConfiguration;
use PHPUnit\Framework\TestCase;
use WP_Error;

final class UpdateConfigurationTest extends TestCase
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
        $config = new UpdateConfiguration(
            repositoryOwner: 'wordpress',
            repositoryName: 'gutenberg',
            pluginSlug: 'dwt-localfonts',
            cacheLifetime: 43200,
            updateChannel: 'stable',
            autoUpdateEnabled: false
        );

        $this->assertSame('wordpress', $config->repositoryOwner);
        $this->assertSame('gutenberg', $config->repositoryName);
        $this->assertSame('dwt-localfonts', $config->pluginSlug);
        $this->assertSame(43200, $config->cacheLifetime);
        $this->assertSame('stable', $config->updateChannel);
        $this->assertFalse($config->autoUpdateEnabled);
    }

    public function test_it_uses_default_cache_lifetime(): void
    {
        $config = new UpdateConfiguration(
            repositoryOwner: 'test-owner',
            repositoryName: 'test-repo',
            pluginSlug: 'test-plugin'
        );

        $this->assertSame(43200, $config->cacheLifetime); // 12 hours
    }

    public function test_it_uses_default_update_channel(): void
    {
        $config = new UpdateConfiguration(
            repositoryOwner: 'test-owner',
            repositoryName: 'test-repo',
            pluginSlug: 'test-plugin'
        );

        $this->assertSame('stable', $config->updateChannel);
    }

    public function test_it_uses_default_auto_update_enabled(): void
    {
        $config = new UpdateConfiguration(
            repositoryOwner: 'test-owner',
            repositoryName: 'test-repo',
            pluginSlug: 'test-plugin'
        );

        $this->assertFalse($config->autoUpdateEnabled);
    }

    public function test_it_rejects_invalid_repository_owner(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid repository owner');

        new UpdateConfiguration(
            repositoryOwner: 'invalid owner!', // Spaces and special chars
            repositoryName: 'test-repo',
            pluginSlug: 'test-plugin'
        );
    }

    public function test_it_rejects_invalid_repository_name(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid repository name');

        new UpdateConfiguration(
            repositoryOwner: 'test-owner',
            repositoryName: 'invalid repo!', // Spaces and invalid chars
            pluginSlug: 'test-plugin'
        );
    }

    public function test_it_rejects_invalid_plugin_slug(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid plugin slug');

        new UpdateConfiguration(
            repositoryOwner: 'test-owner',
            repositoryName: 'test-repo',
            pluginSlug: 'Invalid-Slug' // Uppercase not allowed
        );
    }

    public function test_it_rejects_cache_lifetime_below_minimum(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Cache lifetime must be at least 3600 seconds');

        new UpdateConfiguration(
            repositoryOwner: 'test-owner',
            repositoryName: 'test-repo',
            pluginSlug: 'test-plugin',
            cacheLifetime: 1800 // 30 minutes - below 1 hour minimum
        );
    }

    public function test_it_rejects_invalid_update_channel(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Update channel must be "stable" or "all"');

        new UpdateConfiguration(
            repositoryOwner: 'test-owner',
            repositoryName: 'test-repo',
            pluginSlug: 'test-plugin',
            updateChannel: 'invalid'
        );
    }

    public function test_it_accepts_valid_repository_owner_patterns(): void
    {
        $validOwners = ['wordpress', 'woo-commerce', 'my-org123', 'A1B2-C3D4'];

        foreach ($validOwners as $owner) {
            $config = new UpdateConfiguration(
                repositoryOwner: $owner,
                repositoryName: 'test-repo',
                pluginSlug: 'test-plugin'
            );

            $this->assertSame($owner, $config->repositoryOwner);
        }
    }

    public function test_it_accepts_valid_repository_name_patterns(): void
    {
        $validNames = ['gutenberg', 'woocommerce', 'my-plugin.js', 'test_repo', 'repo-123'];

        foreach ($validNames as $name) {
            $config = new UpdateConfiguration(
                repositoryOwner: 'test-owner',
                repositoryName: $name,
                pluginSlug: 'test-plugin'
            );

            $this->assertSame($name, $config->repositoryName);
        }
    }

    public function test_it_accepts_valid_plugin_slug_patterns(): void
    {
        $validSlugs = ['dwt-localfonts', 'my-plugin', 'test123', 'a-b-c-123'];

        foreach ($validSlugs as $slug) {
            $config = new UpdateConfiguration(
                repositoryOwner: 'test-owner',
                repositoryName: 'test-repo',
                pluginSlug: $slug
            );

            $this->assertSame($slug, $config->pluginSlug);
        }
    }

    public function test_it_converts_to_option_data(): void
    {
        $config = new UpdateConfiguration(
            repositoryOwner: 'wordpress',
            repositoryName: 'gutenberg',
            pluginSlug: 'dwt-localfonts',
            cacheLifetime: 43200,
            updateChannel: 'stable',
            autoUpdateEnabled: false
        );

        $data = $config->toOptionData();

        $this->assertIsArray($data);
        $this->assertSame('wordpress', $data['repository_owner']);
        $this->assertSame('gutenberg', $data['repository_name']);
        $this->assertSame('dwt-localfonts', $data['plugin_slug']);
        $this->assertSame(43200, $data['cache_lifetime']);
        $this->assertSame('stable', $data['update_channel']);
        $this->assertFalse($data['auto_update_enabled']);
    }

    public function test_it_creates_from_option_data(): void
    {
        $data = [
            'repository_owner' => 'wordpress',
            'repository_name' => 'gutenberg',
            'plugin_slug' => 'dwt-localfonts',
            'cache_lifetime' => 43200,
            'update_channel' => 'stable',
            'auto_update_enabled' => false,
        ];

        $config = UpdateConfiguration::fromOptionData($data);

        $this->assertInstanceOf(UpdateConfiguration::class, $config);
        $this->assertSame('wordpress', $config->repositoryOwner);
        $this->assertSame('gutenberg', $config->repositoryName);
        $this->assertSame('dwt-localfonts', $config->pluginSlug);
        $this->assertSame(43200, $config->cacheLifetime);
        $this->assertSame('stable', $config->updateChannel);
        $this->assertFalse($config->autoUpdateEnabled);
    }

    public function test_it_applies_defaults_when_creating_from_option_data(): void
    {
        $data = [
            'repository_owner' => 'wordpress',
            'repository_name' => 'gutenberg',
            'plugin_slug' => 'dwt-localfonts',
        ];

        $config = UpdateConfiguration::fromOptionData($data);

        $this->assertSame(43200, $config->cacheLifetime);
        $this->assertSame('stable', $config->updateChannel);
        $this->assertFalse($config->autoUpdateEnabled);
    }

    public function test_it_returns_wp_error_for_missing_required_fields(): void
    {
        $data = [
            'repository_owner' => 'wordpress',
            // Missing repository_name and plugin_slug
        ];

        $result = UpdateConfiguration::fromOptionData($data);

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertSame('missing_required_field', $result->get_error_code());
    }

    public function test_it_returns_wp_error_for_invalid_data(): void
    {
        $data = [
            'repository_owner' => 'invalid owner!',
            'repository_name' => 'test-repo',
            'plugin_slug' => 'test-plugin',
        ];

        $result = UpdateConfiguration::fromOptionData($data);

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertSame('invalid_configuration', $result->get_error_code());
    }

    public function test_it_accepts_all_channel(): void
    {
        $config = new UpdateConfiguration(
            repositoryOwner: 'test-owner',
            repositoryName: 'test-repo',
            pluginSlug: 'test-plugin',
            updateChannel: 'all'
        );

        $this->assertSame('all', $config->updateChannel);
    }

    public function test_it_enables_auto_update(): void
    {
        $config = new UpdateConfiguration(
            repositoryOwner: 'test-owner',
            repositoryName: 'test-repo',
            pluginSlug: 'test-plugin',
            autoUpdateEnabled: true
        );

        $this->assertTrue($config->autoUpdateEnabled);
    }
}

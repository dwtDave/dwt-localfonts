# Release Process

This document describes how to create releases for the DWT LocalFonts WordPress plugin.

## Overview

The release process is fully automated using GitHub Actions. When you push a tag to the repository, the following happens automatically:

1. ✅ Verify the tag is on the `main` branch
2. 🧪 Run all tests (unit, integration, JavaScript) as final verification
3. 🔍 Run linting (PHPCS) and static analysis (PHPStan)
4. 🔨 Build frontend assets with Vite
5. 📦 Create production-ready plugin zip using `composer release`
6. ✅ Verify plugin version matches tag version
7. 📝 Generate changelog from git commits
8. 🚀 Create GitHub release with zip file attached

## Prerequisites

Before creating a release:

1. **All CI checks must pass on main**
   - Run `composer test:all` locally to verify all PHP tests pass
   - Run `npm run test:run` to verify JavaScript tests pass
   - Run `composer lint` to check coding standards
   - Run `composer phpstan` to verify static analysis

2. **Update plugin version**
   - Update version in `dwt-localfonts.php` header (e.g., `Version: 1.0.2`)
   - Update `DWT_LOCAL_FONTS_VERSION` constant in the same file
   - Commit and push to main: `git commit -am "Bump version to 1.0.2"`

3. **Ensure main branch is clean**
   - All changes should be committed and pushed
   - CI should be passing on the latest commit

## Creating a Release

### Method 1: Using the Create Tag Workflow (Recommended)

This is the safest method as it validates the version format and checks for conflicts.

1. Go to **Actions** → **Create Release Tag**
2. Click **Run workflow**
3. Enter the version number (e.g., `1.0.2` or `v1.0.2`)
4. Choose whether to create the release immediately (recommended: `true`)
5. Click **Run workflow**

The workflow will:
- ✅ Validate version format (semantic versioning)
- ✅ Check if tag already exists
- ✅ Verify plugin version matches tag
- ✅ Create and push the tag
- 🚀 Trigger the release workflow automatically (if enabled)

### Method 2: Manual Tag Creation

If you prefer to create tags manually from the command line:

```bash
# Make sure you're on main and up to date
git checkout main
git pull origin main

# Verify CI is passing (check GitHub Actions)

# Create annotated tag (version must match plugin version)
git tag -a v1.0.2 -m "Release version 1.0.2"

# Push tag to trigger release
git push origin v1.0.2
```

**⚠️ Important:** The tag version (e.g., `v1.0.2`) must match the version in `dwt-localfonts.php` or the release will fail.

## What Happens During Release

1. **Verify CI Status**
   - Checks that the tag is on the main branch
   - Fails if tag is created from a different branch

2. **Run Tests** (Final Verification)
   - PHP unit tests (Brain\Monkey)
   - PHPStan static analysis
   - PHPCS linting
   - JavaScript/React tests (Vitest)

3. **Build Release Package**
   - Executes `composer release` which runs `scripts/build-release.sh`
   - Builds frontend assets with `npm run build`
   - Copies files according to `.distignore`
   - Installs production Composer dependencies (no dev dependencies)
   - Creates `release/dwt-localfonts-{version}.zip`

4. **Create GitHub Release**
   - Generates changelog from git commits since last tag
   - Attaches the plugin zip file
   - Publishes release to GitHub Releases page

## Monitoring the Release

1. Go to **Actions** tab in GitHub
2. Click on the **Release** workflow run
3. Monitor each step's progress
4. If any step fails, the release will be cancelled

## After Release

Once the release is complete:

1. **Verify the release**
   - Check the [Releases page](../../releases)
   - Download the zip file and verify it contains the expected files
   - Test installation on a WordPress site

2. **Announce the release** (if applicable)
   - Update documentation
   - Notify users of new features/fixes

## Troubleshooting

### Release fails with "version does not match tag"

**Problem:** The version in `dwt-localfonts.php` doesn't match the tag.

**Solution:**
1. Delete the tag: `git tag -d v1.0.2 && git push origin :refs/tags/v1.0.2`
2. Update the version in `dwt-localfonts.php`
3. Commit and push: `git commit -am "Fix version" && git push`
4. Create tag again

### Release fails with "tag is not on main branch"

**Problem:** Tag was created from a different branch.

**Solution:**
1. Delete the tag: `git tag -d v1.0.2 && git push origin :refs/tags/v1.0.2`
2. Checkout main: `git checkout main`
3. Create tag again: `git tag -a v1.0.2 -m "Release version 1.0.2"`
4. Push: `git push origin v1.0.2`

### Tests fail during release

**Problem:** Tests that passed locally are failing in CI.

**Solution:**
1. Check the workflow logs to see which test failed
2. Delete the tag: `git tag -d v1.0.2 && git push origin :refs/tags/v1.0.2`
3. Fix the failing tests
4. Ensure CI passes on main before creating tag again

### Build fails with missing dependencies

**Problem:** Composer or npm dependencies are missing.

**Solution:**
1. Ensure `composer.lock` and `package-lock.json` are committed
2. Verify dependencies install correctly locally:
   ```bash
   rm -rf vendor node_modules
   composer install
   npm ci
   composer release
   ```
3. Fix any issues and commit
4. Delete and recreate the tag

## Manual Release (Emergency)

If GitHub Actions is unavailable, you can create a release manually:

```bash
# Build the release package locally
composer release

# The zip file will be in release/dwt-localfonts-{version}.zip

# Create release on GitHub manually:
# 1. Go to Releases → Draft a new release
# 2. Choose the tag (or create new tag)
# 3. Add release notes
# 4. Upload the zip file
# 5. Publish release
```

## Version Numbering

We follow [Semantic Versioning](https://semver.org/):

- **MAJOR** version (1.x.x): Breaking changes
- **MINOR** version (x.1.x): New features, backwards compatible
- **PATCH** version (x.x.1): Bug fixes, backwards compatible

Examples:
- `1.0.0` - Initial release
- `1.0.1` - Bug fix
- `1.1.0` - New feature
- `2.0.0` - Breaking change

## Release Checklist

Before creating a release:

- [ ] All tests pass locally (`composer test:all`)
- [ ] JavaScript tests pass (`npm run test:run`)
- [ ] Linting passes (`composer lint`)
- [ ] PHPStan passes (`composer phpstan`)
- [ ] Version updated in `dwt-localfonts.php`
- [ ] `DWT_LOCAL_FONTS_VERSION` constant updated
- [ ] All changes committed and pushed to main
- [ ] CI passing on main branch
- [ ] Tested plugin in local WordPress environment

After release:

- [ ] Verify release on GitHub
- [ ] Download and test the zip file
- [ ] Update documentation if needed
- [ ] Announce release (if applicable)

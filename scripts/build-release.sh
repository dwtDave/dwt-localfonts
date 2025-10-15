#!/bin/bash

# DWT LocalFonts - Release Build Script
# Creates a production-ready zip file of the plugin without dev dependencies

set -e

# Colors for output
GREEN='\033[0;32m'
BLUE='\033[0;34m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m'

# Get the plugin directory (parent of scripts directory)
PLUGIN_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$PLUGIN_DIR"

# Extract version from main plugin file (macOS compatible)
VERSION=$(grep "Version:" dwt-localfonts.php | awk '{print $3}' | tr -d ' ')
PLUGIN_SLUG="dwt-localfonts"
RELEASE_DIR="$PLUGIN_DIR/release"
BUILD_DIR="$RELEASE_DIR/$PLUGIN_SLUG"
ZIP_FILE="$RELEASE_DIR/$PLUGIN_SLUG-$VERSION.zip"

echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo -e "${BLUE}📦 Building Release Package for DWT LocalFonts${NC}"
echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo ""
echo -e "${YELLOW}Version:${NC} $VERSION"
echo -e "${YELLOW}Plugin:${NC} $PLUGIN_SLUG"
echo ""

# Step 1: Clean up previous release
echo -e "${BLUE}🧹 Cleaning previous release...${NC}"
if [ -d "$RELEASE_DIR" ]; then
    rm -rf "$RELEASE_DIR"
fi
mkdir -p "$BUILD_DIR"
echo -e "${GREEN}✓ Release directory created${NC}"
echo ""

# Step 2: Build frontend assets
echo -e "${BLUE}🔨 Building frontend assets...${NC}"
if [ -f "package.json" ]; then
    npm run build
    echo -e "${GREEN}✓ Frontend assets built${NC}"
else
    echo -e "${YELLOW}⚠ No package.json found, skipping frontend build${NC}"
fi
echo ""

# Step 3: Copy files to build directory (excluding .distignore patterns)
echo -e "${BLUE}📋 Copying plugin files...${NC}"
if [ -f ".distignore" ]; then
    rsync -av --exclude-from=".distignore" \
        --exclude="release" \
        --exclude=".git" \
        ./ "$BUILD_DIR/"
else
    echo -e "${RED}✗ .distignore file not found!${NC}"
    exit 1
fi
echo -e "${GREEN}✓ Files copied${NC}"
echo ""

# Step 4: Remove Strauss hooks from composer.json (already namespaced in strauss/)
echo -e "${BLUE}🔧 Preparing composer.json for production install...${NC}"
cd "$BUILD_DIR"
if [ -f "composer.json" ]; then
    # Remove strauss hooks using sed (they reference dev dependencies)
    sed -i.bak 's/"@strauss",//g; s/"@strauss"//g; s/"vendor\/bin\/strauss"/"echo Strauss already run"/g' composer.json
    rm -f composer.json.bak
    echo -e "${GREEN}✓ Composer hooks cleaned${NC}"
fi
echo ""

# Step 5: Install production Composer dependencies
echo -e "${BLUE}📦 Installing production Composer dependencies...${NC}"
cd "$BUILD_DIR"
if [ -f "composer.json" ]; then
    composer install --no-dev --optimize-autoloader --no-interaction --no-scripts
    echo -e "${GREEN}✓ Production dependencies installed${NC}"
else
    echo -e "${RED}✗ composer.json not found!${NC}"
    exit 1
fi
echo ""

# Step 6: Clean up unnecessary files from build
echo -e "${BLUE}🧹 Cleaning up build directory...${NC}"
cd "$BUILD_DIR"

# Remove composer files (not needed in distribution)
rm -f composer.json composer.lock

# Remove any .git directories that might have been copied
find . -name ".git" -type d -exec rm -rf {} + 2>/dev/null || true

# Remove any .DS_Store files
find . -name ".DS_Store" -type f -delete 2>/dev/null || true

echo -e "${GREEN}✓ Build directory cleaned${NC}"
echo ""

# Step 7: Create zip file
echo -e "${BLUE}📦 Creating zip archive...${NC}"
cd "$RELEASE_DIR"
zip -r "$ZIP_FILE" "$PLUGIN_SLUG" -q
echo -e "${GREEN}✓ Zip file created: ${ZIP_FILE}${NC}"
echo ""

# Step 8: Display summary
FILE_SIZE=$(du -h "$ZIP_FILE" | cut -f1)
echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo -e "${GREEN}✅ Release build completed successfully!${NC}"
echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo ""
echo -e "${YELLOW}Build directory:${NC} $BUILD_DIR"
echo -e "${YELLOW}Zip file:${NC} $ZIP_FILE"
echo -e "${YELLOW}File size:${NC} $FILE_SIZE"
echo ""
echo -e "${BLUE}Next steps:${NC}"
echo -e "  • Test the zip file by installing it in a WordPress site"
echo -e "  • Upload to WordPress.org or distribute as needed"
echo -e "  • To clean up: ${YELLOW}rm -rf $RELEASE_DIR${NC}"
echo ""

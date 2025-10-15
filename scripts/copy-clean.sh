#!/bin/bash

# Copy Clean - Copy plugin files excluding development dependencies and AI-related files
#
# Usage: ./scripts/copy-clean.sh <destination-path>
# Example: ./scripts/copy-clean.sh ~/Desktop/dwt-localfonts-clean

set -e  # Exit on error

# Color codes for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Check if destination argument is provided
if [ $# -eq 0 ]; then
    echo -e "${RED}Error: Destination path is required${NC}"
    echo "Usage: $0 <destination-path>"
    echo "Example: $0 ~/Desktop/dwt-localfonts-clean"
    exit 1
fi

DESTINATION="$1"
SOURCE_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

echo -e "${GREEN}Copy Clean Script${NC}"
echo "Source: $SOURCE_DIR"
echo "Destination: $DESTINATION"
echo ""

# Create destination directory if it doesn't exist
mkdir -p "$DESTINATION"

echo -e "${YELLOW}Copying files...${NC}"

# Use rsync to copy files with exclusions
rsync -av \
    --exclude='.git/' \
    --exclude='.github/' \
    --exclude='node_modules/' \
    --exclude='vendor/' \
    --exclude='.claude/' \
    --exclude='.specify/' \
    --exclude='specs/' \
    --exclude='.mcp.json' \
    --exclude='CLAUDE.MD' \
    --exclude='claude.md' \
    --exclude='coverage/' \
    --exclude='release/' \
    --exclude='build/' \
    --exclude='var/' \
    --exclude='.wp-env.home/' \
    --exclude='.wp-env.override.json' \
    --exclude='.phpunit.result.cache' \
    --exclude='*.log' \
    --exclude='.DS_Store' \
    --exclude='Thumbs.db' \
    --exclude='*.tmp' \
    --exclude='*.bak' \
    --exclude='*.backup' \
    "$SOURCE_DIR/" "$DESTINATION/"

echo ""
echo -e "${GREEN}✓ Copy completed successfully!${NC}"
echo ""
echo "Clean copy created at: $DESTINATION"
echo ""
echo "Excluded items:"
echo "  • Development dependencies (vendor/, node_modules/)"
echo "  • AI/spec files (.claude/, .specify/, specs/, CLAUDE.MD)"
echo "  • Build artifacts (coverage/, release/, build/)"
echo "  • Git repository (.git/)"
echo "  • Temporary files (*.log, *.tmp, .phpunit.result.cache)"
echo ""

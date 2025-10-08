#!/bin/bash

# DWT LocalFonts - Git Hooks Installation Script
# This script installs Git hooks for code quality enforcement

set -e

# Colors for output
GREEN='\033[0;32m'
BLUE='\033[0;34m'
YELLOW='\033[1;33m'
NC='\033[0m'

PLUGIN_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$PLUGIN_DIR"

echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo -e "${BLUE}🔧 Installing Git Hooks for DWT LocalFonts${NC}"
echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo ""

# Check if we're in a git repository
if [ ! -d ".git" ]; then
    echo -e "${YELLOW}Warning: Not a git repository. Skipping hook installation.${NC}"
    exit 0
fi

# Create hooks directory if it doesn't exist
mkdir -p .git/hooks

# Install pre-commit hook
echo -e "${BLUE}▶ Installing pre-commit hook...${NC}"
if [ -f ".git/hooks/pre-commit" ]; then
    echo -e "${YELLOW}⚠ Backing up existing pre-commit hook to .git/hooks/pre-commit.backup${NC}"
    mv .git/hooks/pre-commit .git/hooks/pre-commit.backup
fi

# Create symlink or copy the hook
ln -sf "../../scripts/pre-commit" .git/hooks/pre-commit || cp scripts/pre-commit .git/hooks/pre-commit
chmod +x .git/hooks/pre-commit

echo -e "${GREEN}✓ Pre-commit hook installed successfully${NC}"
echo ""

# Install pre-push hook
echo -e "${BLUE}▶ Installing pre-push hook...${NC}"
if [ -f ".git/hooks/pre-push" ]; then
    echo -e "${YELLOW}⚠ Backing up existing pre-push hook to .git/hooks/pre-push.backup${NC}"
    mv .git/hooks/pre-push .git/hooks/pre-push.backup
fi

# Create symlink or copy the hook
ln -sf "../../scripts/pre-push" .git/hooks/pre-push || cp scripts/pre-push .git/hooks/pre-push
chmod +x .git/hooks/pre-push

echo -e "${GREEN}✓ Pre-push hook installed successfully${NC}"
echo ""

# Verify installation
echo -e "${BLUE}▶ Verifying installation...${NC}"
if [ -x ".git/hooks/pre-commit" ] && [ -x ".git/hooks/pre-push" ]; then
    echo -e "${GREEN}✓ All hooks are properly installed and executable${NC}"
else
    echo -e "${YELLOW}⚠ Some hooks may not be executable. Run: chmod +x .git/hooks/pre-*${NC}"
fi

echo ""
echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo -e "${GREEN}✓ Git hooks installation complete!${NC}"
echo ""
echo -e "${BLUE}What happens now:${NC}"
echo ""
echo -e "${GREEN}Pre-commit hook (runs on 'git commit'):${NC}"
echo -e "  • PHP syntax validation"
echo -e "  • Auto-fix coding standards (PHPCBF)"
echo -e "  • Check for debug statements (var_dump, console.log)"
echo -e "  • Detect sensitive data patterns"
echo -e "  • Validate JSON files"
echo -e "  • TypeScript type check (if .ts/.tsx files changed)"
echo ""
echo -e "${GREEN}Pre-push hook (runs on 'git push'):${NC}"
echo -e "  • composer.json validation"
echo -e "  • PHP coding standards (PHPCS)"
echo -e "  • PHPStan static analysis"
echo -e "  • PHP unit tests"
echo -e "  • TypeScript type checking (if admin files changed)"
echo -e "  • JavaScript tests (if admin files changed)"
echo -e "  • Production build verification"
echo -e "  • Security vulnerability scan"
echo ""
echo -e "${YELLOW}To bypass hooks (not recommended):${NC}"
echo -e "  ${BLUE}git commit --no-verify${NC}  # Skip pre-commit"
echo -e "  ${BLUE}git push --no-verify${NC}    # Skip pre-push"
echo ""
echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"

# Google Fonts Data (Legacy)

⚠️ **This directory is deprecated.** Font data has been moved to `public/data/` to optimize bundle size.

The font list is now:
- Loaded from `public/data/google-fonts-list.json` at runtime (not bundled in JavaScript)
- Updated using `npm run update:fonts` script
- Automatically copied to `build/data/` during production builds

## Migration Information

The font data was moved from `admin/src/data/` to `public/data/` to achieve:
- **55% reduction in JavaScript bundle size** (647KB → 287KB)
- Fonts now load asynchronously on-demand
- Better caching and loading performance

## See Also

- Main font data location: `public/data/google-fonts-list.json`
- Update script: `scripts/update-fonts.sh`
- Update command: `npm run update:fonts`
import { describe, it, expect } from 'vitest';
import {
  UNICODE_RANGE_MAP,
  getUnicodeRangesForSubsets,
  getDisplayNamesForSubsets,
  formatUnicodeRangeForCSS,
  formatUnicodeRangeForDisplay,
  getAllSubsets,
  getCharacterCountEstimate,
  getSubsetInfo,
  fontSupportsSubsets,
} from './unicodeRanges';

describe('unicodeRanges', () => {
  describe('UNICODE_RANGE_MAP', () => {
    it('should contain data for common subsets', () => {
      expect(UNICODE_RANGE_MAP.latin).toBeDefined();
      expect(UNICODE_RANGE_MAP['latin-ext']).toBeDefined();
      expect(UNICODE_RANGE_MAP.cyrillic).toBeDefined();
      expect(UNICODE_RANGE_MAP.greek).toBeDefined();
      expect(UNICODE_RANGE_MAP.vietnamese).toBeDefined();
    });

    it('should have proper structure for each subset', () => {
      const latinData = UNICODE_RANGE_MAP.latin;
      expect(latinData.subset).toBe('latin');
      expect(latinData.displayName).toBe('Latin');
      expect(Array.isArray(latinData.ranges)).toBe(true);
      expect(latinData.ranges.length).toBeGreaterThan(0);
      expect(latinData.description).toBeTruthy();
    });

    it('should have valid Unicode range format', () => {
      const latinData = UNICODE_RANGE_MAP.latin;
      // Check that ranges follow U+XXXX or U+XXXX-XXXX format
      latinData.ranges.forEach(range => {
        expect(range).toMatch(/^U\+[0-9A-F]{4}(-[0-9A-F]{4,5})?$/);
      });
    });
  });

  describe('getUnicodeRangesForSubsets', () => {
    it('should return Unicode ranges for a single subset', () => {
      const ranges = getUnicodeRangesForSubsets(['latin']);
      expect(ranges.length).toBeGreaterThan(0);
      expect(ranges).toContain('U+0000-00FF');
    });

    it('should return combined Unicode ranges for multiple subsets', () => {
      const ranges = getUnicodeRangesForSubsets(['latin', 'cyrillic']);
      expect(ranges.length).toBeGreaterThan(0);
      // Should contain ranges from both subsets
      expect(ranges.some(r => r.startsWith('U+0000'))).toBe(true); // Latin
      expect(ranges.some(r => r.startsWith('U+0400'))).toBe(true); // Cyrillic
    });

    it('should remove duplicate ranges', () => {
      const ranges = getUnicodeRangesForSubsets(['latin', 'latin']);
      const uniqueRanges = [...new Set(ranges)];
      expect(ranges.length).toBe(uniqueRanges.length);
    });

    it('should return empty array for unknown subsets', () => {
      const ranges = getUnicodeRangesForSubsets(['unknown-subset']);
      expect(ranges).toEqual([]);
    });

    it('should handle empty array', () => {
      const ranges = getUnicodeRangesForSubsets([]);
      expect(ranges).toEqual([]);
    });
  });

  describe('getDisplayNamesForSubsets', () => {
    it('should return display names for subsets', () => {
      const names = getDisplayNamesForSubsets(['latin', 'cyrillic', 'greek']);
      expect(names).toEqual(['Latin', 'Cyrillic', 'Greek']);
    });

    it('should filter out unknown subsets', () => {
      const names = getDisplayNamesForSubsets(['latin', 'unknown', 'cyrillic']);
      expect(names).toEqual(['Latin', 'Cyrillic']);
    });

    it('should handle empty array', () => {
      const names = getDisplayNamesForSubsets([]);
      expect(names).toEqual([]);
    });
  });

  describe('formatUnicodeRangeForCSS', () => {
    it('should format ranges for CSS with comma separation', () => {
      const ranges = ['U+0000-00FF', 'U+0131', 'U+0152-0153'];
      const formatted = formatUnicodeRangeForCSS(ranges);
      expect(formatted).toBe('U+0000-00FF, U+0131, U+0152-0153');
    });

    it('should handle single range', () => {
      const formatted = formatUnicodeRangeForCSS(['U+0000-00FF']);
      expect(formatted).toBe('U+0000-00FF');
    });

    it('should handle empty array', () => {
      const formatted = formatUnicodeRangeForCSS([]);
      expect(formatted).toBe('');
    });
  });

  describe('formatUnicodeRangeForDisplay', () => {
    it('should format range with hyphen for display', () => {
      const formatted = formatUnicodeRangeForDisplay('U+0000-00FF');
      expect(formatted).toBe('U+0000 - U+00FF');
    });

    it('should not modify single character range', () => {
      const formatted = formatUnicodeRangeForDisplay('U+0131');
      expect(formatted).toBe('U+0131');
    });
  });

  describe('getAllSubsets', () => {
    it('should return array of all subset keys', () => {
      const subsets = getAllSubsets();
      expect(Array.isArray(subsets)).toBe(true);
      expect(subsets.length).toBeGreaterThan(0);
      expect(subsets).toContain('latin');
      expect(subsets).toContain('cyrillic');
      expect(subsets).toContain('greek');
    });

    it('should contain all keys from UNICODE_RANGE_MAP', () => {
      const subsets = getAllSubsets();
      const mapKeys = Object.keys(UNICODE_RANGE_MAP);
      expect(subsets.length).toBe(mapKeys.length);
      mapKeys.forEach(key => {
        expect(subsets).toContain(key);
      });
    });
  });

  describe('getCharacterCountEstimate', () => {
    it('should return positive count for valid subset', () => {
      const count = getCharacterCountEstimate('latin');
      expect(count).toBeGreaterThan(0);
    });

    it('should calculate range correctly', () => {
      // Latin includes U+0000-00FF which is 256 characters
      const count = getCharacterCountEstimate('latin');
      expect(count).toBeGreaterThan(256);
    });

    it('should return 0 for unknown subset', () => {
      const count = getCharacterCountEstimate('unknown-subset');
      expect(count).toBe(0);
    });
  });

  describe('getSubsetInfo', () => {
    it('should return full info for valid subset', () => {
      const info = getSubsetInfo('latin');
      expect(info).toBeDefined();
      expect(info?.subset).toBe('latin');
      expect(info?.displayName).toBe('Latin');
      expect(info?.ranges.length).toBeGreaterThan(0);
      expect(info?.description).toBeTruthy();
    });

    it('should return undefined for unknown subset', () => {
      const info = getSubsetInfo('unknown-subset');
      expect(info).toBeUndefined();
    });
  });

  describe('fontSupportsSubsets', () => {
    it('should return true when font supports all required subsets', () => {
      const fontSubsets = ['latin', 'latin-ext', 'cyrillic'];
      const requiredSubsets = ['latin', 'cyrillic'];
      expect(fontSupportsSubsets(fontSubsets, requiredSubsets)).toBe(true);
    });

    it('should return false when font is missing a required subset', () => {
      const fontSubsets = ['latin', 'latin-ext'];
      const requiredSubsets = ['latin', 'cyrillic'];
      expect(fontSupportsSubsets(fontSubsets, requiredSubsets)).toBe(false);
    });

    it('should return true for empty required subsets', () => {
      const fontSubsets = ['latin'];
      const requiredSubsets: string[] = [];
      expect(fontSupportsSubsets(fontSubsets, requiredSubsets)).toBe(true);
    });

    it('should return false when font has no subsets', () => {
      const fontSubsets: string[] = [];
      const requiredSubsets = ['latin'];
      expect(fontSupportsSubsets(fontSubsets, requiredSubsets)).toBe(false);
    });
  });

  describe('Subset Coverage', () => {
    it('should have comprehensive coverage of major writing systems', () => {
      const subsets = getAllSubsets();

      // Check for major writing systems
      expect(subsets).toContain('latin');
      expect(subsets).toContain('cyrillic');
      expect(subsets).toContain('greek');
      expect(subsets).toContain('arabic');
      expect(subsets).toContain('hebrew');
      expect(subsets).toContain('devanagari');
      expect(subsets).toContain('thai');
      expect(subsets).toContain('vietnamese');
    });

    it('should have Asian language support', () => {
      const subsets = getAllSubsets();
      expect(subsets).toContain('chinese-simplified');
      expect(subsets).toContain('japanese');
      expect(subsets).toContain('korean');
    });
  });
});
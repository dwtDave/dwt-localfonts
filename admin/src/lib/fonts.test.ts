import { describe, it, expect, beforeAll, vi } from 'vitest';
import {
  getAllFonts,
  searchAndFilterFonts,
  getAvailableSubsets,
} from './fonts';

// Mock window.dwtLocalFonts
beforeAll(() => {
  (globalThis as any).window = {
    dwtLocalFonts: {
      pluginUrl: 'http://example.com/wp-content/plugins/dwt-localfonts',
    },
  };

  // Mock fetch to return sample font data
  globalThis.fetch = vi.fn().mockResolvedValue({
    ok: true,
    json: async () => ([
      {
        id: 'roboto',
        family: 'Roboto',
        category: 'sans-serif',
        weights: [300, 400, 500, 700],
        styles: ['normal', 'italic'],
        subsets: ['latin', 'latin-ext', 'cyrillic'],
        version: 'v30',
        lastModified: '2023-03-01',
      },
      {
        id: 'open-sans',
        family: 'Open Sans',
        category: 'sans-serif',
        weights: [400, 600, 700],
        styles: ['normal'],
        subsets: ['latin'],
        version: 'v29',
        lastModified: '2023-02-15',
      },
      {
        id: 'merriweather',
        family: 'Merriweather',
        category: 'serif',
        weights: [300, 400, 700],
        styles: ['normal', 'italic'],
        subsets: ['latin'],
        version: 'v28',
        lastModified: '2023-01-10',
      },
    ]),
  });
});

describe('Fonts Library', () => {
  describe('getAllFonts', () => {
    it('should return all available fonts', async () => {
      const fonts = await getAllFonts();
      expect(fonts).toBeDefined();
      expect(Array.isArray(fonts)).toBe(true);
      expect(fonts.length).toBe(3);
    });

    it('should return fonts with required properties', async () => {
      const fonts = await getAllFonts();
      const firstFont = fonts[0];

      expect(firstFont).toHaveProperty('family');
      expect(firstFont).toHaveProperty('variants');
      expect(firstFont).toHaveProperty('subsets');
      expect(firstFont).toHaveProperty('category');
      expect(firstFont).toHaveProperty('version');
      expect(firstFont).toHaveProperty('lastModified');
    });

    it('should generate variants correctly from weights and styles', async () => {
      const fonts = await getAllFonts();
      const fontWithMultipleWeights = fonts.find(f => f.variants.length > 1);

      expect(fontWithMultipleWeights).toBeDefined();
      expect(fontWithMultipleWeights!.variants.length).toBeGreaterThan(0);
      expect(fontWithMultipleWeights!.variants.every(v => typeof v === 'string')).toBe(true);
    });
  });

  describe('searchAndFilterFonts', () => {
    it('should return all fonts when search query is empty', async () => {
      const searchResults = await searchAndFilterFonts('');

      expect(searchResults.length).toBe(3);
    });

    it('should filter fonts by name', async () => {
      const results = await searchAndFilterFonts('roboto');

      expect(results.length).toBe(1);
      expect(results[0].family).toBe('Roboto');
    });

    it('should be case-insensitive', async () => {
      const lowerResults = await searchAndFilterFonts('roboto');
      const upperResults = await searchAndFilterFonts('ROBOTO');
      const mixedResults = await searchAndFilterFonts('RoBoto');

      expect(lowerResults.length).toBe(1);
      expect(upperResults.length).toBe(1);
      expect(mixedResults.length).toBe(1);
    });

    it('should return empty array for non-existent font', async () => {
      const results = await searchAndFilterFonts('ThisFontDefinitelyDoesNotExist12345');
      expect(results.length).toBe(0);
    });

    it('should filter by category', async () => {
      const results = await searchAndFilterFonts('', 'serif');

      expect(results.length).toBe(1);
      expect(results[0].family).toBe('Merriweather');
      expect(results[0].category).toBe('serif');
    });

    it('should apply both search and category filters', async () => {
      const results = await searchAndFilterFonts('open', 'sans-serif');

      expect(results.length).toBe(1);
      expect(results[0].family).toBe('Open Sans');
      expect(results[0].category).toBe('sans-serif');
    });

    it('should return empty array when filters match nothing', async () => {
      const results = await searchAndFilterFonts('ThisFontDoesNotExist', 'serif');
      expect(results.length).toBe(0);
    });
  });

  describe('getAvailableSubsets', () => {
    it('should return array of available subsets', async () => {
      const subsets = await getAvailableSubsets();

      expect(Array.isArray(subsets)).toBe(true);
      expect(subsets.length).toBeGreaterThan(0);
    });

    it('should include latin subset', async () => {
      const subsets = await getAvailableSubsets();
      expect(subsets).toContain('latin');
    });

    it('should return unique subsets', async () => {
      const subsets = await getAvailableSubsets();
      const uniqueSubsets = new Set(subsets);

      expect(subsets.length).toBe(uniqueSubsets.size);
    });
  });
});

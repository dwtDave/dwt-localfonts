import type { GoogleFont } from '../types';
import { getUnicodeRangesForSubsets } from './unicodeRanges';

interface FontSourceData {
  id: string;
  family: string;
  category: string;
  weights: number[];
  styles: string[];
  subsets: string[];
  version: string;
  lastModified: string;
}

/**
 * Transform the raw fonts data into GoogleFont format
 */
function transformFontsData(data: FontSourceData[]): GoogleFont[] {
  return data.map((font) => {
    // Generate variants list from weights and styles
    const variants: string[] = [];

    font.weights.forEach((weight) => {
      font.styles.forEach((style) => {
        if (style === 'normal') {
          variants.push(weight.toString());
        } else if (style === 'italic') {
          variants.push(`${weight}italic`);
        }
      });
    });

    // Get Unicode ranges based on subsets
    const unicodeRanges = getUnicodeRangesForSubsets(font.subsets);

    return {
      id: font.id,
      family: font.family,
      variants,
      subsets: font.subsets,
      category: font.category,
      version: font.version,
      lastModified: font.lastModified,
      unicodeRanges,
    };
  });
}

// Font list state
let allFonts: GoogleFont[] = [];
let fontsPromise: Promise<GoogleFont[]> | null = null;
let fontsLoaded = false;

/**
 * Fetch and load the Google Fonts list from the public directory
 */
async function loadFonts(): Promise<GoogleFont[]> {
  if (fontsLoaded && allFonts.length > 0) {
    return allFonts;
  }

  if (fontsPromise) {
    return fontsPromise;
  }

  fontsPromise = (async () => {
    try {
      // Get the WordPress plugin URL from the global object
      const pluginUrl = (window as any).dwtLocalFonts?.pluginUrl || '';
      const fontsUrl = `${pluginUrl}/public/data/google-fonts-list.json`;

      const response = await fetch(fontsUrl);

      if (!response.ok) {
        throw new Error(`Failed to fetch fonts: ${response.status} ${response.statusText}`);
      }

      const data: FontSourceData[] = await response.json();

      if (!Array.isArray(data) || data.length === 0) {
        throw new Error('Invalid fonts data: expected non-empty array');
      }

      allFonts = transformFontsData(data);
      fontsLoaded = true;

      return allFonts;
    } catch (error) {
      console.error('[FontManager] Failed to load google-fonts-list.json:', error);
      fontsPromise = null; // Reset promise so we can retry
      throw error;
    }
  })();

  return fontsPromise;
}

/**
 * Get all available Google Fonts (async version)
 */
export async function getAllFonts(): Promise<GoogleFont[]> {
  return loadFonts();
}

/**
 * Get cached fonts synchronously (may be empty if not loaded yet)
 */
export function getAllFontsSync(): GoogleFont[] {
  return allFonts;
}

/**
 * Check if fonts are loaded
 */
export function areFontsLoaded(): boolean {
  return fontsLoaded;
}

/**
 * Search fonts by name (async version)
 */
export async function searchFonts(query: string): Promise<GoogleFont[]> {
  const fonts = await loadFonts();
  if (!query) return fonts;

  const lowerQuery = query.toLowerCase();
  return fonts.filter((font) =>
    font.family.toLowerCase().includes(lowerQuery)
  );
}

/**
 * Search fonts by name (sync version with cached data)
 */
export function searchFontsSync(query: string): GoogleFont[] {
  if (!query) return allFonts;

  const lowerQuery = query.toLowerCase();
  return allFonts.filter((font) =>
    font.family.toLowerCase().includes(lowerQuery)
  );
}

/**
 * Filter fonts by category (async version)
 */
export async function filterFontsByCategory(category: string): Promise<GoogleFont[]> {
  const fonts = await loadFonts();
  if (!category || category === 'all') return fonts;

  return fonts.filter((font) => font.category === category);
}

/**
 * Filter fonts by category (sync version with cached data)
 */
export function filterFontsByCategorySync(category: string): GoogleFont[] {
  if (!category || category === 'all') return allFonts;

  return allFonts.filter((font) => font.category === category);
}

/**
 * Search and filter fonts (async version)
 */
export async function searchAndFilterFonts(
  query: string = '',
  category: string = 'all'
): Promise<GoogleFont[]> {
  const fonts = await loadFonts();
  let results = fonts;

  // Apply category filter
  if (category && category !== 'all') {
    results = results.filter((font) => font.category === category);
  }

  // Apply search query
  if (query) {
    const lowerQuery = query.toLowerCase();
    results = results.filter((font) =>
      font.family.toLowerCase().includes(lowerQuery)
    );
  }

  return results;
}

/**
 * Search and filter fonts (sync version with cached data)
 */
export function searchAndFilterFontsSync(
  query: string = '',
  category: string = 'all'
): GoogleFont[] {
  let results = allFonts;

  // Apply category filter
  if (category && category !== 'all') {
    results = results.filter((font) => font.category === category);
  }

  // Apply search query
  if (query) {
    const lowerQuery = query.toLowerCase();
    results = results.filter((font) =>
      font.family.toLowerCase().includes(lowerQuery)
    );
  }

  return results;
}

/**
 * Get font by family name (async version)
 */
export async function getFontByFamily(family: string): Promise<GoogleFont | undefined> {
  const fonts = await loadFonts();
  return fonts.find((font) => font.family === family);
}

/**
 * Get font by family name (sync version with cached data)
 */
export function getFontByFamilySync(family: string): GoogleFont | undefined {
  return allFonts.find((font) => font.family === family);
}

/**
 * Get available categories (async version)
 */
export async function getCategories(): Promise<string[]> {
  const fonts = await loadFonts();
  const categories = new Set(fonts.map((font) => font.category));
  return ['all', ...Array.from(categories).sort()];
}

/**
 * Get available categories (sync version with cached data)
 */
export function getCategoriesSync(): string[] {
  const categories = new Set(allFonts.map((font) => font.category));
  return ['all', ...Array.from(categories).sort()];
}

/**
 * Filter fonts by subset/language support (async version)
 */
export async function filterFontsBySubset(subset: string): Promise<GoogleFont[]> {
  const fonts = await loadFonts();
  if (!subset || subset === 'all') return fonts;

  return fonts.filter((font) => font.subsets.includes(subset));
}

/**
 * Filter fonts by subset/language support (sync version with cached data)
 */
export function filterFontsBySubsetSync(subset: string): GoogleFont[] {
  if (!subset || subset === 'all') return allFonts;

  return allFonts.filter((font) => font.subsets.includes(subset));
}

/**
 * Get available subsets from all fonts (async version)
 */
export async function getAvailableSubsets(): Promise<string[]> {
  const fonts = await loadFonts();
  const subsets = new Set<string>();
  fonts.forEach((font) => {
    font.subsets.forEach((subset) => subsets.add(subset));
  });
  return ['all', ...Array.from(subsets).sort()];
}

/**
 * Get available subsets from all fonts (sync version with cached data)
 */
export function getAvailableSubsetsSync(): string[] {
  const subsets = new Set<string>();
  allFonts.forEach((font) => {
    font.subsets.forEach((subset) => subsets.add(subset));
  });
  return ['all', ...Array.from(subsets).sort()];
}

/**
 * Reset fonts cache (for testing)
 */
export function resetFontsCache(): void {
  allFonts = [];
  fontsPromise = null;
  fontsLoaded = false;
}
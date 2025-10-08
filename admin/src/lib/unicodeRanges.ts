/**
 * Unicode Range Utilities
 *
 * Maps Google Fonts subsets to their corresponding Unicode ranges for character coverage.
 * Based on official Google Fonts CSS API unicode-range values.
 */

export interface UnicodeRangeData {
  subset: string;
  displayName: string;
  ranges: string[];
  description: string;
}

/**
 * Comprehensive mapping of Google Fonts subsets to Unicode ranges
 */
export const UNICODE_RANGE_MAP: Record<string, UnicodeRangeData> = {
  latin: {
    subset: 'latin',
    displayName: 'Latin',
    ranges: [
      'U+0000-00FF',
      'U+0131',
      'U+0152-0153',
      'U+02BB-02BC',
      'U+02C6',
      'U+02DA',
      'U+02DC',
      'U+0304',
      'U+0308',
      'U+0329',
      'U+2000-206F',
      'U+2074',
      'U+20AC',
      'U+2122',
      'U+2191',
      'U+2193',
      'U+2212',
      'U+2215',
      'U+FEFF',
      'U+FFFD',
    ],
    description: 'Basic Latin characters (English and Western European languages)',
  },
  'latin-ext': {
    subset: 'latin-ext',
    displayName: 'Latin Extended',
    ranges: [
      'U+0100-02FF',
      'U+0259',
      'U+0304',
      'U+0308',
      'U+0329',
      'U+1D00-1DBF',
      'U+1E00-1EFF',
      'U+2020',
      'U+20A0-20AB',
      'U+20AD-20CF',
      'U+2113',
      'U+2C60-2C7F',
      'U+A720-A7FF',
    ],
    description: 'Extended Latin characters (Central/Eastern European languages)',
  },
  vietnamese: {
    subset: 'vietnamese',
    displayName: 'Vietnamese',
    ranges: [
      'U+0102-0103',
      'U+0110-0111',
      'U+0128-0129',
      'U+0168-0169',
      'U+01A0-01A1',
      'U+01AF-01B0',
      'U+0300-0301',
      'U+0303-0304',
      'U+0308-0309',
      'U+0323',
      'U+0329',
      'U+1EA0-1EF9',
      'U+20AB',
    ],
    description: 'Vietnamese characters and diacritics',
  },
  cyrillic: {
    subset: 'cyrillic',
    displayName: 'Cyrillic',
    ranges: ['U+0301', 'U+0400-045F', 'U+0490-0491', 'U+04B0-04B1', 'U+2116'],
    description: 'Cyrillic characters (Russian, Ukrainian, Bulgarian, etc.)',
  },
  'cyrillic-ext': {
    subset: 'cyrillic-ext',
    displayName: 'Cyrillic Extended',
    ranges: [
      'U+0460-052F',
      'U+1C80-1C88',
      'U+20B4',
      'U+2DE0-2DFF',
      'U+A640-A69F',
      'U+FE2E-FE2F',
    ],
    description: 'Extended Cyrillic characters for additional Slavic languages',
  },
  greek: {
    subset: 'greek',
    displayName: 'Greek',
    ranges: ['U+0370-0377', 'U+037A-037F', 'U+0384-038A', 'U+038C', 'U+038E-03A1', 'U+03A3-03FF'],
    description: 'Greek alphabet characters',
  },
  'greek-ext': {
    subset: 'greek-ext',
    displayName: 'Greek Extended',
    ranges: ['U+1F00-1FFF'],
    description: 'Extended Greek characters including polytonic Greek',
  },
  arabic: {
    subset: 'arabic',
    displayName: 'Arabic',
    ranges: [
      'U+0600-06FF',
      'U+0750-077F',
      'U+0870-088E',
      'U+0890-0891',
      'U+0898-08E1',
      'U+08E3-08FF',
      'U+200C-200E',
      'U+2010-2011',
      'U+204F',
      'U+2E41',
      'U+FB50-FDFF',
      'U+FE70-FE74',
      'U+FE76-FEFC',
      'U+102E0-102FB',
      'U+10E60-10E7E',
      'U+10EFD-10EFF',
      'U+1EE00-1EE03',
      'U+1EE05-1EE1F',
      'U+1EE21-1EE22',
      'U+1EE24',
      'U+1EE27',
      'U+1EE29-1EE32',
      'U+1EE34-1EE37',
      'U+1EE39',
      'U+1EE3B',
      'U+1EE42',
      'U+1EE47',
      'U+1EE49',
      'U+1EE4B',
      'U+1EE4D-1EE4F',
      'U+1EE51-1EE52',
      'U+1EE54',
      'U+1EE57',
      'U+1EE59',
      'U+1EE5B',
      'U+1EE5D',
      'U+1EE5F',
      'U+1EE61-1EE62',
      'U+1EE64',
      'U+1EE67-1EE6A',
      'U+1EE6C-1EE72',
      'U+1EE74-1EE77',
      'U+1EE79-1EE7C',
      'U+1EE7E',
      'U+1EE80-1EE89',
      'U+1EE8B-1EE9B',
      'U+1EEA1-1EEA3',
      'U+1EEA5-1EEA9',
      'U+1EEAB-1EEBB',
    ],
    description: 'Arabic script characters',
  },
  hebrew: {
    subset: 'hebrew',
    displayName: 'Hebrew',
    ranges: ['U+0590-05FF', 'U+200C-2010', 'U+20AA', 'U+25CC', 'U+FB1D-FB4F'],
    description: 'Hebrew alphabet characters',
  },
  devanagari: {
    subset: 'devanagari',
    displayName: 'Devanagari',
    ranges: [
      'U+0900-097F',
      'U+1CD0-1CF9',
      'U+200C-200D',
      'U+20A8',
      'U+20B9',
      'U+20F0',
      'U+25CC',
      'U+A830-A839',
      'U+A8E0-A8FF',
      'U+11B00-11B09',
    ],
    description: 'Devanagari script (Hindi, Sanskrit, Marathi, Nepali)',
  },
  bengali: {
    subset: 'bengali',
    displayName: 'Bengali',
    ranges: [
      'U+0951-0952',
      'U+0964-0965',
      'U+0980-09FE',
      'U+1CD0',
      'U+1CD2',
      'U+1CD5-1CD6',
      'U+1CD8',
      'U+1CE1',
      'U+1CEA',
      'U+1CED',
      'U+1CF2',
      'U+1CF5-1CF7',
      'U+200C-200D',
      'U+20B9',
      'U+25CC',
      'U+A8F1',
    ],
    description: 'Bengali script',
  },
  gujarati: {
    subset: 'gujarati',
    displayName: 'Gujarati',
    ranges: [
      'U+0951-0952',
      'U+0964-0965',
      'U+0A80-0AFF',
      'U+200C-200D',
      'U+20B9',
      'U+25CC',
      'U+A830-A839',
    ],
    description: 'Gujarati script',
  },
  gurmukhi: {
    subset: 'gurmukhi',
    displayName: 'Gurmukhi',
    ranges: ['U+0951-0952', 'U+0964-0965', 'U+0A00-0A76', 'U+200C-200D', 'U+20B9', 'U+25CC', 'U+A830-A839'],
    description: 'Gurmukhi script (Punjabi)',
  },
  kannada: {
    subset: 'kannada',
    displayName: 'Kannada',
    ranges: [
      'U+0951-0952',
      'U+0964-0965',
      'U+0C80-0CF3',
      'U+1CD0',
      'U+1CD2-1CD3',
      'U+1CDA',
      'U+1CF2',
      'U+1CF4',
      'U+200C-200D',
      'U+20B9',
      'U+25CC',
    ],
    description: 'Kannada script',
  },
  malayalam: {
    subset: 'malayalam',
    displayName: 'Malayalam',
    ranges: [
      'U+0951-0952',
      'U+0964-0965',
      'U+0D00-0D7F',
      'U+1CDA',
      'U+1CF2',
      'U+200C-200D',
      'U+25CC',
      'U+A830-A832',
    ],
    description: 'Malayalam script',
  },
  oriya: {
    subset: 'oriya',
    displayName: 'Oriya',
    ranges: [
      'U+0951-0952',
      'U+0964-0965',
      'U+0B00-0B77',
      'U+1CD0',
      'U+1CD2-1CD3',
      'U+1CF2',
      'U+1CF5-1CF7',
      'U+200C-200D',
      'U+20B9',
      'U+25CC',
    ],
    description: 'Oriya script (Odia language)',
  },
  tamil: {
    subset: 'tamil',
    displayName: 'Tamil',
    ranges: [
      'U+0964-0965',
      'U+0B82-0BFA',
      'U+1CDA',
      'U+1CF2',
      'U+200C-200D',
      'U+20B9',
      'U+25CC',
      'U+A8F3',
      'U+11FC0-11FF1',
      'U+11FFF',
    ],
    description: 'Tamil script',
  },
  telugu: {
    subset: 'telugu',
    displayName: 'Telugu',
    ranges: [
      'U+0951-0952',
      'U+0964-0965',
      'U+0C00-0C7F',
      'U+1CD0',
      'U+1CD2-1CD3',
      'U+1CDA',
      'U+1CF2',
      'U+1CF4',
      'U+200C-200D',
      'U+25CC',
    ],
    description: 'Telugu script',
  },
  thai: {
    subset: 'thai',
    displayName: 'Thai',
    ranges: ['U+0E01-0E5B', 'U+200C-200D', 'U+25CC'],
    description: 'Thai script',
  },
  'khmer': {
    subset: 'khmer',
    displayName: 'Khmer',
    ranges: ['U+1780-17FF', 'U+19E0-19FF', 'U+200C-200D', 'U+25CC'],
    description: 'Khmer script (Cambodian)',
  },
  'chinese-simplified': {
    subset: 'chinese-simplified',
    displayName: 'Chinese (Simplified)',
    ranges: ['U+4E00-9FFF', 'U+3400-4DBF', 'U+20000-2A6DF'],
    description: 'Simplified Chinese characters',
  },
  'chinese-traditional': {
    subset: 'chinese-traditional',
    displayName: 'Chinese (Traditional)',
    ranges: ['U+4E00-9FFF', 'U+3400-4DBF', 'U+20000-2A6DF', 'U+F900-FAFF'],
    description: 'Traditional Chinese characters',
  },
  'chinese-hongkong': {
    subset: 'chinese-hongkong',
    displayName: 'Chinese (Hong Kong)',
    ranges: ['U+4E00-9FFF', 'U+3400-4DBF', 'U+20000-2A6DF', 'U+F900-FAFF', 'U+2F800-2FA1F'],
    description: 'Hong Kong Chinese characters',
  },
  japanese: {
    subset: 'japanese',
    displayName: 'Japanese',
    ranges: [
      'U+3000-303F',
      'U+3040-309F',
      'U+30A0-30FF',
      'U+FF00-FFEF',
      'U+4E00-9FAF',
      'U+3400-4DBF',
    ],
    description: 'Japanese characters (Hiragana, Katakana, Kanji)',
  },
  korean: {
    subset: 'korean',
    displayName: 'Korean',
    ranges: ['U+1100-11FF', 'U+3130-318F', 'U+AC00-D7AF', 'U+A960-A97F', 'U+D7B0-D7FF'],
    description: 'Korean Hangul characters',
  },
  sinhala: {
    subset: 'sinhala',
    displayName: 'Sinhala',
    ranges: ['U+0964-0965', 'U+0D81-0DF4', 'U+200C-200D', 'U+25CC', 'U+111E1-111F4'],
    description: 'Sinhala script (Sri Lankan)',
  },
  myanmar: {
    subset: 'myanmar',
    displayName: 'Myanmar',
    ranges: ['U+1000-109F', 'U+200C-200D', 'U+25CC', 'U+A9E0-A9FF', 'U+AA60-AA7F'],
    description: 'Myanmar (Burmese) script',
  },
  adlam: {
    subset: 'adlam',
    displayName: 'Adlam',
    ranges: ['U+1E900-1E95F'],
    description: 'Adlam script (West African Fulani)',
  },
  'symbols': {
    subset: 'symbols',
    displayName: 'Symbols',
    ranges: ['U+2000-206F', 'U+2070-209F', 'U+20A0-20CF', 'U+2100-214F', 'U+2190-21FF', 'U+2200-22FF'],
    description: 'Mathematical and technical symbols',
  },
};

/**
 * Get Unicode ranges for an array of subsets
 */
export function getUnicodeRangesForSubsets(subsets: string[]): string[] {
  const ranges: string[] = [];

  for (const subset of subsets) {
    const data = UNICODE_RANGE_MAP[subset];
    if (data) {
      ranges.push(...data.ranges);
    }
  }

  return [...new Set(ranges)]; // Remove duplicates
}

/**
 * Get display names for subsets
 */
export function getDisplayNamesForSubsets(subsets: string[]): string[] {
  return subsets
    .map(subset => UNICODE_RANGE_MAP[subset]?.displayName)
    .filter(Boolean) as string[];
}

/**
 * Format Unicode range for CSS
 */
export function formatUnicodeRangeForCSS(ranges: string[]): string {
  return ranges.join(', ');
}

/**
 * Format Unicode range for human-readable display
 */
export function formatUnicodeRangeForDisplay(range: string): string {
  // Convert U+0000-00FF to "U+0000 - U+00FF"
  if (range.includes('-')) {
    const [start, end] = range.split('-');
    return `${start} - U+${end}`;
  }
  return range;
}

/**
 * Get all available subsets
 */
export function getAllSubsets(): string[] {
  return Object.keys(UNICODE_RANGE_MAP);
}

/**
 * Get character count estimate for a subset (approximate)
 */
export function getCharacterCountEstimate(subset: string): number {
  const data = UNICODE_RANGE_MAP[subset];
  if (!data) return 0;

  let count = 0;
  for (const range of data.ranges) {
    if (range.includes('-')) {
      const [start, end] = range.replace(/U\+/g, '').split('-');
      const startCode = parseInt(start, 16);
      const endCode = parseInt(end, 16);
      count += endCode - startCode + 1;
    } else {
      count += 1; // Single character
    }
  }

  return count;
}

/**
 * Get subset information by name
 */
export function getSubsetInfo(subset: string): UnicodeRangeData | undefined {
  return UNICODE_RANGE_MAP[subset];
}

/**
 * Check if a font supports specific subsets
 */
export function fontSupportsSubsets(fontSubsets: string[], requiredSubsets: string[]): boolean {
  return requiredSubsets.every(required => fontSubsets.includes(required));
}
import React, { ReactElement } from 'react';
import { render, RenderOptions } from '@testing-library/react';
import type { DWTSettings, GoogleFont, LocalFont } from '../types';

// Mock settings data
export const mockSettings: DWTSettings = {
  google_fonts_api_key: 'test-api-key',
  font_display_swap: true,
  font_hosting_mode: 'local',
  keep_fonts_on_uninstall: false,
};

// Mock Google Fonts source data (raw format from JSON)
export const mockGoogleFontsSourceData = [
  {
    id: 'roboto',
    family: 'Roboto',
    category: 'sans-serif',
    weights: [300, 400, 500, 700],
    styles: ['normal'],
    subsets: ['latin', 'latin-ext'],
    version: 'v30',
    lastModified: '2024-01-01',
  },
  {
    id: 'open-sans',
    family: 'Open Sans',
    category: 'sans-serif',
    weights: [300, 400, 600, 700],
    styles: ['normal'],
    subsets: ['latin'],
    version: 'v34',
    lastModified: '2024-01-01',
  },
  {
    id: 'lora',
    family: 'Lora',
    category: 'serif',
    weights: [400, 500, 600, 700],
    styles: ['normal'],
    subsets: ['latin'],
    version: 'v32',
    lastModified: '2024-01-01',
  },
];

// Mock Google Fonts data (transformed format)
export const mockGoogleFonts: GoogleFont[] = [
  {
    id: 'roboto',
    family: 'Roboto',
    variants: ['300', '400', '500', '700'],
    subsets: ['latin', 'latin-ext'],
    category: 'sans-serif',
    version: 'v30',
    lastModified: '2024-01-01',
    unicodeRanges: [],
  },
  {
    id: 'open-sans',
    family: 'Open Sans',
    variants: ['300', '400', '600', '700'],
    subsets: ['latin'],
    category: 'sans-serif',
    version: 'v34',
    lastModified: '2024-01-01',
    unicodeRanges: [],
  },
  {
    id: 'lora',
    family: 'Lora',
    variants: ['400', '500', '600', '700'],
    subsets: ['latin'],
    category: 'serif',
    version: 'v32',
    lastModified: '2024-01-01',
    unicodeRanges: [],
  },
];

// Mock local fonts data
export const mockLocalFonts: LocalFont[] = [
  {
    family: 'Roboto',
    status: 'downloaded',
    path: '/wp-content/uploads/dwt-fonts/roboto',
    downloadedAt: '2024-01-01T00:00:00Z',
    variants: ['400', '700'],
  },
];

// Custom render function that can include providers
export function renderWithProviders(
  ui: ReactElement,
  options?: Omit<RenderOptions, 'wrapper'>
) {
  return render(ui, { ...options });
}

// Re-export everything from RTL
export * from '@testing-library/react';
export { renderWithProviders as render };
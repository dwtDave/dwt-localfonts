import '@testing-library/jest-dom';
import { expect, afterEach, vi, beforeEach } from 'vitest';
import { cleanup } from '@testing-library/react';
import { mockGoogleFontsSourceData } from './helpers';
import { resetFontsCache } from '../lib/fonts';

// Initialize the global mocks
window.dwtLocalFonts = {
  pluginUrl: 'http://example.com/wp-content/plugins/dwt-localfonts',
  nonce: 'test-nonce-12345',
  apiUrl: 'http://example.com/wp-json',
  uploadsUrl: 'http://example.com/wp-content/uploads',
};

// Reset window globals and clear all mocks before each test
beforeEach(() => {
  window.dwtLocalFonts = {
    pluginUrl: 'http://example.com/wp-content/plugins/dwt-localfonts',
    nonce: 'test-nonce-12345',
    apiUrl: 'http://example.com/wp-json',
    uploadsUrl: 'http://example.com/wp-content/uploads',
  };
  vi.clearAllMocks();
});

// Cleanup after each test
afterEach(() => {
  cleanup();
  vi.clearAllMocks();
  resetFontsCache(); // Reset fonts module state between tests
});

// Mock matchMedia
Object.defineProperty(window, 'matchMedia', {
  writable: true,
  value: vi.fn().mockImplementation(query => ({
    matches: false,
    media: query,
    onchange: null,
    addListener: vi.fn(),
    removeListener: vi.fn(),
    addEventListener: vi.fn(),
    removeEventListener: vi.fn(),
    dispatchEvent: vi.fn(),
  })),
});

// Mock IntersectionObserver
global.IntersectionObserver = class IntersectionObserver {
  constructor() {}
  disconnect() {}
  observe() {}
  takeRecords() {
    return [];
  }
  unobserve() {}
} as any;

// Mock ResizeObserver
global.ResizeObserver = class ResizeObserver {
  constructor() {}
  disconnect() {}
  observe() {}
  unobserve() {}
} as any;

// Mock fetch globally to handle fonts data requests
global.fetch = vi.fn((url: string | URL) => {
  const urlString = typeof url === 'string' ? url : url.toString();

  // Mock the google-fonts-list.json request
  if (urlString.includes('google-fonts-list.json')) {
    return Promise.resolve({
      ok: true,
      status: 200,
      statusText: 'OK',
      json: async () => mockGoogleFontsSourceData,
    } as Response);
  }

  // Default fallback for unmocked requests
  return Promise.reject(new Error(`Unmocked fetch: ${urlString}`));
}) as any;
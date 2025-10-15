import { describe, it, expect, vi, beforeEach, beforeAll } from 'vitest';
import { screen, waitFor } from '@testing-library/react';
import { render } from './test/helpers';

// Mock apiFetch at the module level
vi.mock('@wordpress/api-fetch', () => {
  const mockApiFetch = vi.fn() as any;
  mockApiFetch.use = vi.fn();
  mockApiFetch.createNonceMiddleware = vi.fn(() => vi.fn());
  mockApiFetch.createRootURLMiddleware = vi.fn(() => vi.fn());
  return {
    default: mockApiFetch,
  };
});

import App from './App';
import apiFetch from '@wordpress/api-fetch';

const mockApiFetch = apiFetch as any;

// Capture middleware calls at module load time
let middlewareCalls: { nonce: any[]; rootURL: any[] } = { nonce: [], rootURL: [] };

describe('App Component', () => {
  beforeAll(() => {
    // Capture the middleware setup calls before they're cleared
    middlewareCalls = {
      nonce: [...mockApiFetch.createNonceMiddleware.mock.calls],
      rootURL: [...mockApiFetch.createRootURLMiddleware.mock.calls],
    };
  });

  beforeEach(() => {
    vi.useRealTimers();
    vi.clearAllMocks();
    mockApiFetch.mockImplementation(async (options: any) => {
      if (options.path === '/settings' && !options.method) {
        return { font_display_swap: '1', keep_fonts_on_uninstall: '0', font_hosting_mode: 'local' };
      }
      if (options.path === '/fonts/local') {
        return [];
      }
      if (options.path === '/settings' && options.method === 'POST') {
        return { success: true };
      }
      return {};
    });
  });

  describe('Initial Load', () => {
    it('should render loading state initially', () => {
      render(<App />);
      expect(screen.getByText(/loading/i)).toBeInTheDocument();
    });

    it('should load and display Font Manager', async () => {
      render(<App />);

      await waitFor(() => {
        expect(screen.getByText('Font Manager')).toBeInTheDocument();
      });

      expect(mockApiFetch).toHaveBeenCalledWith({ path: '/settings' });
    });

    it('should set up API fetch middleware on mount', () => {
      // Middleware is set up at module level, so check captured calls
      expect(middlewareCalls.nonce).toHaveLength(1);
      expect(middlewareCalls.nonce[0]).toEqual(['test-nonce-12345']);
      expect(middlewareCalls.rootURL).toHaveLength(1);
      expect(middlewareCalls.rootURL[0]).toEqual(['http://example.com/wp-json']);
    });
  });

  describe('Tab Navigation', () => {
    it('should render all font manager tabs', async () => {
      render(<App />);

      await waitFor(() => {
        expect(screen.getByText('Font Manager')).toBeInTheDocument();
      });

      expect(screen.getByRole('tab', { name: /browse fonts/i })).toBeInTheDocument();
      expect(screen.getByRole('tab', { name: /apply to site/i })).toBeInTheDocument();
      expect(screen.getByRole('tab', { name: /manage downloads/i })).toBeInTheDocument();
      expect(screen.getByRole('tab', { name: /settings/i })).toBeInTheDocument();
    });
  });

  describe('Error Handling', () => {
    it('should handle settings load error gracefully', async () => {
      const consoleErrorSpy = vi.spyOn(console, 'error').mockImplementation(() => {});

      mockApiFetch.mockRejectedValueOnce(new Error('Failed to fetch'));

      render(<App />);

      await waitFor(() => {
        expect(screen.queryByText(/loading/i)).not.toBeInTheDocument();
      }, { timeout: 3000 });

      expect(consoleErrorSpy).toHaveBeenCalledWith(
        'Failed to load settings:',
        expect.any(Error)
      );

      consoleErrorSpy.mockRestore();
    });
  });
});

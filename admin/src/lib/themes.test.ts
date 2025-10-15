import { describe, it, expect, beforeEach, afterEach } from 'vitest';
import { themes, getTheme, applyTheme, type Theme } from './themes';

describe('Themes Configuration', () => {
  describe('themes array', () => {
    it('should contain all predefined themes', () => {
      expect(themes).toHaveLength(6);
      expect(themes.map(t => t.id)).toEqual([
        'default',
        'ocean',
        'forest',
        'sunset',
        'purple',
        'dark',
      ]);
    });

    it('should have valid theme structure', () => {
      themes.forEach(theme => {
        expect(theme).toHaveProperty('id');
        expect(theme).toHaveProperty('name');
        expect(theme).toHaveProperty('description');
        expect(theme).toHaveProperty('colors');
        expect(theme).toHaveProperty('preview');

        expect(typeof theme.id).toBe('string');
        expect(typeof theme.name).toBe('string');
        expect(typeof theme.description).toBe('string');
        expect(typeof theme.colors).toBe('object');
        expect(typeof theme.preview).toBe('object');
      });
    });

    it('should have all required color properties', () => {
      const requiredColors = [
        'background',
        'foreground',
        'card',
        'cardForeground',
        'popover',
        'popoverForeground',
        'primary',
        'primaryForeground',
        'secondary',
        'secondaryForeground',
        'muted',
        'mutedForeground',
        'accent',
        'accentForeground',
        'destructive',
        'destructiveForeground',
        'border',
        'input',
        'ring',
        'radius',
      ];

      themes.forEach(theme => {
        requiredColors.forEach(color => {
          expect(theme.colors).toHaveProperty(color);
          expect(typeof theme.colors[color as keyof typeof theme.colors]).toBe('string');
        });
      });
    });

    it('should have valid preview colors', () => {
      themes.forEach(theme => {
        expect(theme.preview).toHaveProperty('primary');
        expect(theme.preview).toHaveProperty('secondary');
        expect(theme.preview).toHaveProperty('accent');

        // Preview colors should be hex colors
        expect(theme.preview.primary).toMatch(/^#[0-9a-f]{6}$/i);
        expect(theme.preview.secondary).toMatch(/^#[0-9a-f]{6}$/i);
        expect(theme.preview.accent).toMatch(/^#[0-9a-f]{6}$/i);
      });
    });
  });

  describe('getTheme function', () => {
    it('should return theme by id', () => {
      const theme = getTheme('ocean');
      expect(theme).toBeDefined();
      expect(theme.id).toBe('ocean');
      expect(theme.name).toBe('Ocean Blue');
    });

    it('should return default theme for unknown id', () => {
      const theme = getTheme('nonexistent');
      expect(theme).toBeDefined();
      expect(theme.id).toBe('default');
      expect(theme.name).toBe('Default');
    });

    it('should return default theme for empty id', () => {
      const theme = getTheme('');
      expect(theme.id).toBe('default');
    });

    it('should return all different themes', () => {
      const defaultTheme = getTheme('default');
      const oceanTheme = getTheme('ocean');
      const forestTheme = getTheme('forest');
      const sunsetTheme = getTheme('sunset');
      const purpleTheme = getTheme('purple');
      const darkTheme = getTheme('dark');

      expect(defaultTheme.id).toBe('default');
      expect(oceanTheme.id).toBe('ocean');
      expect(forestTheme.id).toBe('forest');
      expect(sunsetTheme.id).toBe('sunset');
      expect(purpleTheme.id).toBe('purple');
      expect(darkTheme.id).toBe('dark');

      // Each theme should be unique
      expect(defaultTheme.colors.primary).not.toBe(oceanTheme.colors.primary);
      expect(forestTheme.colors.primary).not.toBe(sunsetTheme.colors.primary);
    });
  });

  describe('applyTheme function', () => {
    beforeEach(() => {
      // Clear all styles before each test
      document.documentElement.style.cssText = '';
    });

    afterEach(() => {
      // Clean up styles after each test
      document.documentElement.style.cssText = '';
    });

    it('should apply theme colors as CSS variables', () => {
      const theme = getTheme('ocean');
      applyTheme(theme);

      const root = document.documentElement;
      expect(root.style.getPropertyValue('--background')).toBe(theme.colors.background);
      expect(root.style.getPropertyValue('--foreground')).toBe(theme.colors.foreground);
      expect(root.style.getPropertyValue('--primary')).toBe(theme.colors.primary);
    });

    it('should convert camelCase to kebab-case for CSS variables', () => {
      const theme = getTheme('default');
      applyTheme(theme);

      const root = document.documentElement;
      expect(root.style.getPropertyValue('--card-foreground')).toBe(theme.colors.cardForeground);
      expect(root.style.getPropertyValue('--primary-foreground')).toBe(theme.colors.primaryForeground);
      expect(root.style.getPropertyValue('--muted-foreground')).toBe(theme.colors.mutedForeground);
    });

    it('should apply all color properties', () => {
      const theme = getTheme('forest');
      applyTheme(theme);

      const root = document.documentElement;
      const colorKeys = Object.keys(theme.colors);

      colorKeys.forEach(key => {
        const cssVar = `--${key.replace(/([A-Z])/g, '-$1').toLowerCase()}`;
        expect(root.style.getPropertyValue(cssVar)).toBeTruthy();
      });
    });

    it('should overwrite existing theme variables', () => {
      const defaultTheme = getTheme('default');
      const oceanTheme = getTheme('ocean');

      applyTheme(defaultTheme);
      let primaryColor = document.documentElement.style.getPropertyValue('--primary');
      expect(primaryColor).toBe(defaultTheme.colors.primary);

      applyTheme(oceanTheme);
      primaryColor = document.documentElement.style.getPropertyValue('--primary');
      expect(primaryColor).toBe(oceanTheme.colors.primary);
    });

    it('should handle all theme variations', () => {
      themes.forEach(theme => {
        applyTheme(theme);
        const root = document.documentElement;
        expect(root.style.getPropertyValue('--primary')).toBe(theme.colors.primary);
        expect(root.style.getPropertyValue('--background')).toBe(theme.colors.background);
      });
    });
  });

  describe('Theme Specific Tests', () => {
    it('should have proper default theme colors', () => {
      const theme = getTheme('default');
      expect(theme.colors.primary).toBe('222.2 47.4% 11.2%');
      expect(theme.colors.radius).toBe('0.5rem');
    });

    it('should have proper dark theme colors', () => {
      const theme = getTheme('dark');
      expect(theme.colors.background).toBe('222.2 84% 4.9%');
      expect(theme.colors.foreground).toBe('210 40% 98%');
    });

    it('should have proper ocean theme colors', () => {
      const theme = getTheme('ocean');
      expect(theme.colors.primary).toBe('221 83% 53%');
      expect(theme.colors.radius).toBe('0.75rem');
    });

    it('should have descriptive names and descriptions', () => {
      themes.forEach(theme => {
        expect(theme.name.length).toBeGreaterThan(0);
        expect(theme.description.length).toBeGreaterThan(0);
        expect(theme.name).not.toBe(theme.id);
      });
    });
  });
});
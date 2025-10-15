export interface Theme {
  id: string;
  name: string;
  description: string;
  colors: {
    // Base colors
    background: string;
    foreground: string;

    // UI colors
    card: string;
    cardForeground: string;
    popover: string;
    popoverForeground: string;

    // Interactive colors
    primary: string;
    primaryForeground: string;
    secondary: string;
    secondaryForeground: string;

    // State colors
    muted: string;
    mutedForeground: string;
    accent: string;
    accentForeground: string;
    destructive: string;
    destructiveForeground: string;

    // Form colors
    border: string;
    input: string;
    ring: string;

    // Design system
    radius: string;
  };
  preview: {
    primary: string;
    secondary: string;
    accent: string;
  };
}

export const themes: Theme[] = [
  {
    id: 'default',
    name: 'Default',
    description: 'Clean and professional default theme',
    colors: {
      background: '0 0% 100%',
      foreground: '222.2 84% 4.9%',
      card: '0 0% 100%',
      cardForeground: '222.2 84% 4.9%',
      popover: '0 0% 100%',
      popoverForeground: '222.2 84% 4.9%',
      primary: '222.2 47.4% 11.2%',
      primaryForeground: '210 40% 98%',
      secondary: '210 40% 96%',
      secondaryForeground: '222.2 47.4% 11.2%',
      muted: '210 40% 96%',
      mutedForeground: '215.4 16.3% 46.9%',
      accent: '210 40% 96%',
      accentForeground: '222.2 47.4% 11.2%',
      destructive: '0 84.2% 60.2%',
      destructiveForeground: '210 40% 98%',
      border: '214.3 31.8% 91.4%',
      input: '214.3 31.8% 91.4%',
      ring: '222.2 84% 4.9%',
      radius: '0.5rem',
    },
    preview: {
      primary: '#1c1c1e',
      secondary: '#f1f5f9',
      accent: '#f1f5f9',
    },
  },
  {
    id: 'ocean',
    name: 'Ocean Blue',
    description: 'Calming blue theme inspired by ocean depths',
    colors: {
      background: '210 40% 98%',
      foreground: '215 25% 27%',
      card: '210 40% 98%',
      cardForeground: '215 25% 27%',
      popover: '210 40% 98%',
      popoverForeground: '215 25% 27%',
      primary: '221 83% 53%',
      primaryForeground: '210 40% 98%',
      secondary: '214 32% 91%',
      secondaryForeground: '215 25% 27%',
      muted: '214 32% 91%',
      mutedForeground: '215 20% 65%',
      accent: '210 100% 95%',
      accentForeground: '221 83% 53%',
      destructive: '0 84% 60%',
      destructiveForeground: '210 40% 98%',
      border: '214 32% 91%',
      input: '214 32% 91%',
      ring: '221 83% 53%',
      radius: '0.75rem',
    },
    preview: {
      primary: '#3b82f6',
      secondary: '#e0f2fe',
      accent: '#f0f9ff',
    },
  },
  {
    id: 'forest',
    name: 'Forest Green',
    description: 'Natural green theme with earthy tones',
    colors: {
      background: '60 9% 98%',
      foreground: '120 10% 15%',
      card: '60 9% 98%',
      cardForeground: '120 10% 15%',
      popover: '60 9% 98%',
      popoverForeground: '120 10% 15%',
      primary: '142 76% 36%',
      primaryForeground: '60 9% 98%',
      secondary: '120 60% 95%',
      secondaryForeground: '120 10% 15%',
      muted: '120 60% 95%',
      mutedForeground: '120 5% 64%',
      accent: '142 30% 95%',
      accentForeground: '142 76% 36%',
      destructive: '0 84% 60%',
      destructiveForeground: '60 9% 98%',
      border: '120 13% 85%',
      input: '120 13% 85%',
      ring: '142 76% 36%',
      radius: '0.5rem',
    },
    preview: {
      primary: '#22c55e',
      secondary: '#f0fdf4',
      accent: '#dcfce7',
    },
  },
  {
    id: 'sunset',
    name: 'Sunset Orange',
    description: 'Warm and energetic orange theme',
    colors: {
      background: '60 9% 98%',
      foreground: '20 14% 15%',
      card: '60 9% 98%',
      cardForeground: '20 14% 15%',
      popover: '60 9% 98%',
      popoverForeground: '20 14% 15%',
      primary: '24 95% 53%',
      primaryForeground: '60 9% 98%',
      secondary: '60 5% 96%',
      secondaryForeground: '20 14% 15%',
      muted: '60 5% 96%',
      mutedForeground: '25 5% 65%',
      accent: '24 100% 95%',
      accentForeground: '24 95% 53%',
      destructive: '0 84% 60%',
      destructiveForeground: '60 9% 98%',
      border: '20 6% 90%',
      input: '20 6% 90%',
      ring: '24 95% 53%',
      radius: '0.375rem',
    },
    preview: {
      primary: '#f97316',
      secondary: '#fef3c7',
      accent: '#fff7ed',
    },
  },
  {
    id: 'purple',
    name: 'Royal Purple',
    description: 'Elegant purple theme with sophisticated styling',
    colors: {
      background: '270 20% 98%',
      foreground: '270 15% 15%',
      card: '270 20% 98%',
      cardForeground: '270 15% 15%',
      popover: '270 20% 98%',
      popoverForeground: '270 15% 15%',
      primary: '271 81% 56%',
      primaryForeground: '270 20% 98%',
      secondary: '270 20% 95%',
      secondaryForeground: '270 15% 15%',
      muted: '270 20% 95%',
      mutedForeground: '270 8% 65%',
      accent: '270 100% 97%',
      accentForeground: '271 81% 56%',
      destructive: '0 84% 60%',
      destructiveForeground: '270 20% 98%',
      border: '270 20% 88%',
      input: '270 20% 88%',
      ring: '271 81% 56%',
      radius: '0.5rem',
    },
    preview: {
      primary: '#8b5cf6',
      secondary: '#f3e8ff',
      accent: '#faf5ff',
    },
  },
  {
    id: 'dark',
    name: 'Dark Mode',
    description: 'Sleek dark theme for reduced eye strain',
    colors: {
      background: '222.2 84% 4.9%',
      foreground: '210 40% 98%',
      card: '222.2 84% 4.9%',
      cardForeground: '210 40% 98%',
      popover: '222.2 84% 4.9%',
      popoverForeground: '210 40% 98%',
      primary: '210 40% 98%',
      primaryForeground: '222.2 47.4% 11.2%',
      secondary: '217.2 32.6% 17.5%',
      secondaryForeground: '210 40% 98%',
      muted: '217.2 32.6% 17.5%',
      mutedForeground: '215 20.2% 65.1%',
      accent: '217.2 32.6% 17.5%',
      accentForeground: '210 40% 98%',
      destructive: '0 62.8% 30.6%',
      destructiveForeground: '210 40% 98%',
      border: '217.2 32.6% 17.5%',
      input: '217.2 32.6% 17.5%',
      ring: '212.7 26.8% 83.9%',
      radius: '0.5rem',
    },
    preview: {
      primary: '#fafafa',
      secondary: '#27272a',
      accent: '#3f3f46',
    },
  },
];

export const getTheme = (themeId: string): Theme => {
  return themes.find(theme => theme.id === themeId) || themes[0];
};

export const applyTheme = (theme: Theme): void => {
  const root = document.documentElement;

  // Apply all color variables
  Object.entries(theme.colors).forEach(([key, value]) => {
    const cssVar = `--${key.replace(/([A-Z])/g, '-$1').toLowerCase()}`;
    root.style.setProperty(cssVar, value);
  });
};
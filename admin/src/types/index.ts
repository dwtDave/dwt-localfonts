export interface DWTSettings {
  // Font settings
  google_fonts_api_key?: string;
  font_rules?: string;
  keep_fonts_on_uninstall?: string | boolean;
  font_display_swap?: string | boolean;
  include_unicode_range?: string | boolean;
  font_hosting_mode?: string;
}

export interface WindowDWTLocalFonts {
  apiUrl: string;
  nonce: string;
  uploadsUrl: string;
  pluginUrl: string;
}

declare global {
  interface Window {
    dwtLocalFonts?: WindowDWTLocalFonts;
  }
}

export type SettingsKey = keyof DWTSettings;

// Font-related interfaces
export interface GoogleFont {
  id?: string; // Fontsource font ID (lowercase, hyphenated)
  family: string;
  variants: string[];
  subsets: string[];
  version?: string;
  lastModified?: string;
  files?: { [key: string]: string };
  category?: string;
  unicodeRanges?: string[]; // Unicode character ranges supported by this font
}

export interface LocalFont {
  family: string;
  status: 'downloaded' | 'downloading' | 'error' | 'missing_all_files' | 'missing_some_files' | 'missing_css' | 'missing_css_entry';
  path?: string;
  downloadedAt?: string;
  variants?: string[];
  font_files?: string[];
  file_count?: number;
  validation?: {
    missing_files: string[];
    total_files: number;
  };
  font_face_css?: string; // Legacy support for old downloaded fonts
}

export interface FontDownloadRequest {
  font_url: string;
  font_name?: string;
  variants?: string[];
}

export interface FontDeleteRequest {
  font_family: string;
}

export interface FontApiResponse {
  success: boolean;
  message: string;
  downloaded_fonts?: string[];
  css?: string;
  families?: string[];
  error?: string;
}

export interface FontDiscoveryResponse {
  success: boolean;
  discovered_fonts: string[];
  added_fonts: string[];
  total_fonts: number;
  message: string;
}

export interface FontPreviewProps {
  fontFamily: string;
  variants?: string[];
  previewText?: string;
  size?: 'small' | 'medium' | 'large';
}

export interface FontManagerProps {
  googleFonts: GoogleFont[];
  localFonts: LocalFont[];
  onDownloadFont: (font: GoogleFont, variants: string[]) => Promise<void>;
  onDeleteFont: (fontFamily: string) => Promise<void>;
  loading?: boolean;
}
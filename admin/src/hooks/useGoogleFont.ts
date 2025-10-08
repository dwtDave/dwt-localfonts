import { useEffect } from 'react';

/**
 * Convert variant string to numeric weight
 */
const normalizeVariant = (variant: string): string => {
    // Map common variant names to weights
    const variantMap: Record<string, string> = {
        'thin': '100',
        'extralight': '200',
        'light': '300',
        'regular': '400',
        'normal': '400',
        'medium': '500',
        'semibold': '600',
        'bold': '700',
        'extrabold': '800',
        'black': '900'
    };

    // Remove 'italic' suffix for now (we'll handle separately)
    const baseVariant = variant.replace(/italic$/i, '').trim();

    // If it's already numeric, return it
    if (/^\d+$/.test(baseVariant)) {
        return baseVariant;
    }

    // Otherwise, look it up in the map, default to 400
    return variantMap[baseVariant.toLowerCase()] || '400';
};

/**
 * Hook to dynamically load Google Fonts via BunnyCDN
 */
export const useGoogleFont = (fontFamily: string, variants: string[] = ['400']) => {
    useEffect(() => {
        // Sanitize font family name for URL
        const fontFamilyUrl = fontFamily.replace(/ /g, '+');

        // Check if we have any italic variants
        const hasItalic = variants.some(v => v.includes('italic'));

        let fontUrl: string;

        if (hasItalic) {
            // Use ital,wght format when we have italic variants
            const variantString = variants
                .map(v => {
                    const match = v.match(/^(\d+)(italic)?$/);
                    if (match) {
                        const weight = match[1];
                        const isItalic = !!match[2];
                        return `${isItalic ? '1' : '0'},${weight}`;
                    }
                    return null;
                })
                .filter(Boolean)
                .join(';');

            fontUrl = `https://fonts.bunny.net/css2?family=${fontFamilyUrl}:ital,wght@${variantString}&display=swap`;
        } else {
            // Use wght format when we only have normal variants
            const normalizedWeights = variants
                .map(normalizeVariant)
                .filter((v, i, arr) => arr.indexOf(v) === i); // Remove duplicates

            const weightsParam = normalizedWeights.join(';');
            fontUrl = `https://fonts.bunny.net/css2?family=${fontFamilyUrl}:wght@${weightsParam}&display=swap`;
        }

        // Check if font is already loaded
        const linkId = `google-font-${fontFamily.replace(/ /g, '-')}`;
        const existingLink = document.getElementById(linkId);

        if (existingLink) {
            return; // Font already loaded
        }

        // Create and append link element
        const link = document.createElement('link');
        link.id = linkId;
        link.href = fontUrl;
        link.rel = 'stylesheet';
        link.type = 'text/css';
        document.head.appendChild(link);

        // Cleanup function
        return () => {
            const linkToRemove = document.getElementById(linkId);
            if (linkToRemove) {
                document.head.removeChild(linkToRemove);
            }
        };
    }, [fontFamily, variants.join(',')]);
};

/**
 * Load multiple Google Fonts at once via BunnyCDN
 */
export const useGoogleFonts = (fonts: Array<{ family: string; variants?: string[] }>) => {
    useEffect(() => {
        const loadedLinks: string[] = [];

        fonts.forEach(({ family, variants = ['400'] }) => {
            const fontFamilyUrl = family.replace(/ /g, '+');

            // Check if we have any italic variants
            const hasItalic = variants.some(v => v.includes('italic'));

            let fontUrl: string;

            if (hasItalic) {
                // Use ital,wght format when we have italic variants
                const variantString = variants
                    .map(v => {
                        const match = v.match(/^(\d+)(italic)?$/);
                        if (match) {
                            const weight = match[1];
                            const isItalic = !!match[2];
                            return `${isItalic ? '1' : '0'},${weight}`;
                        }
                        return null;
                    })
                    .filter(Boolean)
                    .join(';');

                fontUrl = `https://fonts.bunny.net/css2?family=${fontFamilyUrl}:ital,wght@${variantString}&display=swap`;
            } else {
                // Use wght format when we only have normal variants
                const normalizedWeights = variants
                    .map(normalizeVariant)
                    .filter((v, i, arr) => arr.indexOf(v) === i); // Remove duplicates

                const weightsParam = normalizedWeights.join(';');
                fontUrl = `https://fonts.bunny.net/css2?family=${fontFamilyUrl}:wght@${weightsParam}&display=swap`;
            }

            const linkId = `google-font-${family.replace(/ /g, '-')}`;
            const existingLink = document.getElementById(linkId);

            if (!existingLink) {
                const link = document.createElement('link');
                link.id = linkId;
                link.href = fontUrl;
                link.rel = 'stylesheet';
                link.type = 'text/css';
                document.head.appendChild(link);
                loadedLinks.push(linkId);
            }
        });

        // Cleanup function
        return () => {
            loadedLinks.forEach(linkId => {
                const linkToRemove = document.getElementById(linkId);
                if (linkToRemove) {
                    document.head.removeChild(linkToRemove);
                }
            });
        };
    }, [JSON.stringify(fonts.map(f => ({ family: f.family, variants: f.variants?.join(',') })))]);
};
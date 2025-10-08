import React from 'react';
import { useGoogleFont } from '../hooks/useGoogleFont';

interface GoogleFontPreviewProps {
    fontFamily: string;
    variants?: string[];
    children: React.ReactNode;
    className?: string;
}

/**
 * Component that loads a Google Font and renders children with that font applied
 */
const GoogleFontPreview: React.FC<GoogleFontPreviewProps> = ({
    fontFamily,
    variants = ['400'],
    children,
    className = ''
}) => {
    // Load the font from Google Fonts
    useGoogleFont(fontFamily, variants);

    return (
        <div className={className} style={{ fontFamily: `"${fontFamily}", sans-serif` }}>
            {children}
        </div>
    );
};

export default GoogleFontPreview;
import React, { useState } from 'react';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from './ui/card';
import { Button } from './ui/button';
import { Separator } from './ui/separator';
import { Checkbox } from './ui/checkbox';
import { FileText } from 'lucide-react';
import { toast } from 'sonner';
import type { LocalFont } from '../types';

interface FontWeight {
    weight: string;
    style: string;
}

interface SelectedFontWeights {
    [fontFamily: string]: Set<string>;
}

interface FontApplyProps {
    localFonts: LocalFont[];
}

const FontApply: React.FC<FontApplyProps> = ({ localFonts }) => {
    const [selectedFontsForExport, setSelectedFontsForExport] = useState<Set<string>>(new Set());
    const [selectedWeights, setSelectedWeights] = useState<SelectedFontWeights>({});

    const extractAvailableWeights = (font: LocalFont): FontWeight[] => {
        // First try to extract from font_files (new format)
        if (font.font_files && font.font_files.length > 0) {
            const seen = new Set<string>();
            const uniqueWeights: FontWeight[] = [];

            font.font_files.forEach(filename => {
                // Parse filename to extract weight and style
                // Examples:
                // - roboto-v30-latin-regular.woff2 -> weight: 400, style: normal
                // - roboto-v30-latin-700.woff2 -> weight: 700, style: normal
                // - roboto-v30-latin-700italic.woff2 -> weight: 700, style: italic
                // - lato-latin-700-normal.woff2 -> weight: 700, style: normal
                // - lato-latin-700-italic.woff2 -> weight: 700, style: italic

                const isItalic = filename.includes('italic');
                const style = isItalic ? 'italic' : 'normal';

                // Try multiple patterns to match weight number
                // Pattern 1: -700-normal.woff2 or -700-italic.woff2 (Fontsource format)
                // Pattern 2: -700.woff2 or -700italic.woff2 (Google Fonts format)
                const weightMatch = filename.match(/-(\d{3})(?:-(?:normal|italic)|italic)?\.woff2?$/i);
                let weight = '400'; // default

                if (weightMatch) {
                    weight = weightMatch[1];
                } else if (filename.includes('regular')) {
                    weight = '400';
                } else if (filename.includes('bold')) {
                    weight = '700';
                } else if (filename.includes('light')) {
                    weight = '300';
                } else if (filename.includes('medium')) {
                    weight = '500';
                } else if (filename.includes('semibold')) {
                    weight = '600';
                } else if (filename.includes('black')) {
                    weight = '900';
                } else if (filename.includes('thin')) {
                    weight = '100';
                }

                const key = `${weight}-${style}`;
                if (!seen.has(key)) {
                    seen.add(key);
                    uniqueWeights.push({ weight, style });
                }
            });

            uniqueWeights.sort((a, b) => {
                const wa = parseInt(a.weight, 10);
                const wb = parseInt(b.weight, 10);
                if (wa !== wb) return wa - wb;
                if (a.style === b.style) return 0;
                return a.style === 'normal' ? -1 : 1;
            });

            return uniqueWeights;
        }

        // Fallback: try to extract from font_face_css (legacy support)
        if (font.font_face_css) {
            const seen = new Set<string>();
            const uniqueWeights: FontWeight[] = [];
            const fontFaceBlocks: string[] = font.font_face_css.match(/@font-face\s*\{[^}]*\}/gs) || [];

            fontFaceBlocks.forEach(block => {
                const weightMatch = block.match(/font-weight:\s*(\d+)/);
                const styleMatch = block.match(/font-style:\s*(normal|italic)/);

                if (weightMatch) {
                    const weight = weightMatch[1];
                    const style = styleMatch ? styleMatch[1] : 'normal';
                    const key = `${weight}-${style}`;
                    if (!seen.has(key)) {
                        seen.add(key);
                        uniqueWeights.push({ weight, style });
                    }
                }
            });

            uniqueWeights.sort((a, b) => {
                const wa = parseInt(a.weight, 10);
                const wb = parseInt(b.weight, 10);
                if (wa !== wb) return wa - wb;
                if (a.style === b.style) return 0;
                return a.style === 'normal' ? -1 : 1;
            });

            return uniqueWeights;
        }

        return [];
    };

    const toggleFontSelection = (fontFamily: string) => {
        setSelectedFontsForExport(prev => {
            const newSet = new Set(prev);
            if (newSet.has(fontFamily)) {
                newSet.delete(fontFamily);
                setSelectedWeights(prevWeights => {
                    const newWeights = { ...prevWeights };
                    delete newWeights[fontFamily];
                    return newWeights;
                });
            } else {
                newSet.add(fontFamily);
            }
            return newSet;
        });
    };

    const toggleWeightSelection = (fontFamily: string, weight: string, style: string) => {
        const weightKey = `${weight}-${style}`;
        setSelectedWeights(prev => {
            const fontWeights = prev[fontFamily] || new Set();
            const newFontWeights = new Set(fontWeights);

            if (newFontWeights.has(weightKey)) {
                newFontWeights.delete(weightKey);
            } else {
                newFontWeights.add(weightKey);
            }

            return {
                ...prev,
                [fontFamily]: newFontWeights
            };
        });
    };

    const generateCSS = () => {
        const fontsToExport = Array.from(selectedFontsForExport);
        let css = '';

        if (fontsToExport.length > 0 && localFonts.length > 0) {
            const fontFaceDeclarations = fontsToExport.map(fontFamily => {
                const localFont = localFonts.find(f => f.family === fontFamily);
                if (!localFont?.font_files || localFont.font_files.length === 0) return '';

                // Get selected weights for this font (if any)
                const selectedWeightKeys = selectedWeights[fontFamily];
                const hasWeightFilter = selectedWeightKeys && selectedWeightKeys.size > 0;

                // Default to latin and latin-ext subsets
                const subsetsToInclude = ['latin', 'latin-ext'];

                // Generate @font-face rules from font files
                const uploadsUrl = (window as any).dwtLocalFonts?.uploadsUrl || '';
                const fontDir = 'dwt-local-fonts';

                // Group font files by weight-style to combine subsets into single @font-face rules
                const fontFaceGroups = new Map<string, string[]>();

                localFont.font_files.forEach(filename => {
                    // Only use .woff2 files (skip .woff for modern browsers)
                    if (!filename.endsWith('.woff2')) {
                        return;
                    }

                    // Extract weight and style from filename
                    const isItalic = filename.includes('italic');
                    const style = isItalic ? 'italic' : 'normal';

                    // Try multiple patterns to match weight number
                    const weightMatch = filename.match(/-(\d{3})(?:-(?:normal|italic)|italic)?\.woff2?$/i);
                    let weight = '400';

                    if (weightMatch) {
                        weight = weightMatch[1];
                    } else if (filename.includes('regular')) {
                        weight = '400';
                    } else if (filename.includes('bold')) {
                        weight = '700';
                    } else if (filename.includes('light')) {
                        weight = '300';
                    } else if (filename.includes('medium')) {
                        weight = '500';
                    } else if (filename.includes('semibold')) {
                        weight = '600';
                    } else if (filename.includes('black')) {
                        weight = '900';
                    } else if (filename.includes('thin')) {
                        weight = '100';
                    }

                    // Filter by selected weights
                    const weightKey = `${weight}-${style}`;
                    if (hasWeightFilter && !selectedWeightKeys.has(weightKey)) {
                        return; // Skip this weight
                    }

                    // Filter by unicode subset (default to latin and latin-ext)
                    const hasSubsetMatch = subsetsToInclude.some(subset =>
                        filename.includes(`-${subset}-`)
                    );

                    if (!hasSubsetMatch) {
                        return; // Skip this subset
                    }

                    // Group by weight-style combination
                    if (!fontFaceGroups.has(weightKey)) {
                        fontFaceGroups.set(weightKey, []);
                    }
                    fontFaceGroups.get(weightKey)!.push(filename);
                });

                // Generate @font-face rules from groups
                return Array.from(fontFaceGroups.entries())
                    .map(([weightKey, files]) => {
                        const [weight, style] = weightKey.split('-');

                        // Use only the first file (latin takes precedence over latin-ext)
                        const filename = files.sort()[0];

                        return `@font-face {
  font-family: '${fontFamily}';
  font-style: ${style};
  font-weight: ${weight};
  src: url('${uploadsUrl}/${fontDir}/${filename}') format('woff2');
}`;
                    })
                    .join('\n\n');
            }).filter(Boolean);

            if (fontFaceDeclarations.length > 0) {
                css += fontFaceDeclarations.join('\n\n');
            }
        }

        return css;
    };

    const copyToClipboard = async () => {
        try {
            await navigator.clipboard.writeText(generateCSS());
            toast.success('CSS copied to clipboard!');
        } catch (error) {
            console.error('Failed to copy CSS:', error);
            toast.error('Failed to copy CSS to clipboard');
        }
    };

    const downloadCSS = () => {
        const css = generateCSS();
        const blob = new Blob([css], { type: 'text/css' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = 'custom-fonts.css';
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(url);
    };

    if (localFonts.length === 0) {
        return (
            <Card>
                <CardContent className="text-center py-12">
                    <FileText className="mx-auto h-12 w-12 text-muted-foreground mb-4" />
                    <h3 className="text-lg font-medium mb-2 text-foreground">
                        No downloaded fonts
                    </h3>
                    <p className="text-muted-foreground mb-4">
                        Download fonts first from the Browse & Download tab to generate CSS
                    </p>
                </CardContent>
            </Card>
        );
    }

    return (
        <div className="space-y-6">
            <Card>
                <CardHeader>
                    <CardTitle>Generate Font CSS</CardTitle>
                    <CardDescription>
                        Select which fonts to include in your CSS file. Perfect for self-hosting fonts and improving performance.
                    </CardDescription>
                </CardHeader>
                <CardContent className="space-y-6">
                    {/* Font Selection */}
                    <div className="space-y-3">
                        <div className="flex items-center justify-between">
                            <h4 className="font-medium text-foreground">
                                Select Fonts
                            </h4>
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={() => {
                                    if (selectedFontsForExport.size === localFonts.length) {
                                        setSelectedFontsForExport(new Set());
                                        setSelectedWeights({});
                                    } else {
                                        setSelectedFontsForExport(new Set(localFonts.map(f => f.family)));
                                    }
                                }}
                            >
                                {selectedFontsForExport.size === localFonts.length ? 'Deselect All' : 'Select All'}
                            </Button>
                        </div>

                        {localFonts.map(font => {
                            const isSelected = selectedFontsForExport.has(font.family);
                            const availableWeights = extractAvailableWeights(font);

                            return (
                                <div key={font.family} className="border rounded-lg">
                                    <label className="flex items-center gap-3 p-4 cursor-pointer hover:bg-muted/50 transition-colors">
                                        <Checkbox
                                            checked={isSelected}
                                            onCheckedChange={() => toggleFontSelection(font.family)}
                                        />
                                        <div className="flex-1">
                                            <span className="font-medium text-foreground">
                                                {font.family}
                                            </span>
                                            {availableWeights.length > 0 && (
                                                <span className="text-sm text-muted-foreground ml-2">
                                                    ({availableWeights.length} weight{availableWeights.length > 1 ? 's' : ''})
                                                </span>
                                            )}
                                        </div>
                                    </label>

                                    {isSelected && availableWeights.length > 0 && (
                                        <div className="px-4 pb-4 pt-0">
                                            <Separator className="mb-3" />
                                            <p className="text-xs text-muted-foreground mb-2">Select weights to include:</p>
                                            <div className="flex flex-wrap gap-2 items-center">
                                                {(() => {
                                                    const selectedSet = selectedWeights[font.family] || new Set<string>();
                                                    const allSelected = selectedSet.size === availableWeights.length;
                                                    return (
                                                        <Button
                                                            key="all"
                                                            size="sm"
                                                            variant={allSelected ? 'default' : 'outline'}
                                                            className="text-xs px-3 py-1 h-auto"
                                                            onClick={() => {
                                                                setSelectedWeights(prev => {
                                                                    if (!allSelected) {
                                                                        const all = new Set<string>(availableWeights.map(({ weight, style }) => `${weight}-${style}`));
                                                                        return { ...prev, [font.family]: all };
                                                                    }
                                                                    const clone = { ...prev } as Record<string, Set<string>>;
                                                                    delete clone[font.family];
                                                                    return clone;
                                                                });
                                                            }}
                                                        >
                                                            All
                                                        </Button>
                                                    );
                                                })()}
                                                {availableWeights.map(({ weight, style }) => {
                                                    const weightKey = `${weight}-${style}`;
                                                    const isWeightSelected = selectedWeights[font.family]?.has(weightKey) || false;

                                                    return (
                                                        <Button
                                                            key={weightKey}
                                                            variant={isWeightSelected ? 'default' : 'outline'}
                                                            size="sm"
                                                            className="text-xs h-auto px-3 py-1"
                                                            onClick={() => toggleWeightSelection(font.family, weight, style)}
                                                        >
                                                            {weight} {style !== 'normal' && `(${style})`}
                                                        </Button>
                                                    );
                                                })}
                                            </div>
                                        </div>
                                    )}
                                </div>
                            );
                        })}
                    </div>

                    {selectedFontsForExport.size > 0 && (
                        <>
                            <Separator />

                            {/* Generated CSS Preview */}
                            <div className="space-y-3">
                                <div className="flex items-center justify-between">
                                    <h4 className="font-medium text-foreground">
                                        Generated CSS
                                    </h4>
                                    <div className="flex gap-2">
                                        <Button
                                            onClick={downloadCSS}
                                            variant="outline"
                                            size="sm"
                                        >
                                            Download
                                        </Button>
                                        <Button
                                            onClick={copyToClipboard}
                                            variant="default"
                                            size="sm"
                                        >
                                            Copy to Clipboard
                                        </Button>
                                    </div>
                                </div>
                                <div className="bg-muted p-4 rounded-lg border max-h-96 overflow-auto">
                                    <pre className="text-xs text-muted-foreground font-mono whitespace-pre-wrap">
                                        {generateCSS() || '/* Select fonts and options above to generate CSS */'}
                                    </pre>
                                </div>
                                <div className="bg-blue-50 dark:bg-blue-950 border border-blue-200 dark:border-blue-800 rounded-lg p-4">
                                    <h5 className="font-medium text-sm mb-2 text-blue-900 dark:text-blue-100">
                                        How to use this CSS:
                                    </h5>
                                    <ol className="text-sm text-blue-800 dark:text-blue-200 space-y-1 list-decimal list-inside">
                                        <li>Copy the generated CSS above</li>
                                        <li>Paste it into your theme's CSS file or style.css</li>
                                        <li>Use the font in your CSS: <code className="bg-blue-100 dark:bg-blue-900 px-1 rounded">font-family: "Font Name", sans-serif;</code></li>
                                        <li>The @font-face declarations use relative URLs to the locally hosted font files</li>
                                    </ol>
                                </div>
                            </div>
                        </>
                    )}
                </CardContent>
            </Card>
        </div>
    );
};

export default FontApply;
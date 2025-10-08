import React, { useState, useEffect, useMemo, useCallback } from 'react';
import apiFetch from '@wordpress/api-fetch';
import type { GoogleFont, LocalFont } from '../types';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from './ui/card';
import { Button } from './ui/button';
import { Input } from './ui/input';
import { Badge } from './ui/badge';
import { Label } from './ui/label';
import { Checkbox } from './ui/checkbox';
import { Table, TableBody, TableCaption, TableCell, TableHead, TableHeader, TableRow } from './ui/table';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from './ui/alert-dialog';
import { toast } from 'sonner';
import { searchAndFilterFonts, getAvailableSubsetsSync } from '../lib/fonts';
import { getDisplayNamesForSubsets, getSubsetInfo, fontSupportsSubsets } from '../lib/unicodeRanges';
import GoogleFontPreview from './GoogleFontPreview';

interface FontBrowseProps {
    localFonts: LocalFont[];
    onFontDownload: () => Promise<void>;
    onFontDelete: () => Promise<void>;
}

const FONTS_PER_PAGE = 12;
const POPULAR_SUBSETS = ['latin', 'latin-ext', 'cyrillic', 'greek', 'arabic', 'hebrew', 'vietnamese'];

const FontBrowse: React.FC<FontBrowseProps> = ({ localFonts, onFontDownload, onFontDelete }) => {
    const [googleFonts, setGoogleFonts] = useState<GoogleFont[]>([]);
    const [loading, setLoading] = useState(false);
    const [searchTerm, setSearchTerm] = useState('');
    const [selectedCategory, setSelectedCategory] = useState<string>('all');
    const [selectedSubsets, setSelectedSubsets] = useState<string[]>([]);
    const [downloadingFonts, setDownloadingFonts] = useState<Set<string>>(new Set());
    const [searchLoading, setSearchLoading] = useState(false);
    const [displayLimit, setDisplayLimit] = useState(FONTS_PER_PAGE);
    const [showAllSubsets, setShowAllSubsets] = useState(false);
    const [variantDialogOpen, setVariantDialogOpen] = useState(false);
    const [selectedFont, setSelectedFont] = useState<GoogleFont | null>(null);
    const [selectedVariants, setSelectedVariants] = useState<string[]>([]);

    // Debounce search function
    const debounceSearch = useCallback((searchFunction: () => void) => {
        const timeoutId = setTimeout(searchFunction, 300);
        return () => clearTimeout(timeoutId);
    }, []);

    const loadFonts = useCallback(async (search = '', category = '', subsetsFilter: string[] = []) => {
        try {
            setSearchLoading(true);
            let googleResponse = await searchAndFilterFonts(search, category);

            if (Array.isArray(subsetsFilter) && subsetsFilter.length > 0) {
                googleResponse = googleResponse.filter((font) => fontSupportsSubsets(font.subsets, subsetsFilter));
            }

            setGoogleFonts(googleResponse || []);
        } catch (error) {
            console.error('[FontBrowse] Failed to load fonts:', error);
            setGoogleFonts([]);
        } finally {
            setLoading(false);
            setSearchLoading(false);
        }
    }, []);

    useEffect(() => {
        const cleanup = debounceSearch(() => {
            if (searchTerm || selectedCategory !== 'all' || selectedSubsets.length > 0) {
                loadFonts(searchTerm, selectedCategory, selectedSubsets);
                setDisplayLimit(FONTS_PER_PAGE);
            }
        });
        return cleanup;
    }, [searchTerm, selectedCategory, selectedSubsets, debounceSearch, loadFonts]);

    const displayedFonts = useMemo(() => {
        return googleFonts.slice(0, displayLimit);
    }, [googleFonts, displayLimit]);

    const hasMoreFonts = googleFonts.length > displayLimit;

    const loadMoreFonts = () => {
        setDisplayLimit(prev => prev + FONTS_PER_PAGE);
    };

    const categories = useMemo(() => {
        return ['all', 'serif', 'sans-serif', 'display', 'handwriting', 'monospace'];
    }, []);

    const allSubsets = useMemo(() => {
        return getAvailableSubsetsSync();
    }, []);

    const displayedSubsets = useMemo(() => {
        if (showAllSubsets) {
            return allSubsets.filter(s => s !== 'all');
        }
        return POPULAR_SUBSETS;
    }, [showAllSubsets, allSubsets]);

    const isDownloaded = (fontFamily: string) => {
        return localFonts.some(font => font.family === fontFamily);
    };

    // Parse variants into grouped format for display
    const parseVariants = (variants: string[]) => {
        const weights = new Set<string>();
        const variantMap = new Map<string, { normal: boolean; italic: boolean }>();

        variants.forEach(variant => {
            const match = variant.match(/^(\d+)(italic)?$/);
            if (match) {
                const weight = match[1];
                const isItalic = !!match[2];

                weights.add(weight);
                if (!variantMap.has(weight)) {
                    variantMap.set(weight, { normal: false, italic: false });
                }

                if (isItalic) {
                    variantMap.get(weight)!.italic = true;
                } else {
                    variantMap.get(weight)!.normal = true;
                }
            }
        });

        return {
            weights: Array.from(weights).sort(),
            variantMap
        };
    };

    // Get weight name for display
    const getWeightName = (weight: string): string => {
        const weightNames: { [key: string]: string } = {
            '100': 'Thin',
            '200': 'Extra Light',
            '300': 'Light',
            '400': 'Regular',
            '500': 'Medium',
            '600': 'Semi Bold',
            '700': 'Bold',
            '800': 'Extra Bold',
            '900': 'Black'
        };
        return weightNames[weight] || weight;
    };

    // Open variant selection dialog
    const handleDownloadFont = (font: GoogleFont) => {
        if (isDownloaded(font.family) || downloadingFonts.has(font.family)) {
            return;
        }

        setSelectedFont(font);
        // Pre-select Regular (400) and Bold (700) if available, otherwise all variants
        const defaultVariants = font.variants.filter(v => v === '400' || v === '700');
        setSelectedVariants(defaultVariants.length > 0 ? defaultVariants : font.variants);
        setVariantDialogOpen(true);
    };

    // Perform actual download with selected variants
    const confirmDownload = async () => {
        if (!selectedFont || selectedVariants.length === 0) {
            toast.error('Please select at least one variant');
            return;
        }

        setVariantDialogOpen(false);
        setDownloadingFonts(prev => new Set([...prev, selectedFont.family]));

        try {
            // Build font URL with selected variants
            const hasItalic = selectedVariants.some(v => v.includes('italic'));
            let fontUrl: string;

            if (hasItalic) {
                // Use ital,wght format when we have italic variants
                const variantString = selectedVariants
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

                const subsetParam = selectedSubsets.length > 0 ? `&subset=${selectedSubsets.join(',')}` : '';
                fontUrl = `https://fonts.bunny.net/css2?family=${selectedFont.family.replace(/ /g, '+')}:ital,wght@${variantString}&display=swap${subsetParam}`;
            } else {
                // Use wght format when we only have normal variants
                const weights = selectedVariants.join(';');
                const subsetParam = selectedSubsets.length > 0 ? `&subset=${selectedSubsets.join(',')}` : '';
                fontUrl = `https://fonts.bunny.net/css2?family=${selectedFont.family.replace(/ /g, '+')}:wght@${weights}&display=swap${subsetParam}`;
            }

            const response = await apiFetch({
                path: '/fonts/download',
                method: 'POST',
                data: {
                    font_url: fontUrl,
                    font_name: selectedFont.family,
                    variants: selectedVariants
                }
            }) as { success: boolean; message: string; downloaded_fonts: string[] };

            if (response.success) {
                await onFontDownload();
                toast.success(`Downloaded ${selectedFont.family}`);
            }
        } catch (error) {
            console.error('Failed to download font:', error);
            toast.error('Failed to download font');
        } finally {
            setDownloadingFonts(prev => {
                const newSet = new Set(prev);
                newSet.delete(selectedFont.family);
                return newSet;
            });
        }
    };

    // Toggle variant selection
    const toggleVariant = (variant: string) => {
        setSelectedVariants(prev =>
            prev.includes(variant)
                ? prev.filter(v => v !== variant)
                : [...prev, variant]
        );
    };

    // Select all variants for a weight (normal + italic)
    const toggleWeight = (weight: string, hasNormal: boolean, hasItalic: boolean) => {
        const normalVariant = weight;
        const italicVariant = `${weight}italic`;

        const normalSelected = selectedVariants.includes(normalVariant);
        const italicSelected = selectedVariants.includes(italicVariant);

        if (normalSelected && italicSelected) {
            // Both selected, unselect both
            setSelectedVariants(prev => prev.filter(v => v !== normalVariant && v !== italicVariant));
        } else {
            // Select all available variants for this weight
            const toAdd: string[] = [];
            if (hasNormal && !normalSelected) toAdd.push(normalVariant);
            if (hasItalic && !italicSelected) toAdd.push(italicVariant);
            setSelectedVariants(prev => [...prev, ...toAdd]);
        }
    };

    if (loading) {
        return (
            <div className="space-y-4">
                <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    {[...Array(6)].map((_, i) => (
                        <Card key={i} className="animate-pulse">
                            <CardContent className="p-6">
                                <div className="h-4 bg-muted rounded w-1/2 mb-4"></div>
                                <div className="h-8 bg-muted rounded w-full mb-2"></div>
                                <div className="h-3 bg-muted rounded w-3/4"></div>
                            </CardContent>
                        </Card>
                    ))}
                </div>
            </div>
        );
    }

    return (
        <div className="space-y-6">
            {/* Search and Filter Controls */}
            <Card>
                <CardContent className="pt-6 space-y-4">
                    <div className="relative">
                        <Input
                            type="text"
                            placeholder="Search Google Fonts..."
                            value={searchTerm}
                            onChange={(e) => setSearchTerm(e.target.value)}
                            className="w-full"
                        />
                        {searchLoading && (
                            <div className="absolute right-3 top-1/2 transform -translate-y-1/2">
                                <div className="animate-spin rounded-full h-4 w-4 border-b-2 border-primary"></div>
                            </div>
                        )}
                    </div>

                    {/* Category Filter */}
                    <div className="space-y-4">
                        <h3 className="text-sm font-semibold text-foreground">Category</h3>
                        <div className="flex gap-2 flex-wrap">
                            {categories.map(category => (
                                <Button
                                    key={category}
                                    variant={selectedCategory === category ? "default" : "outline"}
                                    size="sm"
                                    onClick={() => setSelectedCategory(category)}
                                    className="capitalize"
                                >
                                    {category}
                                </Button>
                            ))}
                        </div>
                    </div>

                    {/* Language/Script Filter - Collapsible */}
                    <div className="space-y-4">
                        <h3 className="text-sm font-semibold text-foreground">Language / Script Support</h3>
                        <div className="flex gap-2 flex-wrap">
                            <Button
                                key="all"
                                variant={selectedSubsets.length === 0 ? "default" : "outline"}
                                size="sm"
                                onClick={() => setSelectedSubsets([])}
                            >
                                All
                            </Button>

                            {displayedSubsets.map(subset => {
                                const subsetInfo = getSubsetInfo(subset);
                                const displayName = subsetInfo?.displayName || subset;
                                const isSelected = selectedSubsets.includes(subset);

                                return (
                                    <Button
                                        key={subset}
                                        variant={isSelected ? "default" : "outline"}
                                        size="sm"
                                        onClick={() => {
                                            setSelectedSubsets(prev => {
                                                if (prev.includes(subset)) {
                                                    return prev.filter(s => s !== subset);
                                                }
                                                return [...prev, subset];
                                            });
                                        }}
                                        className="capitalize"
                                        title={subsetInfo?.description}
                                    >
                                        {displayName}
                                    </Button>
                                );
                            })}

                            <Button
                                variant="ghost"
                                size="sm"
                                onClick={() => setShowAllSubsets(!showAllSubsets)}
                            >
                                {showAllSubsets ? 'Show less' : `Show more (${allSubsets.length - POPULAR_SUBSETS.length - 1})`}
                            </Button>
                        </div>
                    </div>
                </CardContent>
            </Card>

            {/* Results Summary */}
            <div className="flex items-center justify-between">
                <div>
                    <h3 className="text-lg font-semibold text-foreground">
                        {googleFonts.length} Font{googleFonts.length !== 1 ? 's' : ''} Available
                        {searchLoading && <span className="text-sm font-normal text-muted-foreground ml-2">(searching...)</span>}
                    </h3>
                    <p className="text-sm text-muted-foreground">
                        {searchTerm || selectedCategory !== 'all' || selectedSubsets.length > 0
                            ? (() => {
                                const subsetNames = selectedSubsets.length > 0 ? getDisplayNamesForSubsets(selectedSubsets) : [];
                                const subsetPart = subsetNames.length > 0 ? ` • ${subsetNames.join(', ')} support` : '';
                                return `${searchTerm ? `"${searchTerm}"` : ''}${selectedCategory !== 'all' ? ` • ${selectedCategory}` : ''}${subsetPart}`;
                            })()
                            : 'Click download to add fonts to your site'
                        }
                    </p>
                </div>
                {localFonts.length > 0 && (
                    <Badge variant="secondary" className="text-sm">
                        {localFonts.length} downloaded
                    </Badge>
                )}
            </div>

            {/* Font Table */}
            <Card>
                <CardContent className="p-0">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead className="w-[30%]">Family</TableHead>
                                <TableHead className="w-[15%]">Category</TableHead>
                                <TableHead className="w-[15%]">Status</TableHead>
                                <TableHead>Preview</TableHead>
                                <TableHead className="w-[140px] text-right">Action</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {displayedFonts.map(font => (
                                <TableRow key={font.family}>
                                    <TableCell className="font-medium text-foreground truncate">
                                        {font.family}
                                    </TableCell>
                                    <TableCell>
                                        {font.category && (
                                            <Badge variant="outline" className="capitalize">
                                                {font.category}
                                            </Badge>
                                        )}
                                    </TableCell>
                                    <TableCell>
                                        {isDownloaded(font.family) && (
                                            <Badge variant="secondary">Downloaded</Badge>
                                        )}
                                    </TableCell>
                                    <TableCell>
                                        <GoogleFontPreview
                                            fontFamily={font.family}
                                            variants={font.variants}
                                            className="text-base py-3"
                                        >
                                            The quick brown fox jumps over the lazy dog
                                        </GoogleFontPreview>
                                    </TableCell>
                                    <TableCell className="text-right">
                                        <Button
                                            size="sm"
                                            onClick={() => handleDownloadFont(font)}
                                            disabled={isDownloaded(font.family) || downloadingFonts.has(font.family)}
                                            variant={isDownloaded(font.family) ? "secondary" : "default"}
                                        >
                                            {downloadingFonts.has(font.family)
                                                ? "Downloading..."
                                                : isDownloaded(font.family)
                                                    ? "Downloaded"
                                                    : "Download"}
                                        </Button>
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                </CardContent>
            </Card>

            {/* Load More */}
            {hasMoreFonts && (
                <div className="text-center">
                    <Button
                        onClick={loadMoreFonts}
                        variant="outline"
                        size="lg"
                    >
                        Load More ({googleFonts.length - displayLimit} remaining)
                    </Button>
                </div>
            )}

            {/* Empty State - Before Search */}
            {googleFonts.length === 0 && !loading && !searchLoading && !searchTerm && selectedCategory === 'all' && selectedSubsets.length === 0 && (
                <Card>
                    <CardContent className="text-center py-12">
                        <p className="text-muted-foreground mb-2">
                            Search for fonts by name, category, or language support
                        </p>
                        <p className="text-sm text-muted-foreground">
                            Use the search and filters above to browse available fonts
                        </p>
                    </CardContent>
                </Card>
            )}

            {/* No Results */}
            {googleFonts.length === 0 && !loading && !searchLoading && (searchTerm || selectedCategory !== 'all' || selectedSubsets.length > 0) && (
                <Card>
                    <CardContent className="text-center py-12">
                        <p className="text-muted-foreground mb-4">
                            {(() => {
                                const subsetNames = selectedSubsets.length > 0 ? getDisplayNamesForSubsets(selectedSubsets) : [];
                                const subsetPart = subsetNames.length > 0 ? ` with ${subsetNames.join(', ')} support` : '';
                                return `No fonts found${searchTerm ? ` matching "${searchTerm}"` : ''}${selectedCategory !== 'all' ? ` in category "${selectedCategory}"` : ''}${subsetPart}`;
                            })()}
                        </p>
                        <div className="flex gap-2 justify-center flex-wrap">
                            {searchTerm && (
                                <Button
                                    variant="outline"
                                    onClick={() => setSearchTerm('')}
                                >
                                    Clear search
                                </Button>
                            )}
                            {selectedCategory !== 'all' && (
                                <Button
                                    variant="outline"
                                    onClick={() => setSelectedCategory('all')}
                                >
                                    Clear category
                                </Button>
                            )}
                            {selectedSubsets.length > 0 && (
                                <Button
                                    variant="outline"
                                    onClick={() => setSelectedSubsets([])}
                                >
                                    Clear languages
                                </Button>
                            )}
                        </div>
                    </CardContent>
                </Card>
            )}

            {/* Variant Selection Dialog */}
            <AlertDialog open={variantDialogOpen} onOpenChange={setVariantDialogOpen}>
                <AlertDialogContent className="max-w-2xl max-h-[80vh] overflow-y-auto">
                    <AlertDialogHeader>
                        <AlertDialogTitle>
                            Select Font Variants for {selectedFont?.family}
                        </AlertDialogTitle>
                        <AlertDialogDescription>
                            Choose the weights and styles you want to download. Selecting only the variants you need reduces page load time.
                        </AlertDialogDescription>
                    </AlertDialogHeader>

                    {selectedFont && (() => {
                        const { weights, variantMap } = parseVariants(selectedFont.variants);

                        return (
                            <div className="space-y-4 py-4">
                                <div className="flex gap-2 flex-wrap">
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        onClick={() => setSelectedVariants(selectedFont.variants)}
                                    >
                                        Select All
                                    </Button>
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        onClick={() => setSelectedVariants([])}
                                    >
                                        Deselect All
                                    </Button>
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        onClick={() => {
                                            const defaults = selectedFont.variants.filter(v => v === '400' || v === '700');
                                            setSelectedVariants(defaults.length > 0 ? defaults : ['400']);
                                        }}
                                    >
                                        Regular + Bold
                                    </Button>
                                </div>

                                <Card>
                                    <CardContent className="p-0">
                                        <Table>
                                            <TableHeader>
                                                <TableRow>
                                                    <TableHead className="w-[30%]">Weight</TableHead>
                                                    <TableHead className="w-[20%]">Select</TableHead>
                                                    <TableHead>Variants</TableHead>
                                                </TableRow>
                                            </TableHeader>
                                            <TableBody>
                                                {weights.map(weight => {
                                                    const variant = variantMap.get(weight)!;
                                                    const normalVariant = weight;
                                                    const italicVariant = `${weight}italic`;

                                                    const allSelected = selectedVariants.includes(normalVariant) && selectedVariants.includes(italicVariant);

                                                    return (
                                                        <TableRow key={weight}>
                                                            <TableCell className="font-medium text-foreground">
                                                                {getWeightName(weight)} ({weight})
                                                            </TableCell>
                                                            <TableCell>
                                                                <Button
                                                                    variant="ghost"
                                                                    size="sm"
                                                                    onClick={() => toggleWeight(weight, variant.normal, variant.italic)}
                                                                >
                                                                    {allSelected ? 'Deselect All' : 'Select All'}
                                                                </Button>
                                                            </TableCell>
                                                            <TableCell>
                                                                <div className="grid grid-cols-2 gap-4">
                                                                    {variant.normal && (
                                                                        <div className="flex items-center space-x-2">
                                                                            <Checkbox
                                                                                id={`variant-${normalVariant}`}
                                                                                checked={selectedVariants.includes(normalVariant)}
                                                                                onCheckedChange={() => toggleVariant(normalVariant)}
                                                                            />
                                                                            <Label htmlFor={`variant-${normalVariant}`} className="cursor-pointer text-gray-200">
                                                                                Normal
                                                                            </Label>
                                                                        </div>
                                                                    )}
                                                                    {variant.italic && (
                                                                        <div className="flex items-center space-x-2">
                                                                            <Checkbox
                                                                                id={`variant-${italicVariant}`}
                                                                                checked={selectedVariants.includes(italicVariant)}
                                                                                onCheckedChange={() => toggleVariant(italicVariant)}
                                                                            />
                                                                            <Label htmlFor={`variant-${italicVariant}`} className="cursor-pointer italic text-gray-200">
                                                                                Italic
                                                                            </Label>
                                                                        </div>
                                                                    )}
                                                                </div>
                                                            </TableCell>
                                                        </TableRow>
                                                    );
                                                })}
                                            </TableBody>
                                        </Table>
                                    </CardContent>
                                </Card>

                                <div className="pt-4 border-t border-border">
                                    <p className="text-sm text-gray-300">
                                        {selectedVariants.length} variant{selectedVariants.length !== 1 ? 's' : ''} selected
                                    </p>
                                </div>
                            </div>
                        );
                    })()}

                    <AlertDialogFooter>
                        <AlertDialogCancel>Cancel</AlertDialogCancel>
                        <AlertDialogAction onClick={confirmDownload} disabled={selectedVariants.length === 0}>
                            Download Selected
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </div>
    );
};

export default FontBrowse;
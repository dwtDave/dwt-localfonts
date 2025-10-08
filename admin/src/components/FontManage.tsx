import React, { useState } from 'react';
import apiFetch from '@wordpress/api-fetch';
import type { LocalFont, FontDiscoveryResponse } from '../types';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from './ui/card';
import { Button } from './ui/button';
import { Badge } from './ui/badge';
import { Input } from './ui/input';
import { Checkbox } from './ui/checkbox';
import { FileText, RefreshCw, AlertTriangle } from 'lucide-react';
import { toast } from 'sonner';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from './ui/tooltip';
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

interface FontManageProps {
    localFonts: LocalFont[];
    onFontDelete: () => Promise<void>;
}

const FontManage: React.FC<FontManageProps> = ({ localFonts, onFontDelete }) => {
    const [deletingFonts, setDeletingFonts] = useState<Set<string>>(new Set());
    const [selectedFonts, setSelectedFonts] = useState<Set<string>>(new Set());
    const [searchTerm, setSearchTerm] = useState('');
    const [previewText] = useState('The quick brown fox jumps over the lazy dog');
    const [fontToDelete, setFontToDelete] = useState<string | null>(null);
    const [showBulkDeleteDialog, setShowBulkDeleteDialog] = useState(false);
    const [discovering, setDiscovering] = useState(false);

    const filteredFonts = React.useMemo(() => {
        if (!searchTerm) return localFonts;
        return localFonts.filter(font =>
            font.family.toLowerCase().includes(searchTerm.toLowerCase())
        );
    }, [localFonts, searchTerm]);

    const confirmDeleteFont = async () => {
        if (!fontToDelete) return;

        setDeletingFonts(prev => new Set([...prev, fontToDelete]));

        try {
            const response = await apiFetch({
                path: '/fonts/delete',
                method: 'POST',
                data: { font_family: fontToDelete }
            }) as { success: boolean; message: string };

            if (response.success) {
                await onFontDelete();
                setSelectedFonts(prev => {
                    const newSet = new Set(prev);
                    newSet.delete(fontToDelete);
                    return newSet;
                });
            }
        } catch (error) {
            console.error('Failed to delete font:', error);
        } finally {
            setDeletingFonts(prev => {
                const newSet = new Set(prev);
                newSet.delete(fontToDelete);
                return newSet;
            });
            setFontToDelete(null);
        }
    };

    const confirmBulkDelete = async () => {
        if (selectedFonts.size === 0) return;

        const fontsToDelete = Array.from(selectedFonts);
        setDeletingFonts(new Set(fontsToDelete));

        try {
            await Promise.all(
                fontsToDelete.map(fontFamily =>
                    apiFetch({
                        path: '/fonts/delete',
                        method: 'POST',
                        data: { font_family: fontFamily }
                    })
                )
            );

            await onFontDelete();
            setSelectedFonts(new Set());
        } catch (error) {
            console.error('Failed to delete fonts:', error);
        } finally {
            setDeletingFonts(new Set());
            setShowBulkDeleteDialog(false);
        }
    };

    const handleDiscoverFonts = async () => {
        setDiscovering(true);

        try {
            const response = await apiFetch({
                path: '/fonts/discover',
                method: 'POST'
            }) as FontDiscoveryResponse;

            if (response.success) {
                if (response.added_fonts.length > 0) {
                    toast.success(
                        `Discovered ${response.discovered_fonts.length} font(s), added ${response.added_fonts.length} new font(s)!`
                    );
                } else if (response.discovered_fonts.length > 0) {
                    toast.info('All discovered fonts are already in your library');
                } else {
                    toast.info('No font files found in the directory');
                }

                // Reload font list
                await onFontDelete();
            } else {
                toast.error('Font discovery failed. Please try again.');
            }
        } catch (error) {
            console.error('Failed to discover fonts:', error);
            toast.error('Failed to discover fonts. Please try again.');
        } finally {
            setDiscovering(false);
        }
    };

    const toggleFontSelection = (fontFamily: string) => {
        setSelectedFonts(prev => {
            const newSet = new Set(prev);
            if (newSet.has(fontFamily)) {
                newSet.delete(fontFamily);
            } else {
                newSet.add(fontFamily);
            }
            return newSet;
        });
    };

    const toggleSelectAll = () => {
        if (selectedFonts.size === filteredFonts.length) {
            setSelectedFonts(new Set());
        } else {
            setSelectedFonts(new Set(filteredFonts.map(f => f.family)));
        }
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
                        Go to the Browse & Download tab to add fonts to your site
                    </p>
                    <div className="flex flex-col items-center gap-2">
                        <Button
                            variant="outline"
                            onClick={handleDiscoverFonts}
                            disabled={discovering}
                        >
                            {discovering ? (
                                <>
                                    <RefreshCw className="mr-2 h-4 w-4 animate-spin" />
                                    Discovering...
                                </>
                            ) : (
                                <>
                                    <RefreshCw className="mr-2 h-4 w-4" />
                                    Discover Fonts
                                </>
                            )}
                        </Button>
                        <p className="text-sm text-muted-foreground">
                            Or scan for fonts already in your uploads directory
                        </p>
                    </div>
                </CardContent>
            </Card>
        );
    }

    return (
        <div className="space-y-4">
            {/* Header with Actions */}
            <Card>
                <CardHeader>
                    <div className="flex items-center justify-between">
                        <div>
                            <CardTitle>Downloaded Fonts ({localFonts.length})</CardTitle>
                            <CardDescription>Manage fonts currently available on your site</CardDescription>
                        </div>
                        <div className="flex gap-2">
                            <Button
                                variant="outline"
                                onClick={handleDiscoverFonts}
                                disabled={discovering || deletingFonts.size > 0}
                            >
                                {discovering ? (
                                    <>
                                        <RefreshCw className="mr-2 h-4 w-4 animate-spin" />
                                        Discovering...
                                    </>
                                ) : (
                                    <>
                                        <RefreshCw className="mr-2 h-4 w-4" />
                                        Discover Fonts
                                    </>
                                )}
                            </Button>
                            {selectedFonts.size > 0 && (
                                <Button
                                    variant="destructive"
                                    onClick={() => setShowBulkDeleteDialog(true)}
                                    disabled={deletingFonts.size > 0 || discovering}
                                >
                                    Delete Selected ({selectedFonts.size})
                                </Button>
                            )}
                        </div>
                    </div>
                </CardHeader>
                <CardContent>
                    <div className="flex items-center gap-4">
                        <Input
                            type="text"
                            placeholder="Search downloaded fonts..."
                            value={searchTerm}
                            onChange={(e) => setSearchTerm(e.target.value)}
                            className="flex-1"
                        />
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={toggleSelectAll}
                        >
                            {selectedFonts.size === filteredFonts.length ? 'Deselect All' : 'Select All'}
                        </Button>
                    </div>
                </CardContent>
            </Card>

            {/* Font List */}
            {filteredFonts.length === 0 ? (
                <Card>
                    <CardContent className="text-center py-12">
                        <p className="text-muted-foreground">No fonts match your search</p>
                    </CardContent>
                </Card>
            ) : (
                <div className="grid grid-cols-1 gap-4">
                    {filteredFonts.map(font => {
                        const isSelected = selectedFonts.has(font.family);
                        const isDeleting = deletingFonts.has(font.family);

                        return (
                            <Card key={font.family} className={`transition-all ${isSelected ? 'ring-2 ring-primary' : ''}`}>
                                <CardContent className="p-6">
                                    <div className="flex items-start gap-4">
                                        <Checkbox
                                            checked={isSelected}
                                            onCheckedChange={() => toggleFontSelection(font.family)}
                                            disabled={isDeleting}
                                            className="mt-1"
                                        />
                                        <div className="flex-1 space-y-3">
                                            <div className="flex items-start justify-between">
                                                <div>
                                                    <h4 className="font-semibold text-lg text-foreground">
                                                        {font.family}
                                                    </h4>
                                                    <div className="flex items-center gap-2 mt-1">
                                                        {font.status === 'downloaded' && (
                                                            <Badge variant="secondary">
                                                                Downloaded
                                                            </Badge>
                                                        )}
                                                        {font.status === 'missing_some_files' && (
                                                            <TooltipProvider>
                                                                <Tooltip>
                                                                    <TooltipTrigger asChild>
                                                                        <Badge variant="outline" className="border-yellow-500 text-yellow-600 cursor-help flex items-center gap-1">
                                                                            <AlertTriangle className="h-3 w-3" />
                                                                            Missing Files ({font.validation?.missing_files.length}/{font.validation?.total_files})
                                                                        </Badge>
                                                                    </TooltipTrigger>
                                                                    <TooltipContent className="max-w-md">
                                                                        <p className="font-semibold mb-1">Missing font files:</p>
                                                                        <ul className="text-xs list-disc list-inside">
                                                                            {font.validation?.missing_files.slice(0, 5).map((file, idx) => (
                                                                                <li key={idx}>{file}</li>
                                                                            ))}
                                                                            {(font.validation?.missing_files.length ?? 0) > 5 && (
                                                                                <li>...and {(font.validation?.missing_files.length ?? 0) - 5} more</li>
                                                                            )}
                                                                        </ul>
                                                                        <p className="text-xs mt-2 text-muted-foreground">
                                                                            Delete and re-download this font to fix issues.
                                                                        </p>
                                                                    </TooltipContent>
                                                                </Tooltip>
                                                            </TooltipProvider>
                                                        )}
                                                        {(font.status === 'missing_css' || font.status === 'missing_css_entry' || font.status === 'missing_all_files') && (
                                                            <Badge variant="destructive">
                                                                {font.status === 'missing_css' ? 'CSS Missing' : font.status === 'missing_css_entry' ? 'Not in CSS' : 'All Files Missing'}
                                                            </Badge>
                                                        )}
                                                    </div>
                                                </div>
                                                <Button
                                                    variant="destructive"
                                                    size="sm"
                                                    onClick={() => setFontToDelete(font.family)}
                                                    disabled={isDeleting}
                                                >
                                                    {isDeleting ? 'Deleting...' : 'Delete'}
                                                </Button>
                                            </div>
                                            <div
                                                className="text-xl py-4 border-t"
                                                style={{ fontFamily: `"${font.family}", sans-serif` }}
                                            >
                                                {previewText}
                                            </div>
                                            {font.font_face_css && (
                                                <details className="text-sm">
                                                    <summary className="cursor-pointer text-muted-foreground hover:text-foreground">
                                                        View available weights and styles
                                                    </summary>
                                                    <div className="mt-2 flex flex-wrap gap-2">
                                                        {(() => {
                                                            const fontFaceBlocks = font.font_face_css.match(/@font-face\s*\{[^}]*\}/gs) || [];
                                                            const weights = fontFaceBlocks.map(block => {
                                                                const weightMatch = block.match(/font-weight:\s*(\d+)/);
                                                                const styleMatch = block.match(/font-style:\s*(normal|italic)/);
                                                                if (weightMatch) {
                                                                    const weight = weightMatch[1];
                                                                    const style = styleMatch ? styleMatch[1] : 'normal';
                                                                    return `${weight}${style !== 'normal' ? ` ${style}` : ''}`;
                                                                }
                                                                return null;
                                                            }).filter(Boolean);

                                                            const uniqueWeights = Array.from(new Set(weights));

                                                            return uniqueWeights.map((weight, idx) => (
                                                                <Badge key={idx} variant="outline" className="text-xs">
                                                                    {weight}
                                                                </Badge>
                                                            ));
                                                        })()}
                                                    </div>
                                                </details>
                                            )}
                                        </div>
                                    </div>
                                </CardContent>
                            </Card>
                        );
                    })}
                </div>
            )}

            {/* Single Font Delete Confirmation */}
            <AlertDialog open={fontToDelete !== null} onOpenChange={(open) => !open && setFontToDelete(null)}>
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>Delete Font</AlertDialogTitle>
                        <AlertDialogDescription>
                            Are you sure you want to delete {fontToDelete}? This action cannot be undone.
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel>Cancel</AlertDialogCancel>
                        <AlertDialogAction onClick={confirmDeleteFont} className="bg-destructive text-destructive-foreground hover:bg-destructive/90">
                            Delete
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>

            {/* Bulk Delete Confirmation */}
            <AlertDialog open={showBulkDeleteDialog} onOpenChange={setShowBulkDeleteDialog}>
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>Delete Multiple Fonts</AlertDialogTitle>
                        <AlertDialogDescription>
                            Are you sure you want to delete {selectedFonts.size} font{selectedFonts.size > 1 ? 's' : ''}? This action cannot be undone.
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel>Cancel</AlertDialogCancel>
                        <AlertDialogAction onClick={confirmBulkDelete} className="bg-destructive text-destructive-foreground hover:bg-destructive/90">
                            Delete All
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </div>
    );
};

export default FontManage;
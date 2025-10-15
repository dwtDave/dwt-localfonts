import React, { useState, useEffect } from 'react';
import apiFetch from '@wordpress/api-fetch';
import type { DWTSettings, LocalFont } from './types';
import { Button } from './components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from './components/ui/card';
import { Switch } from './components/ui/switch';
import { Label } from './components/ui/label';
import { Tabs, TabsList, TabsTrigger, TabsContent } from './components/ui/tabs';
import { Badge } from './components/ui/badge';
import { Toaster } from './components/ui/sonner';
import { toast } from 'sonner';
import { Loader2 } from 'lucide-react';
import FontBrowse from './components/FontBrowse';
import FontApply from './components/FontApply';
import FontManage from './components/FontManage';

function FontManagerApp(): React.JSX.Element {
    const [activeView, setActiveView] = useState<string>('browse');
    const [settings, setSettings] = useState<DWTSettings>({});
    const [localFonts, setLocalFonts] = useState<LocalFont[]>([]);
    const [loading, setLoading] = useState<boolean>(true);
    const [saving, setSaving] = useState<boolean>(false);

    useEffect(() => {
        loadSettings();
        loadLocalFonts();
    }, []);

    const loadSettings = async (): Promise<void> => {
        try {
            const data = await apiFetch({ path: '/settings' }) as DWTSettings;
            setSettings(data);
        } catch (error) {
            console.error('Failed to load settings:', error);
        } finally {
            setLoading(false);
        }
    };

    const loadLocalFonts = async () => {
        try {
            const fonts = await apiFetch({ path: '/fonts/local' }) as LocalFont[];
            setLocalFonts(fonts || []);
        } catch (error) {
            console.error('Failed to load local fonts:', error);
            setLocalFonts([]);
        }
    };

    const handleFontDownload = async () => {
        await loadLocalFonts();
    };

    const handleFontDelete = async () => {
        await loadLocalFonts();
    };

    const updateSetting = (key: keyof DWTSettings, value: boolean | string): void => {
        setSettings(prev => ({
            ...prev,
            [key]: value
        }));
    };

    const saveSettings = async (): Promise<void> => {
        try {
            setSaving(true);
            await apiFetch({
                path: '/settings',
                method: 'POST',
                data: settings
            });
            toast.success('Settings saved successfully!');
        } catch (error) {
            console.error('Failed to save settings:', error);
            toast.error('Failed to save settings. Please try again.');
        } finally {
            setSaving(false);
        }
    };

    if (loading) {
        return (
            <div className="p-6">
                <div className="flex items-center justify-center min-h-[200px]">
                    <div className="text-center space-y-2">
                        <Loader2 className="w-8 h-8 animate-spin mx-auto text-primary" />
                        <p className="text-muted-foreground">Loading...</p>
                    </div>
                </div>
            </div>
        );
    }

    return (
        <div className="p-6 max-w-full mx-auto">
            <header className="mb-6">
                <h1 className="text-3xl font-bold text-foreground">Font Manager</h1>
                <p className="text-muted-foreground mt-2">
                    Browse, download, and manage Google Fonts for your WordPress site
                </p>
            </header>

            <Tabs value={activeView} onValueChange={setActiveView} className="space-y-6">
                <TabsList className="w-full justify-start">
                    <TabsTrigger value="browse">Browse Fonts</TabsTrigger>
                    <TabsTrigger value="apply">
                        Apply to Site
                        {localFonts.length > 0 && (
                            <Badge variant="secondary" className="ml-2">
                                {localFonts.length}
                            </Badge>
                        )}
                    </TabsTrigger>
                    <TabsTrigger value="manage">
                        Manage Downloads
                        {localFonts.length > 0 && (
                            <Badge variant="secondary" className="ml-2">
                                {localFonts.length}
                            </Badge>
                        )}
                    </TabsTrigger>
                    <TabsTrigger value="settings">Settings</TabsTrigger>
                </TabsList>

                <TabsContent value="browse">
                    <FontBrowse
                        localFonts={localFonts}
                        onFontDownload={handleFontDownload}
                        onFontDelete={handleFontDelete}
                    />
                </TabsContent>

                <TabsContent value="apply">
                    <FontApply localFonts={localFonts} />
                </TabsContent>

                <TabsContent value="manage">
                    <FontManage
                        localFonts={localFonts}
                        onFontDelete={handleFontDelete}
                    />
                </TabsContent>

                <TabsContent value="settings">
                    <>
                        <Card>
                            <CardHeader>
                                <CardTitle>Font Display Optimization</CardTitle>
                                <CardDescription>
                                    Configure font loading behavior for better performance
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <div className="flex items-center justify-between space-x-4 py-3">
                                    <div className="space-y-1">
                                        <Label htmlFor="font_display_swap" className="text-sm font-medium leading-none">
                                            Enable font-display: swap
                                        </Label>
                                        <p className="text-sm text-muted-foreground">
                                            Improve perceived performance by showing fallback fonts while custom fonts load
                                        </p>
                                    </div>
                                    <Switch
                                        id="font_display_swap"
                                        checked={settings.font_display_swap === '1' || settings.font_display_swap === true}
                                        onCheckedChange={(checked) => updateSetting('font_display_swap', checked)}
                                    />
                                </div>

                                <div className="flex items-center justify-between space-x-4 py-3 border-t">
                                    <div className="space-y-1">
                                        <Label htmlFor="include_unicode_range" className="text-sm font-medium leading-none">
                                            Include unicode-range in CSS
                                        </Label>
                                        <p className="text-sm text-muted-foreground">
                                            Optimize font loading by specifying character ranges. Disable if you experience font rendering issues.
                                        </p>
                                    </div>
                                    <Switch
                                        id="include_unicode_range"
                                        checked={settings.include_unicode_range === '1' || settings.include_unicode_range === true || settings.include_unicode_range === undefined}
                                        onCheckedChange={(checked) => updateSetting('include_unicode_range', checked)}
                                    />
                                </div>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle>Data Retention</CardTitle>
                                <CardDescription>
                                    Control what happens to downloaded fonts when the plugin is uninstalled
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <div className="flex items-center justify-between space-x-4 py-3">
                                    <div className="space-y-1">
                                        <Label htmlFor="keep_fonts_on_uninstall" className="text-sm font-medium leading-none">
                                            Keep downloaded fonts on uninstall
                                        </Label>
                                        <p className="text-sm text-muted-foreground">
                                            Preserve your downloaded font files when uninstalling the plugin. Disable to remove all data completely.
                                        </p>
                                    </div>
                                    <Switch
                                        id="keep_fonts_on_uninstall"
                                        checked={settings.keep_fonts_on_uninstall === '1' || settings.keep_fonts_on_uninstall === true}
                                        onCheckedChange={(checked) => updateSetting('keep_fonts_on_uninstall', checked)}
                                    />
                                </div>
                                {(settings.keep_fonts_on_uninstall === '1' || settings.keep_fonts_on_uninstall === true) && (
                                    <div className="bg-muted/50 rounded-lg p-4 border border-border">
                                        <p className="text-sm text-muted-foreground">
                                            <strong className="text-foreground">💡 Font Discovery:</strong> When you reinstall the plugin after keeping fonts, use the "Discover Fonts" button in the Manage Downloads tab to automatically detect and restore your font library.
                                        </p>
                                    </div>
                                )}
                            </CardContent>
                        </Card>


                        <Card className="mt-8">
                            <CardContent className="pt-6">
                                <div className="flex items-center justify-between">
                                    <div className="space-y-1">
                                        <h2 className="text-lg font-semibold text-foreground">Save Changes</h2>
                                        <p id="save-description" className="text-sm text-muted-foreground">
                                            Apply your configuration changes
                                        </p>
                                    </div>
                                    <Button
                                        onClick={saveSettings}
                                        disabled={saving}
                                        size="lg"
                                        aria-describedby="save-description"
                                    >
                                        {saving ? 'Saving...' : 'Save Settings'}
                                    </Button>
                                </div>
                            </CardContent>
                        </Card>
                    </>
                </TabsContent>
            </Tabs>
            <Toaster />
        </div>
    );
}

export default FontManagerApp;

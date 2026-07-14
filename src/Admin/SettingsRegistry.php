<?php

namespace OnikImages\Admin;

class SettingsRegistry
{
    public static function register(): void
    {
        self::registerOptions();
        self::registerSections();
        self::registerFields();
    }

    private static function registerOptions(): void
    {
        register_setting('onik_images_settings', 'onik_images_enabled', [
            'sanitize_callback' => 'onik_images_sanitize_enabled',
        ]);
        register_setting('onik_images_settings', 'onik_images_tenant', [
            'sanitize_callback' => 'sanitize_text_field',
        ]);
        register_setting('onik_images_settings', 'onik_images_site', [
            'sanitize_callback' => 'sanitize_text_field',
        ]);
        register_setting('onik_images_settings', 'onik_images_allow_domains', [
            'sanitize_callback' => 'sanitize_text_field',
        ]);
        register_setting('onik_images_settings', 'onik_images_forbidden_domains', [
            'sanitize_callback' => 'sanitize_text_field',
        ]);
        register_setting('onik_images_settings', 'onik_images_image_converter_url', [
            'sanitize_callback' => 'onik_images_sanitize_image_converter_url',
        ]);
        register_setting('onik_images_settings', 'onik_images_image_settings', [
            'sanitize_callback' => 'onik_images_sanitize_image_settings',
        ]);
        register_setting('onik_images_settings', 'onik_images_regex_replace', [
            'sanitize_callback' => 'onik_images_sanitize_regex_replace',
        ]);
        register_setting('onik_images_settings', 'onik_images_preloads', [
            'sanitize_callback' => 'onik_images_sanitize_preloads',
        ]);
        register_setting('onik_images_settings', 'onik_images_debug');
        register_setting('onik_images_settings', 'onik_images_youtube_enabled');
        register_setting('onik_images_settings', 'onik_images_youtube_settings', [
            'sanitize_callback' => 'onik_images_sanitize_youtube_settings',
        ]);
    }

    private static function registerSections(): void
    {
        $advanced = AdvancedMode::isEnabled();

        add_settings_section('onik_images_general_section', '', '', 'onik_images_settings_general');
        add_settings_section('onik_images_image_settings_section', '', '', 'onik_images_settings_image_settings');
        if ($advanced) {
            add_settings_section('onik_images_regex_replace_section', '', '', 'onik_images_settings_regex_replace');
        }
        add_settings_section('onik_images_preloads_section', '', '', 'onik_images_settings_preloads');
        add_settings_section(
            'onik_images_youtube_section',
            'YouTube Optimization Settings',
            [Renderers\SimpleRenderers::class, 'youtubeSection'],
            'onik_images_settings_youtube_facade'
        );
    }

    private static function registerFields(): void
    {
        $advanced = AdvancedMode::isEnabled();

        add_settings_field(
            'onik_images_enabled',
            'Enable Lens Images',
            [Renderers\GeneralRenderers::class, 'enabled'],
            'onik_images_settings_general',
            'onik_images_general_section'
        );

        if ($advanced) {
            add_settings_field(
                'onik_images_image_converter_url',
                'Image Converter URL',
                [Renderers\GeneralRenderers::class, 'imageConverterUrl'],
                'onik_images_settings_general',
                'onik_images_general_section'
            );

            add_settings_field(
                'onik_images_tenant',
                'Tenant:',
                [Renderers\GeneralRenderers::class, 'tenant'],
                'onik_images_settings_general',
                'onik_images_general_section'
            );

            add_settings_field(
                'onik_images_site',
                'Site:',
                [Renderers\GeneralRenderers::class, 'site'],
                'onik_images_settings_general',
                'onik_images_general_section'
            );

            add_settings_field(
                'onik_images_allow_domains',
                'Allow Domains',
                [Renderers\GeneralRenderers::class, 'allowDomains'],
                'onik_images_settings_general',
                'onik_images_general_section'
            );

            add_settings_field(
                'onik_images_forbidden_domains',
                'Forbidden Domains',
                [Renderers\GeneralRenderers::class, 'forbiddenDomains'],
                'onik_images_settings_general',
                'onik_images_general_section'
            );

            add_settings_field(
                'onik_images_debug',
                'Debug to frontend console',
                [Renderers\GeneralRenderers::class, 'debug'],
                'onik_images_settings_general',
                'onik_images_general_section'
            );
        }

        add_settings_field(
            'onik_images_youtube_enabled',
            'Enable YouTube Optimization',
            [Renderers\SimpleRenderers::class, 'youtubeEnabled'],
            'onik_images_settings_youtube_facade',
            'onik_images_youtube_section'
        );

        add_settings_field(
            'onik_images_youtube_settings',
            'YouTube Settings',
            [Renderers\YoutubeSettingsRenderer::class, 'render'],
            'onik_images_settings_youtube_facade',
            'onik_images_youtube_section'
        );

        add_settings_field(
            'onik_images_image_settings',
            'Image Settings',
            [Renderers\ImageSettingsRenderer::class, 'render'],
            'onik_images_settings_image_settings',
            'onik_images_image_settings_section'
        );

        if ($advanced) {
            add_settings_field(
                'onik_images_regex_replace',
                'Regex Replace',
                [Renderers\SimpleRenderers::class, 'regexReplace'],
                'onik_images_settings_regex_replace',
                'onik_images_regex_replace_section'
            );
        }

        add_settings_field(
            'onik_images_preloads',
            'Preloads',
            [Renderers\PreloadsRenderer::class, 'render'],
            'onik_images_settings_preloads',
            'onik_images_preloads_section'
        );

    }
}

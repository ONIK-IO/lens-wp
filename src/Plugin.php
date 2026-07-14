<?php

namespace OnikImages;

class Plugin
{
    private static string $pluginFile = '';

    public static function boot(string $pluginFile): void
    {
        self::$pluginFile = $pluginFile;

        if (function_exists('register_activation_hook')) {
            register_activation_hook($pluginFile, [Activation\Installer::class, 'install']);
        }

        add_action('admin_menu', [Admin\Menu::class, 'register']);
        add_action('admin_init', [Admin\AdvancedMode::class, 'checkToggle']);
        add_action('admin_init', [Admin\SettingsRegistry::class, 'register']);
        add_action('admin_init', [Activation\Gate::class, 'handleFormSubmission']);
        add_action('admin_init', [Activation\Gate::class, 'handleDeactivate']);
        add_action('admin_init', [Activation\Gate::class, 'checkIfDue']);
        add_action('wp_enqueue_scripts', [Frontend\YoutubeAssets::class, 'enqueue']);
        add_action('template_redirect', [Frontend\OutputBuffer::class, 'register']);
    }

    public static function pluginFile(): string
    {
        return self::$pluginFile;
    }

    public static function pluginDir(): string
    {
        return self::$pluginFile === '' ? __DIR__ . '/..' : dirname(self::$pluginFile);
    }
}

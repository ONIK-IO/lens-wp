<?php

namespace OnikImages;

class Plugin
{
    private static string $pluginFile = '';

    public static function boot(string $pluginFile): void
    {
        self::$pluginFile = $pluginFile;

        if (function_exists('register_activation_hook')) {
            register_activation_hook($pluginFile, [Activation\Installer::class, 'onActivate']);
        }
        if (function_exists('register_deactivation_hook')) {
            register_deactivation_hook($pluginFile, [Cron\Verifier::class, 'unschedule']);
        }

        // Native update prompts sourced from GitHub releases. Skipped under tests
        // because the update-checker library does real WP work on construction.
        if (!defined('ONIK_IMAGES_TESTS')) {
            Updates\UpdateChecker::register($pluginFile);
        }

        // "Settings" shortcut on the Plugins listing row.
        Admin\PluginLinks::register($pluginFile);

        add_action('admin_menu', [Admin\Menu::class, 'register']);
        add_action('admin_init', [Admin\AdvancedMode::class, 'checkToggle']);
        add_action('admin_init', [Admin\SettingsRegistry::class, 'register']);
        // Activate: anonymous subscription check that gates rewriting. Runs
        // automatically on plugin activation (onActivate) and re-checks
        // periodically here — no manual button.
        add_action('admin_init', [Activation\Gate::class, 'handleDeactivate']);
        add_action('admin_init', [Activation\Gate::class, 'checkIfDue']);
        // Connect: token-based identity link that syncs tenant/site.
        add_action('admin_init', [Connect\Gate::class, 'handleFormSubmission']);
        add_action('admin_init', [Connect\Gate::class, 'handleDisconnect']);
        add_action('admin_init', [Connect\Gate::class, 'checkIfDue']);
        // CSS Backgrounds tab: adds a discovered selector to image settings.
        add_action('admin_init', [Css\Gate::class, 'handleAddSelector']);
        // Background re-verification so a valid site stays fresh without any
        // admin login. `schedule` is idempotent; `init` fires on all requests.
        add_action('init', [Cron\Verifier::class, 'schedule']);
        add_action(Cron\Verifier::HOOK, [Cron\Verifier::class, 'run']);
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

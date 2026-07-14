<?php

namespace OnikImages\Admin;

class Menu
{
    public static function register(): void
    {
        add_submenu_page(
            'options-general.php',
            'ONIK Lens',
            'ONIK Lens',
            'manage_options',
            'onik_images_settings',
            [SettingsPage::class, 'render']
        );
    }
}

<?php

namespace OnikImages\Admin;

/**
 * Adds a "Settings" shortcut to the plugin's row on the Plugins listing screen,
 * next to Activate/Deactivate, pointing at the ONIK Lens settings page.
 */
class PluginLinks
{
    public static function register(string $pluginFile): void
    {
        // plugin_action_links_{basename} is the per-plugin filter WordPress runs
        // to build the action links shown under the plugin's row.
        add_filter(
            'plugin_action_links_' . plugin_basename($pluginFile),
            [self::class, 'addSettingsLink']
        );
    }

    /**
     * @param string[] $links Existing action links (HTML anchors).
     * @return string[]
     */
    public static function addSettingsLink(array $links): array
    {
        $url = admin_url('options-general.php?page=onik_images_settings');
        $settingsLink = sprintf(
            '<a href="%s">%s</a>',
            esc_url($url),
            esc_html__('Settings', 'onik-lens')
        );

        // Prepend so Settings reads first, ahead of Deactivate.
        array_unshift($links, $settingsLink);

        return $links;
    }
}

<?php

namespace OnikImages\Admin;

/**
 * Tiny render helpers for settings fields. Each method echoes a single HTML
 * input for the given option name.
 */
class FieldHelpers
{
    public static function text(string $name): void
    {
        $setting = get_option($name);
        ?>
        <input type="text" name="<?php echo esc_attr($name); ?>"
            value="<?php echo isset($setting) ? esc_attr($setting) : ''; ?>" class="regular-text">
        <?php
    }

    public static function textarea(string $name): void
    {
        $setting = get_option($name);

        // Preserve submitted value when there are validation errors.
        $submitted_value = '';
        if (isset($_POST[$name])) {
            $submitted_value = $_POST[$name];
        }

        $display_value = !empty($submitted_value) ? $submitted_value : (isset($setting) ? $setting : '');
        ?>
        <textarea name="<?php echo esc_attr($name); ?>" class="regular-text" rows="10"
            style="min-height: 500px; width: 100%;"><?php echo esc_textarea($display_value); ?></textarea>
        <?php
    }

    public static function checkbox(string $name): void
    {
        $setting = get_option($name);
        ?>
        <input type="checkbox" name="<?php echo esc_attr($name); ?>" value="1" <?php checked(1, $setting); ?>>
        <?php
    }
}

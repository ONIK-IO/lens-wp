<?php

namespace OnikImages\Css;

/**
 * The CSS Backgrounds tab.
 *
 * Paste a stylesheet URL, get every background-image in it with the selector
 * that owns it. Scanning is a plain GET because it changes nothing, which also
 * makes a result linkable and refresh-safe.
 *
 * Rendered outside the main options.php <form>, like Connect\AdminPanel: the
 * add button posts to its own handler and nesting forms is invalid HTML.
 */
class AdminPanel
{
    public static function render(): void
    {
        $requested = isset($_GET['onik_css_url']) ? rawurldecode((string) $_GET['onik_css_url']) : '';
        $requested = trim($requested);

        self::renderAddedNotice();
        self::renderIntro($requested);

        if ($requested === '') {
            return;
        }

        $report = Discovery::inspect($requested, false);

        if (!$report['ok']) {
            self::renderError($report);
            return;
        }

        self::renderSummary($report);

        if ($report['rows'] === []) {
            echo '<p>No background images in this stylesheet.</p>';
            return;
        }

        self::renderTable($report);
    }

    private static function renderIntro(string $requested): void
    {
        $action = esc_url(admin_url('options-general.php'));

        echo '<h2>CSS backgrounds</h2>';
        echo '<p>ONIK Lens rewrites images in the page HTML, so a background image that lives in a '
            . 'stylesheet file is invisible to it. Paste a stylesheet URL below to see what is in there. '
            . 'You can find the URL in your browser\'s view-source or Network tab.</p>';
        echo '<p><em>Local stylesheets only. A sheet served from another host is reported rather than '
            . 'fetched, so a page render never waits on an outside request.</em></p>';

        echo '<form method="get" action="' . $action . '">';
        echo '<input type="hidden" name="page" value="onik_images_settings" />';
        echo '<input type="hidden" name="tab" value="css_backgrounds" />';
        echo '<p>';
        echo '<input type="url" name="onik_css_url" class="regular-text code" style="width:36em;max-width:100%;" '
            . 'placeholder="https://example.com/wp-content/uploads/astra-addon/astra-addon-dynamic-css-post-15811.css" '
            . 'value="' . esc_attr($requested) . '" />';
        echo ' <button type="submit" class="button button-primary">Scan stylesheet</button>';
        echo '</p>';
        echo '</form>';
    }

    private static function renderSummary(array $report): void
    {
        $summary = $report['summary'];

        echo '<p><strong>' . (int) $summary['images'] . '</strong> background image'
            . ((int) $summary['images'] === 1 ? '' : 's')
            . ' across <strong>' . (int) $summary['rules'] . '</strong> rule'
            . ((int) $summary['rules'] === 1 ? '' : 's')
            . ' in ' . esc_html(size_format($report['bytes'])) . ' of CSS. '
            . '<strong>' . (int) $summary['convertible'] . '</strong> can be optimized.</p>';
    }

    private static function renderError(array $report): void
    {
        switch ($report['error']) {
            case StylesheetReader::ERROR_NOT_LOCAL:
                $message = 'That stylesheet is not a readable file on this server. It may be hosted on another '
                    . 'domain or a CDN, served by a caching plugin that concatenates CSS, or the URL may be wrong.';
                break;
            case StylesheetReader::ERROR_TOO_LARGE:
                $message = 'That stylesheet is too large to scan.';
                break;
            case StylesheetReader::ERROR_UNREADABLE:
                $message = 'That file exists but could not be read. Check its file permissions.';
                break;
            default:
                $message = 'That stylesheet could not be scanned.';
        }

        echo '<div class="notice notice-warning inline"><p>' . esc_html($message) . '</p></div>';
    }

    private static function renderTable(array $report): void
    {
        echo '<table class="widefat striped" style="margin-top:12px;">';
        echo '<thead><tr>'
            . '<th>Selector</th>'
            . '<th>Applies when</th>'
            . '<th>Image</th>'
            . '<th style="width:9em;">Source size</th>'
            . '<th style="width:16em;">Status</th>'
            . '</tr></thead><tbody>';

        foreach ($report['rows'] as $row) {
            echo '<tr>';

            echo '<td><code>' . esc_html($row['selector']) . '</code>';
            if ((int) $row['ruleCount'] > 1) {
                echo '<br /><span class="description">Set in ' . (int) $row['ruleCount']
                    . ' rules in this file. An override has to win against all of them.</span>';
            }
            if ($row['important']) {
                echo '<br /><span class="description">Declared <code>!important</code>.</span>';
            }
            echo '</td>';

            echo '<td>';
            echo $row['atRules'] === []
                ? '<span class="description">Always</span>'
                : '<code>' . esc_html(implode(' ', $row['atRules'])) . '</code>';
            echo '</td>';

            echo '<td style="word-break:break-all;">';
            if ($row['imageUrl'] === null) {
                echo '<code>' . esc_html($row['raw']) . '</code>';
            } else {
                echo '<a href="' . esc_url($row['imageUrl']) . '" target="_blank" rel="noopener">'
                    . esc_html(self::shorten($row['imageUrl'])) . '</a>';
            }
            echo '</td>';

            echo '<td>' . esc_html(self::describeSize($row)) . '</td>';

            echo '<td>';
            self::renderAction($row, $report['url']);
            echo '</td>';

            echo '</tr>';
        }

        echo '</tbody></table>';
    }

    private static function renderAction(array $row, string $sourceUrl): void
    {
        if (!$row['convertible']) {
            echo '<span class="description">' . esc_html((string) $row['reason']) . '</span>';
            return;
        }

        if ($row['configured']) {
            echo '<span class="description">Already in Image Settings.</span>';
            return;
        }

        $suggested = self::suggestWidth($row);
        $action    = esc_url(admin_url('options-general.php?page=onik_images_settings&tab=css_backgrounds'));

        echo '<form method="post" action="' . $action . '" style="display:flex;gap:6px;align-items:center;">';
        wp_nonce_field(Gate::NONCE_ACTION, Gate::NONCE_FIELD);
        echo '<input type="hidden" name="onik_lens_css_selector" value="' . esc_attr($row['selector']) . '" />';
        echo '<input type="hidden" name="onik_lens_css_source" value="' . esc_attr($sourceUrl) . '" />';
        echo '<input type="number" name="onik_lens_css_width" min="1" max="10000" step="1" '
            . 'value="' . esc_attr((string) $suggested) . '" style="width:6.5em;" aria-label="Width in pixels" />';
        echo '<button type="submit" name="onik_lens_css_add" value="1" class="button">Optimize</button>';
        echo '</form>';
    }

    private static function renderAddedNotice(): void
    {
        if (!isset($_GET['onik_css_added'])) {
            return;
        }

        switch ($_GET['onik_css_added']) {
            case Gate::STATUS_ADDED:
                $class   = 'notice-success';
                $message = 'Selector added to Image Settings in ExternalCssUrl mode.';
                break;
            case Gate::STATUS_EXISTS:
                $class   = 'notice-info';
                $message = 'That selector is already configured on the Image Settings tab.';
                break;
            default:
                $class   = 'notice-error';
                $message = 'That selector could not be saved.';
        }

        echo '<div class="notice ' . esc_attr($class) . ' inline"><p>' . esc_html($message) . '</p></div>';
    }

    /**
     * A background image has no intrinsic layout width, so there is nothing to
     * measure. Use the source image's own width when we know it, capped at a
     * sane display size, and let the user correct it.
     */
    private static function suggestWidth(array $row): int
    {
        $natural = (int) ($row['width'] ?? 0);

        if ($natural >= 1 && $natural <= 1920) {
            return $natural;
        }

        return 1920;
    }

    private static function describeSize(array $row): string
    {
        if ($row['width'] === null || $row['height'] === null) {
            return $row['imageBytes'] === null ? 'Unknown' : size_format($row['imageBytes']);
        }

        $size = $row['width'] . ' x ' . $row['height'];
        if ($row['imageBytes'] !== null) {
            $size .= ', ' . size_format($row['imageBytes']);
        }

        return $size;
    }

    private static function shorten(string $url, int $max = 72): string
    {
        if (strlen($url) <= $max) {
            return $url;
        }

        return substr($url, 0, 28) . '...' . substr($url, -($max - 31));
    }
}

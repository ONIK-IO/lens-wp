<?php

namespace OnikImages\Css;

/**
 * Turns a stylesheet URL into the view model the discovery table renders.
 *
 * Read-only. Nothing here changes settings or page output; it answers "what
 * background images does this stylesheet load, and which of them could ONIK
 * Lens optimize".
 */
class Discovery
{
    /**
     * @return array{
     *   url: string,
     *   ok: bool,
     *   error: ?string,
     *   bytes: int,
     *   cached: bool,
     *   rows: array<int, array<string, mixed>>,
     *   summary: array{rules: int, images: int, convertible: int, skipped: int}
     * }
     */
    public static function inspect(string $url, bool $useCache = true): array
    {
        $url    = trim($url);
        $result = StylesheetReader::read($url, $useCache);

        $base = [
            'url'     => $url,
            'ok'      => $result['ok'],
            'error'   => $result['error'],
            'bytes'   => $result['bytes'],
            'cached'  => $result['cached'],
            'rows'    => [],
            'summary' => ['rules' => 0, 'images' => 0, 'convertible' => 0, 'skipped' => 0],
        ];

        if (!$result['ok']) {
            return $base;
        }

        $configured   = self::configuredSelectors();
        $ruleCounts   = self::countSelectorRules($result['records']);
        $rows         = [];
        $convertible  = 0;
        $skipped      = 0;

        foreach ($result['records'] as $record) {
            foreach ($record['urls'] as $imageUrl) {
                $isConvertible = false;

                if ($imageUrl['resolved'] !== null && $imageUrl['skip'] === null) {
                    $isConvertible = should_alter_image_based_on_src($imageUrl['resolved']);
                }

                if ($isConvertible) {
                    $convertible++;
                } else {
                    $skipped++;
                }

                $info = $imageUrl['resolved'] === null
                    ? ['width' => null, 'height' => null, 'bytes' => null]
                    : StylesheetReader::describeImage($imageUrl['resolved']);

                $rows[] = [
                    'selector'    => $record['selector'],
                    'selectors'   => $record['selectors'],
                    'atRules'     => $record['atRules'],
                    'property'    => $record['property'],
                    'important'   => $record['important'],
                    'raw'         => $imageUrl['raw'],
                    'imageUrl'    => $imageUrl['resolved'],
                    'skip'        => $imageUrl['skip'],
                    'convertible' => $isConvertible,
                    'reason'      => self::reason($imageUrl, $isConvertible),
                    'width'       => $info['width'],
                    'height'      => $info['height'],
                    'imageBytes'  => $info['bytes'],
                    'configured'  => in_array($record['selector'], $configured, true),
                    'ruleCount'   => $ruleCounts[$record['selector']] ?? 1,
                ];
            }
        }

        $base['rows']    = $rows;
        $base['summary'] = [
            'rules'       => count($result['records']),
            'images'      => count($rows),
            'convertible' => $convertible,
            'skipped'     => $skipped,
        ];

        return $base;
    }

    /**
     * A short, plain explanation of why a row cannot be optimized. Showing an
     * empty table with no reason is the most annoying possible outcome.
     */
    private static function reason(array $imageUrl, bool $convertible): ?string
    {
        if ($convertible) {
            return null;
        }

        switch ($imageUrl['skip']) {
            case UrlResolver::SKIP_DATA_URI:
                return 'Inline data URI, already embedded';
            case UrlResolver::SKIP_FRAGMENT:
                return 'In-document reference, not an image file';
            case UrlResolver::SKIP_EMPTY:
                return 'Empty url()';
        }

        if ($imageUrl['resolved'] === null) {
            return 'Could not resolve against the stylesheet URL';
        }

        return 'Blocked by the extension or domain rules on the General tab';
    }

    /**
     * Selectors already present in onik_images_image_settings, whatever their
     * srcSwap mode. Used to stop the table offering to add a duplicate.
     *
     * @return array<int, string>
     */
    public static function configuredSelectors(): array
    {
        $raw = get_option('onik_images_image_settings');
        if (!is_string($raw) || trim($raw) === '') {
            return [];
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return [];
        }

        return array_keys($decoded);
    }

    /**
     * How many rules in this stylesheet set a background on each selector.
     *
     * More than one is usually responsive CSS and perfectly normal, but it is
     * also the shape that makes an override lose, so the table says so. What
     * this cannot detect is a DIFFERENT, higher-specificity selector targeting
     * the same element; nothing short of rendering the page would find that.
     *
     * @param array<int, array<string, mixed>> $records
     * @return array<string, int>
     */
    private static function countSelectorRules(array $records): array
    {
        $counts = [];
        foreach ($records as $record) {
            $selector = $record['selector'];
            $counts[$selector] = ($counts[$selector] ?? 0) + 1;
        }

        return $counts;
    }
}

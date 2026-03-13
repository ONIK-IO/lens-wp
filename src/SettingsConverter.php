<?php

namespace OnikImages;

class SettingsConverter
{
    /**
     * Convert JSON settings string to a table format array.
     * 
     * @param string $json The JSON string to convert.
     * @return array The table format array.
     */
    public function jsonToTable(string $json): array
    {
        $data = json_decode($json, true);
        
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
            return [];
        }

        $table = [];

        foreach ($data as $selector => $settings) {
            if (!is_array($settings)) {
                continue;
            }

            $row = [
                'selector' => (string)$selector,
                'widths' => isset($settings['widths']) && is_array($settings['widths']) ? implode(', ', $settings['widths']) : '',
                'quality' => isset($settings['quality']) ? (string)$settings['quality'] : '',
                'loading' => isset($settings['loading']) ? (string)$settings['loading'] : '',
                'sizes' => isset($settings['sizes']) ? (string)$settings['sizes'] : '',
                'fetchpriority' => isset($settings['fetchpriority']) ? (string)$settings['fetchpriority'] : '',
                'decoding' => isset($settings['decoding']) ? (string)$settings['decoding'] : '',
                'format' => isset($settings['format']) ? (string)$settings['format'] : '',
                'srcSwap' => isset($settings['srcSwap']) ? (string)$settings['srcSwap'] : '',
                'setWidth' => isset($settings['setWidth']) ? (string)$settings['setWidth'] : '',
                'setHeight' => isset($settings['setHeight']) ? (string)$settings['setHeight'] : '',
                'lazyLoadAfter' => isset($settings['lazyLoadAfter']) ? (string)$settings['lazyLoadAfter'] : '',
            ];

            $table[] = $row;
        }

        return $table;
    }

    /**
     * Convert table format array back to JSON settings string.
     * 
     * @param array $table The table format array.
     * @return string The JSON settings string.
     */
    public function tableToJson(array $table): string
    {
        $data = [];

        foreach ($table as $row) {
            if (empty($row['selector'])) {
                continue;
            }

            $selector = $row['selector'];
            $config = [];

            // Handle widths
            if (!empty($row['widths'])) {
                $widths = array_map('intval', array_map('trim', explode(',', $row['widths'])));
                // Filter out invalid widths (0 or empty)
                $widths = array_filter($widths, function($w) { return $w > 0; });
                if (!empty($widths)) {
                    $config['widths'] = array_values($widths);
                }
            }

            // Handle other fields
            $fields = ['quality', 'loading', 'sizes', 'fetchpriority', 'decoding', 'format', 'srcSwap', 'setWidth', 'setHeight', 'lazyLoadAfter'];
            foreach ($fields as $field) {
                if (isset($row[$field]) && $row[$field] !== '') {
                    $value = $row[$field];
                    // Cast quality, setWidth, setHeight, lazyLoadAfter to int
                    if (in_array($field, ['quality', 'setWidth', 'setHeight', 'lazyLoadAfter'], true)) {
                        $value = (int)$value;
                    }
                    $config[$field] = $value;
                }
            }

            $data[$selector] = $config;
        }

        // Return empty object if empty array to match typical JSON object behavior
        if (empty($data)) {
            return '{}';
        }

        return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }
    /**
     * Convert YouTube settings JSON string to a table format array.
     * 
     * @param string $json The JSON string to convert.
     * @return array The table format array.
     */
    public function youtubeJsonToTable(string $json): array
    {
        $data = json_decode($json, true);
        
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
            return [];
        }

        $table = [];

        foreach ($data as $selector => $settings) {
            if (!is_array($settings)) {
                continue;
            }

            $row = [
                'selector' => (string)$selector,
                'playlabel' => isset($settings['playlabel']) ? (string)$settings['playlabel'] : '',
                'title' => isset($settings['title']) ? (string)$settings['title'] : '',
                'params' => isset($settings['params']) ? (string)$settings['params'] : '',
                'js_api' => isset($settings['js_api']) ? (bool)$settings['js_api'] : false,
                'style' => isset($settings['style']) ? (string)$settings['style'] : '',
            ];

            $table[] = $row;
        }

        return $table;
    }

    /**
     * Convert table format array back to YouTube settings JSON string.
     * 
     * @param array $table The table format array.
     * @return string The JSON settings string.
     */
    public function tableToYoutubeJson(array $table): string
    {
        $data = [];

        foreach ($table as $row) {
            if (empty($row['selector'])) {
                continue;
            }

            $selector = $row['selector'];
            $config = [];

            // Handle fields
            $fields = ['playlabel', 'title', 'params', 'style'];
            foreach ($fields as $field) {
                if (isset($row[$field]) && $row[$field] !== '') {
                    $config[$field] = $row[$field];
                }
            }

            // Handle boolean fields
            if (isset($row['js_api'])) {
                // Check if it's a boolean or string '1'/'true'
                $val = $row['js_api'];
                if ($val === true || $val === '1' || $val === 1 || $val === 'true') {
                    $config['js_api'] = true;
                }
            }

            $data[$selector] = $config;
        }

        // Return empty object if empty array to match typical JSON object behavior
        if (empty($data)) {
            return '{}';
        }

        return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }
    /**
     * Convert Preloads settings JSON string to a table format array.
     * Note: Preloads JSON is an array of objects (no selector key).
     * 
     * @param string $json The JSON string to convert.
     * @return array The table format array.
     */
    public function preloadsJsonToTable(string $json): array
    {
        $data = json_decode($json, true);
        
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
            return [];
        }

        $table = [];

        foreach ($data as $preload) {
            if (!is_array($preload)) {
                continue;
            }

            $row = [
                'rel' => isset($preload['rel']) ? (string)$preload['rel'] : 'preload',
                'fetchpriority' => isset($preload['fetchpriority']) ? (string)$preload['fetchpriority'] : '',
                'as' => isset($preload['as']) ? (string)$preload['as'] : '',
                'href' => isset($preload['href']) ? (string)$preload['href'] : '',
                'type' => isset($preload['type']) ? (string)$preload['type'] : '',
                'urlFilter' => isset($preload['urlFilter']) ? (string)$preload['urlFilter'] : '',
            ];

            $table[] = $row;
        }

        return $table;
    }

    /**
     * Convert table format array back to Preloads settings JSON string.
     * Note: Preloads JSON is an array of objects (no selector key).
     * 
     * @param array $table The table format array.
     * @return string The JSON settings string.
     */
    public function tableToPreloadsJson(array $table): string
    {
        $data = [];

        foreach ($table as $row) {
            $config = [];

            // Handle fields
            if (isset($row['rel']) && $row['rel'] !== '' && $row['rel'] !== 'preload') {
                $config['rel'] = $row['rel'];
            }
            
            if (isset($row['fetchpriority']) && $row['fetchpriority'] !== '') {
                $config['fetchpriority'] = $row['fetchpriority'];
            }
            
            if (isset($row['as']) && $row['as'] !== '') {
                $config['as'] = $row['as'];
            }
            
            if (isset($row['href']) && $row['href'] !== '') {
                $config['href'] = $row['href'];
            }
            
            if (isset($row['type']) && $row['type'] !== '') {
                $config['type'] = $row['type'];
            }
            
            if (isset($row['urlFilter']) && $row['urlFilter'] !== '') {
                $config['urlFilter'] = $row['urlFilter'];
            }

            // Only add if at least href is present
            if (!empty($config['href'])) {
                $data[] = $config;
            }
        }

        // Return empty array if no data
        if (empty($data)) {
            return '[]';
        }

        return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }
}

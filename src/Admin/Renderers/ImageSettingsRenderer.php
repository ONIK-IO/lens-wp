<?php

namespace OnikImages\Admin\Renderers;

use OnikImages\Admin\AdvancedMode;
use OnikImages\SettingsConverter;

/**
 * Image Settings table renderer — selector-based image rewrite rules edited
 * via a jQuery-driven inline editor with an add/edit modal.
 */
class ImageSettingsRenderer
{
    public static function render(): void
    {
        $setting = get_option('onik_images_image_settings');
        $converter = new SettingsConverter();
        $tableData = $converter->jsonToTable($setting ?: '{}');
    
        // Ensure we have at least one row if empty, or just let it be empty
        ?>
        <style>
            #onik_images_image_settings_table {
                width: 100%;
                table-layout: fixed;
                /* Optional: helps with long content */
            }
    
            #onik_images_image_settings_table th,
            #onik_images_image_settings_table td {
                word-wrap: break-word;
                overflow-wrap: break-word;
            }
    
            /* Adjust column widths if necessary */
            .col-selector {
                width: 15%;
            }
    
            .col-widths {
                width: 10%;
            }
    
            /* ... other columns ... */
        </style>
        <div class="wrap">
            <table class="widefat fixed" id="onik_images_image_settings_table">
                <thead>
                    <tr>
                        <th style="width: 15%;">Selector</th>
                        <th style="width: 10%;">Widths</th>
                        <th style="width: 5%;">Quality</th>
                        <th style="width: 7%;">Loading</th>
                        <th style="width: 10%;">Sizes</th>
                        <th style="width: 8%;">Fetch Priority</th>
                        <th style="width: 7%;">Decoding</th>
                        <th style="width: 7%;">Format</th>
                        <th style="width: 8%;">SrcSwap</th>
                        <th style="width: 6%;">Set Width</th>
                        <th style="width: 6%;">Set Height</th>
                        <th style="width: 6%;">Lazy Load After</th>
                        <th style="width: 10%;">Actions</th>
                        <th style="width: 10%;">Actions</th>
                    </tr>
                </thead>
                <tbody id="onik_images_image_settings_tbody">
                    <?php if (empty($tableData)): ?>
                        <tr class="no-items">
                            <td colspan="12">No settings found.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($tableData as $index => $row): ?>
                            <tr>
                                <td class="col-selector">
                                    <span class="display-value"><?php echo esc_html($row['selector']); ?></span>
                                    <input type="hidden" name="onik_images_image_settings[<?php echo $index; ?>][selector]"
                                        value="<?php echo esc_attr($row['selector']); ?>" />
                                </td>
                                <td class="col-widths">
                                    <span class="display-value"><?php echo esc_html($row['widths']); ?></span>
                                    <input type="hidden" name="onik_images_image_settings[<?php echo $index; ?>][widths]"
                                        value="<?php echo esc_attr($row['widths']); ?>" />
                                </td>
                                <td class="col-quality">
                                    <span class="display-value"><?php echo esc_html($row['quality']); ?></span>
                                    <input type="hidden" name="onik_images_image_settings[<?php echo $index; ?>][quality]"
                                        value="<?php echo esc_attr($row['quality']); ?>" />
                                </td>
                                <td class="col-loading">
                                    <span class="display-value"><?php echo esc_html($row['loading']); ?></span>
                                    <input type="hidden" name="onik_images_image_settings[<?php echo $index; ?>][loading]"
                                        value="<?php echo esc_attr($row['loading']); ?>" />
                                </td>
                                <td class="col-sizes">
                                    <span class="display-value"><?php echo esc_html($row['sizes']); ?></span>
                                    <input type="hidden" name="onik_images_image_settings[<?php echo $index; ?>][sizes]"
                                        value="<?php echo esc_attr($row['sizes']); ?>" />
                                </td>
                                <td class="col-fetchpriority">
                                    <span class="display-value"><?php echo esc_html($row['fetchpriority']); ?></span>
                                    <input type="hidden" name="onik_images_image_settings[<?php echo $index; ?>][fetchpriority]"
                                        value="<?php echo esc_attr($row['fetchpriority']); ?>" />
                                </td>
                                <td class="col-decoding">
                                    <span class="display-value"><?php echo esc_html($row['decoding']); ?></span>
                                    <input type="hidden" name="onik_images_image_settings[<?php echo $index; ?>][decoding]"
                                        value="<?php echo esc_attr($row['decoding']); ?>" />
                                </td>
                                <td class="col-format">
                                    <span class="display-value"><?php echo esc_html($row['format']); ?></span>
                                    <input type="hidden" name="onik_images_image_settings[<?php echo $index; ?>][format]"
                                        value="<?php echo esc_attr($row['format']); ?>" />
                                </td>
                                <td class="col-srcSwap">
                                    <span class="display-value"><?php echo esc_html($row['srcSwap']); ?></span>
                                    <input type="hidden" name="onik_images_image_settings[<?php echo $index; ?>][srcSwap]"
                                        value="<?php echo esc_attr($row['srcSwap']); ?>" />
                                </td>
                                <td class="col-setWidth">
                                    <span class="display-value"><?php echo esc_html($row['setWidth']); ?></span>
                                    <input type="hidden" name="onik_images_image_settings[<?php echo $index; ?>][setWidth]"
                                        value="<?php echo esc_attr($row['setWidth']); ?>" />
                                </td>
                                <td class="col-setHeight">
                                    <span class="display-value"><?php echo esc_html($row['setHeight']); ?></span>
                                    <input type="hidden" name="onik_images_image_settings[<?php echo $index; ?>][setHeight]"
                                        value="<?php echo esc_attr($row['setHeight']); ?>" />
                                </td>
                                <td class="col-lazyLoadAfter">
                                    <span class="display-value"><?php echo esc_html($row['lazyLoadAfter']); ?></span>
                                    <input type="hidden" name="onik_images_image_settings[<?php echo $index; ?>][lazyLoadAfter]"
                                        value="<?php echo esc_attr($row['lazyLoadAfter']); ?>" />
                                </td>
                                <td>
                                    <div style="display:flex; gap:5px;">
                                        <button type="button" class="button edit-row" title="Edit">✎</button>
                                        <button type="button" class="button move-up" title="Move Up">↑</button>
                                        <button type="button" class="button move-down" title="Move Down">↓</button>
                                        <button type="button" class="button delete-row" title="Delete">×</button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
            <p>
                <button type="button" class="button" id="add-row">Add Row</button>
            </p>
        </div>
    
        <div id="onik-image-settings-modal"
            style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:100000;">
            <div
                style="background:#fff; width:800px; margin:100px auto; padding:20px; border-radius:5px; box-shadow:0 0 10px rgba(0,0,0,0.3); max-height: 80vh; overflow-y: auto;">
                <h2 id="onik-modal-title" style="margin-top:0;">Edit Image Setting</h2>
                <div id="onik-modal-form">
                    <input type="hidden" id="onik-modal-row-index" value="">
                    <table class="form-table">
                        <tr>
                            <th><label for="onik-modal-selector">Selector</label></th>
                            <td>
                                <input type="text" id="onik-modal-selector" class="regular-text" style="width:100%;">
                                <p class="description">CSS selector to target images.</p>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="onik-modal-widths">Widths</label></th>
                            <td>
                                <input type="text" id="onik-modal-widths" class="regular-text" style="width:100%;"
                                    placeholder="e.g. 300, 600, 900">
                                <p class="description">Array of integers between 1-10000 representing image widths in pixels. If
                                    not provided, the width will be extracted from the image element's width attribute.</p>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="onik-modal-quality">Quality</label></th>
                            <td>
                                <input type="number" id="onik-modal-quality" class="small-text" min="1" max="100">
                                <p class="description">Integer between 1-100 for image quality percentage (default: 80).</p>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="onik-modal-loading">Loading</label></th>
                            <td>
                                <select id="onik-modal-loading">
                                    <option value="">Default</option>
                                    <option value="lazy">Lazy</option>
                                    <option value="eager">Eager</option>
                                </select>
                                <p class="description">"lazy", "eager", or empty for browser default.</p>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="onik-modal-sizes">Sizes</label></th>
                            <td>
                                <input type="text" id="onik-modal-sizes" class="regular-text" style="width:100%;">
                                <p class="description">String for CSS sizes attribute value.</p>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="onik-modal-fetchpriority">Fetch Priority</label></th>
                            <td>
                                <select id="onik-modal-fetchpriority">
                                    <option value="">Default</option>
                                    <option value="high">High</option>
                                    <option value="low">Low</option>
                                    <option value="auto">Auto</option>
                                </select>
                                <p class="description">"high", "low", "auto", or empty for no attribute.</p>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="onik-modal-decoding">Decoding</label></th>
                            <td>
                                <select id="onik-modal-decoding">
                                    <option value="">Default</option>
                                    <option value="sync">Sync</option>
                                    <option value="async">Async</option>
                                    <option value="auto">Auto</option>
                                </select>
                                <p class="description">"sync", "async", "auto", or empty for no attribute.</p>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="onik-modal-format">Format</label></th>
                            <td>
                                <select id="onik-modal-format">
                                    <option value="">Default</option>
                                    <option value="auto">Auto</option>
                                    <option value="jpg">JPG</option>
                                    <option value="jpeg">JPEG</option>
                                    <option value="png">PNG</option>
                                    <option value="webp">WebP</option>
                                    <option value="avif">AVIF</option>
                                </select>
                                <p class="description">"auto", "jpg", "jpeg", "png", "gif", "avif", "webp", or empty for no format
                                    specification (default: "auto").</p>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="onik-modal-srcSwap">SrcSwap</label></th>
                            <td>
                                <select id="onik-modal-srcSwap">
                                    <option value="">Default</option>
                                    <option value="srcSet">srcSet</option>
                                    <option value="src">src</option>
                                    <option value="srcAndSrcSet">srcAndSrcSet</option>
                                    <option value="InlineStyleUrl">InlineStyleUrl</option>
                                    <option value="ExternalCssUrl">ExternalCssUrl</option>
                                </select>
                                <p class="description">Controls which image attributes to swap (default: "srcSet").
                                    Use "InlineStyleUrl" for background-image URLs in inline &lt;style&gt; blocks, and
                                    "ExternalCssUrl" for background-image URLs in enqueued stylesheet files. The CSS
                                    Backgrounds tab finds candidates for the latter.</p>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="onik-modal-setWidth">Set Width</label></th>
                            <td>
                                <input type="number" id="onik-modal-setWidth" class="small-text" min="1">
                                <p class="description">Fixed width attribute to add to the image tag (null or positive integer).
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="onik-modal-setHeight">Set Height</label></th>
                            <td>
                                <input type="number" id="onik-modal-setHeight" class="small-text" min="1">
                                <p class="description">Fixed height attribute to add to the image tag (null or positive
                                    integer).</p>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="onik-modal-lazyLoadAfter">Lazy Load After</label></th>
                            <td>
                                <input type="number" id="onik-modal-lazyLoadAfter" class="small-text" min="0">
                                <p class="description">Number of images to process before enabling lazy loading (default: 0).
                                </p>
                            </td>
                        </tr>
                    </table>
                    <p class="submit" style="text-align:right; margin-top:20px;">
                        <button type="button" class="button" id="onik-modal-cancel">Cancel</button>
                        <button type="button" class="button button-primary" id="onik-modal-save">Save</button>
                    </p>
                </div>
            </div>
        </div>
    
        <?php if (AdvancedMode::isEnabled()): ?>
            <div style="margin-top: 10px;">
                <a href="#" id="onik-debug-json-link" style="text-decoration: none; border-bottom: 1px dashed #0073aa;">Debug
                    JSON</a>
                <div id="onik-debug-json-popup"
                    style="display: none; position: absolute; background: #fff; border: 1px solid #ccc; padding: 10px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); z-index: 9999; max-width: 600px; max-height: 400px; overflow: auto;">
                    <pre style="margin: 0; font-family: monospace; white-space: pre-wrap;"></pre>
                </div>
            </div>
    
            <div style="margin-top: 20px; border-top: 1px solid #ccc; padding-top: 10px;">
                <h3>Import / Export Settings</h3>
                <p>Paste the JSON settings string below to import settings. This will replace the current table contents.</p>
                <textarea id="onik-import-settings-json" rows="5" style="width: 100%; font-family: monospace;"><?php
                // Format current settings as pretty JSON for the textarea
                if ($setting && $setting !== '{}') {
                    $decoded = json_decode($setting, true);
                    if ($decoded !== null) {
                        echo esc_textarea(json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
                    }
                }
                ?></textarea>
                <p><button type="button" class="button" id="onik-import-settings-btn">Import</button></p>
            </div>
        <?php endif; ?>
    
        <script>
            jQuery(document).ready(function ($) {
                var $table = $('#onik_images_image_settings_table tbody');
                var $modal = $('#onik-image-settings-modal');
                var $modalForm = $('#onik-modal-form');
                var $modalTitle = $('#onik-modal-title');
                var $rowIndexInput = $('#onik-modal-row-index');
    
                // Move modal to body to avoid z-index/positioning issues
                $('body').append($modal);
    
                function updateRowIndices() {
                    $table.find('tr').each(function (index) {
                        $(this).find('input[type="hidden"]').each(function () {
                            var name = $(this).attr('name');
                            if (name) {
                                var newName = name.replace(/\[\d+\]/, '[' + index + ']');
                                $(this).attr('name', newName);
                            }
                        });
                    });
                }
    
                function openModal(row) {
                    // Reset form fields manually since it's a div now
                    $modalForm.find('input[type="text"], input[type="number"]').val('');
                    $modalForm.find('select').val('');
                    $rowIndexInput.val('');
                    if (row) {
                        $modalTitle.text('Edit Image Setting');
                        var index = $table.find('tr').index(row);
                        $rowIndexInput.val(index);
    
                        // Populate form
                        $('#onik-modal-selector').val(row.find('.col-selector input').val());
                        $('#onik-modal-widths').val(row.find('.col-widths input').val());
                        $('#onik-modal-quality').val(row.find('.col-quality input').val());
                        $('#onik-modal-loading').val(row.find('.col-loading input').val());
                        $('#onik-modal-sizes').val(row.find('.col-sizes input').val());
                        $('#onik-modal-fetchpriority').val(row.find('.col-fetchpriority input').val());
                        $('#onik-modal-decoding').val(row.find('.col-decoding input').val());
                        $('#onik-modal-format').val(row.find('.col-format input').val());
                        $('#onik-modal-srcSwap').val(row.find('.col-srcSwap input').val());
                        $('#onik-modal-setWidth').val(row.find('.col-setWidth input').val());
                        $('#onik-modal-setHeight').val(row.find('.col-setHeight input').val());
                        $('#onik-modal-lazyLoadAfter').val(row.find('.col-lazyLoadAfter input').val());
                    } else {
                        $modalTitle.text('Add Image Setting');
                        $rowIndexInput.val('');
                    }
                    $modal.show();
                }
    
                function closeModal() {
                    $modal.hide();
                }
    
                function renderRow(index, data) {
                    var rowHtml = '<tr>';
                    $.each(data, function (key, value) {
                        var displayValue = value;
                        if (key === 'widths' && Array.isArray(value)) {
                            displayValue = value.join(', ');
                        }
                        rowHtml += '<td class="col-' + key + '"><span class="display-value">' + $('<div>').text(displayValue).html() + '</span><input type="hidden" name="onik_images_image_settings[' + index + '][' + key + ']" value="' + $('<div>').text(displayValue).html() + '" /></td>';
                    });
                    rowHtml += '<td><div style="display:flex; gap:5px;"><button type="button" class="button edit-row" title="Edit">✎</button><button type="button" class="button move-up" title="Move Up">↑</button><button type="button" class="button move-down" title="Move Down">↓</button><button type="button" class="button delete-row" title="Delete">×</button></div></td></tr>';
                    return rowHtml;
                }
    
                $('#add-row').on('click', function () {
                    openModal(null);
                });
    
                $table.on('click', '.edit-row', function (e) {
                    e.preventDefault();
                    console.log('Edit row clicked');
                    openModal($(this).closest('tr'));
                });
    
                $('#onik-modal-cancel').on('click', closeModal);
    
                $('#onik-modal-save').on('click', function () {
                    var index = $rowIndexInput.val();
                    var data = {
                        selector: $('#onik-modal-selector').val(),
                        widths: $('#onik-modal-widths').val(),
                        quality: $('#onik-modal-quality').val(),
                        loading: $('#onik-modal-loading').val(),
                        sizes: $('#onik-modal-sizes').val(),
                        fetchpriority: $('#onik-modal-fetchpriority').val(),
                        decoding: $('#onik-modal-decoding').val(),
                        format: $('#onik-modal-format').val(),
                        srcSwap: $('#onik-modal-srcSwap').val(),
                        setWidth: $('#onik-modal-setWidth').val(),
                        setHeight: $('#onik-modal-setHeight').val(),
                        lazyLoadAfter: $('#onik-modal-lazyLoadAfter').val()
                    };
    
                    if (!data.selector) {
                        alert('Selector is required.');
                        return;
                    }
    
                    if (index === '') {
                        // Add new row
                        if ($table.find('.no-items').length) {
                            $table.empty();
                        }
                        index = $table.find('tr').length;
                        $table.append(renderRow(index, data));
                    } else {
                        // Update existing row
                        var $row = $table.find('tr').eq(index);
                        $.each(data, function (key, value) {
                            var $cell = $row.find('.col-' + key);
                            $cell.find('.display-value').text(value);
                            $cell.find('input').val(value);
                        });
                    }
    
                    closeModal();
                });
    
                $table.on('click', '.delete-row', function () {
                    $(this).closest('tr').remove();
                    if ($table.find('tr').length === 0) {
                        $table.append('<tr class="no-items"><td colspan="12">No settings found.</td></tr>');
                    } else {
                        updateRowIndices();
                    }
                });
    
                $table.on('click', '.move-up', function () {
                    var $row = $(this).closest('tr');
                    if ($row.prev().length) {
                        $row.insertBefore($row.prev());
                        updateRowIndices();
                    }
                });
    
                $table.on('click', '.move-down', function () {
                    var $row = $(this).closest('tr');
                    if ($row.next().length) {
                        $row.insertAfter($row.next());
                        updateRowIndices();
                    }
                });
    
                // Import Settings Logic
                $('#onik-import-settings-btn').on('click', function () {
                    var jsonStr = $('#onik-import-settings-json').val();
                    if (!jsonStr) {
                        alert('Please paste JSON settings to import.');
                        return;
                    }
    
                    try {
                        var importedData = JSON.parse(jsonStr);
    
                        // Validate structure (basic check)
                        if (typeof importedData !== 'object' || importedData === null) {
                            throw new Error('Invalid JSON format');
                        }
    
                        // Clear existing table
                        $table.empty();
    
                        var index = 0;
                        // Handle both array format (from settings) and object format (from debug)
                        // Debug format: { "selector": { config... }, ... }
                        // Settings format might be different, but let's assume we want to support the debug format primarily as requested
    
                        // Check if it's the debug format (object with selectors as keys)
                        // or potentially an array of setting objects
    
                        $.each(importedData, function (key, config) {
                            // If key is a selector (string) and config is an object
                            var rowData = {
                                selector: key,
                                widths: '',
                                quality: '',
                                loading: '',
                                sizes: '',
                                fetchpriority: '',
                                decoding: '',
                                format: '',
                                srcSwap: '',
                                setWidth: '',
                                setHeight: '',
                                lazyLoadAfter: ''
                            };
    
                            // Map config values to rowData
                            if (config.widths && Array.isArray(config.widths)) {
                                rowData.widths = config.widths.join(', ');
                            }
    
                            var fields = ['quality', 'loading', 'sizes', 'fetchpriority', 'decoding', 'format', 'srcSwap', 'setWidth', 'setHeight', 'lazyLoadAfter'];
                            fields.forEach(function (field) {
                                if (config[field] !== undefined && config[field] !== null) {
                                    rowData[field] = config[field];
                                }
                            });
    
                            $table.append(renderRow(index, rowData));
                            index++;
                        });
    
                        if (index === 0) {
                            $table.append('<tr class="no-items"><td colspan="12">No settings found.</td></tr>');
                        }
    
                        // Clear the textarea
                        $('#onik-import-settings-json').val('');
                        alert('Settings imported successfully! Click "Save Settings" to persist changes.');
    
                    } catch (e) {
                        alert('Error importing settings: ' + e.message);
                        console.error(e);
                    }
                });
    
                // Debug JSON Popup Logic
                var $debugLink = $('#onik-debug-json-link');
                var $popup = $('#onik-debug-json-popup');
                var $pre = $popup.find('pre');
    
                $debugLink.on('mouseenter', function (e) {
                    var data = {};
    
                    $table.find('tr').each(function () {
                        var $row = $(this);
                        if ($row.hasClass('no-items')) return;
    
                        var selector = $row.find('input[name*="[selector]"]').val();
                        if (!selector) return;
    
                        var config = {};
    
                        // Widths
                        var widthsStr = $row.find('input[name*="[widths]"]').val();
                        if (widthsStr) {
                            var widths = widthsStr.split(',').map(function (w) { return parseInt(w.trim(), 10); }).filter(function (w) { return !isNaN(w) && w > 0; });
                            if (widths.length > 0) {
                                config.widths = widths;
                            }
                        }
    
                        // Other fields
                        var fields = ['quality', 'loading', 'sizes', 'fetchpriority', 'decoding', 'format', 'srcSwap', 'setWidth', 'setHeight', 'lazyLoadAfter'];
                        fields.forEach(function (field) {
                            var $input = $row.find('[name*="[' + field + ']"]');
                            var val = $input.val();
                            if (val !== '') {
                                if (['quality', 'setWidth', 'setHeight', 'lazyLoadAfter'].indexOf(field) !== -1) {
                                    val = parseInt(val, 10);
                                }
                                config[field] = val;
                            }
                        });
    
                        data[selector] = config;
                    });
    
                    $pre.text(JSON.stringify(data, null, 4));
    
                    // Position popup near the link
                    var offset = $debugLink.offset();
                    $popup.css({
                        top: offset.top + 20,
                        left: offset.left
                    }).show();
                });
    
                $debugLink.on('mouseleave', function () {
                    $popup.hide();
                });
            });
        </script>
        <?php
    }
}

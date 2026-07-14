<?php

namespace OnikImages\Admin\Renderers;

use OnikImages\Admin\AdvancedMode;
use OnikImages\SettingsConverter;

/**
 * YouTube Settings table renderer — per-selector lite-yt-embed config.
 */
class YoutubeSettingsRenderer
{
    public static function render(): void
    {
        $setting = get_option('onik_images_youtube_settings');
        $converter = new SettingsConverter();
        $tableData = $converter->youtubeJsonToTable($setting ?: '{}');
    
        ?>
        <style>
            #onik_images_youtube_settings_table {
                width: 100%;
                table-layout: fixed;
            }
    
            #onik_images_youtube_settings_table th,
            #onik_images_youtube_settings_table td {
                word-wrap: break-word;
                overflow-wrap: break-word;
            }
    
            .col-selector {
                width: 20%;
            }
    
            .col-playlabel {
                width: 15%;
            }
    
            .col-title {
                width: 15%;
            }
    
            .col-params {
                width: 20%;
            }
    
            .col-js_api {
                width: 5%;
            }
    
            .col-style {
                width: 15%;
            }
    
            .col-actions {
                width: 10%;
            }
        </style>
        <div class="wrap">
            <table class="widefat fixed" id="onik_images_youtube_settings_table">
                <thead>
                    <tr>
                        <th class="col-selector">Selector</th>
                        <th class="col-playlabel">Play Label</th>
                        <th class="col-title">Title</th>
                        <th class="col-params">Params</th>
                        <th class="col-js_api">JS API</th>
                        <th class="col-style">Style</th>
                        <th class="col-actions">Actions</th>
                    </tr>
                </thead>
                <tbody id="onik_images_youtube_settings_tbody">
                    <?php if (empty($tableData)): ?>
                        <tr class="no-items">
                            <td colspan="7">No settings found.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($tableData as $index => $row): ?>
                            <tr>
                                <td class="col-selector">
                                    <span class="display-value"><?php echo esc_html($row['selector']); ?></span>
                                    <input type="hidden" name="onik_images_youtube_settings[<?php echo $index; ?>][selector]"
                                        value="<?php echo esc_attr($row['selector']); ?>" />
                                </td>
                                <td class="col-playlabel">
                                    <span class="display-value"><?php echo esc_html($row['playlabel']); ?></span>
                                    <input type="hidden" name="onik_images_youtube_settings[<?php echo $index; ?>][playlabel]"
                                        value="<?php echo esc_attr($row['playlabel']); ?>" />
                                </td>
                                <td class="col-title">
                                    <span class="display-value"><?php echo esc_html($row['title']); ?></span>
                                    <input type="hidden" name="onik_images_youtube_settings[<?php echo $index; ?>][title]"
                                        value="<?php echo esc_attr($row['title']); ?>" />
                                </td>
                                <td class="col-params">
                                    <span class="display-value"><?php echo esc_html($row['params']); ?></span>
                                    <input type="hidden" name="onik_images_youtube_settings[<?php echo $index; ?>][params]"
                                        value="<?php echo esc_attr($row['params']); ?>" />
                                </td>
                                <td class="col-js_api">
                                    <span class="display-value"><?php echo $row['js_api'] ? 'Yes' : 'No'; ?></span>
                                    <input type="hidden" name="onik_images_youtube_settings[<?php echo $index; ?>][js_api]"
                                        value="<?php echo $row['js_api'] ? '1' : '0'; ?>" />
                                </td>
                                <td class="col-style">
                                    <span class="display-value"><?php echo esc_html($row['style']); ?></span>
                                    <input type="hidden" name="onik_images_youtube_settings[<?php echo $index; ?>][style]"
                                        value="<?php echo esc_attr($row['style']); ?>" />
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
                <button type="button" class="button" id="add-youtube-row">Add Row</button>
            </p>
        </div>
    
        <div id="onik-youtube-settings-modal"
            style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:100000;">
            <div
                style="background:#fff; width:800px; margin:100px auto; padding:20px; border-radius:5px; box-shadow:0 0 10px rgba(0,0,0,0.3); max-height: 80vh; overflow-y: auto;">
                <h2 id="onik-youtube-modal-title" style="margin-top:0;">Edit YouTube Setting</h2>
                <div id="onik-youtube-modal-form">
                    <input type="hidden" id="onik-youtube-modal-row-index" value="">
                    <table class="form-table">
                        <tr>
                            <th><label for="onik-youtube-modal-selector">Selector</label></th>
                            <td>
                                <input type="text" id="onik-youtube-modal-selector" class="regular-text" style="width:100%;">
                                <p class="description">CSS selector to target YouTube videos (e.g.,
                                    <code>iframe[src*='youtube']</code>).
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="onik-youtube-modal-playlabel">Play Label</label></th>
                            <td>
                                <input type="text" id="onik-youtube-modal-playlabel" class="regular-text" style="width:100%;"
                                    placeholder="Play: {video_id}">
                                <p class="description">String for the play button label (default: "Play: {video_id}").</p>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="onik-youtube-modal-title">Title</label></th>
                            <td>
                                <input type="text" id="onik-youtube-modal-title" class="regular-text" style="width:100%;">
                                <p class="description">String for the video title attribute.</p>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="onik-youtube-modal-params">Params</label></th>
                            <td>
                                <input type="text" id="onik-youtube-modal-params" class="regular-text" style="width:100%;"
                                    placeholder="controls=1&autoplay=0">
                                <p class="description">String for YouTube player parameters (e.g.,
                                    "controls=0&start=10&end=30&modestbranding=2&rel=0").</p>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="onik-youtube-modal-js_api">JS API</label></th>
                            <td>
                                <select id="onik-youtube-modal-js_api">
                                    <option value="0">No</option>
                                    <option value="1">Yes</option>
                                </select>
                                <p class="description">Enable YouTube IFrame Player API (default: false).</p>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="onik-youtube-modal-style">Style</label></th>
                            <td>
                                <input type="text" id="onik-youtube-modal-style" class="regular-text" style="width:100%;">
                                <p class="description">String containing CSS styles to append to the lite-youtube element's
                                    style attribute.</p>
                            </td>
                        </tr>
                    </table>
                    <p class="submit" style="text-align:right; margin-top:20px;">
                        <button type="button" class="button" id="onik-youtube-modal-cancel">Cancel</button>
                        <button type="button" class="button button-primary" id="onik-youtube-modal-save">Save</button>
                    </p>
                </div>
            </div>
        </div>
    
        <?php if (AdvancedMode::isEnabled()): ?>
            <div style="margin-top: 10px;">
                <a href="#" id="onik-youtube-debug-json-link"
                    style="text-decoration: none; border-bottom: 1px dashed #0073aa;">Debug JSON</a>
                <div id="onik-youtube-debug-json-popup"
                    style="display: none; position: absolute; background: #fff; border: 1px solid #ccc; padding: 10px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); z-index: 9999; max-width: 600px; max-height: 400px; overflow: auto;">
                    <pre style="margin: 0; font-family: monospace; white-space: pre-wrap;"></pre>
                </div>
            </div>
        <?php endif; ?>
    
        <script>
            jQuery(document).ready(function ($) {
                var $table = $('#onik_images_youtube_settings_table tbody');
                var $modal = $('#onik-youtube-settings-modal');
                var $modalForm = $('#onik-youtube-modal-form');
                var $modalTitle = $('#onik-youtube-modal-title');
                var $rowIndexInput = $('#onik-youtube-modal-row-index');
    
                // Move modal to body
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
                    $modalForm.find('input[type="text"]').val('');
                    $modalForm.find('select').val('0');
                    $rowIndexInput.val('');
                    if (row) {
                        $modalTitle.text('Edit YouTube Setting');
                        var index = $table.find('tr').index(row);
                        $rowIndexInput.val(index);
    
                        $('#onik-youtube-modal-selector').val(row.find('.col-selector input').val());
                        $('#onik-youtube-modal-playlabel').val(row.find('.col-playlabel input').val());
                        $('#onik-youtube-modal-title').val(row.find('.col-title input').val());
                        $('#onik-youtube-modal-params').val(row.find('.col-params input').val());
                        $('#onik-youtube-modal-js_api').val(row.find('.col-js_api input').val());
                        $('#onik-youtube-modal-style').val(row.find('.col-style input').val());
                    } else {
                        $modalTitle.text('Add YouTube Setting');
                        $rowIndexInput.val('');
                    }
                    $modal.show();
                }
    
                function closeModal() {
                    $modal.hide();
                }
    
                $('#add-youtube-row').on('click', function () {
                    openModal(null);
                });
    
                $table.on('click', '.edit-row', function (e) {
                    e.preventDefault();
                    openModal($(this).closest('tr'));
                });
    
                $('#onik-youtube-modal-cancel').on('click', closeModal);
    
                $('#onik-youtube-modal-save').on('click', function () {
                    var index = $rowIndexInput.val();
                    var data = {
                        selector: $('#onik-youtube-modal-selector').val(),
                        playlabel: $('#onik-youtube-modal-playlabel').val(),
                        title: $('#onik-youtube-modal-title').val(),
                        params: $('#onik-youtube-modal-params').val(),
                        js_api: $('#onik-youtube-modal-js_api').val(),
                        style: $('#onik-youtube-modal-style').val()
                    };
    
                    if (!data.selector) {
                        alert('Selector is required.');
                        return;
                    }
    
                    if (index === '') {
                        if ($table.find('.no-items').length) {
                            $table.empty();
                        }
                        index = $table.find('tr').length;
                        var rowHtml = '<tr>';
    
                        // Selector
                        rowHtml += '<td class="col-selector"><span class="display-value">' + $('<div>').text(data.selector).html() + '</span><input type="hidden" name="onik_images_youtube_settings[' + index + '][selector]" value="' + $('<div>').text(data.selector).html() + '" /></td>';
    
                        // 
                        rowHtml += '<td class="col-playlabel"><span class="display-value">' + $('<div>').text(data.playlabel).html() + '</span><input type="hidden" name="onik_images_youtube_settings[' + index + '][playlabel]" value="' + $('<div>').text(data.playlabel).html() + '" /></td>';
    
                        // Title
                        rowHtml += '<td class="col-title"><span class="display-value">' + $('<div>').text(data.title).html() + '</span><input type="hidden" name="onik_images_youtube_settings[' + index + '][title]" value="' + $('<div>').text(data.title).html() + '" /></td>';
    
                        // Params
                        rowHtml += '<td class="col-params"><span class="display-value">' + $('<div>').text(data.params).html() + '</span><input type="hidden" name="onik_images_youtube_settings[' + index + '][params]" value="' + $('<div>').text(data.params).html() + '" /></td>';
    
                        // JS API
                        var jsApiDisplay = data.js_api == '1' ? 'Yes' : 'No';
                        rowHtml += '<td class="col-js_api"><span class="display-value">' + jsApiDisplay + '</span><input type="hidden" name="onik_images_youtube_settings[' + index + '][js_api]" value="' + data.js_api + '" /></td>';
    
                        // Style
                        rowHtml += '<td class="col-style"><span class="display-value">' + $('<div>').text(data.style).html() + '</span><input type="hidden" name="onik_images_youtube_settings[' + index + '][style]" value="' + $('<div>').text(data.style).html() + '" /></td>';
    
                        rowHtml += '<td><div style="display:flex; gap:5px;"><button type="button" class="button edit-row" title="Edit">✎</button><button type="button" class="button move-up" title="Move Up">↑</button><button type="button" class="button move-down" title="Move Down">↓</button><button type="button" class="button delete-row" title="Delete">×</button></div></td></tr>';
                        $table.append(rowHtml);
                    } else {
                        var $row = $table.find('tr').eq(index);
    
                        $row.find('.col-selector .display-value').text(data.selector);
                        $row.find('.col-selector input').val(data.selector);
    
                        $row.find('.col-playlabel .display-value').text(data.playlabel);
                        $row.find('.col-playlabel input').val(data.playlabel);
    
                        $row.find('.col-title .display-value').text(data.title);
                        $row.find('.col-title input').val(data.title);
    
                        $row.find('.col-params .display-value').text(data.params);
                        $row.find('.col-params input').val(data.params);
    
                        $row.find('.col-js_api .display-value').text(data.js_api == '1' ? 'Yes' : 'No');
                        $row.find('.col-js_api input').val(data.js_api);
    
                        $row.find('.col-style .display-value').text(data.style);
                        $row.find('.col-style input').val(data.style);
                    }
    
                    closeModal();
                });
    
                $table.on('click', '.delete-row', function () {
                    $(this).closest('tr').remove();
                    if ($table.find('tr').length === 0) {
                        $table.append('<tr class="no-items"><td colspan="7">No settings found.</td></tr>');
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
    
                // Debug JSON Popup Logic
                var $debugLink = $('#onik-youtube-debug-json-link');
                var $popup = $('#onik-youtube-debug-json-popup');
                var $pre = $popup.find('pre');
    
                $debugLink.on('mouseenter', function (e) {
                    var data = {};
    
                    $table.find('tr').each(function () {
                        var $row = $(this);
                        if ($row.hasClass('no-items')) return;
    
                        var selector = $row.find('input[name*="[selector]"]').val();
                        if (!selector) return;
    
                        var config = {};
    
                        var playlabel = $row.find('input[name*="[playlabel]"]').val();
                        if (playlabel) config.playlabel = playlabel;
    
                        var title = $row.find('input[name*="[title]"]').val();
                        if (title) config.title = title;
    
                        var params = $row.find('input[name*="[params]"]').val();
                        if (params) config.params = params;
    
                        var js_api = $row.find('input[name*="[js_api]"]').val();
                        if (js_api == '1') config.js_api = true;
    
                        var style = $row.find('input[name*="[style]"]').val();
                        if (style) config.style = style;
    
                        data[selector] = config;
                    });
    
                    $pre.text(JSON.stringify(data, null, 4));
    
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

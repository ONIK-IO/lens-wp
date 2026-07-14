<?php

namespace OnikImages\Admin\Renderers;

use OnikImages\Admin\AdvancedMode;
use OnikImages\SettingsConverter;

/**
 * Preloads renderer — table editor for <link rel="preload"> hints with
 * optional per-page urlFilter regex.
 */
class PreloadsRenderer
{
    public static function render(): void
    {
        $setting = get_option('onik_images_preloads');
        $converter = new SettingsConverter();
        $tableData = $converter->preloadsJsonToTable($setting ?: '[]');
    
        ?>
        <style>
            #onik_images_preloads_table {
                width: 100%;
                table-layout: fixed;
            }
    
            #onik_images_preloads_table th,
            #onik_images_preloads_table td {
                word-wrap: break-word;
                overflow-wrap: break-word;
            }
    
            .col-rel {
                width: 10%;
            }
    
            .col-fetchpriority {
                width: 10%;
            }
    
            .col-as {
                width: 10%;
            }
    
            .col-href {
                width: 30%;
            }
    
            .col-type {
                width: 15%;
            }
    
            .col-urlFilter {
                width: 15%;
            }
    
            .col-actions {
                width: 10%;
            }
        </style>
        <div class="wrap">
            <table class="widefat fixed" id="onik_images_preloads_table">
                <thead>
                    <tr>
                        <th class="col-rel">Rel</th>
                        <th class="col-fetchpriority">Fetch Priority</th>
                        <th class="col-as">As</th>
                        <th class="col-href">Href</th>
                        <th class="col-type">Type</th>
                        <th class="col-urlFilter">URL Filter</th>
                        <th class="col-actions">Actions</th>
                    </tr>
                </thead>
                <tbody id="onik_images_preloads_tbody">
                    <?php if (empty($tableData)): ?>
                        <tr class="no-items">
                            <td colspan="7">No preloads found.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($tableData as $index => $row): ?>
                            <tr>
                                <td class="col-rel">
                                    <span class="display-value"><?php echo esc_html($row['rel']); ?></span>
                                    <input type="hidden" name="onik_images_preloads[<?php echo $index; ?>][rel]"
                                        value="<?php echo esc_attr($row['rel']); ?>" />
                                </td>
                                <td class="col-fetchpriority">
                                    <span class="display-value"><?php echo esc_html($row['fetchpriority']); ?></span>
                                    <input type="hidden" name="onik_images_preloads[<?php echo $index; ?>][fetchpriority]"
                                        value="<?php echo esc_attr($row['fetchpriority']); ?>" />
                                </td>
                                <td class="col-as">
                                    <span class="display-value"><?php echo esc_html($row['as']); ?></span>
                                    <input type="hidden" name="onik_images_preloads[<?php echo $index; ?>][as]"
                                        value="<?php echo esc_attr($row['as']); ?>" />
                                </td>
                                <td class="col-href">
                                    <span class="display-value"><?php echo esc_html($row['href']); ?></span>
                                    <input type="hidden" name="onik_images_preloads[<?php echo $index; ?>][href]"
                                        value="<?php echo esc_attr($row['href']); ?>" />
                                </td>
                                <td class="col-type">
                                    <span class="display-value"><?php echo esc_html($row['type']); ?></span>
                                    <input type="hidden" name="onik_images_preloads[<?php echo $index; ?>][type]"
                                        value="<?php echo esc_attr($row['type']); ?>" />
                                </td>
                                <td class="col-urlFilter">
                                    <span class="display-value"><?php echo esc_html($row['urlFilter']); ?></span>
                                    <input type="hidden" name="onik_images_preloads[<?php echo $index; ?>][urlFilter]"
                                        value="<?php echo esc_attr($row['urlFilter']); ?>" />
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
                <button type="button" class="button" id="add-preload-row">Add Row</button>
            </p>
        </div>
    
        <div id="onik-preloads-modal"
            style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:100000;">
            <div
                style="background:#fff; width:800px; margin:100px auto; padding:20px; border-radius:5px; box-shadow:0 0 10px rgba(0,0,0,0.3); max-height: 80vh; overflow-y: auto;">
                <h2 id="onik-preload-modal-title" style="margin-top:0;">Edit Preload</h2>
                <div id="onik-preload-modal-form">
                    <input type="hidden" id="onik-preload-modal-row-index" value="">
                    <table class="form-table">
                        <tr>
                            <th><label for="onik-preload-modal-rel">Rel</label></th>
                            <td>
                                <select id="onik-preload-modal-rel">
                                    <option value="preload">preload</option>
                                    <option value="prefetch">prefetch</option>
                                    <option value="dns-prefetch">dns-prefetch</option>
                                    <option value="preconnect">preconnect</option>
                                </select>
                                <p class="description">Link relationship type (default: "preload").</p>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="onik-preload-modal-fetchpriority">Fetch Priority</label></th>
                            <td>
                                <select id="onik-preload-modal-fetchpriority">
                                    <option value="">Default</option>
                                    <option value="high">High</option>
                                    <option value="low">Low</option>
                                </select>
                                <p class="description">Fetch priority for the resource ("high" or "low").</p>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="onik-preload-modal-as">As</label></th>
                            <td>
                                <select id="onik-preload-modal-as">
                                    <option value="">Select type...</option>
                                    <option value="image">image</option>
                                    <option value="script">script</option>
                                    <option value="style">style</option>
                                    <option value="font">font</option>
                                    <option value="fetch">fetch</option>
                                    <option value="document">document</option>
                                    <option value="video">video</option>
                                    <option value="audio">audio</option>
                                </select>
                                <p class="description">Type of resource being loaded (required for preload).</p>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="onik-preload-modal-href">Href</label></th>
                            <td>
                                <input type="text" id="onik-preload-modal-href" class="regular-text" style="width:100%;"
                                    placeholder="https://example.com/resource">
                                <p class="description">URL of the resource to preload (required).</p>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="onik-preload-modal-type">Type</label></th>
                            <td>
                                <input type="text" id="onik-preload-modal-type" class="regular-text" style="width:100%;"
                                    placeholder="image/jpeg">
                                <p class="description">MIME type of the resource (e.g., "image/jpeg", "text/css").</p>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="onik-preload-modal-urlFilter">URL Filter</label></th>
                            <td>
                                <input type="text" id="onik-preload-modal-urlFilter" class="regular-text" style="width:100%;"
                                    placeholder="#/blog/.*#">
                                <p class="description">Optional regex pattern to only inject preloads on specific pages (e.g.,
                                    "#/blog/.*#").</p>
                            </td>
                        </tr>
                    </table>
                    <p class="submit" style="text-align:right; margin-top:20px;">
                        <button type="button" class="button" id="onik-preload-modal-cancel">Cancel</button>
                        <button type="button" class="button button-primary" id="onik-preload-modal-save">Save</button>
                    </p>
                </div>
            </div>
        </div>
    
        <?php if (AdvancedMode::isEnabled()): ?>
            <div style="margin-top: 10px;">
                <a href="#" id="onik-preload-debug-json-link"
                    style="text-decoration: none; border-bottom: 1px dashed #0073aa;">Debug JSON</a>
                <div id="onik-preload-debug-json-popup"
                    style="display: none; position: absolute; background: #fff; border: 1px solid #ccc; padding: 10px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); z-index: 9999; max-width: 600px; max-height: 400px; overflow: auto;">
                    <pre style="margin: 0; font-family: monospace; white-space: pre-wrap;"></pre>
                </div>
            </div>
        <?php endif; ?>
    
        <script>
            jQuery(document).ready(function ($) {
                var $table = $('#onik_images_preloads_table tbody');
                var $modal = $('#onik-preloads-modal');
                var $modalForm = $('#onik-preload-modal-form');
                var $modalTitle = $('#onik-preload-modal-title');
                var $rowIndexInput = $('#onik-preload-modal-row-index');
    
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
                    $modalForm.find('select').each(function () {
                        if ($(this).attr('id') === 'onik-preload-modal-rel') {
                            $(this).val('preload');
                        } else {
                            $(this).val('');
                        }
                    });
                    $rowIndexInput.val('');
                    if (row) {
                        $modalTitle.text('Edit Preload');
                        var index = $table.find('tr').index(row);
                        $rowIndexInput.val(index);
    
                        $('#onik-preload-modal-rel').val(row.find('.col-rel input').val());
                        $('#onik-preload-modal-fetchpriority').val(row.find('.col-fetchpriority input').val());
                        $('#onik-preload-modal-as').val(row.find('.col-as input').val());
                        $('#onik-preload-modal-href').val(row.find('.col-href input').val());
                        $('#onik-preload-modal-type').val(row.find('.col-type input').val());
                        $('#onik-preload-modal-urlFilter').val(row.find('.col-urlFilter input').val());
                    } else {
                        $modalTitle.text('Add Preload');
                        $rowIndexInput.val('');
                    }
                    $modal.show();
                }
    
                function closeModal() {
                    $modal.hide();
                }
    
                $('#add-preload-row').on('click', function () {
                    openModal(null);
                });
    
                $table.on('click', '.edit-row', function (e) {
                    e.preventDefault();
                    openModal($(this).closest('tr'));
                });
    
                $('#onik-preload-modal-cancel').on('click', closeModal);
    
                $('#onik-preload-modal-save').on('click', function () {
                    var index = $rowIndexInput.val();
                    var data = {
                        rel: $('#onik-preload-modal-rel').val(),
                        fetchpriority: $('#onik-preload-modal-fetchpriority').val(),
                        as: $('#onik-preload-modal-as').val(),
                        href: $('#onik-preload-modal-href').val(),
                        type: $('#onik-preload-modal-type').val(),
                        urlFilter: $('#onik-preload-modal-urlFilter').val()
                    };
    
                    if (!data.href) {
                        alert('Href is required.');
                        return;
                    }
    
                    if (index === '') {
                        if ($table.find('.no-items').length) {
                            $table.empty();
                        }
                        index = $table.find('tr').length;
                        var rowHtml = '<tr>';
    
                        rowHtml += '<td class="col-rel"><span class="display-value">' + $('<div>').text(data.rel).html() + '</span><input type="hidden" name="onik_images_preloads[' + index + '][rel]" value="' + $('<div>').text(data.rel).html() + '" /></td>';
                        rowHtml += '<td class="col-fetchpriority"><span class="display-value">' + $('<div>').text(data.fetchpriority).html() + '</span><input type="hidden" name="onik_images_preloads[' + index + '][fetchpriority]" value="' + $('<div>').text(data.fetchpriority).html() + '" /></td>';
                        rowHtml += '<td class="col-as"><span class="display-value">' + $('<div>').text(data.as).html() + '</span><input type="hidden" name="onik_images_preloads[' + index + '][as]" value="' + $('<div>').text(data.as).html() + '" /></td>';
                        rowHtml += '<td class="col-href"><span class="display-value">' + $('<div>').text(data.href).html() + '</span><input type="hidden" name="onik_images_preloads[' + index + '][href]" value="' + $('<div>').text(data.href).html() + '" /></td>';
                        rowHtml += '<td class="col-type"><span class="display-value">' + $('<div>').text(data.type).html() + '</span><input type="hidden" name="onik_images_preloads[' + index + '][type]" value="' + $('<div>').text(data.type).html() + '" /></td>';
                        rowHtml += '<td class="col-urlFilter"><span class="display-value">' + $('<div>').text(data.urlFilter).html() + '</span><input type="hidden" name="onik_images_preloads[' + index + '][urlFilter]" value="' + $('<div>').text(data.urlFilter).html() + '" /></td>';
    
                        rowHtml += '<td><div style="display:flex; gap:5px;"><button type="button" class="button edit-row" title="Edit">✎</button><button type="button" class="button move-up" title="Move Up">↑</button><button type="button" class="button move-down" title="Move Down">↓</button><button type="button" class="button delete-row" title="Delete">×</button></div></td></tr>';
                        $table.append(rowHtml);
                    } else {
                        var $row = $table.find('tr').eq(index);
    
                        $row.find('.col-rel .display-value').text(data.rel);
                        $row.find('.col-rel input').val(data.rel);
    
                        $row.find('.col-fetchpriority .display-value').text(data.fetchpriority);
                        $row.find('.col-fetchpriority input').val(data.fetchpriority);
    
                        $row.find('.col-as .display-value').text(data.as);
                        $row.find('.col-as input').val(data.as);
    
                        $row.find('.col-href .display-value').text(data.href);
                        $row.find('.col-href input').val(data.href);
    
                        $row.find('.col-type .display-value').text(data.type);
                        $row.find('.col-type input').val(data.type);
    
                        $row.find('.col-urlFilter .display-value').text(data.urlFilter);
                        $row.find('.col-urlFilter input').val(data.urlFilter);
                    }
    
                    closeModal();
                });
    
                $table.on('click', '.delete-row', function () {
                    $(this).closest('tr').remove();
                    if ($table.find('tr').length === 0) {
                        $table.append('<tr class="no-items"><td colspan="7">No preloads found.</td></tr>');
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
                var $debugLink = $('#onik-preload-debug-json-link');
                var $popup = $('#onik-preload-debug-json-popup');
                var $pre = $popup.find('pre');
    
                $debugLink.on('mouseenter', function (e) {
                    var data = [];
    
                    $table.find('tr').each(function () {
                        var $row = $(this);
                        if ($row.hasClass('no-items')) return;
    
                        var config = {};
    
                        var rel = $row.find('input[name*="[rel]"]').val();
                        if (rel && rel !== 'preload') config.rel = rel;
    
                        var fetchpriority = $row.find('input[name*="[fetchpriority]"]').val();
                        if (fetchpriority) config.fetchpriority = fetchpriority;
    
                        var as = $row.find('input[name*="[as]"]').val();
                        if (as) config.as = as;
    
                        var href = $row.find('input[name*="[href]"]').val();
                        if (href) config.href = href;
    
                        var type = $row.find('input[name*="[type]"]').val();
                        if (type) config.type = type;
    
                        var urlFilter = $row.find('input[name*="[urlFilter]"]').val();
                        if (urlFilter) config.urlFilter = urlFilter;
    
                        if (config.href) {
                            data.push(config);
                        }
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

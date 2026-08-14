{$meta_title=$btr->sviat__product_images_importer__page_title scope=global}

<div class="main_header">
    <div class="main_header__item">
        <div class="main_header__inner">
            <div class="box_heading heading_page">{$btr->sviat__product_images_importer__page_title|escape}</div>
        </div>
    </div>
    <div class="main_header__item">
        <div class="main_header__inner">
            <a href="{$root_url}/backend/index.php?controller=Sviat.ProductImagesImporter.ProductImagesImportLogAdmin"
                class="btn btn_small btn_border-info ml-1">{$btr->sviat__product_images_importer__btn_to_import_log|escape}
            </a>
            <a href="{$root_url}/backend/index.php?controller=ImportAdmin"
                class="btn btn_small btn_blue btn_border_blue">{$btr->sviat__product_images_importer__btn_to_products_import|escape}
            </a>
        </div>
    </div>
</div>

{if $import_result}
    <div class="row mt-2">
        <div class="col-lg-12 col-md-12">
            {if $import_result.warnings|default:[]|@count > 0}
                <div class="alert alert--icon alert--warning">
                    <div class="alert__content">
                        <div class="alert__title">{$btr->sviat__product_images_importer__alert_partial_title|escape}</div>
                        <p class="mb-1">{$btr->sviat__product_images_importer__alert_partial_text|escape}</p>
                        <ul class="mb-0">
                            {foreach $import_result.warnings as $w}
                                <li>{$w|escape}</li>
                            {/foreach}
                        </ul>
                    </div>
                </div>
            {/if}
            {if $import_result.errors|@count > 0}
                <div class="alert alert--icon alert--error">
                    <div class="alert__content">
                        <div class="alert__title">{$btr->sviat__product_images_importer__alert_error_title|escape}</div>
                        <p>{$btr->sviat__product_images_importer__label_total_files|escape}: {$import_result.total}, {$btr->sviat__product_images_importer__label_processed_ok|escape}: {$import_result.processed}, {$btr->sviat__product_images_importer__label_errors|escape}: {$import_result.errors|@count}</p>
                        <ul class="">
                            {foreach $import_result.errors as $error}
                                <li>{$error|escape}</li>
                            {/foreach}
                        </ul>
                    </div>
                </div>
            {elseif $import_result.warnings|default:[]|@count == 0}
                <div class="alert alert--icon alert--success">
                    <div class="alert__content">
                        <div class="alert__title">{$btr->sviat__product_images_importer__alert_success_title|escape}</div>
                        <p>{$btr->sviat__product_images_importer__alert_success_uploaded|escape} {$import_result.success|@count}.</p>
                        <p class="mb-0">{$btr->sviat__product_images_importer__label_total_files|escape}: {$import_result.total}, {$btr->sviat__product_images_importer__label_processed_ok|escape}: {$import_result.processed}</p>
                    </div>
                </div>
            {/if}
        </div>
    </div>
{/if}

<form method="post" enctype="multipart/form-data" class="mt-2">
    <input type="hidden" name="session_id" value="{$smarty.session.id}">

    <div class="row">
        <div class="col-lg-12 col-md-12">
            <div class="boxed">
                <div class="heading_box">
                    {$btr->sviat__product_images_importer__import_box_title|escape}
                </div>
                <div class="boxed_text">
                    <div class="alert alert--icon alert--info">
                        <div class="alert__content">
                            <div class="alert__title">{$btr->sviat__product_images_importer__instruction_title|escape}</div>
                            <p class="mb-2">
                                {$btr->sviat__product_images_importer__instruction_text}
                            </p>
                        </div>
                    </div>

                    <div class="form-group mb-2">
                        <div class="heading_label heading_label--required">
                            <span>{$btr->sviat__product_images_importer__files_label|escape}</span>
                        </div>
                        <div class="input_file_container">
                            <input class="input_file" id="product_images" type="file" name="product_images[]" multiple accept="image/*,.zip">
                            <label tabindex="0" for="product_images" class="input_file_trigger">
                                {include file='svg_icon.tpl' svgId='upload'}
                                <span>{$btr->sviat__product_images_importer__files_choose|escape}</span>
                            </label>
                        </div>
                        <p class="input_file_return" id="product_images_input_file_return"></p>
                        <div class="font_12 text_grey">
                            {$btr->sviat__product_images_importer__files_formats|escape}
                        </div>
                    </div>

                    <div class="form-group">
                        <div class="heading_label">
                            <span>{$btr->sviat__product_images_importer__attach_heading|escape}</span>
                        </div>
                        {assign var="am" value=$smarty.post.attach_mode|default:'filename_sku'}
                        <div class="okay_type_radio_wrap">
                            <input id="pii_attach_filename_sku" class="hidden_check" name="attach_mode" type="radio" value="filename_sku" {if $am == 'filename_sku'}checked{/if}>
                            <label for="pii_attach_filename_sku" class="okay_type_radio">
                                <span>{$btr->sviat__product_images_importer__attach_filename_sku_label|escape}</span>
                                <i class="fn_tooltips" title="{$btr->sviat__product_images_importer__attach_filename_sku_hint|escape}">
                                    {include file='svg_icon.tpl' svgId='icon_tooltips'}
                                </i>
                            </label>
                        </div>
                        <div class="okay_type_radio_wrap">
                            <input id="pii_attach_filename_product_id" class="hidden_check" name="attach_mode" type="radio" value="filename_product_id" {if $am == 'filename_product_id'}checked{/if}>
                            <label for="pii_attach_filename_product_id" class="okay_type_radio">
                                <span>{$btr->sviat__product_images_importer__attach_filename_product_id_label|escape}</span>
                                <i class="fn_tooltips" title="{$btr->sviat__product_images_importer__attach_filename_product_id_hint|escape}">
                                    {include file='svg_icon.tpl' svgId='icon_tooltips'}
                                </i>
                            </label>
                        </div>
                        <div class="okay_type_radio_wrap">
                            <input id="pii_attach_none" class="hidden_check" name="attach_mode" type="radio" value="none" {if $am == 'none'}checked{/if}>
                            <label for="pii_attach_none" class="okay_type_radio">
                                <span>{$btr->sviat__product_images_importer__attach_none_label|escape}</span>
                                <i class="fn_tooltips" title="{$btr->sviat__product_images_importer__attach_none_hint|escape}">
                                    {include file='svg_icon.tpl' svgId='icon_tooltips'}
                                </i>
                            </label>
                        </div>

                        {assign var="pii_kr_sku" value=$pii_filename_key_rules_sku|default:[]}
                        <div id="pii_filename_key_rules_sku" {if $am != 'filename_sku'}style="display:none"{/if}>
                            <p class="font_12 text_grey mb-2">
                                {$btr->sviat__product_images_importer__filename_key_rules_intro_sku|escape}
                                {* <i class="fn_tooltips" title="{$btr->sviat__product_images_importer__filename_key_rules_hint_sku|escape}">
                                    {include file='svg_icon.tpl' svgId='icon_tooltips'}
                                </i> *}
                            </p>
                            <div class="permission_block">
                                <div class="permission_boxes row">
                                    <div class="col-xl-12 col-lg-12 col-md-12">
                                        <div class="permission_box permission_box--long">
                                            <span>{$btr->sviat__product_images_importer__filename_key_rule_underscore_num_sku|escape}</span>
                                            <label class="switch switch-default">
                                                <input id="pii_fkr_sku_underscore" class="switch-input" name="filename_key_rules_sku[]" value="underscore_num" type="checkbox" {if is_array($pii_kr_sku) && in_array('underscore_num', $pii_kr_sku)}checked{/if}>
                                                <span class="switch-label"></span>
                                                <span class="switch-handle"></span>
                                            </label>
                                        </div>
                                    </div>
                                    <div class="col-xl-12 col-lg-12 col-md-12">
                                        <div class="permission_box permission_box--long">
                                            <span>{$btr->sviat__product_images_importer__filename_key_rule_dash_num_sku|escape}</span>
                                            <label class="switch switch-default">
                                                <input id="pii_fkr_sku_dash" class="switch-input" name="filename_key_rules_sku[]" value="dash_num" type="checkbox" {if is_array($pii_kr_sku) && in_array('dash_num', $pii_kr_sku)}checked{/if}>
                                                <span class="switch-label"></span>
                                                <span class="switch-handle"></span>
                                            </label>
                                        </div>
                                    </div>
                                    <div class="col-xl-12 col-lg-12 col-md-12">
                                        <div class="permission_box permission_box--long">
                                            <span>{$btr->sviat__product_images_importer__filename_key_rule_paren_num_sku|escape}</span>
                                            <label class="switch switch-default">
                                                <input id="pii_fkr_sku_paren" class="switch-input" name="filename_key_rules_sku[]" value="paren_num" type="checkbox" {if is_array($pii_kr_sku) && in_array('paren_num', $pii_kr_sku)}checked{/if}>
                                                <span class="switch-label"></span>
                                                <span class="switch-handle"></span>
                                            </label>
                                        </div>
                                    </div>
                                    <div class="col-xl-12 col-lg-12 col-md-12">
                                        <div class="permission_box permission_box--long">
                                            <span>{$btr->sviat__product_images_importer__filename_key_rule_space_paren_num_sku|escape}</span>
                                            <label class="switch switch-default">
                                                <input id="pii_fkr_sku_space_paren" class="switch-input" name="filename_key_rules_sku[]" value="space_paren_num" type="checkbox" {if is_array($pii_kr_sku) && in_array('space_paren_num', $pii_kr_sku)}checked{/if}>
                                                <span class="switch-label"></span>
                                                <span class="switch-handle"></span>
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {assign var="pii_kr_pid" value=$pii_filename_key_rules_pid|default:[]}
                        <div id="pii_filename_key_rules_pid" {if $am != 'filename_product_id'}style="display:none"{/if}>
                            <p class="font_12 text_grey mb-2">
                                {$btr->sviat__product_images_importer__filename_key_rules_intro_pid|escape}
                                {* <i class="fn_tooltips" title="{$btr->sviat__product_images_importer__filename_key_rules_hint_pid|escape}">
                                    {include file='svg_icon.tpl' svgId='icon_tooltips'}
                                </i> *}
                            </p>
                            <div class="permission_block">
                                <div class="permission_boxes row">
                                    <div class="col-xl-12 col-lg-12 col-md-12">
                                        <div class="permission_box permission_box--long">
                                            <span>{$btr->sviat__product_images_importer__filename_key_rule_underscore_num_pid|escape}</span>
                                            <label class="switch switch-default">
                                                <input id="pii_fkr_pid_underscore" class="switch-input" name="filename_key_rules_product_id[]" value="underscore_num" type="checkbox" {if is_array($pii_kr_pid) && in_array('underscore_num', $pii_kr_pid)}checked{/if}>
                                                <span class="switch-label"></span>
                                                <span class="switch-handle"></span>
                                            </label>
                                        </div>
                                    </div>
                                    <div class="col-xl-12 col-lg-12 col-md-12">
                                        <div class="permission_box permission_box--long">
                                            <span>{$btr->sviat__product_images_importer__filename_key_rule_dash_num_pid|escape}</span>
                                            <label class="switch switch-default">
                                                <input id="pii_fkr_pid_dash" class="switch-input" name="filename_key_rules_product_id[]" value="dash_num" type="checkbox" {if is_array($pii_kr_pid) && in_array('dash_num', $pii_kr_pid)}checked{/if}>
                                                <span class="switch-label"></span>
                                                <span class="switch-handle"></span>
                                            </label>
                                        </div>
                                    </div>
                                    <div class="col-xl-12 col-lg-12 col-md-12">
                                        <div class="permission_box permission_box--long">
                                            <span>{$btr->sviat__product_images_importer__filename_key_rule_paren_num_pid|escape}</span>
                                            <label class="switch switch-default">
                                                <input id="pii_fkr_pid_paren" class="switch-input" name="filename_key_rules_product_id[]" value="paren_num" type="checkbox" {if is_array($pii_kr_pid) && in_array('paren_num', $pii_kr_pid)}checked{/if}>
                                                <span class="switch-label"></span>
                                                <span class="switch-handle"></span>
                                            </label>
                                        </div>
                                    </div>
                                    <div class="col-xl-12 col-lg-12 col-md-12">
                                        <div class="permission_box permission_box--long">
                                            <span>{$btr->sviat__product_images_importer__filename_key_rule_space_paren_num_pid|escape}</span>
                                            <label class="switch switch-default">
                                                <input id="pii_fkr_pid_space_paren" class="switch-input" name="filename_key_rules_product_id[]" value="space_paren_num" type="checkbox" {if is_array($pii_kr_pid) && in_array('space_paren_num', $pii_kr_pid)}checked{/if}>
                                                <span class="switch-label"></span>
                                                <span class="switch-handle"></span>
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        {assign var="mpp" value=$pii_missing_product_policy|default:'skip'}
                        <div id="product_images_importer_missing_product_policy" style="{if $am != 'filename_sku' && $am != 'filename_product_id'}display:none{/if}">
                            <div class="heading_label">
                                <span>{$btr->sviat__product_images_importer__missing_product_policy_heading|escape}</span>
                                <i class="fn_tooltips" title="{$btr->sviat__product_images_importer__missing_product_policy_hint|escape}">
                                    {include file='svg_icon.tpl' svgId='icon_tooltips'}
                                </i>
                            </div>
                            <div class="d_flex mb-2" style="gap: 20px; align-items: center;">
                                <div class="okay_type_radio_wrap mb-0">
                                    <input id="pii_missing_product_skip" class="hidden_check" name="missing_product_policy" type="radio" value="skip" {if $mpp == 'skip'}checked{/if}>
                                    <label for="pii_missing_product_skip" class="okay_type_radio">
                                        <span>{$btr->sviat__product_images_importer__missing_product_policy_skip|escape}</span>
                                    </label>
                                </div>
                                <div class="okay_type_radio_wrap mb-0">
                                    <input id="pii_missing_product_save" class="hidden_check" name="missing_product_policy" type="radio" value="save" {if $mpp == 'save'}checked{/if}>
                                    <label for="pii_missing_product_save" class="okay_type_radio">
                                        <span>{$btr->sviat__product_images_importer__missing_product_policy_save|escape}</span>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <div class="heading_label">
                            <span>{$btr->sviat__product_images_importer__existing_files_heading|escape}</span>
                            <i class="fn_tooltips" title="{$btr->sviat__product_images_importer__existing_files_hint|escape}">
                                {include file='svg_icon.tpl' svgId='icon_tooltips'}
                            </i>
                        </div>
                        {assign var="efp" value=$pii_existing_files_policy|default:'skip'}
                        <div class="d_flex" style="gap: 20px; align-items: center;">
                            <div class="okay_type_radio_wrap mb-0">
                                <input id="pii_existing_skip" class="hidden_check" name="existing_files_policy" type="radio" value="skip" {if $efp == 'skip'}checked{/if}>
                                <label for="pii_existing_skip" class="okay_type_radio">
                                    <span>{$btr->sviat__product_images_importer__existing_files_skip|escape}</span>
                                    <i class="fn_tooltips" title="{$btr->sviat__product_images_importer__existing_files_skip_hint|escape}">
                                        {include file='svg_icon.tpl' svgId='icon_tooltips'}
                                    </i>
                                </label>
                            </div>
                            <div class="okay_type_radio_wrap mb-0">
                                <input id="pii_existing_unique" class="hidden_check" name="existing_files_policy" type="radio" value="unique" {if $efp == 'unique'}checked{/if}>
                                <label for="pii_existing_unique" class="okay_type_radio">
                                    <span>{$btr->sviat__product_images_importer__existing_files_unique|escape}</span>
                                    <i class="fn_tooltips" title="{$btr->sviat__product_images_importer__existing_files_unique_hint|escape}">
                                        {include file='svg_icon.tpl' svgId='icon_tooltips'}
                                    </i>
                                </label>
                            </div>
                            <div class="okay_type_radio_wrap mb-0">
                                <input id="pii_existing_overwrite" class="hidden_check" name="existing_files_policy" type="radio" value="overwrite" {if $efp == 'overwrite'}checked{/if}>
                                <label for="pii_existing_overwrite" class="okay_type_radio">
                                    <span>{$btr->sviat__product_images_importer__existing_files_overwrite|escape}</span>
                                    <i class="fn_tooltips" title="{$btr->sviat__product_images_importer__existing_files_overwrite_hint|escape}">
                                        {include file='svg_icon.tpl' svgId='icon_tooltips'}
                                    </i>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="boxed_footer">
                    <button type="submit" name="upload_images" value="1" class="btn btn_small btn_blue">
                        {include file='svg_icon.tpl' svgId='import'}
                        <span>{$btr->sviat__product_images_importer__btn_import|escape}</span>
                    </button>
                    <div class="mt-1">
                        <progress id="product_images_import_progressbar" class="progress progress-xs progress-info mt-1" style="display: none" value="0" max="100"></progress>
                    </div>
                </div>
            </div>
        </div>
    </div>
</form>

<script>
(function () {
    var filesAttachedCountLabel = '{$btr->sviat__product_images_importer__files_attached_count|escape:"javascript"}';
    var noFilesErrorText = '{$btr->sviat__product_images_importer__files_required_error|escape:"javascript"}';
    var filenameKeySku = document.getElementById('pii_filename_key_rules_sku');
    var filenameKeyPid = document.getElementById('pii_filename_key_rules_pid');
    var missingProductPolicy = document.getElementById('product_images_importer_missing_product_policy');
    var fileInput = document.getElementById('product_images');
    var fileReturn = document.getElementById('product_images_input_file_return');
    function sync() {
        var sku = document.querySelector('input[name="attach_mode"][value="filename_sku"]');
        var pid = document.querySelector('input[name="attach_mode"][value="filename_product_id"]');
        var filenameMode = (sku && sku.checked) || (pid && pid.checked);
        if (filenameKeySku) {
            filenameKeySku.style.display = (sku && sku.checked) ? '' : 'none';
        }
        if (filenameKeyPid) {
            filenameKeyPid.style.display = (pid && pid.checked) ? '' : 'none';
        }
        if (missingProductPolicy) {
            missingProductPolicy.style.display = filenameMode ? '' : 'none';
            }
    }
    document.querySelectorAll('input[name="attach_mode"]').forEach(function (el) {
        el.addEventListener('change', sync);
    });
    sync();

    function getSelectedFilesText() {
        var filesCount = fileInput.files ? fileInput.files.length : 0;
        if (filesCount === 0) {
            return '';
        }

        if (filesCount === 1) {
            return fileInput.files[0].name || '';
        }

        return filesAttachedCountLabel + ' ' + filesCount;
    }

    function syncSelectedFilesText() {
        if (!fileInput || !fileReturn) {
            return;
        }

        fileReturn.classList.remove('text_red');
        fileReturn.textContent = getSelectedFilesText();
    }

    if (fileInput) {
        fileInput.addEventListener('change', function () {
            syncSelectedFilesText();
            // Інші скрипти адмінки можуть перезаписувати текст fakepath-ом після change.
            setTimeout(syncSelectedFilesText, 0);
            if (window.requestAnimationFrame) {
                window.requestAnimationFrame(syncSelectedFilesText);
            }
        });
        fileInput.addEventListener('input', syncSelectedFilesText);
        syncSelectedFilesText();
    }

    var form = document.querySelector('form[enctype="multipart/form-data"]');
    var progress = document.getElementById('product_images_import_progressbar');

    if (form && progress) {
        form.addEventListener('submit', function (e) {
            if (!window.XMLHttpRequest || !window.FormData) {
                return;
            }

            var filesCount = fileInput && fileInput.files ? fileInput.files.length : 0;
            if (filesCount === 0) {
                e.preventDefault();
                if (fileReturn) {
                    fileReturn.textContent = noFilesErrorText;
                    fileReturn.classList.add('text_red');
                }
                return;
            }

            e.preventDefault();
            progress.style.display = '';
            progress.value = 0;

            // PHP приймає щонайбільше max_file_uploads файлів на запит (дефолт — 20)
            // і відкидає решту мовчки, без помилки в $_FILES. Тому шлемо пачками:
            // перша повертає import_key, під ним же лягають усі наступні.
            var CHUNK_SIZE = 10;
            var files = Array.prototype.slice.call(fileInput.files);
            var chunks = [];
            for (var i = 0; i < files.length; i += CHUNK_SIZE) {
                chunks.push(files.slice(i, i + CHUNK_SIZE));
            }

            var importKey = null;
            var lastRedirectUrl = null;

            function buildChunkData(chunk) {
                var data = new FormData(form);
                // Файли з форми беруться пачками вручну — прибираємо цілу вибірку.
                data.delete('product_images[]');
                for (var j = 0; j < chunk.length; j++) {
                    data.append('product_images[]', chunk[j], chunk[j].name);
                }
                data.append('ajax', '1');
                data.append('upload_images', '1');
                if (importKey) {
                    data.append('import_key', importKey);
                }
                return data;
            }

            function setProgress(chunkIndex, fraction) {
                progress.value = Math.round(((chunkIndex + fraction) / chunks.length) * 100);
            }

            function fail(message) {
                progress.style.display = 'none';
                alert(message || 'Import request failed');
            }

            function finish() {
                progress.value = 100;
                if (lastRedirectUrl) {
                    window.location.href = lastRedirectUrl;
                } else {
                    window.location.reload();
                }
            }

            function sendChunk(index) {
                if (index >= chunks.length) {
                    finish();
                    return;
                }

                var xhr = new XMLHttpRequest();
                xhr.open('POST', window.location.href, true);
                xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');

                xhr.upload.addEventListener('progress', function (evt) {
                    if (!evt.lengthComputable) {
                        return;
                    }
                    setProgress(index, (evt.loaded / evt.total) * 0.9);
                });

                xhr.onreadystatechange = function () {
                    if (xhr.readyState !== 4) {
                        return;
                    }

                    if (xhr.status < 200 || xhr.status >= 300) {
                        fail(xhr.responseText);
                        return;
                    }

                    var response = {};
                    try {
                        response = JSON.parse(xhr.responseText || '{}');
                    } catch (err) {
                        // Пачка пройшла, але відповідь не JSON — далі слати нема куди.
                        window.location.reload();
                        return;
                    }

                    if (response.success === false) {
                        fail(response.error);
                        return;
                    }

                    if (response.import_key) {
                        importKey = response.import_key;
                    }
                    if (response.redirect_url) {
                        lastRedirectUrl = response.redirect_url;
                    }

                    setProgress(index, 1);
                    sendChunk(index + 1);
                };

                xhr.send(buildChunkData(chunks[index]));
            }

            sendChunk(0);
        });
    }
})();
</script>

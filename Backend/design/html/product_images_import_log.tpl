{$meta_title=$btr->sviat__product_images_importer__log_page_title scope=global}

<div class="main_header">
    <div class="main_header__item">
        <div class="main_header__inner">
            {if $logs_count}
                <div class="box_heading heading_page">
                    {$btr->sviat__product_images_importer__log_page_title|escape}: {$logs_count}
                </div>
            {else}
                <div class="box_heading heading_page">{$btr->sviat__product_images_importer__log_empty|escape}</div>
            {/if}
        </div>
    </div>
    <div class="main_header__item">
        <div class="main_header__inner">
            <a href="{$root_url}/backend/index.php?controller=Sviat.ProductImagesImporter.ProductImagesImporterAdmin"
               class="btn btn_small btn_blue btn_border_blue">
                {$btr->sviat__product_images_importer__btn_back_to_import|escape}
            </a>
        </div>
    </div>
</div>

<div class="boxed fn_toggle_wrap">
    <div class="row">
        <div class="col-lg-12 col-md-12">
            <div class="boxed_sorting">
                <div class="row">
                    <div class="col-md-3 col-lg-3 col-sm-12">
                        <select class="selectpicker form-control" onchange="location = this.value;">
                            <option value="{url status=null page=null}" {if !$status}selected{/if}>{$btr->general_all|escape}</option>
                            <option value="{url status='success' page=null}" {if $status == 'success'}selected{/if}>{$btr->sviat__product_images_importer__status_success|escape}</option>
                            <option value="{url status='warning' page=null}" {if $status == 'warning'}selected{/if}>{$btr->sviat__product_images_importer__status_warning|escape}</option>
                            <option value="{url status='error' page=null}" {if $status == 'error'}selected{/if}>{$btr->sviat__product_images_importer__status_error|escape}</option>
                        </select>
                    </div>
                    <div class="col-md-5 col-lg-5 col-sm-12">
                        <form class="search" method="get">
                            <input type="hidden" name="controller" value="Sviat.ProductImagesImporter.ProductImagesImportLogAdmin">
                            <input type="hidden" name="import_key" value="{$import_key|escape}">
                            <input type="hidden" name="status" value="{$status|escape}">
                            <div class="input-group input-group--search">
                                <input name="keyword" class="form-control" placeholder="{$btr->general_search|escape}" type="text" value="{$keyword|escape}">
                                <span class="input-group-btn">
                                    <button type="submit" class="btn btn_blue"><i class="fa fa-search"></i></button>
                                </span>
                            </div>
                        </form>
                    </div>
                    <div class="col-lg-4 col-md-4 col-sm-12">
                        <div class="pull-right">
                            <select onchange="location = this.value;" class="selectpicker form-control">
                                <option value="{url limit=25 page=null}" {if $current_limit == 25}selected{/if}>{$btr->general_show_by|escape} 25</option>
                                <option value="{url limit=50 page=null}" {if $current_limit == 50}selected{/if}>{$btr->general_show_by|escape} 50</option>
                                <option value="{url limit=100 page=null}" {if $current_limit == 100}selected{/if}>{$btr->general_show_by|escape} 100</option>
                                <option value="{url limit=200 page=null}" {if $current_limit == 200}selected{/if}>{$btr->general_show_by|escape} 200</option>
                                <option value="{url limit=500 page=null}" {if $current_limit == 500}selected{/if}>{$btr->general_show_by|escape} 500</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {if $logs}
        <div class="row">
            <div class="col-md-12 col-lg-12 col-sm-12">
                {include file='pagination.tpl'}
            </div>
            <div class="col-lg-12 col-md-12 col-sm-12">
                <div class="okay_list fn_pii_log_list">
                    <div class="okay_list_head">
                        <div class="okay_list_heading okay_list_check">#</div>
                        <div class="okay_list_heading okay_list_photo">{$btr->general_photo|escape}</div>
                        <div class="okay_list_heading okay_list_log_name">{$btr->sviat__product_images_importer__log_col_file|escape}</div>
                        <div class="okay_list_heading fn_pii_col_status">{$btr->sviat__product_images_importer__log_col_status|escape}</div>
                        <div class="okay_list_heading fn_pii_col_operation">{$btr->sviat__product_images_importer__log_col_operation|escape}</div>
                        <div class="okay_list_heading okay_list_log_name">{$btr->sviat__product_images_importer__log_col_message|escape}</div>
                    </div>
                    <div class="okay_list_body">
                        {foreach $logs as $log}
                            <div class="fn_row okay_list_body_item">
                                <div class="okay_list_row">
                                    <div class="okay_list_boding okay_list_check">{$log@iteration}</div>
                                    <div class="okay_list_boding okay_list_photo">
                                        {if $log->stored_filename}
                                            <a href="{$log->stored_filename|escape|resize:120:120}" target="_blank">
                                                <img src="{$log->stored_filename|escape|resize:55:55}" alt="{$log->filename|escape}">
                                            </a>
                                        {else}
                                            <img height="55" width="55" src="design/images/no_image.png" alt="">
                                        {/if}
                                    </div>
                                    <div class="okay_list_boding okay_list_log_name">
                                        <div class="fn_pii_file_name">{$log->filename|escape}</div>
                                        {if $log->product_id}
                                            <a class="link text_grey font_12"
                                               href="{url controller=ProductAdmin id=$log->product_id return=$smarty.server.REQUEST_URI}"
                                               target="_blank">
                                                {$btr->sviat__product_images_importer__log_product_link_label|escape} #{$log->product_id}
                                            </a>
                                        {else}
                                            <div class="text_grey font_12">{$btr->sviat__product_images_importer__log_product_not_found|escape}</div>
                                        {/if}
                                    </div>
                                    <div class="okay_list_boding fn_pii_col_status">
                                        {if $log->status == 'success'}
                                            <span class="tag tag-success fn_pii_tag">{$btr->sviat__product_images_importer__status_success|escape}</span>
                                        {elseif $log->status == 'warning'}
                                            <span class="tag tag-warning fn_pii_tag">{$btr->sviat__product_images_importer__status_warning|escape}</span>
                                        {else}
                                            <span class="tag tag-danger fn_pii_tag">{$btr->sviat__product_images_importer__status_error|escape}</span>
                                        {/if}
                                    </div>
                                    <div class="okay_list_boding fn_pii_col_operation">
                                        {if $log->operation == 'saved'}
                                            <span class="tag tag-default fn_pii_tag">{$btr->sviat__product_images_importer__operation_saved|escape}</span>
                                        {elseif $log->operation == 'overwritten'}
                                            <span class="tag tag-info fn_pii_tag">{$btr->sviat__product_images_importer__operation_overwritten|escape}</span>
                                        {elseif $log->operation == 'skipped_existing'}
                                            <span class="tag tag-warning fn_pii_tag">{$btr->sviat__product_images_importer__operation_skipped_existing|escape}</span>
                                        {elseif $log->operation == 'attached'}
                                            <span class="tag tag-success fn_pii_tag">{$btr->sviat__product_images_importer__operation_attached|escape}</span>
                                        {elseif $log->operation == 'attach_failed'}
                                            <span class="tag tag-danger fn_pii_tag">{$btr->sviat__product_images_importer__operation_attach_failed|escape}</span>
                                        {elseif $log->operation == 'attach_skipped'}
                                            <span class="tag tag-warning fn_pii_tag">{$btr->sviat__product_images_importer__operation_attach_skipped|escape}</span>
                                        {elseif $log->operation == 'upload_failed'}
                                            <span class="tag tag-danger fn_pii_tag">{$btr->sviat__product_images_importer__operation_upload_failed|escape}</span>
                                        {elseif $log->operation == 'skipped_missing_product'}
                                            <span class="tag tag-warning fn_pii_tag">{$btr->sviat__product_images_importer__operation_skipped_missing_product|escape}</span>
                                        {else}
                                            <span class="tag tag-default fn_pii_tag">{$log->operation|escape}</span>
                                        {/if}
                                    </div>
                                    <div class="okay_list_boding okay_list_log_name fn_pii_message">{$log->message|escape}</div>
                                </div>
                            </div>
                        {/foreach}
                    </div>
                </div>
            </div>
        </div>
    {else}
        <div class="heading_box mt-1">
            <div class="text_grey">{$btr->sviat__product_images_importer__log_empty|escape}</div>
        </div>
    {/if}
</div>

<style>
    .fn_pii_log_list .okay_list_head,
    .fn_pii_log_list .okay_list_row {
        display: flex;
        width: 100%;
        min-width: 0;
    }

    .fn_pii_log_list .okay_list_heading,
    .fn_pii_log_list .okay_list_boding {
        float: none;
        flex-shrink: 0;
        padding: 10px 8px;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .fn_pii_log_list .okay_list_check { width: 42px; min-width: 42px; }
    .fn_pii_log_list .okay_list_photo { width: 70px; min-width: 70px; }
    .fn_pii_log_list .okay_list_log_name { width: 240px; min-width: 240px; }
    .fn_pii_log_list .fn_pii_col_status { width: 130px; min-width: 130px; text-align: center; }
    .fn_pii_log_list .fn_pii_col_operation { width: 220px; min-width: 220px; }
    .fn_pii_log_list .fn_pii_message { flex: 1 1 auto; min-width: 240px; white-space: normal; }

    .fn_pii_log_list .fn_pii_file_name {
        font-weight: 500;
        line-height: 1.25;
    }

    .fn_pii_log_list .fn_pii_tag {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        white-space: nowrap;
        max-width: 100%;
        line-height: 1.2;
    }

    .fn_pii_log_list .okay_list_photo img {
        width: 55px;
        height: 55px;
        object-fit: cover;
        border-radius: 4px;
    }

    @media (max-width: 991px) {
        .fn_pii_log_list {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }
        .fn_pii_log_list .okay_list_head,
        .fn_pii_log_list .okay_list_row {
            min-width: 980px;
        }
    }
</style>

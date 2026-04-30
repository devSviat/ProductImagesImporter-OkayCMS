        <div class="main_header">
            <div class="main_header__item">
                <div class="main_header__inner">
                    <div class="box_heading heading_page">
                        {$btr->import_products|escape}
                        <a class="export_block export_users hint-bottom-middle-t-info-s-small-mobile  hint-anim" data-hint="{$btr->general_example|escape}" href="files/import/example.csv" target="_blank">
                            {include file='svg_icon.tpl' svgId='export'}
                        </a>
                    </div>
                </div>
            </div>
            <div class="main_header__item">
                <div class="main_header__inner">
                    <a href="{$root_url}/backend/index.php?controller=Sviat.ProductImagesImporter.ProductImagesImporterAdmin"
                        class="btn btn_small btn_blue btn_border_blue"
                        title="{$btr->sviat__product_images_importer__import_tpl_link_hint|escape}">
                        {include file='svg_icon.tpl' svgId='images'}
                        <span>{$btr->sviat__product_images_importer__import_tpl_link|escape}</span>
                    </a>
                </div>
            </div>
        </div>

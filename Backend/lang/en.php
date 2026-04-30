<?php

$lang['left_product_images_importer'] = 'Image import';
$lang['import_images'] = 'Product image import';

$lang['sviat__product_images_importer__import_tpl_link'] = 'Image import';
$lang['sviat__product_images_importer__import_tpl_link_hint'] = 'Separate upload of product images (ZIP, matching by SKU or product ID in filename).';

$lang['sviat__product_images_importer__page_title'] = 'Product image import';
$lang['sviat__product_images_importer__import_box_title'] = 'File import';
$lang['sviat__product_images_importer__instruction_title'] = 'Instructions';
$lang['sviat__product_images_importer__instruction_text'] = 'You can upload individual images or a ZIP archive. Files are stored in <code>files/originals/products</code>. Below, choose whether to immediately attach images to catalog products by SKU or by product ID in the filename.';

$lang['sviat__product_images_importer__attach_heading'] = 'How to attach images to products';

$lang['sviat__product_images_importer__attach_none_label'] = 'Only save files on the server';
$lang['sviat__product_images_importer__attach_none_hint'] = 'Images will be uploaded to the originals folder, but will not appear in the product card in admin or on the storefront until you add them manually. Useful for preloading.';

$lang['sviat__product_images_importer__attach_filename_sku_label'] = 'Each file by article (article / SKU in filename)';
$lang['sviat__product_images_importer__attach_filename_sku_hint'] = 'The filename part before the dot must match a variant SKU. You can upload multiple images for one product if filenames contain suffixes like _1, -1, (1) - enable corresponding rules below. In ZIP, each file is processed separately.';

$lang['sviat__product_images_importer__attach_filename_product_id_label'] = 'Each file by product number in admin (product ID)';
$lang['sviat__product_images_importer__attach_filename_product_id_hint'] = 'Filename text without extension is treated as product ID or external_id. Multiple images per product: if filenames have _1, (2), etc., select normalization rules below. Example: "45123.jpg" for product #45123.';

$lang['sviat__product_images_importer__filename_key_rules_intro_sku'] = 'If you need multiple files for one SKU, enable rules to remove "copy number" suffixes from the end of the filename before extension. Rules are applied in order and repeated while the string changes. If none selected, search is done by full filename as-is.';
$lang['sviat__product_images_importer__filename_key_rules_hint_sku'] = 'Examples: ABC12_1.jpg and ABC12_2.jpg -> enable "_number"; ABC12-1.jpg -> "-number"; ABC12(1).jpg -> "(number)".';

$lang['sviat__product_images_importer__filename_key_rules_intro_pid'] = 'If you need multiple files for one admin product ID or external_id, enable the same suffix types - they are removed from the end of the name. Rules are applied in order and repeatedly. If none selected, search is done by full filename as-is.';
$lang['sviat__product_images_importer__filename_key_rules_hint_pid'] = 'ID examples: 45123_1.jpg and 45123_2.jpg -> "_number" gives 45123; 45123-1.jpg -> "-number"; 45123(1).jpg -> "(number)"; for code ext-42: ext-42_1.jpg -> "_number" -> ext-42.';

$lang['sviat__product_images_importer__filename_key_rule_underscore_num_sku'] = 'Remove "_number" suffix (for example, ABC12_1.jpg -> ABC12)';
$lang['sviat__product_images_importer__filename_key_rule_dash_num_sku'] = 'Remove "-number" suffix (ABC12-1.jpg -> ABC12)';
$lang['sviat__product_images_importer__filename_key_rule_paren_num_sku'] = 'Remove "(number)" at the end (ABC12(1).jpg -> ABC12)';
$lang['sviat__product_images_importer__filename_key_rule_space_paren_num_sku'] = 'Remove space and "(number)" at the end (ABC12 (1).jpg -> ABC12)';

$lang['sviat__product_images_importer__filename_key_rule_underscore_num_pid'] = 'Remove "_number" suffix (for example, 45123_1.jpg -> 45123)';
$lang['sviat__product_images_importer__filename_key_rule_dash_num_pid'] = 'Remove "-number" suffix (45123-1.jpg -> 45123)';
$lang['sviat__product_images_importer__filename_key_rule_paren_num_pid'] = 'Remove "(number)" at the end (45123(1).jpg -> 45123)';
$lang['sviat__product_images_importer__filename_key_rule_space_paren_num_pid'] = 'Remove space and "(number)" at the end (45123 (1).jpg -> 45123)';

$lang['sviat__product_images_importer__files_label'] = 'Files to import';
$lang['sviat__product_images_importer__files_formats'] = 'JPG, PNG, GIF, WebP or ZIP archive with images.';
$lang['sviat__product_images_importer__files_choose'] = 'Choose files...';
$lang['sviat__product_images_importer__files_attached_count'] = 'Files attached:';
$lang['sviat__product_images_importer__files_required_error'] = 'Select at least one file for import.';
$lang['sviat__product_images_importer__missing_product_policy_heading'] = 'If product is not found';
$lang['sviat__product_images_importer__missing_product_policy_hint'] = 'Works only for "Each file by SKU" and "Each file by product ID" modes.';
$lang['sviat__product_images_importer__missing_product_policy_save'] = 'Save file on the server';
$lang['sviat__product_images_importer__missing_product_policy_skip'] = 'Skip file';

$lang['sviat__product_images_importer__existing_files_heading'] = 'If a file with the same name already exists on the server';
$lang['sviat__product_images_importer__existing_files_hint'] = 'Comparison is done by sanitized name (latin letters, digits, _ and -) in the originals folder. Default is to not overwrite existing files. "Unique name" adds a suffix with timestamp and random fragment. "Overwrite" replaces the existing file.';
$lang['sviat__product_images_importer__existing_files_unique'] = 'Unique name';
$lang['sviat__product_images_importer__existing_files_unique_hint'] = 'Always generates a new name with suffix (time and random fragment); collisions with existing files are highly unlikely.';
$lang['sviat__product_images_importer__existing_files_skip'] = 'Skip';
$lang['sviat__product_images_importer__existing_files_skip_hint'] = 'Default behavior. If sanitized_name.ext already exists in originals, the imported file is not saved.';
$lang['sviat__product_images_importer__existing_files_overwrite'] = 'Overwrite';
$lang['sviat__product_images_importer__existing_files_overwrite_hint'] = 'The file on disk is replaced with imported content (same sanitized_name.ext).';

$lang['sviat__product_images_importer__btn_import'] = 'Import images';
$lang['sviat__product_images_importer__btn_to_products_import'] = 'To product import';
$lang['sviat__product_images_importer__btn_to_import_log'] = 'Image import log';
$lang['sviat__product_images_importer__btn_back_to_import'] = 'Back to image import';

$lang['sviat__product_images_importer__log_page_title'] = 'Last image import log';
$lang['sviat__product_images_importer__log_empty'] = 'Log is empty';
$lang['sviat__product_images_importer__log_col_file'] = 'File';
$lang['sviat__product_images_importer__log_col_status'] = 'Status';
$lang['sviat__product_images_importer__log_col_operation'] = 'Operation';
$lang['sviat__product_images_importer__log_col_message'] = 'Message';
$lang['sviat__product_images_importer__log_product_link_label'] = 'Product';
$lang['sviat__product_images_importer__log_product_not_found'] = 'Product not identified';
$lang['sviat__product_images_importer__status_success'] = 'Success';
$lang['sviat__product_images_importer__status_warning'] = 'Warning';
$lang['sviat__product_images_importer__status_error'] = 'Error';
$lang['sviat__product_images_importer__operation_saved'] = 'Saved to server';
$lang['sviat__product_images_importer__operation_overwritten'] = 'File overwritten';
$lang['sviat__product_images_importer__operation_skipped_existing'] = 'Skipped (already exists)';
$lang['sviat__product_images_importer__operation_attached'] = 'Attached to product';
$lang['sviat__product_images_importer__operation_attach_failed'] = 'Attach failed';
$lang['sviat__product_images_importer__operation_attach_skipped'] = 'Not attached';
$lang['sviat__product_images_importer__operation_upload_failed'] = 'Upload error';
$lang['sviat__product_images_importer__operation_skipped_missing_product'] = 'Skipped (product not found)';

$lang['sviat__product_images_importer__alert_partial_title'] = 'Partial';
$lang['sviat__product_images_importer__alert_partial_text'] = 'Files were saved, but some could not be attached to products:';
$lang['sviat__product_images_importer__alert_error_title'] = 'Important!';
$lang['sviat__product_images_importer__alert_success_title'] = 'Success!';
$lang['sviat__product_images_importer__alert_success_uploaded'] = 'Images added to catalog:';
$lang['sviat__product_images_importer__label_total_files'] = 'Total files';
$lang['sviat__product_images_importer__label_processed_ok'] = 'Successfully processed';
$lang['sviat__product_images_importer__label_errors'] = 'Errors';

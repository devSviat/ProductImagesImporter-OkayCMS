<?php

$lang['left_product_images_importer'] = 'Импорт фото';
$lang['import_images'] = 'Импорт фото продуктов';

$lang['sviat__product_images_importer__import_tpl_link'] = 'Импорт фото';
$lang['sviat__product_images_importer__import_tpl_link_hint'] = 'Отдельная загрузка изображений товаров (ZIP, привязка по SKU или ID товара в имени файла).';

$lang['sviat__product_images_importer__page_title'] = 'Импорт фото продуктов';
$lang['sviat__product_images_importer__import_box_title'] = 'Импорт файлов';
$lang['sviat__product_images_importer__instruction_title'] = 'Инструкция';
$lang['sviat__product_images_importer__instruction_text'] = 'Можно загрузить отдельные изображения или ZIP-архив. Файлы сохраняются в папке <code>files/originals/products</code>. Ниже выберите, нужно ли сразу прикреплять снимки к товарам в каталоге — по SKU или по ID товара в названии файла.';

$lang['sviat__product_images_importer__attach_heading'] = 'Как прикреплять фото к товарам';

$lang['sviat__product_images_importer__attach_none_label'] = 'Только сохранить файлы на сервере';
$lang['sviat__product_images_importer__attach_none_hint'] = 'Фото попадут в папку оригиналов, но не появятся в карточке товара в админке и на сайте, пока вы не добавите их вручную. Подходит для предварительной загрузки.';

$lang['sviat__product_images_importer__attach_filename_sku_label'] = 'Каждый файл — по артикулу (артикул / SKU в имени файла)';
$lang['sviat__product_images_importer__attach_filename_sku_hint'] = 'Часть названия до точки должна совпадать с артикулом (SKU) варианта. Можно загрузить несколько фото на один товар, если в названиях есть суффиксы вроде _1, -1, (1) — включите соответствующие правила ниже. В ZIP каждый файл обрабатывается отдельно.';

$lang['sviat__product_images_importer__attach_filename_product_id_label'] = 'Каждый файл — по номеру товара в админке (ID товара)';
$lang['sviat__product_images_importer__attach_filename_product_id_hint'] = 'Текст в имени файла без расширения — это ID товара или external_id. Несколько фото на один товар: если в названиях есть _1, (2) и т.п., выберите правила нормализации ниже. Пример: «45123.jpg» для товара №45123.';

$lang['sviat__product_images_importer__filename_key_rules_intro_sku'] = 'Если для одного SKU нужно несколько файлов, отметьте правила, по которым убирать «номер копии» с конца названия до расширения. Правила применяются по очереди и повторяются, пока строка изменяется. Ничего не выбрано — поиск по полному названию файла без изменений.';
$lang['sviat__product_images_importer__filename_key_rules_hint_sku'] = 'Примеры: ABC12_1.jpg и ABC12_2.jpg → включите «_число»; ABC12-1.jpg → «-число»; ABC12(1).jpg → «(число)».';

$lang['sviat__product_images_importer__filename_key_rules_intro_pid'] = 'Если для одного ID товара в админке или external_id нужно несколько файлов, отметьте те же типы суффиксов — они убираются с конца названия. Правила по очереди и повторно. Ничего не выбрано — поиск по полному названию без изменений.';
$lang['sviat__product_images_importer__filename_key_rules_hint_pid'] = 'Примеры с ID: 45123_1.jpg и 45123_2.jpg → «_число» дает 45123; 45123-1.jpg → «-число»; 45123(1).jpg → «(число)»; для кода ext-42: ext-42_1.jpg → «_число» → ext-42.';

$lang['sviat__product_images_importer__filename_key_rule_underscore_num_sku'] = 'Убрать суффикс «_число» (например, ABC12_1.jpg → ABC12)';
$lang['sviat__product_images_importer__filename_key_rule_dash_num_sku'] = 'Убрать суффикс «-число» (ABC12-1.jpg → ABC12)';
$lang['sviat__product_images_importer__filename_key_rule_paren_num_sku'] = 'Убрать «(число)» в конце (ABC12(1).jpg → ABC12)';
$lang['sviat__product_images_importer__filename_key_rule_space_paren_num_sku'] = 'Убрать пробел и «(число)» в конце (ABC12 (1).jpg → ABC12)';

$lang['sviat__product_images_importer__filename_key_rule_underscore_num_pid'] = 'Убрать суффикс «_число» (например, 45123_1.jpg → 45123)';
$lang['sviat__product_images_importer__filename_key_rule_dash_num_pid'] = 'Убрать суффикс «-число» (45123-1.jpg → 45123)';
$lang['sviat__product_images_importer__filename_key_rule_paren_num_pid'] = 'Убрать «(число)» в конце (45123(1).jpg → 45123)';
$lang['sviat__product_images_importer__filename_key_rule_space_paren_num_pid'] = 'Убрать пробел и «(число)» в конце (45123 (1).jpg → 45123)';

$lang['sviat__product_images_importer__files_label'] = 'Файлы для импорта';
$lang['sviat__product_images_importer__files_formats'] = 'JPG, PNG, GIF, WebP или ZIP-архив с фото.';
$lang['sviat__product_images_importer__files_choose'] = 'Выберите файлы...';
$lang['sviat__product_images_importer__files_attached_count'] = 'Прикреплено файлов:';
$lang['sviat__product_images_importer__files_required_error'] = 'Выберите хотя бы один файл для импорта.';
$lang['sviat__product_images_importer__missing_product_policy_heading'] = 'Если товар не найден';
$lang['sviat__product_images_importer__missing_product_policy_hint'] = 'Работает только для режимов «Каждый файл — по SKU» и «Каждый файл — по ID товара».';
$lang['sviat__product_images_importer__missing_product_policy_save'] = 'Сохранить файл на сервере';
$lang['sviat__product_images_importer__missing_product_policy_skip'] = 'Пропустить файл';

$lang['sviat__product_images_importer__existing_files_heading'] = 'Если файл с таким названием уже есть на сервере';
$lang['sviat__product_images_importer__existing_files_hint'] = 'Сравнение по имени после санитизации (латиница, цифры, _ и -) в папке оригиналов. По умолчанию — не перезаписывать существующие файлы. «Уникальное имя» добавляет суффикс со временем и случайным фрагментом. «Перезаписать» — заменить существующий файл.';
$lang['sviat__product_images_importer__existing_files_unique'] = 'Уникальное имя';
$lang['sviat__product_images_importer__existing_files_unique_hint'] = 'Всегда новое имя с суффиксом (время и случайный фрагмент); коллизий с существующими файлами практически не бывает.';
$lang['sviat__product_images_importer__existing_files_skip'] = 'Пропустить';
$lang['sviat__product_images_importer__existing_files_skip_hint'] = 'По умолчанию. Если sanitized_name.ext уже есть в папке originals, файл из импорта не сохраняется.';
$lang['sviat__product_images_importer__existing_files_overwrite'] = 'Перезаписать';
$lang['sviat__product_images_importer__existing_files_overwrite_hint'] = 'Файл на диске заменяется содержимым из импорта (тот же sanitized_name.ext).';

$lang['sviat__product_images_importer__btn_import'] = 'Импортировать фото';
$lang['sviat__product_images_importer__btn_to_products_import'] = 'К импорту товаров';
$lang['sviat__product_images_importer__btn_to_import_log'] = 'Журнал импорта фото';
$lang['sviat__product_images_importer__btn_back_to_import'] = 'Вернуться к импорту фото';

$lang['sviat__product_images_importer__log_page_title'] = 'Журнал последнего импорта фото';
$lang['sviat__product_images_importer__log_empty'] = 'Журнал пуст';
$lang['sviat__product_images_importer__log_col_file'] = 'Файл';
$lang['sviat__product_images_importer__log_col_status'] = 'Статус';
$lang['sviat__product_images_importer__log_col_operation'] = 'Операция';
$lang['sviat__product_images_importer__log_col_message'] = 'Сообщение';
$lang['sviat__product_images_importer__log_product_link_label'] = 'Товар';
$lang['sviat__product_images_importer__log_product_not_found'] = 'Товар не определен';
$lang['sviat__product_images_importer__status_success'] = 'Успешно';
$lang['sviat__product_images_importer__status_warning'] = 'Предупреждение';
$lang['sviat__product_images_importer__status_error'] = 'Ошибка';
$lang['sviat__product_images_importer__operation_saved'] = 'Сохранено на сервер';
$lang['sviat__product_images_importer__operation_overwritten'] = 'Файл перезаписан';
$lang['sviat__product_images_importer__operation_skipped_existing'] = 'Пропущено (уже существует)';
$lang['sviat__product_images_importer__operation_attached'] = 'Прикреплено к товару';
$lang['sviat__product_images_importer__operation_attach_failed'] = 'Не удалось прикрепить';
$lang['sviat__product_images_importer__operation_attach_skipped'] = 'Не прикреплено';
$lang['sviat__product_images_importer__operation_upload_failed'] = 'Ошибка загрузки';
$lang['sviat__product_images_importer__operation_skipped_missing_product'] = 'Пропущено (товар не найден)';

$lang['sviat__product_images_importer__alert_partial_title'] = 'Частично';
$lang['sviat__product_images_importer__alert_partial_text'] = 'Файлы сохранены, но часть не удалось прикрепить к товару:';
$lang['sviat__product_images_importer__alert_error_title'] = 'Важно!';
$lang['sviat__product_images_importer__alert_success_title'] = 'Успешно!';
$lang['sviat__product_images_importer__alert_success_uploaded'] = 'Добавлено снимков в каталог:';
$lang['sviat__product_images_importer__label_total_files'] = 'Всего файлов';
$lang['sviat__product_images_importer__label_processed_ok'] = 'Успешно обработано';
$lang['sviat__product_images_importer__label_errors'] = 'Ошибок';

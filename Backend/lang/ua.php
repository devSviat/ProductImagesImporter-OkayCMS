<?php

$lang['left_product_images_importer'] = 'Імпорт фото';
$lang['import_images'] = 'Імпорт фото продуктів';

$lang['sviat__product_images_importer__import_tpl_link'] = 'Імпорт фото';
$lang['sviat__product_images_importer__import_tpl_link_hint'] = 'Окреме завантаження зображень товарів (ZIP, прив’язка за SKU або ID товару у назві файлу).';

$lang['sviat__product_images_importer__page_title'] = 'Імпорт фото продуктів';
$lang['sviat__product_images_importer__import_box_title'] = 'Імпорт файлів';
$lang['sviat__product_images_importer__instruction_title'] = 'Інструкція';
$lang['sviat__product_images_importer__instruction_text'] = 'Можна завантажити окремі зображення або ZIP-архів. Файли зберігаються в теці <code>files/originals/products</code>. Нижче оберіть, чи одразу прикріплювати знімки до товарів у каталозі — за SKU або за ID товару в назві файлу.';

$lang['sviat__product_images_importer__attach_heading'] = 'Як прикріпити фото до товарів';

$lang['sviat__product_images_importer__attach_none_label'] = 'Тільки зберегти файли на сервері';
$lang['sviat__product_images_importer__attach_none_hint'] = 'Фото потраплять у теку з оригіналами, але не з’являться в картці товару в адмінці й на сайті, доки ви не додасте їх вручну. Підійде для попереднього завантаження.';


$lang['sviat__product_images_importer__attach_filename_sku_label'] = 'Кожен файл — за артикулом (артикул / SKU у назві файлу)';
$lang['sviat__product_images_importer__attach_filename_sku_hint'] = 'Частина назви до крапки має збігатися з артикулом (SKU) варіанта. Можна завантажити кілька фото на один товар, якщо в назвах є суфікси на кшталт _1, -1, (1) — увімкніть відповідні правила нижче. У ZIP кожен файл обробляється окремо.';

$lang['sviat__product_images_importer__attach_filename_product_id_label'] = 'Кожен файл — за номером товару в адмінці (ID товару)';
$lang['sviat__product_images_importer__attach_filename_product_id_hint'] = 'Текст у назві файлу без розширення — це ID товару або external_id. Кілька фото на один товар: якщо в назвах є _1, (2) тощо, оберіть правила нормалізації нижче. Приклад: «45123.jpg» для товару №45123.';

$lang['sviat__product_images_importer__filename_key_rules_intro_sku'] = 'Якщо для одного SKU потрібно кілька файлів, відмітьте правила, за якими прибирати «номер копії» з кінця назви до розширення. Правила застосовуються по черзі й повторюються, доки рядок змінюється. Нічого не обрано — пошук за повною назвою файлу без змін.';
$lang['sviat__product_images_importer__filename_key_rules_hint_sku'] = 'Приклади: ABC12_1.jpg і ABC12_2.jpg → увімкніть «_число»; ABC12-1.jpg → «-число»; ABC12(1).jpg → «(число)».';

$lang['sviat__product_images_importer__filename_key_rules_intro_pid'] = 'Якщо для одного ID товару в адмінці або external_id потрібно кілька файлів, відмітьте ті самі типи суфіксів — вони прибираються з кінця назви. Правила по черзі й повторно. Нічого не обрано — пошук за повною назвою без змін.';
$lang['sviat__product_images_importer__filename_key_rules_hint_pid'] = 'Приклади з ID: 45123_1.jpg і 45123_2.jpg → «_число» дає 45123; 45123-1.jpg → «-число»; 45123(1).jpg → «(число)»; для коду ext-42: ext-42_1.jpg → «_число» → ext-42.';

$lang['sviat__product_images_importer__filename_key_rule_underscore_num_sku'] = 'Прибрати суфікс «_число» (наприклад, ABC12_1.jpg → ABC12)';
$lang['sviat__product_images_importer__filename_key_rule_dash_num_sku'] = 'Прибрати суфікс «-число» (ABC12-1.jpg → ABC12)';
$lang['sviat__product_images_importer__filename_key_rule_paren_num_sku'] = 'Прибрати «(число)» у кінці (ABC12(1).jpg → ABC12)';
$lang['sviat__product_images_importer__filename_key_rule_space_paren_num_sku'] = 'Прибрати пробіл і «(число)» у кінці (ABC12 (1).jpg → ABC12)';

$lang['sviat__product_images_importer__filename_key_rule_underscore_num_pid'] = 'Прибрати суфікс «_число» (наприклад, 45123_1.jpg → 45123)';
$lang['sviat__product_images_importer__filename_key_rule_dash_num_pid'] = 'Прибрати суфікс «-число» (45123-1.jpg → 45123)';
$lang['sviat__product_images_importer__filename_key_rule_paren_num_pid'] = 'Прибрати «(число)» у кінці (45123(1).jpg → 45123)';
$lang['sviat__product_images_importer__filename_key_rule_space_paren_num_pid'] = 'Прибрати пробіл і «(число)» у кінці (45123 (1).jpg → 45123)';

$lang['sviat__product_images_importer__files_label'] = 'Файли для імпорту';
$lang['sviat__product_images_importer__files_formats'] = 'JPG, PNG, GIF, WebP або ZIP-архів з фото.';
$lang['sviat__product_images_importer__files_choose'] = 'Виберіть файли…';
$lang['sviat__product_images_importer__files_attached_count'] = 'Прикріплено файлів:';
$lang['sviat__product_images_importer__files_required_error'] = 'Оберіть хоча б один файл для імпорту.';
$lang['sviat__product_images_importer__missing_product_policy_heading'] = 'Якщо товар не знайдено';
$lang['sviat__product_images_importer__missing_product_policy_hint'] = 'Працює тільки для режимів «Кожен файл — за SKU» та «Кожен файл — за ID товару».';
$lang['sviat__product_images_importer__missing_product_policy_save'] = 'Зберегти файл на сервері';
$lang['sviat__product_images_importer__missing_product_policy_skip'] = 'Пропустити файл';

$lang['sviat__product_images_importer__existing_files_heading'] = 'Якщо файл з такою назвою вже є на сервері';
$lang['sviat__product_images_importer__existing_files_hint'] = 'Порівняння за ім’ям після санітизації (латиниця, цифри, _ та -) у теці з оригіналами. За замовчуванням — не перезаписувати наявні файли. «Унікальне ім’я» додає суфікс з часом і випадковим фрагментом. «Перезаписати» — замінити існуючий файл.';
$lang['sviat__product_images_importer__existing_files_unique'] = 'Унікальне ім’я';
$lang['sviat__product_images_importer__existing_files_unique_hint'] = 'Завжди нове ім’я з суфіксом (час і випадковий фрагмент); колізій з існуючими файлами практично не буває.';
$lang['sviat__product_images_importer__existing_files_skip'] = 'Пропустити';
$lang['sviat__product_images_importer__existing_files_skip_hint'] = 'За замовчуванням. Якщо sanitized_name.ext уже є в теці originals, файл з імпорту не зберігається.';
$lang['sviat__product_images_importer__existing_files_overwrite'] = 'Перезаписати';
$lang['sviat__product_images_importer__existing_files_overwrite_hint'] = 'Файл на диску замінюється вмістом з імпорту (той самий sanitized_name.ext).';

$lang['sviat__product_images_importer__btn_import'] = 'Імпортувати фото';
$lang['sviat__product_images_importer__btn_to_products_import'] = 'До імпорту товарів';
$lang['sviat__product_images_importer__btn_to_import_log'] = 'Журнал імпорту фото';
$lang['sviat__product_images_importer__btn_back_to_import'] = 'Повернутись до імпорту фото';

$lang['sviat__product_images_importer__log_page_title'] = 'Журнал останнього імпорту фото';
$lang['sviat__product_images_importer__log_empty'] = 'Журнал порожній';
$lang['sviat__product_images_importer__log_col_file'] = 'Файл';
$lang['sviat__product_images_importer__log_col_status'] = 'Статус';
$lang['sviat__product_images_importer__log_col_operation'] = 'Операція';
$lang['sviat__product_images_importer__log_col_message'] = 'Повідомлення';
$lang['sviat__product_images_importer__log_product_link_label'] = 'Товар';
$lang['sviat__product_images_importer__log_product_not_found'] = 'Товар не визначено';
$lang['sviat__product_images_importer__status_success'] = 'Успішно';
$lang['sviat__product_images_importer__status_warning'] = 'Попередження';
$lang['sviat__product_images_importer__status_error'] = 'Помилка';
$lang['sviat__product_images_importer__operation_saved'] = 'Збережено на сервер';
$lang['sviat__product_images_importer__operation_overwritten'] = 'Файл перезаписано';
$lang['sviat__product_images_importer__operation_skipped_existing'] = 'Пропущено (вже існує)';
$lang['sviat__product_images_importer__operation_attached'] = 'Прикріплено до товару';
$lang['sviat__product_images_importer__operation_attach_failed'] = 'Не вдалося прикріпити';
$lang['sviat__product_images_importer__operation_attach_skipped'] = 'Не прикріплено';
$lang['sviat__product_images_importer__operation_upload_failed'] = 'Помилка завантаження';
$lang['sviat__product_images_importer__operation_skipped_missing_product'] = 'Пропущено (товар не знайдено)';

$lang['sviat__product_images_importer__alert_partial_title'] = 'Частково';
$lang['sviat__product_images_importer__alert_partial_text'] = 'Файли збережено, але для частини не вдалося прикріпити до товару:';
$lang['sviat__product_images_importer__alert_error_title'] = 'Важливо!';
$lang['sviat__product_images_importer__alert_success_title'] = 'Успішно!';
$lang['sviat__product_images_importer__alert_success_uploaded'] = 'Додано знімків до каталогу:';
$lang['sviat__product_images_importer__label_total_files'] = 'Всього файлів';
$lang['sviat__product_images_importer__label_processed_ok'] = 'Успішно оброблено';
$lang['sviat__product_images_importer__label_errors'] = 'Помилок';

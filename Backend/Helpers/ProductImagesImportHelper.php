<?php

namespace Okay\Modules\Sviat\ProductImagesImporter\Backend\Helpers;

use Okay\Core\Config;
use Okay\Core\EntityFactory;
use Okay\Core\Modules\Extender\ExtenderFacade;
use Okay\Entities\ImagesEntity;
use Okay\Entities\ProductsEntity;
use Okay\Entities\VariantsEntity;
use Okay\Modules\Sviat\ProductImagesImporter\Entities\ProductImagesImportLogEntity;

class ProductImagesImportHelper
{
    public const ATTACH_NONE = 'none';
    public const ATTACH_FILENAME_SKU = 'filename_sku';
    public const ATTACH_FILENAME_PRODUCT_ID = 'filename_product_id';

    /** Правила нормалізації назви файлу до ключа SKU / ID (кілька фото на один товар) */
    public const FILENAME_KEY_RULE_UNDERSCORE_NUM = 'underscore_num';
    public const FILENAME_KEY_RULE_DASH_NUM = 'dash_num';
    public const FILENAME_KEY_RULE_PAREN_NUM = 'paren_num';
    public const FILENAME_KEY_RULE_SPACE_PAREN_NUM = 'space_paren_num';

    /** Якщо у теці originals уже є файл з таким ім’ям після санітизації */
    public const EXISTING_FILES_UNIQUE = 'unique';
    public const EXISTING_FILES_SKIP = 'skip';
    public const EXISTING_FILES_OVERWRITE = 'overwrite';
    public const ON_MISSING_PRODUCT_SAVE = 'save';
    public const ON_MISSING_PRODUCT_SKIP = 'skip';

    private const array ALLOWED_EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    private const MAX_FILE_SIZE = 10 * 1024 * 1024;
    private const MAX_ZIP_UNCOMPRESSED_SIZE = 100 * 1024 * 1024;

    private const string SOURCE_DIRECT = 'direct';
    private const string SOURCE_ZIP = 'zip';

    private Config $config;
    private ProductsEntity $productsEntity;
    private VariantsEntity $variantsEntity;
    private ImagesEntity $imagesEntity;
    private ProductImagesImportLogEntity $importLogEntity;

    public function __construct(Config $config, EntityFactory $entityFactory)
    {
        $this->config = $config;
        $this->productsEntity = $entityFactory->get(ProductsEntity::class);
        $this->variantsEntity = $entityFactory->get(VariantsEntity::class);
        $this->imagesEntity = $entityFactory->get(ImagesEntity::class);
        $this->importLogEntity = $entityFactory->get(ProductImagesImportLogEntity::class);
    }

    /**
     * Аплоад великої вибірки йде пачками (див. JS імпортера), і кожна пачка — окремий
     * запит. Спільний import_key склеює їх в один імпорт у журналі.
     */
    public static function generateImportKey(): string
    {
        return date('YmdHis') . '_' . bin2hex(random_bytes(4));
    }

    public static function isValidImportKey(?string $key): bool
    {
        return $key !== null && preg_match('/^\d{14}_[0-9a-f]{8}$/', $key) === 1;
    }

    public static function isValidAttachMode(string $mode): bool
    {
        return in_array($mode, [
            self::ATTACH_NONE,
            self::ATTACH_FILENAME_SKU,
            self::ATTACH_FILENAME_PRODUCT_ID,
        ], true);
    }

    /**
     * @param string[] $rawIds значення з POST filename_key_rules[]
     *
     * @return string[] відсортовані унікальні дозволені id правил
     */
    public static function sanitizeFilenameKeyRules(array $rawIds): array
    {
        $allowedFlip = array_flip(self::filenameKeyRuleOrder());
        $out = [];
        foreach ($rawIds as $id) {
            if (!is_string($id)) {
                continue;
            }
            $id = trim($id);
            if ($id !== '' && isset($allowedFlip[$id])) {
                $out[$id] = true;
            }
        }

        $ordered = [];
        foreach (self::filenameKeyRuleOrder() as $id) {
            if (isset($out[$id])) {
                $ordered[] = $id;
            }
        }

        return $ordered;
    }

    /**
     * @return string[]
     */
    public static function filenameKeyRuleOrder(): array
    {
        return [
            self::FILENAME_KEY_RULE_UNDERSCORE_NUM,
            self::FILENAME_KEY_RULE_DASH_NUM,
            self::FILENAME_KEY_RULE_PAREN_NUM,
            self::FILENAME_KEY_RULE_SPACE_PAREN_NUM,
        ];
    }

    public static function sanitizeExistingFilesPolicy(?string $raw): string
    {
        $raw = $raw !== null ? trim($raw) : '';
        if (in_array($raw, [
            self::EXISTING_FILES_UNIQUE,
            self::EXISTING_FILES_SKIP,
            self::EXISTING_FILES_OVERWRITE,
        ], true)) {
            return $raw;
        }

        return self::EXISTING_FILES_SKIP;
    }

    public static function sanitizeMissingProductPolicy(?string $raw): string
    {
        $raw = $raw !== null ? trim($raw) : '';
        if (in_array($raw, [self::ON_MISSING_PRODUCT_SAVE, self::ON_MISSING_PRODUCT_SKIP], true)) {
            return $raw;
        }

        return self::ON_MISSING_PRODUCT_SKIP;
    }

    /**
     * За назви файлу без розширення — отримати ключ для пошуку SKU / ID (з урахуванням обраних правил).
     *
     * @param string   $baseName назва без розширення
     * @param string[] $ruleIds  результат sanitizeFilenameKeyRules()
     */
    public static function normalizeFilenameBase(string $baseName, array $ruleIds): string
    {
        $s = trim($baseName);
        if ($s === '' || $ruleIds === []) {
            return $s;
        }

        $enabled = array_flip($ruleIds);
        $order = self::filenameKeyRuleOrder();
        $maxPasses = 16;

        for ($pass = 0; $pass < $maxPasses; $pass++) {
            $before = $s;
            foreach ($order as $ruleId) {
                if (!isset($enabled[$ruleId])) {
                    continue;
                }
                // Обрізаємо хвіст після кожного правила, а не лише в кінці:
                // paren_num зрізає "(3)" і лишає пробіл, а решта правил
                // прив'язані до кінця рядка ($), тож без цього вони переставали
                // спрацьовувати й "ABC_1-2 (3)" зупинявся на "ABC_1-2".
                $s = rtrim(self::applyFilenameKeyRule($s, $ruleId));
            }
            if ($s === $before) {
                break;
            }
        }

        return trim($s);
    }

    /**
     * Усі ключі, за якими має сенс шукати товар, від найдовшого до найкоротшого.
     *
     * normalizeFilenameBase() зрізає суфікси до нерухомої точки, і це руйнує SKU,
     * у якому дефіс із цифрами — частина самого артикулу: `SS-203758-1` ставав `SS`,
     * тож ані друге фото, ані навіть перше не знаходили товар. Тут же кожен проміжний
     * стан лишається кандидатом, а вибір робить БД — виграє перший, що реально існує.
     *
     * @param string   $baseName назва файлу без розширення
     * @param string[] $ruleIds  результат sanitizeFilenameKeyRules()
     *
     * @return string[] унікальні непорожні ключі; перший — саме ім'я файлу
     */
    public static function filenameKeyCandidates(string $baseName, array $ruleIds): array
    {
        $s = trim($baseName);
        if ($s === '') {
            return [];
        }

        // Ключі масиву — щоб дешево відсіювати дублі. Числові SKU (`480132102047`)
        // PHP приводить до int, тож на виході повертаємо їх назад у рядки.
        $candidates = [$s => true];
        if ($ruleIds === []) {
            return self::candidateKeysToStrings($candidates);
        }

        $enabled = array_flip($ruleIds);
        $order = self::filenameKeyRuleOrder();
        $maxPasses = 16;

        for ($pass = 0; $pass < $maxPasses; $pass++) {
            $before = $s;
            foreach ($order as $ruleId) {
                if (!isset($enabled[$ruleId])) {
                    continue;
                }
                $next = rtrim(self::applyFilenameKeyRule($s, $ruleId));
                if ($next === $s) {
                    continue;
                }
                $s = $next;
                $trimmed = trim($s);
                if ($trimmed !== '') {
                    $candidates[$trimmed] = true;
                }
            }
            if ($s === $before) {
                break;
            }
        }

        return self::candidateKeysToStrings($candidates);
    }

    /**
     * @param array<array-key, true> $candidates
     *
     * @return string[]
     */
    private static function candidateKeysToStrings(array $candidates): array
    {
        return array_map(strval(...), array_keys($candidates));
    }

    private static function applyFilenameKeyRule(string $s, string $ruleId): string
    {
        switch ($ruleId) {
            case self::FILENAME_KEY_RULE_UNDERSCORE_NUM:
                return (string) preg_replace('/_\d+$/u', '', $s);
            case self::FILENAME_KEY_RULE_DASH_NUM:
                return (string) preg_replace('/-\d+$/u', '', $s);
            case self::FILENAME_KEY_RULE_PAREN_NUM:
                return (string) preg_replace('/\(\d+\)$/u', '', $s);
            case self::FILENAME_KEY_RULE_SPACE_PAREN_NUM:
                return (string) preg_replace('/\s+\(\d+\)$/u', '', $s);
            default:
                return $s;
        }
    }

    /**
     * Триетапний пайплайн імпорту:
     *   1) triage: збираємо кандидатів з direct-аплоадів і ZIP-архівів (без запису на диск)
     *   2) resolve: батч-запит у БД, одна вибірка на attach_mode (а не N)
     *   3) commit: запис у originals/ + INSERT у images
     *
     * @param array       $files                масив файлів (нормалізований з $_FILES)
     * @param string      $attachMode           одна з констант ATTACH_*
     * @param string|null $globalProductId      зарезервовано для сумісності сигнатури
     * @param string[]    $filenameKeyRules     для ATTACH_FILENAME_*: правила нормалізації
     * @param string      $existingFilesPolicy  одна з EXISTING_FILES_*
     * @param string      $missingProductPolicy одна з ON_MISSING_PRODUCT_*
     * @param string|null $importKey            ключ уже початого імпорту (наступна пачка файлів)
     */
    public function processUploadedFiles(
        array $files,
        string $attachMode = self::ATTACH_NONE,
        ?string $globalProductId = null,
        array $filenameKeyRules = [],
        string $existingFilesPolicy = self::EXISTING_FILES_SKIP,
        string $missingProductPolicy = self::ON_MISSING_PRODUCT_SKIP,
        ?string $importKey = null
    ): array {
        $result = [
            'success' => [],
            'errors' => [],
            'warnings' => [],
            'total' => 0,
            'processed' => 0,
            'import_key' => self::isValidImportKey($importKey) ? $importKey : self::generateImportKey(),
        ];

        $candidates = [];
        $openZips = [];

        try {
            foreach ($files as $file) {
                if (!is_array($file) || empty($file['tmp_name'])) {
                    continue;
                }
                $filename = basename($file['name']);
                $fileExt = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

                if ($fileExt === 'zip') {
                    $this->collectZipCandidates($file['tmp_name'], $candidates, $openZips, $result);
                } elseif (in_array($fileExt, self::ALLOWED_EXTENSIONS, true)) {
                    $this->collectDirectCandidate($file, $candidates, $result);
                } else {
                    $result['errors'][] = "Невідомий формат файлу: {$filename}";
                    $result['total']++;
                }
            }

            $productIdMap = $this->batchResolveProductIds(
                $candidates,
                $attachMode,
                $globalProductId,
                $filenameKeyRules
            );

            $this->commitCandidates(
                $candidates,
                $productIdMap,
                $attachMode,
                $existingFilesPolicy,
                $missingProductPolicy,
                $openZips,
                $result['import_key'],
                $result
            );

            if ($attachMode !== self::ATTACH_NONE) {
                $this->backfillMainImageIds($productIdMap);
            }
        } finally {
            foreach ($openZips as $zip) {
                $zip->close();
            }
        }

        return ExtenderFacade::execute(__METHOD__, $result, func_get_args());
    }

    private function collectDirectCandidate(array $file, array &$candidates, array &$result): void
    {
        $filename = basename($file['name']);
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        if (!is_uploaded_file($file['tmp_name'])) {
            $result['errors'][] = "Невалідний файл: {$filename}";
            $result['total']++;

            return;
        }

        if ($file['size'] > self::MAX_FILE_SIZE) {
            $result['errors'][] = "Файл занадто великий: {$filename} (макс. 10MB)";
            $result['total']++;

            return;
        }

        $candidates[] = [
            'src' => self::SOURCE_DIRECT,
            'tmpPath' => $file['tmp_name'],
            'displayName' => $filename,
            'nameFromFile' => pathinfo($filename, PATHINFO_FILENAME),
            'ext' => $ext,
        ];
    }

    /**
     * Зчитує маніфест ZIP без розпаковування на диск; реальний extract відкладений до commit-фази
     * (stream напряму в originals/, без проміжного tempdir).
     */
    private function collectZipCandidates(string $zipPath, array &$candidates, array &$openZips, array &$result): void
    {
        $zip = new \ZipArchive();
        if ($zip->open($zipPath) !== true) {
            $result['errors'][] = 'Помилка при відкритті ZIP архіву';

            return;
        }

        $uncompressedSize = 0;
        $pending = [];
        $count = $zip->numFiles;
        for ($i = 0; $i < $count; $i++) {
            $stat = $zip->statIndex($i);
            if ($stat === false) {
                continue;
            }

            $entryName = $stat['name'];
            if ($entryName === '' || str_ends_with($entryName, '/')) {
                continue;
            }

            $size = isset($stat['size']) ? (int) $stat['size'] : 0;
            $uncompressedSize += $size;
            if ($uncompressedSize > self::MAX_ZIP_UNCOMPRESSED_SIZE) {
                $result['errors'][] = 'ZIP архів занадто великий (макс. 100MB розпакованого розміру)';
                $zip->close();

                return;
            }

            if (str_contains($entryName, "\0") || str_contains($entryName, '..')) {
                $result['errors'][] = 'Невалідний шлях до файлу: ' . basename($entryName);
                continue;
            }

            $base = basename($entryName);
            $ext = strtolower(pathinfo($base, PATHINFO_EXTENSION));
            if (!in_array($ext, self::ALLOWED_EXTENSIONS, true)) {
                continue;
            }

            $pending[] = [
                'src' => self::SOURCE_ZIP,
                'zipPath' => $zipPath,
                'entryName' => $entryName,
                'displayName' => $base,
                'nameFromFile' => pathinfo($base, PATHINFO_FILENAME),
                'ext' => $ext,
            ];
        }

        if ($pending === []) {
            $zip->close();

            return;
        }

        $openZips[$zipPath] = $zip;
        foreach ($pending as $cand) {
            $candidates[] = $cand;
        }
    }

    /**
     * Один-два батч-запити в БД замість N окремих find()-ів — головний виграш по швидкості.
     *
     * @return array<int, int|string> індекс кандидата → product_id (int) або текст помилки (string)
     */
    private function batchResolveProductIds(
        array $candidates,
        string $attachMode,
        ?string $globalProductId,
        array $filenameKeyRules
    ): array {
        if ($attachMode === self::ATTACH_NONE || $candidates === []) {
            return [];
        }

        $candidateLists = [];
        $uniqueKeys = [];
        $out = [];
        foreach ($candidates as $i => $cand) {
            $raw = trim($cand['nameFromFile']);
            if ($raw === '') {
                $out[$i] = 'Порожня назва файлу (без розширення)';
                continue;
            }
            $keys = self::filenameKeyCandidates($raw, $filenameKeyRules);
            if ($keys === []) {
                $out[$i] = 'Після нормалізації назви файлу ключ порожній';
                continue;
            }
            $candidateLists[$i] = $keys;
            foreach ($keys as $key) {
                $uniqueKeys[$key] = true;
            }
        }

        if ($candidateLists === []) {
            return $out;
        }

        // Одна вибірка на всі кандидати всіх файлів — запитів стільки ж, скільки й було.
        $uniqueKeys = self::candidateKeysToStrings($uniqueKeys);

        if ($attachMode === self::ATTACH_FILENAME_SKU) {
            return $out + $this->resolveBySkuBatch($candidateLists, $uniqueKeys);
        }

        if ($attachMode === self::ATTACH_FILENAME_PRODUCT_ID) {
            return $out + $this->resolveByProductIdBatch($candidateLists, $uniqueKeys);
        }

        return $out;
    }

    /**
     * Текст для журналу: що саме шукали, коли товар не знайшовся.
     *
     * @param string[] $keys
     */
    private static function describeTriedKeys(array $keys): string
    {
        $head = array_shift($keys);
        if ($keys === []) {
            return (string) $head;
        }

        return $head . ' (пробували також: ' . implode(', ', $keys) . ')';
    }

    /**
     * @param array<int, string[]> $candidateLists індекс файлу → ключі від найдовшого
     * @param string[]             $uniqueKeys
     *
     * @return array<int, int|string>
     */
    private function resolveBySkuBatch(array $candidateLists, array $uniqueKeys): array
    {
        $productIdBySku = [];
        $duplicateSkus = [];

        $variants = $this->variantsEntity
            ->cols(['sku', 'product_id'])
            ->noLimit()
            ->find(['sku' => $uniqueKeys]);

        foreach ($variants as $v) {
            $sku = $v->sku;
            if (isset($productIdBySku[$sku])) {
                $duplicateSkus[$sku] = true;
            } else {
                $productIdBySku[$sku] = (int) $v->product_id;
            }
        }

        $out = [];
        foreach ($candidateLists as $i => $keys) {
            $out[$i] = 'Варіант з SKU не знайдений: ' . self::describeTriedKeys($keys);
            foreach ($keys as $key) {
                // Найдовший збіг виграє: `SS-203758-1` спершу пробує себе, потім
                // `SS-203758` — і зупиняється там, не доходячи до огризка `SS`.
                if (isset($duplicateSkus[$key])) {
                    $out[$i] = "Кілька варіантів з однаковим SKU: {$key}";
                    break;
                }
                if (isset($productIdBySku[$key])) {
                    $out[$i] = $productIdBySku[$key];
                    break;
                }
            }
        }

        return $out;
    }

    /**
     * @param array<int, string[]> $candidateLists індекс файлу → ключі від найдовшого
     * @param string[]             $uniqueKeys
     *
     * @return array<int, int|string>
     */
    private function resolveByProductIdBatch(array $candidateLists, array $uniqueKeys): array
    {
        $numericKeys = [];
        $stringKeys = [];
        foreach ($uniqueKeys as $k) {
            if (is_numeric($k)) {
                $numericKeys[(int) $k] = true;
            } else {
                $stringKeys[] = $k;
            }
        }

        $byId = [];
        if ($numericKeys !== []) {
            $rows = $this->productsEntity
                ->cols(['id'])
                ->noLimit()
                ->find(['id' => array_keys($numericKeys)]);
            foreach ($rows as $row) {
                $id = is_object($row) ? (int) $row->id : (int) $row;
                $byId[$id] = $id;
            }
        }

        $byExternalId = [];
        if ($stringKeys !== []) {
            $rows = $this->productsEntity
                ->cols(['id', 'external_id'])
                ->noLimit()
                ->find(['external_id' => $stringKeys]);
            foreach ($rows as $row) {
                $byExternalId[$row->external_id] ??= (int) $row->id;
            }
        }

        $out = [];
        foreach ($candidateLists as $i => $keys) {
            $out[$i] = 'Товар не знайдений за ID/external_id: ' . self::describeTriedKeys($keys);
            foreach ($keys as $key) {
                if (is_numeric($key) && isset($byId[(int) $key])) {
                    $out[$i] = (int) $key;
                    break;
                }
                if (isset($byExternalId[$key])) {
                    $out[$i] = $byExternalId[$key];
                    break;
                }
            }
        }

        return $out;
    }

    private function commitCandidates(
        array $candidates,
        array $productIdMap,
        string $attachMode,
        string $existingFilesPolicy,
        string $missingProductPolicy,
        array $openZips,
        string $importKey,
        array &$result
    ): void {
        if ($candidates === []) {
            return;
        }

        $originalsDir = $this->config->root_dir . $this->config->original_images_dir;

        foreach ($candidates as $i => $cand) {
            $resolved = $productIdMap[$i] ?? null;
            $isFilenameAttachMode = in_array($attachMode, [self::ATTACH_FILENAME_SKU, self::ATTACH_FILENAME_PRODUCT_ID], true);
            if ($isFilenameAttachMode && $missingProductPolicy === self::ON_MISSING_PRODUCT_SKIP && !is_int($resolved)) {
                $message = is_string($resolved) ? $resolved : 'Не вдалося знайти товар';
                $result['warnings'][] = $cand['displayName'] . ': ' . $message . '. Файл пропущено';
                $this->addImportLogRow(
                    $importKey,
                    $cand['displayName'],
                    null,
                    null,
                    'warning',
                    'skipped_missing_product',
                    $attachMode,
                    $existingFilesPolicy,
                    $message . '. Файл пропущено'
                );
                $result['total']++;
                continue;
            }

            $sanitized = $this->sanitizeFilename($cand['nameFromFile']);
            $sourceExists = is_file($originalsDir . $sanitized . '.' . $cand['ext']);
            $stored = $this->resolveStorageFilename($sanitized, $cand['ext'], $existingFilesPolicy, $originalsDir);
            if ($stored === null) {
                $result['warnings'][] = $cand['displayName'] . ': файл уже є на сервері, пропущено';
                $this->addImportLogRow(
                    $importKey,
                    $cand['displayName'],
                    null,
                    null,
                    'warning',
                    'skipped_existing',
                    $attachMode,
                    $existingFilesPolicy,
                    'Файл уже є на сервері, пропущено'
                );
                $result['total']++;
                continue;
            }

            $uploadPath = $originalsDir . $stored;
            if ($existingFilesPolicy === self::EXISTING_FILES_OVERWRITE && is_file($uploadPath)) {
                @unlink($uploadPath);
            }

            if (!$this->writeCandidate($cand, $uploadPath, $openZips)) {
                $result['errors'][] = "Помилка при завантаженні: {$cand['displayName']}";
                $this->addImportLogRow(
                    $importKey,
                    $cand['displayName'],
                    $stored,
                    null,
                    'error',
                    'upload_failed',
                    $attachMode,
                    $existingFilesPolicy,
                    'Помилка при завантаженні файлу'
                );
                $result['total']++;
                continue;
            }

            $result['success'][] = $stored;
            $result['processed']++;
            $result['total']++;

            if ($attachMode === self::ATTACH_NONE) {
                $this->addImportLogRow(
                    $importKey,
                    $cand['displayName'],
                    $stored,
                    null,
                    'success',
                    $sourceExists && $existingFilesPolicy === self::EXISTING_FILES_OVERWRITE ? 'overwritten' : 'saved',
                    $attachMode,
                    $existingFilesPolicy,
                    $sourceExists && $existingFilesPolicy === self::EXISTING_FILES_OVERWRITE
                        ? 'Файл перезаписано на сервері'
                        : 'Файл збережено'
                );
                continue;
            }

            if (is_int($resolved)) {
                $insertResult = $this->insertImageRow($stored, $resolved, $cand['displayName'], $existingFilesPolicy);
                if (!$insertResult['success']) {
                    $result['warnings'][] = $cand['displayName'] . ': ' . $insertResult['error'];
                    $this->addImportLogRow(
                        $importKey,
                        $cand['displayName'],
                        $stored,
                        $resolved,
                        'warning',
                        'attach_failed',
                        $attachMode,
                        $existingFilesPolicy,
                        (
                            $sourceExists && $existingFilesPolicy === self::EXISTING_FILES_OVERWRITE
                                ? 'Файл перезаписано, але '
                                : 'Файл збережено, але '
                        ) . $insertResult['error']
                    );
                } else {
                    $this->addImportLogRow(
                        $importKey,
                        $cand['displayName'],
                        $stored,
                        $resolved,
                        'success',
                        'attached',
                        $attachMode,
                        $existingFilesPolicy,
                        $sourceExists && $existingFilesPolicy === self::EXISTING_FILES_OVERWRITE
                            ? (
                                !empty($insertResult['reused_existing_image'])
                                    ? 'Файл перезаписано'
                                    : 'Файл перезаписано та прикріплено до товару'
                            )
                            : 'Фото прикріплено до товару'
                    );
                }
            } elseif (is_string($resolved)) {
                $result['warnings'][] = $cand['displayName'] . ': ' . $resolved;
                $this->addImportLogRow(
                    $importKey,
                    $cand['displayName'],
                    $stored,
                    null,
                    'warning',
                    'attach_skipped',
                    $attachMode,
                    $existingFilesPolicy,
                    (
                        $sourceExists && $existingFilesPolicy === self::EXISTING_FILES_OVERWRITE
                            ? 'Файл перезаписано, '
                            : 'Файл збережено, '
                    ) . $resolved
                );
            } else {
                $result['warnings'][] = $cand['displayName'] . ': не вдалося прив\'язати до товару';
                $this->addImportLogRow(
                    $importKey,
                    $cand['displayName'],
                    $stored,
                    null,
                    'warning',
                    'attach_skipped',
                    $attachMode,
                    $existingFilesPolicy,
                    (
                        $sourceExists && $existingFilesPolicy === self::EXISTING_FILES_OVERWRITE
                            ? 'Файл перезаписано, '
                            : 'Файл збережено, '
                    ) . 'не вдалося прив’язати до товару'
                );
            }
        }
    }

    private function addImportLogRow(
        string $importKey,
        string $filename,
        ?string $storedFilename,
        ?int $productId,
        string $status,
        ?string $operation,
        string $attachMode,
        string $existingFilesPolicy,
        ?string $message
    ): void {
        $row = new \stdClass();
        $row->import_key = $importKey;
        $row->filename = $filename;
        $row->stored_filename = $storedFilename;
        $row->product_id = $productId;
        $row->status = $status;
        $row->operation = $operation;
        $row->attach_mode = $attachMode;
        $row->existing_files_policy = $existingFilesPolicy;
        $row->message = $message;
        $row->created_at = date('Y-m-d H:i:s');
        $this->importLogEntity->add($row);
    }

    /**
     * Direct upload → move_uploaded_file (атомарний rename у межах ФС tmp/originals).
     * ZIP entry → stream_copy_to_stream без проміжного tempdir.
     */
    private function writeCandidate(array $cand, string $uploadPath, array $openZips): bool
    {
        if ($cand['src'] === self::SOURCE_DIRECT) {
            return @move_uploaded_file($cand['tmpPath'], $uploadPath);
        }

        if ($cand['src'] !== self::SOURCE_ZIP) {
            return false;
        }

        $zip = $openZips[$cand['zipPath']] ?? null;
        if ($zip === null) {
            return false;
        }

        $stream = $zip->getStream($cand['entryName']);
        if (!is_resource($stream)) {
            return false;
        }

        $out = @fopen($uploadPath, 'wb');
        if (!is_resource($out)) {
            fclose($stream);

            return false;
        }

        $copied = stream_copy_to_stream($stream, $out);
        fclose($stream);
        fclose($out);

        if ($copied === false) {
            @unlink($uploadPath);

            return false;
        }

        return true;
    }

    /**
     * @return array{success: bool, error?: string, reused_existing_image?: bool}
     */
    private function insertImageRow(
        string $filename,
        int $productId,
        string $contextLabel,
        string $existingFilesPolicy = self::EXISTING_FILES_SKIP
    ): array
    {
        // Для режиму "перезаписати" не створюємо дубль у images, якщо цей файл уже прив'язаний до товару.
        if ($existingFilesPolicy === self::EXISTING_FILES_OVERWRITE) {
            $existingImage = $this->imagesEntity->findOne([
                'product_id' => $productId,
                'filename' => $filename,
            ]);
            if (!empty($existingImage->id)) {
                return [
                    'success' => true,
                    'reused_existing_image' => true,
                ];
            }
        }

        $image = new \stdClass();
        $image->product_id = $productId;
        $image->filename = $filename;
        $image->name = '';

        $imageId = $this->imagesEntity->add($image);

        if ($imageId === false) {
            return [
                'success' => false,
                'error' => "Помилка при додаванні зображення ({$contextLabel})",
            ];
        }

        // Списки товарів (адмінка/каталог) беруть прев'ю з products.main_image_id.
        // Якщо поле порожнє, проставляємо щойно імпортоване фото як головне.
        $product = $this->productsEntity
            ->cols(['id', 'main_image_id'])
            ->findOne(['id' => $productId]);

        if (!empty($product->id) && empty($product->main_image_id)) {
            $this->productsEntity->update($productId, ['main_image_id' => (int) $imageId]);
        }

        return ['success' => true];
    }

    /**
     * Для всіх успішно зарезолвених товарів гарантує main_image_id, якщо є хоча б одне фото.
     */
    private function backfillMainImageIds(array $productIdMap): void
    {
        $productIds = [];
        foreach ($productIdMap as $resolved) {
            if (is_int($resolved)) {
                $productIds[$resolved] = true;
            }
        }

        if ($productIds === []) {
            return;
        }

        foreach (array_keys($productIds) as $productId) {
            $product = $this->productsEntity
                ->cols(['id', 'main_image_id'])
                ->findOne(['id' => $productId]);

            if (empty($product->id) || !empty($product->main_image_id)) {
                continue;
            }

            $firstImage = $this->imagesEntity
                ->cols(['id'])
                ->findOne(['product_id' => $productId]);

            if (!empty($firstImage->id)) {
                $this->productsEntity->update($productId, ['main_image_id' => (int) $firstImage->id]);
            }
        }
    }

    /**
     * Підібрати ім'я файлу в теці originals: унікальне, стабільне з перезаписом або null якщо пропуск.
     */
    private function resolveStorageFilename(string $sanitizedBase, string $extension, string $policy, string $originalsDir): ?string
    {
        $ext = strtolower($extension);
        $base = $sanitizedBase !== '' ? $sanitizedBase : 'upload';

        if ($policy === self::EXISTING_FILES_UNIQUE) {
            // 8 байт ентропії (2^64) + timestamp роблять колізію в межах однієї секунди фактично неможливою —
            // одного виклику достатньо, не потрібен цикл з is_file().
            return $this->generateUniqueFilename($base, $ext);
        }

        $stable = $base . '.' . $ext;
        if (!is_file($originalsDir . $stable)) {
            return $stable;
        }
        if ($policy === self::EXISTING_FILES_SKIP) {
            return null;
        }
        if ($policy === self::EXISTING_FILES_OVERWRITE) {
            return $stable;
        }

        return $this->generateUniqueFilename($base, $ext);
    }

    private function sanitizeFilename(string $filename): string
    {
        $filename = preg_replace('/[^a-z0-9_\-]/i', '_', $filename);
        $filename = preg_replace('/_+/', '_', $filename);

        return trim($filename, '_');
    }

    private function generateUniqueFilename(string $baseName, string $extension): string
    {
        return $baseName . '_' . time() . '_' . bin2hex(random_bytes(8)) . '.' . $extension;
    }
}

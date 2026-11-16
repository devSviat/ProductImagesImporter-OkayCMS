<?php

namespace Okay\Modules\Sviat\ProductImagesImporter\Backend\Helpers;

use Okay\Core\Config;
use Okay\Core\EntityFactory;
use Okay\Core\Modules\Extender\ExtenderFacade;
use Okay\Core\Translit;
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
     * у якому дефіс із цифрами — частина самого артикулу: `AB-123456-1` ставав `AB`,
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

        // Ключі масиву — щоб дешево відсіювати дублі. Числові SKU (`400000000017`)
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
        /** @var list<array{0: string, 1: string, 2: string}> $rejected [ім'я, status, повідомлення] */
        $rejected = [];

        try {
            foreach ($files as $file) {
                if (!is_array($file) || !isset($file['name'])) {
                    continue;
                }
                $filename = basename((string) $file['name']);
                $uploadError = (int) ($file['error'] ?? UPLOAD_ERR_OK);
                // Файл понад upload_max_filesize приходить без tmp_name: без цієї гілки
                // він зникав з імпорту без жодного сліду в журналі.
                if ($uploadError !== UPLOAD_ERR_OK || empty($file['tmp_name'])) {
                    if ($uploadError !== UPLOAD_ERR_NO_FILE) {
                        $rejected[] = [$filename, 'error', self::describeUploadError($uploadError)];
                    }
                    continue;
                }
                $fileExt = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

                if ($fileExt === 'zip') {
                    $this->collectZipCandidates($file['tmp_name'], $filename, $candidates, $openZips, $rejected);
                } elseif (in_array($fileExt, self::ALLOWED_EXTENSIONS, true)) {
                    $this->collectDirectCandidate($file, $candidates, $rejected);
                } else {
                    $rejected[] = [$filename, 'error', 'Невідомий формат файлу (дозволено JPG, PNG, GIF, WebP або ZIP)'];
                }
            }

            foreach ($rejected as [$name, $status, $message]) {
                $result[$status === 'error' ? 'errors' : 'warnings'][] = $name . ': ' . $message;
                $this->logOutcome($result, $result['import_key'], $name, null, null, $status, 'rejected', $attachMode, $existingFilesPolicy, $message);
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
        } finally {
            foreach ($openZips as $zip) {
                $zip->close();
            }
        }

        return ExtenderFacade::execute(__METHOD__, $result, func_get_args());
    }

    private static function describeUploadError(int $code): string
    {
        return match ($code) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Файл більший за ліміт сервера (upload_max_filesize), не завантажено',
            UPLOAD_ERR_PARTIAL => 'Файл завантажився не повністю',
            default => "Сервер не прийняв файл (код помилки {$code})",
        };
    }

    protected function isUploadedFile(string $path): bool
    {
        return is_uploaded_file($path);
    }

    /**
     * @param list<array{0: string, 1: string, 2: string}> $rejected
     */
    private function collectDirectCandidate(array $file, array &$candidates, array &$rejected): void
    {
        $filename = basename($file['name']);
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        if (!$this->isUploadedFile($file['tmp_name'])) {
            $rejected[] = [$filename, 'error', 'Невалідний файл'];

            return;
        }

        if ((int) $file['size'] > self::MAX_FILE_SIZE) {
            $rejected[] = [$filename, 'error', 'Файл занадто великий (макс. 10MB)'];

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
     * Зчитує маніфест ZIP без розпаковування на диск; вміст читається лише в commit-фазі,
     * по одному файлу.
     *
     * @param list<array{0: string, 1: string, 2: string}> $rejected
     */
    private function collectZipCandidates(string $zipPath, string $zipName, array &$candidates, array &$openZips, array &$rejected): void
    {
        $zip = new \ZipArchive();
        if ($zip->open($zipPath) !== true) {
            $rejected[] = [$zipName, 'error', 'Не вдалося відкрити ZIP-архів'];

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

            // Ім'я на диску береться лише з basename і проходить санітизацію,
            // тож шлях усередині архіву вийти за межі originals не може.
            $base = basename($entryName);
            // `__MACOSX/._A.jpg` (AppleDouble від архіватора macOS) має ту саму назву й
            // розширення, що й фото, і в режимі «Перезаписати» затирав би справжній файл.
            if (str_starts_with($entryName, '__MACOSX/') || str_starts_with($base, '.')) {
                continue;
            }

            $size = isset($stat['size']) ? (int) $stat['size'] : 0;
            $uncompressedSize += $size;
            if ($uncompressedSize > self::MAX_ZIP_UNCOMPRESSED_SIZE) {
                $rejected[] = [$zipName, 'error', 'ZIP-архів занадто великий (макс. 100MB розпакованого розміру), архів пропущено'];
                $zip->close();

                return;
            }

            if (str_contains($entryName, "\0")) {
                $rejected[] = [$base, 'error', 'Невалідний шлях до файлу в ZIP'];
                continue;
            }

            $ext = strtolower(pathinfo($base, PATHINFO_EXTENSION));
            if (!in_array($ext, self::ALLOWED_EXTENSIONS, true)) {
                $rejected[] = [$base, 'warning', 'Непідтримуваний формат у ZIP, файл пропущено'];
                continue;
            }

            if ($size > self::MAX_FILE_SIZE) {
                $rejected[] = [$base, 'error', 'Файл занадто великий (макс. 10MB)'];
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
     * MySQL порівнює sku/external_id без урахування регістру й хвостових пробілів
     * і повертає значення з БД, а не те, що шукали. Мапа мусить зводити обидва боки однаково.
     */
    private static function lookupKey(string $value): string
    {
        return mb_strtolower(trim($value));
    }

    /**
     * @param array<int, string[]> $candidateLists індекс файлу → ключі від найдовшого
     * @param string[]             $uniqueKeys
     *
     * @return array<int, int|string>
     */
    private function resolveBySkuBatch(array $candidateLists, array $uniqueKeys): array
    {
        /** @var array<string, array<int, true>> $exact */
        $exact = [];
        /** @var array<string, array<int, true>> $loose */
        $loose = [];

        $variants = $this->variantsEntity
            ->cols(['sku', 'product_id'])
            ->noLimit()
            ->find(['sku' => $uniqueKeys]);

        // Множини товарів, а не перший-ліпший: кілька варіантів одного товару з тим
        // самим SKU — не двозначність, фото однаково належить цьому товару.
        foreach ($variants as $v) {
            $productId = (int) $v->product_id;
            $exact[trim((string) $v->sku)][$productId] = true;
            $loose[self::lookupKey((string) $v->sku)][$productId] = true;
        }

        $out = [];
        foreach ($candidateLists as $i => $keys) {
            $out[$i] = 'Варіант з SKU не знайдений: ' . self::describeTriedKeys($keys);
            foreach ($keys as $key) {
                // Найдовший збіг виграє: `AB-123456-1` спершу пробує себе, потім
                // `AB-123456` — і зупиняється там, не доходячи до огризка `AB`.
                // Точний регістр важливіший: `ab-12` і `AB-12` можуть бути різними товарами.
                $productIds = $exact[$key] ?? $loose[self::lookupKey($key)] ?? [];
                if (count($productIds) > 1) {
                    $out[$i] = "Кілька товарів з однаковим SKU: {$key}";
                    break;
                }
                if (count($productIds) === 1) {
                    $out[$i] = (int) array_key_first($productIds);
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
            // is_numeric() пропускав `1e3` і `12.5`, а (int) робив з них 1000 і 12.
            if (ctype_digit($k)) {
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
        $byExternalIdLoose = [];
        if ($stringKeys !== []) {
            $rows = $this->productsEntity
                ->cols(['id', 'external_id'])
                ->noLimit()
                ->find(['external_id' => $stringKeys]);
            foreach ($rows as $row) {
                $byExternalId[trim((string) $row->external_id)] ??= (int) $row->id;
                $byExternalIdLoose[self::lookupKey((string) $row->external_id)] ??= (int) $row->id;
            }
        }

        $out = [];
        foreach ($candidateLists as $i => $keys) {
            $out[$i] = 'Товар не знайдений за ID/external_id: ' . self::describeTriedKeys($keys);
            foreach ($keys as $key) {
                if (ctype_digit($key) && isset($byId[(int) $key])) {
                    $out[$i] = (int) $key;
                    break;
                }
                $byExternal = $byExternalId[$key] ?? $byExternalIdLoose[self::lookupKey($key)] ?? null;
                if ($byExternal !== null) {
                    $out[$i] = $byExternal;
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
        $isFilenameAttachMode = in_array($attachMode, [self::ATTACH_FILENAME_SKU, self::ATTACH_FILENAME_PRODUCT_ID], true);

        $productIds = [];
        foreach ($productIdMap as $resolved) {
            if (is_int($resolved)) {
                $productIds[$resolved] = $resolved;
            }
        }
        $productIds = array_values($productIds);
        $attachedImages = $this->loadAttachedImages($productIds);

        foreach ($candidates as $i => $cand) {
            $resolved = $productIdMap[$i] ?? null;
            if ($isFilenameAttachMode && $missingProductPolicy === self::ON_MISSING_PRODUCT_SKIP && !is_int($resolved)) {
                $message = is_string($resolved) ? $resolved : 'Не вдалося знайти товар';
                $result['warnings'][] = $cand['displayName'] . ': ' . $message . '. Файл пропущено';
                $this->logOutcome($result, $importKey, $cand['displayName'], null, null, 'warning', 'skipped_missing_product', $attachMode, $existingFilesPolicy, $message . '. Файл пропущено');
                continue;
            }

            $content = $this->readCandidate($cand, $openZips);
            $invalidReason = $content === null ? 'Не вдалося прочитати файл' : self::describeInvalidImage($content);
            if ($invalidReason !== null) {
                $result['errors'][] = $cand['displayName'] . ': ' . $invalidReason;
                $this->logOutcome($result, $importKey, $cand['displayName'], null, null, 'error', 'rejected', $attachMode, $existingFilesPolicy, $invalidReason);
                continue;
            }
            $cand['content'] = $content;

            $base = $this->sanitizeFilename($cand['nameFromFile']);
            $stable = $base . '.' . $cand['ext'];
            $stableOnDisk = is_file($originalsDir . $stable);
            $productId = $isFilenameAttachMode && is_int($resolved) ? $resolved : null;

            if ($productId !== null) {
                $plan = $this->planReuseOfAttachedImage(
                    $cand,
                    $attachedImages[$productId] ?? [],
                    $base,
                    $stable,
                    $stableOnDisk,
                    $existingFilesPolicy,
                    $originalsDir
                );
                if ($plan !== null && $plan['action'] === 'skip') {
                    $message = 'Файл з такою назвою вже є на сервері, новий вміст не застосовано';
                    $result['warnings'][] = $cand['displayName'] . ': ' . $message;
                    $this->logOutcome($result, $importKey, $cand['displayName'], null, $productId, 'warning', 'skipped_existing', $attachMode, $existingFilesPolicy, $message);
                    continue;
                }
                if ($plan !== null) {
                    $reusedFilename = $plan['filename'];
                    if ($plan['write'] && !$this->storeCandidate($cand, $originalsDir, $reusedFilename, $stableOnDisk)) {
                        $result['errors'][] = "Помилка при завантаженні: {$cand['displayName']}";
                        $this->logOutcome($result, $importKey, $cand['displayName'], $reusedFilename, $productId, 'error', 'upload_failed', $attachMode, $existingFilesPolicy, 'Помилка при завантаженні файлу');
                        continue;
                    }
                    $suffix = '';
                    if ($plan['repairFrom'] !== null) {
                        $attachedImages[$productId] = $this->renameAttachedImage($attachedImages[$productId], $plan['repairFrom'], $reusedFilename);
                        $suffix = " Назву в рядку виправлено: «{$plan['repairFrom']}» → «{$reusedFilename}»";
                    }
                    $prefix = match (true) {
                        !$plan['write'] => 'Такий самий файл уже є на сервері. ',
                        $stableOnDisk => 'Файл перезаписано. ',
                        default => 'Файл збережено. ',
                    };
                    $result['success'][] = $reusedFilename;
                    $result['processed']++;
                    $this->logOutcome($result, $importKey, $cand['displayName'], $reusedFilename, $productId, 'success', 'already_attached', $attachMode, $existingFilesPolicy, $prefix . 'Фото вже прикріплено до товару, новий рядок не створено.' . $suffix);
                    continue;
                }
            }

            $stored = $this->resolveStorageFilename($base, $cand['ext'], $existingFilesPolicy, $originalsDir);
            if ($stored === null) {
                $result['warnings'][] = $cand['displayName'] . ': файл уже є на сервері, пропущено';
                $this->logOutcome($result, $importKey, $cand['displayName'], null, null, 'warning', 'skipped_existing', $attachMode, $existingFilesPolicy, 'Файл уже є на сервері, пропущено');
                continue;
            }

            $overwritten = $stored === $stable && $stableOnDisk;
            if (!$this->storeCandidate($cand, $originalsDir, $stored, $overwritten)) {
                $result['errors'][] = "Помилка при завантаженні: {$cand['displayName']}";
                $this->logOutcome($result, $importKey, $cand['displayName'], $stored, null, 'error', 'upload_failed', $attachMode, $existingFilesPolicy, 'Помилка при завантаженні файлу');
                continue;
            }

            $result['success'][] = $stored;
            $result['processed']++;
            $savedPrefix = $overwritten ? 'Файл перезаписано' : 'Файл збережено';

            if ($attachMode === self::ATTACH_NONE) {
                $this->logOutcome($result, $importKey, $cand['displayName'], $stored, null, 'success', $overwritten ? 'overwritten' : 'saved', $attachMode, $existingFilesPolicy, $overwritten ? 'Файл перезаписано на сервері' : 'Файл збережено');
                continue;
            }

            if ($productId === null) {
                $reason = is_string($resolved) ? $resolved : 'не вдалося прив’язати до товару';
                $result['warnings'][] = $cand['displayName'] . ': ' . $reason;
                $this->logOutcome($result, $importKey, $cand['displayName'], $stored, null, 'warning', 'attach_skipped', $attachMode, $existingFilesPolicy, $savedPrefix . ', ' . $reason);
                continue;
            }

            $imageId = $this->insertImageRow($stored, $productId);
            if ($imageId === null) {
                $error = "Помилка при додаванні зображення ({$cand['displayName']})";
                $result['warnings'][] = $cand['displayName'] . ': ' . $error;
                $this->logOutcome($result, $importKey, $cand['displayName'], $stored, $productId, 'warning', 'attach_failed', $attachMode, $existingFilesPolicy, $savedPrefix . ', але ' . $error);
                continue;
            }

            $attachedImages[$productId][$stored] = $imageId;
            $this->logOutcome($result, $importKey, $cand['displayName'], $stored, $productId, 'success', 'attached', $attachMode, $existingFilesPolicy, $overwritten ? 'Файл перезаписано та прикріплено до товару' : 'Фото прикріплено до товару');
        }

        $this->backfillMainImageIds($productIds, $attachedImages);
    }

    /**
     * Чи вже є в товару рядок images для цього файлу. CSV-імпорт товарів створює рядок
     * за назвою з колонки images ще до того, як файл з'являється на диску, тож без цієї
     * перевірки імпорт фото додавав другий рядок із тим самим іменем.
     *
     * Назви порівнюються байт у байт: колація MySQL нечутлива до регістру, а на диску
     * `x1.jpg` і `X1.jpg` — різні файли.
     *
     * @param array<string, int> $attached filename → id рядка images цього товару
     *
     * @return array{action: 'reuse'|'skip', filename: string, write: bool, repairFrom: ?string}|null
     *         reuse — лишити наявний рядок (write: чи писати файл; repairFrom: стара назва
     *         в рядку, яку треба замінити на filename); skip — інший вміст під тією самою
     *         назвою в режимі «Пропустити»; null — рядка нема, фото додається як нове
     */
    private function planReuseOfAttachedImage(
        array $cand,
        array $attached,
        string $base,
        string $stable,
        bool $stableOnDisk,
        string $policy,
        string $originalsDir
    ): ?array {
        $hash = hash('sha256', $cand['content']);
        $raw = $cand['displayName'];
        $repairFrom = null;

        // CSV пише назву як є (`ABC.JPG`, `AB 12.jpg`, `фото.jpg`), а файл ляже під
        // санітизованою. Рядок без файлу лагодимо; рядок із живим файлом не чіпаємо.
        if (!isset($attached[$stable]) && $raw !== $stable && isset($attached[$raw])) {
            if (is_file($originalsDir . $raw)) {
                if (hash_file('sha256', $originalsDir . $raw) === $hash) {
                    return ['action' => 'reuse', 'filename' => $raw, 'write' => false, 'repairFrom' => null];
                }
            } else {
                $repairFrom = $raw;
                $attached[$stable] = $attached[$raw];
            }
        }

        if (isset($attached[$stable])) {
            if (!$stableOnDisk || $policy === self::EXISTING_FILES_OVERWRITE) {
                return ['action' => 'reuse', 'filename' => $stable, 'write' => true, 'repairFrom' => $repairFrom];
            }
            if (hash_file('sha256', $originalsDir . $stable) === $hash) {
                return ['action' => 'reuse', 'filename' => $stable, 'write' => false, 'repairFrom' => $repairFrom];
            }
            if ($policy === self::EXISTING_FILES_SKIP) {
                return ['action' => 'skip', 'filename' => $stable, 'write' => false, 'repairFrom' => null];
            }
        }

        if ($policy !== self::EXISTING_FILES_UNIQUE) {
            return null;
        }

        // «Унікальне ім'я» щоразу дає нову назву, тож повторний імпорт того самого фото
        // впізнається лише за вмістом серед уже прикріплених копій цього файлу.
        $copyPattern = '/^' . preg_quote($base, '/') . '_\d+_[0-9a-f]{16}\.' . preg_quote($cand['ext'], '/') . '$/';
        foreach (array_keys($attached) as $filename) {
            $filename = (string) $filename;
            if (preg_match($copyPattern, $filename) !== 1 || !is_file($originalsDir . $filename)) {
                continue;
            }
            if (hash_file('sha256', $originalsDir . $filename) === $hash) {
                return ['action' => 'reuse', 'filename' => $filename, 'write' => false, 'repairFrom' => null];
            }
        }

        return null;
    }

    /**
     * Порядок ключів зберігається: перший елемент — перше фото товару для main_image_id.
     *
     * @param array<string, int> $attached
     *
     * @return array<string, int>
     */
    private function renameAttachedImage(array $attached, string $from, string $to): array
    {
        $this->imagesEntity->update($attached[$from], ['filename' => $to]);

        $out = [];
        foreach ($attached as $filename => $id) {
            $out[(string) $filename === $from ? $to : (string) $filename] = $id;
        }

        return $out;
    }

    /**
     * Читаємо щонайбільше MAX_FILE_SIZE + 1 байт: розмір у заголовку ZIP задає автор
     * архіву, тож межу перевіряємо за фактично розпакованими даними.
     */
    private function readCandidate(array $cand, array $openZips): ?string
    {
        if ($cand['src'] === self::SOURCE_DIRECT) {
            $content = @file_get_contents($cand['tmpPath'], false, null, 0, self::MAX_FILE_SIZE + 1);

            return $content === false ? null : $content;
        }

        $zip = $openZips[$cand['zipPath']] ?? null;
        $stream = $zip?->getStream($cand['entryName']);
        if (!is_resource($stream)) {
            return null;
        }
        $content = stream_get_contents($stream, self::MAX_FILE_SIZE + 1);
        fclose($stream);

        return $content === false ? null : $content;
    }

    /**
     * Розширення каже лише, як файл назвали. Не-зображення в режимі «Перезаписати»
     * знищило б справжнє фото, а нарізка на ньому однаково впала б.
     */
    private static function describeInvalidImage(string $content): ?string
    {
        if (strlen($content) > self::MAX_FILE_SIZE) {
            return 'Файл занадто великий (макс. 10MB)';
        }

        $info = @getimagesizefromstring($content);
        if ($info === false || !in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_GIF, IMAGETYPE_WEBP], true)) {
            return 'Файл не є зображенням JPG, PNG, GIF або WebP';
        }

        return null;
    }

    /**
     * Рядки images вибраних товарів одним запитом: filename → id у порядку position,
     * тож перший елемент — перше фото товару.
     *
     * @param int[] $productIds
     *
     * @return array<int, array<string, int>>
     */
    private function loadAttachedImages(array $productIds): array
    {
        if ($productIds === []) {
            return [];
        }

        $out = [];
        $rows = $this->imagesEntity
            ->cols(['id', 'product_id', 'filename', 'position'])
            ->noLimit()
            ->find(['product_id' => $productIds]);
        foreach ($rows as $row) {
            $out[(int) $row->product_id][(string) $row->filename] ??= (int) $row->id;
        }

        return $out;
    }

    private function logOutcome(
        array &$result,
        string $importKey,
        string $filename,
        ?string $storedFilename,
        ?int $productId,
        string $status,
        string $operation,
        string $attachMode,
        string $existingFilesPolicy,
        string $message
    ): void {
        $result['total']++;
        $this->addImportLogRow($importKey, $filename, $storedFilename, $productId, $status, $operation, $attachMode, $existingFilesPolicy, $message);
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

    private function storeCandidate(array $cand, string $originalsDir, string $filename, bool $replacesExisting): bool
    {
        // Назва може прийти з images.filename (сирий CSV): на диск і в glob нарізок
        // пускаємо лише санітизовану.
        if (preg_match('/^[A-Za-z0-9_-]+\.(jpe?g|png|gif|webp)$/', $filename) !== 1) {
            return false;
        }
        $path = $originalsDir . $filename;
        // Пишемо поруч і перейменовуємо: при збої запису старий файл лишається цілим.
        $tmpPath = $path . '.part-' . bin2hex(random_bytes(4));
        if (@file_put_contents($tmpPath, $cand['content']) === false || !@rename($tmpPath, $path)) {
            @unlink($tmpPath);

            return false;
        }
        if ($replacesExisting) {
            $this->deleteResizedCopies($filename);
        }

        return true;
    }

    /**
     * Нарізки ріжуться на льоту лише тоді, коли їх нема на диску, тож після
     * перезапису оригіналу сайт віддавав би старе фото. Маска та сама, що в ImagesEntity::delete().
     */
    private function deleteResizedCopies(string $filename): void
    {
        $resizedDir = (string) $this->config->get('resized_images_dir');
        if ($resizedDir === '') {
            return;
        }

        $file = pathinfo($filename, PATHINFO_FILENAME);
        $ext = pathinfo($filename, PATHINFO_EXTENSION);
        $prefix = $this->config->get('root_dir') . $resizedDir . $file;
        // Маска з ImagesEntity::delete() ловить і `X.v2.100x100.jpg` — нарізку іншого
        // оригіналу `X.v2.jpg`; регулярка відсіює все, крім формату Image::addResizeParams().
        $own = '/^' . preg_quote($file, '/') . '\.\d*x\d*w?(\.[^.\/]+\.[^.\/]+)?\.' . preg_quote($ext, '/') . '(\.webp)?$/';
        foreach (glob($prefix . '.*x*.' . $ext . '*') ?: [] as $resized) {
            if (preg_match($own, basename($resized)) === 1) {
                @unlink($resized);
            }
        }
    }

    /**
     * @return int|null id нового рядка images; null — вставка не вдалась
     */
    private function insertImageRow(string $filename, int $productId): ?int
    {
        $image = new \stdClass();
        $image->product_id = $productId;
        $image->filename = $filename;
        $image->name = '';

        $imageId = $this->imagesEntity->add($image);

        return $imageId === false ? null : (int) $imageId;
    }

    /**
     * Списки товарів (адмінка/каталог) беруть прев'ю з products.main_image_id.
     * Порожнє поле заповнюємо першим фото товару.
     *
     * @param int[]                          $productIds
     * @param array<int, array<string, int>> $attachedImages
     */
    private function backfillMainImageIds(array $productIds, array $attachedImages): void
    {
        if ($productIds === []) {
            return;
        }

        $products = $this->productsEntity
            ->cols(['id', 'main_image_id'])
            ->noLimit()
            ->find(['id' => $productIds]);

        foreach ($products as $product) {
            $productId = (int) $product->id;
            if (!empty($product->main_image_id) || empty($attachedImages[$productId])) {
                continue;
            }
            $this->productsEntity->update($productId, ['main_image_id' => reset($attachedImages[$productId])]);
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
        $original = $filename;
        // Без транслітерації кирилична назва ставала порожньою, і всі такі файли
        // лягали в один `upload.jpg`. Регістр латиниці не чіпаємо: від нього залежать
        // уже наявні імена в originals і рядки images.
        if (preg_match('/[^\x00-\x7F]/', $filename) === 1) {
            foreach (Translit::getTranslitPairs() as $pair) {
                $filename = str_replace(explode('-', $pair['from']), explode('-', $pair['to']), $filename);
            }
        }
        $filename = (string) preg_replace('/[^a-z0-9_\-]/i', '_', $filename);
        $filename = (string) preg_replace('/_+/', '_', $filename);
        $filename = trim($filename, '_');

        return $filename !== '' ? $filename : 'upload_' . substr(sha1($original), 0, 12);
    }

    private function generateUniqueFilename(string $baseName, string $extension): string
    {
        return $baseName . '_' . time() . '_' . bin2hex(random_bytes(8)) . '.' . $extension;
    }
}

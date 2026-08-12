<?php

namespace Okay\Modules\Sviat\ProductImagesImporter\Backend\Controllers;

use Okay\Admin\Controllers\IndexAdmin;
use Okay\Core\BackendTranslations;
use Okay\Core\QueryFactory;
use Okay\Core\Request;
use Okay\Core\Response;
use Okay\Modules\Sviat\ProductImagesImporter\Backend\Helpers\ProductImagesImportHelper;
use Okay\Modules\Sviat\ProductImagesImporter\Entities\ProductImagesImportLogEntity;

class ProductImagesImporterAdmin extends IndexAdmin
{
    public function fetch(
        ProductImagesImportHelper $importHelper,
        ProductImagesImportLogEntity $importLogEntity,
        BackendTranslations $backendTranslations,
        QueryFactory $queryFactory,
        Request $request,
        Response $response
    ) {
        $rulesSkuPost = isset($_POST['filename_key_rules_sku']) && is_array($_POST['filename_key_rules_sku'])
            ? $_POST['filename_key_rules_sku']
            : [];
        $rulesPidPost = isset($_POST['filename_key_rules_product_id']) && is_array($_POST['filename_key_rules_product_id'])
            ? $_POST['filename_key_rules_product_id']
            : [];
        $sanitizedSkuRules = ProductImagesImportHelper::sanitizeFilenameKeyRules($rulesSkuPost);
        $sanitizedPidRules = ProductImagesImportHelper::sanitizeFilenameKeyRules($rulesPidPost);
        $this->design->assign('pii_filename_key_rules_sku', $sanitizedSkuRules);
        $this->design->assign('pii_filename_key_rules_pid', $sanitizedPidRules);

        $existingPolicyRaw = isset($_POST['existing_files_policy']) && is_string($_POST['existing_files_policy'])
            ? $_POST['existing_files_policy']
            : '';
        $existingFilesPolicy = ProductImagesImportHelper::sanitizeExistingFilesPolicy($existingPolicyRaw);
        $this->design->assign('pii_existing_files_policy', $existingFilesPolicy);
        $missingProductPolicyRaw = isset($_POST['missing_product_policy']) && is_string($_POST['missing_product_policy'])
            ? $_POST['missing_product_policy']
            : '';
        $missingProductPolicy = ProductImagesImportHelper::sanitizeMissingProductPolicy($missingProductPolicyRaw);
        $this->design->assign('pii_missing_product_policy', $missingProductPolicy);

        if ($request->post('upload_images')) {
            $attachMode = $request->post('attach_mode', 'string') ?: ProductImagesImportHelper::ATTACH_NONE;
            if (!ProductImagesImportHelper::isValidAttachMode($attachMode)) {
                $attachMode = ProductImagesImportHelper::ATTACH_NONE;
            }
            $files = $this->normalizeFiles();

            $filenameKeyRulesForUpload = [];
            if ($attachMode === ProductImagesImportHelper::ATTACH_FILENAME_SKU) {
                $filenameKeyRulesForUpload = $sanitizedSkuRules;
            } elseif ($attachMode === ProductImagesImportHelper::ATTACH_FILENAME_PRODUCT_ID) {
                $filenameKeyRulesForUpload = $sanitizedPidRules;
            }

            if (empty($files)) {
                $emptyFilesError = $backendTranslations->getTranslation('sviat__product_images_importer__files_required_error');
                if ($request->post('ajax', 'boolean')) {
                    $response->setContent(json_encode([
                        'success' => false,
                        'error' => $emptyFilesError,
                    ], JSON_UNESCAPED_UNICODE), RESPONSE_JSON);
                    return;
                }

                $this->design->assign('import_result', [
                    'success' => [],
                    'errors' => [$emptyFilesError],
                    'warnings' => [],
                    'total' => 0,
                    'processed' => 0,
                ]);
            } else {
                // Велика вибірка приходить пачками, кожна окремим запитом. Ключ першої
                // пачки повертається клієнту й далі їде з рештою — журнал чистимо тільки
                // на першій, інакше кожна наступна стирала б попередні.
                $importKeyRaw = $request->post('import_key', 'string');
                $importKey = ProductImagesImportHelper::isValidImportKey($importKeyRaw) ? $importKeyRaw : null;

                if ($importKey === null) {
                    // Як у стандартному імпорті товарів: перед новим імпортом очищуємо журнал.
                    $queryFactory->newSqlQuery()
                        ->setStatement('TRUNCATE __sviat_product_images_import_log')
                        ->execute();
                }

                $result = $importHelper->processUploadedFiles(
                    $files,
                    $attachMode,
                    null,
                    $filenameKeyRulesForUpload,
                    $existingFilesPolicy,
                    $missingProductPolicy,
                    $importKey
                );

                // PHP мовчки відкидає файли понад max_file_uploads: помилки в $_FILES нема,
                // модуль просто не бачить решти. Єдиний доступний сигнал — упертися в ліміт.
                $maxFileUploads = (int) ini_get('max_file_uploads');
                if ($maxFileUploads > 0 && count($files) >= $maxFileUploads) {
                    $result['warnings'][] = sprintf(
                        $backendTranslations->getTranslation('sviat__product_images_importer__max_file_uploads_warning'),
                        $maxFileUploads
                    );
                }

                if ($request->post('ajax', 'boolean')) {
                    $response->setContent(json_encode([
                        'success' => true,
                        'import_key' => $result['import_key'] ?? null,
                        'redirect_url' => 'index.php?controller=Sviat.ProductImagesImporter.ProductImagesImportLogAdmin&import_key=' . urlencode((string) ($result['import_key'] ?? '')),
                    ], JSON_UNESCAPED_UNICODE), RESPONSE_JSON);
                    return;
                }

                $this->design->assign('import_result', $result);
            }
        }

        $lastImport = $importLogEntity->find(['limit' => 1, 'sort' => 'id_desc']);
        if (!empty($lastImport)) {
            $this->design->assign('last_import_key', reset($lastImport)->import_key);
        }

        $response->setContent($this->design->fetch('product_images_importer.tpl'));
    }

    /**
     * Нормалізація масиву $_FILES для обробки
     */
    private function normalizeFiles(): array
    {
        $files = [];

        if (empty($_FILES['product_images'])) {
            return $files;
        }

        if (is_array($_FILES['product_images']['name'])) {
            for ($i = 0; $i < count($_FILES['product_images']['name']); $i++) {
                $files[] = [
                    'name' => $_FILES['product_images']['name'][$i],
                    'type' => $_FILES['product_images']['type'][$i],
                    'tmp_name' => $_FILES['product_images']['tmp_name'][$i],
                    'error' => $_FILES['product_images']['error'][$i],
                    'size' => $_FILES['product_images']['size'][$i],
                ];
            }
        } else {
            $files[] = $_FILES['product_images'];
        }

        return $files;
    }
}

<?php

namespace Okay\Modules\Sviat\ProductImagesImporter\Init;

use Okay\Core\Modules\AbstractInit;
use Okay\Core\Modules\EntityField;

class Init extends AbstractInit
{
    private const PERMISSION = 'sviat__product_images_importer';
    private const IMPORT_LOG_TABLE = '__sviat_product_images_import_log';

    public function install()
    {
        $this->migrateImportLogTable();
        $this->setBackendMainController('ProductImagesImporterAdmin');
    }

    public function update_1_1_0()
    {
        $this->migrateImportLogTable();
    }

    public function init()
    {
        $this->registerBackendController('ProductImagesImporterAdmin');
        $this->registerBackendController('ProductImagesImportLogAdmin');
        $this->addBackendControllerPermission('ProductImagesImporterAdmin', self::PERMISSION);
        $this->addBackendControllerPermission('ProductImagesImportLogAdmin', self::PERMISSION);
    }

    private function migrateImportLogTable(): void
    {
        $this->migrateCustomTable(self::IMPORT_LOG_TABLE, [
            (new EntityField('id'))->setIndexPrimaryKey()->setTypeInt(11, false)->setAutoIncrement(),
            (new EntityField('import_key'))->setTypeVarchar(64)->setIndex(),
            (new EntityField('filename'))->setTypeVarchar(255),
            (new EntityField('stored_filename'))->setTypeVarchar(255, true),
            (new EntityField('product_id'))->setTypeInt(11, true)->setIndex(),
            (new EntityField('status'))->setTypeVarchar(32)->setIndex(),
            (new EntityField('operation'))->setTypeVarchar(64, true)->setIndex(),
            (new EntityField('attach_mode'))->setTypeVarchar(64, true),
            (new EntityField('existing_files_policy'))->setTypeVarchar(32, true),
            (new EntityField('message'))->setTypeText(),
            (new EntityField('created_at'))->setTypeDatetime()->setIndex(),
        ]);
    }
}

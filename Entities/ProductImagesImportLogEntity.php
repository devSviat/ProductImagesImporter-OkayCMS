<?php

namespace Okay\Modules\Sviat\ProductImagesImporter\Entities;

use Aura\SqlQuery\Common\Select;
use Okay\Core\Entity\Entity;

class ProductImagesImportLogEntity extends Entity
{
    /** @var Select */
    protected $select;

    protected static $table = '__sviat_product_images_import_log';
    protected static $tableAlias = 'pil';

    protected static $fields = [
        'id',
        'import_key',
        'filename',
        'stored_filename',
        'product_id',
        'status',
        'operation',
        'attach_mode',
        'existing_files_policy',
        'message',
        'created_at',
    ];

    protected static $searchFields = [
        'filename',
        'stored_filename',
        'message',
    ];

    protected function filter__keyword($keyword)
    {
        $kw = trim((string) $keyword);
        if ($kw === '') {
            return;
        }

        $this->select->where(
            "(
                pil.filename LIKE :keyword
                OR pil.stored_filename LIKE :keyword
                OR pil.message LIKE :keyword
            )"
        )->bindValue('keyword', '%' . $kw . '%');
    }
}

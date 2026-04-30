<?php

namespace Okay\Modules\Sviat\ProductImagesImporter\Backend\Controllers;

use Okay\Admin\Controllers\IndexAdmin;
use Okay\Core\Request;
use Okay\Modules\Sviat\ProductImagesImporter\Entities\ProductImagesImportLogEntity;

class ProductImagesImportLogAdmin extends IndexAdmin
{
    public function fetch(Request $request, ProductImagesImportLogEntity $importLogEntity)
    {
        $filter = [];
        $filter['page'] = max(1, $request->get('page', 'integer'));
        $filter['limit'] = max(25, min(500, $request->get('limit', 'integer') ?: 100));
        $filter['sort'] = 'id_desc';

        $importKey = trim((string) $request->get('import_key', 'string'));
        $keyword = trim((string) $request->get('keyword'));
        $status = trim((string) $request->get('status', 'string'));

        if ($importKey === '') {
            $last = $importLogEntity->find(['limit' => 1, 'sort' => 'id_desc']);
            if (!empty($last)) {
                $importKey = (string) reset($last)->import_key;
            }
        }

        if ($importKey !== '') {
            $filter['import_key'] = $importKey;
            $this->design->assign('import_key', $importKey);
        }
        if ($keyword !== '') {
            $filter['keyword'] = $keyword;
            $this->design->assign('keyword', $keyword);
        }
        if (in_array($status, ['success', 'warning', 'error'], true)) {
            $filter['status'] = $status;
            $this->design->assign('status', $status);
        }

        $logsCount = $importLogEntity->count($filter);
        $pagesCount = $filter['limit'] > 0 ? (int) ceil($logsCount / $filter['limit']) : 0;
        $filter['page'] = min($filter['page'], max(1, $pagesCount));

        $logs = $importLogEntity->find($filter);
        $this->design->assign('logs', $logs);
        $this->design->assign('logs_count', $logsCount);
        $this->design->assign('pages_count', $pagesCount);
        $this->design->assign('current_page', $filter['page']);
        $this->design->assign('current_limit', $filter['limit']);

        $this->response->setContent($this->design->fetch('product_images_import_log.tpl'));
    }
}

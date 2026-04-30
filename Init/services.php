<?php

namespace Okay\Modules\Sviat\ProductImagesImporter\Init;

use Okay\Core\Config;
use Okay\Core\EntityFactory;
use Okay\Core\OkayContainer\Reference\ServiceReference as SR;
use Okay\Modules\Sviat\ProductImagesImporter\Backend\Helpers\ProductImagesImportHelper;

return [
    ProductImagesImportHelper::class => [
        'class' => ProductImagesImportHelper::class,
        'arguments' => [
            new SR(Config::class),
            new SR(EntityFactory::class),
        ],
    ],
];

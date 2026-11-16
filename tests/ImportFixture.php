<?php

namespace Modules\Sviat\ProductImagesImporter;

use Okay\Core\Config;
use Okay\Core\EntityFactory;
use Okay\Entities\ImagesEntity;
use Okay\Entities\ProductsEntity;
use Okay\Entities\VariantsEntity;
use Okay\Modules\Sviat\ProductImagesImporter\Backend\Helpers\ProductImagesImportHelper;
use Okay\Modules\Sviat\ProductImagesImporter\Entities\ProductImagesImportLogEntity;
use PHPUnit\Framework\TestCase;

/**
 * Таблиці images / products / variants і журнал у пам'яті плюс тимчасовий корінь
 * із теками оригіналів і нарізок. Фільтри find() повторюють поведінку MySQL там,
 * де вона важлива для імпорту: порівняння sku/external_id без урахування регістру.
 */
abstract class ImportFixture extends TestCase
{
    /** 1×1 GIF; ImportFixture::image() міняє колір палітри, щоб отримати інший вміст. */
    private const string GIF_1X1 = 'R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7';

    protected string $root;
    protected string $originals;
    protected string $resized;

    /** @var array<int, \stdClass> */
    protected array $images = [];
    /** @var array<int, \stdClass> */
    protected array $products = [];
    /** @var list<\stdClass> */
    protected array $variants = [];
    /** @var list<\stdClass> */
    protected array $log = [];

    /** @var array<string, int> лічильник звернень до БД: "entity.method" → кількість */
    protected array $queries = [];

    private int $nextImageId = 1000;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/pii_' . bin2hex(random_bytes(6)) . '/';
        $this->originals = $this->root . 'files/originals/products/';
        $this->resized = $this->root . 'files/resized/products/';
        mkdir($this->originals, 0777, true);
        mkdir($this->resized, 0777, true);
    }

    protected function tearDown(): void
    {
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->root, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($it as $f) {
            $f->isDir() ? rmdir($f->getPathname()) : unlink($f->getPathname());
        }
        rmdir($this->root);
    }

    protected static function image(int $variant = 0): string
    {
        $bytes = base64_decode(self::GIF_1X1);
        // Байти 13..15 — перший колір глобальної палітри.
        return substr_replace($bytes, chr($variant % 256) . chr(intdiv($variant, 256) % 256) . "\x01", 13, 3);
    }

    protected function addProduct(int $id, ?int $mainImageId = null, ?string $externalId = null): void
    {
        $this->products[$id] = (object) ['id' => $id, 'main_image_id' => $mainImageId, 'external_id' => $externalId];
    }

    protected function addVariant(string $sku, int $productId): void
    {
        $this->variants[] = (object) ['sku' => $sku, 'product_id' => $productId];
    }

    /** Рядок images, який створив би CSV-імпорт товарів. */
    protected function addImageRow(int $productId, string $filename): int
    {
        $id = $this->nextImageId++;
        $this->images[$id] = (object) ['id' => $id, 'product_id' => $productId, 'filename' => $filename, 'name' => '', 'position' => $id];

        return $id;
    }

    /** @return list<\stdClass> */
    protected function imagesOf(int $productId): array
    {
        return array_values(array_filter($this->images, static fn($r) => (int) $r->product_id === $productId));
    }

    /** @return list<string> */
    protected function operations(): array
    {
        return array_map(static fn($r) => (string) $r->operation, $this->log);
    }

    /**
     * @param array<string, string> $entries ім'я всередині архіву → вміст
     *
     * @return array<string, mixed> елемент нормалізованого $_FILES
     */
    protected function zip(array $entries): array
    {
        $path = $this->root . 'upload_' . bin2hex(random_bytes(4)) . '.zip';
        $zip = new \ZipArchive();
        $zip->open($path, \ZipArchive::CREATE);
        foreach ($entries as $name => $content) {
            $zip->addFromString($name, $content);
        }
        $zip->close();

        return ['name' => 'photos.zip', 'type' => 'application/zip', 'tmp_name' => $path, 'error' => UPLOAD_ERR_OK, 'size' => filesize($path)];
    }

    protected function helper(): ProductImagesImportHelper
    {
        $config = $this->getMockBuilder(Config::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['get'])
            ->getMock();
        $config->method('get')->willReturnCallback(fn(string $name) => match ($name) {
            'root_dir' => $this->root,
            'original_images_dir' => 'files/originals/products/',
            'resized_images_dir' => 'files/resized/products/',
            default => null,
        });

        $factory = $this->createStub(EntityFactory::class);
        $map = [
            ImagesEntity::class => $this->tableMock(ImagesEntity::class, 'images'),
            ProductsEntity::class => $this->tableMock(ProductsEntity::class, 'products'),
            VariantsEntity::class => $this->tableMock(VariantsEntity::class, 'variants'),
            ProductImagesImportLogEntity::class => $this->tableMock(ProductImagesImportLogEntity::class, 'log'),
        ];
        $factory->method('get')->willReturnCallback(static fn(string $class) => $map[$class]);

        return $this->makeHelper($config, $factory);
    }

    protected function makeHelper(Config $config, EntityFactory $factory): ProductImagesImportHelper
    {
        return new ProductImagesImportHelper($config, $factory);
    }

    /** @param list<array<string, mixed>> $files */
    protected function import(
        array $files,
        string $attachMode,
        string $existingPolicy,
        string $missingPolicy = ProductImagesImportHelper::ON_MISSING_PRODUCT_SKIP,
        array $rules = []
    ): array {
        return $this->helper()->processUploadedFiles($files, $attachMode, null, $rules, $existingPolicy, $missingPolicy);
    }

    private function tableMock(string $class, string $table): object
    {
        $mock = $this->getMockBuilder($class)
            ->disableOriginalConstructor()
            ->onlyMethods(['cols', 'noLimit', 'find', 'findOne', 'add', 'update'])
            ->getMock();
        $mock->method('cols')->willReturnSelf();
        $mock->method('noLimit')->willReturnSelf();

        $mock->method('find')->willReturnCallback(function (array $filter = []) use ($table) {
            $this->queries[$table . '.find'] = ($this->queries[$table . '.find'] ?? 0) + 1;

            return $this->select($table, $filter);
        });
        $mock->method('findOne')->willReturnCallback(function (array $filter = []) use ($table) {
            $this->queries[$table . '.findOne'] = ($this->queries[$table . '.findOne'] ?? 0) + 1;
            $rows = $this->select($table, $filter);

            return $rows === [] ? false : $rows[0];
        });
        $mock->method('add')->willReturnCallback(function ($object) use ($table) {
            $object = (object) (array) $object;
            if ($table === 'log') {
                $this->log[] = $object;

                return count($this->log);
            }
            if ($table !== 'images') {
                throw new \LogicException("add() у {$table} не очікується");
            }
            $id = $this->nextImageId++;
            $object->id = $id;
            $object->position ??= $id;
            $this->images[$id] = $object;

            return $id;
        });
        $mock->method('update')->willReturnCallback(function ($ids, $object) use ($table) {
            $this->queries[$table . '.update'] = ($this->queries[$table . '.update'] ?? 0) + 1;
            foreach ((array) $ids as $id) {
                foreach ((array) $object as $k => $v) {
                    $this->{$table}[(int) $id]->{$k} = $v;
                }
            }

            return true;
        });

        return $mock;
    }

    /** @return list<\stdClass> */
    private function select(string $table, array $filter): array
    {
        $rows = array_values($this->{$table});
        foreach ($filter as $field => $value) {
            if (in_array($field, ['limit', 'page', 'sort'], true)) {
                continue;
            }
            $wanted = array_map(static fn($v) => mb_strtolower(rtrim((string) $v)), (array) $value);
            $rows = array_values(array_filter(
                $rows,
                static fn($r) => isset($r->{$field}) && in_array(mb_strtolower(rtrim((string) $r->{$field})), $wanted, true)
            ));
        }
        if ($table === 'images') {
            usort($rows, static fn($a, $b) => [$a->product_id, $a->position] <=> [$b->product_id, $b->position]);
        }

        return array_map(static fn($r) => clone $r, $rows);
    }
}

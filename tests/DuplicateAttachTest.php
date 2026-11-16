<?php

namespace Modules\Sviat\ProductImagesImporter;

use Okay\Modules\Sviat\ProductImagesImporter\Backend\Helpers\ProductImagesImportHelper as H;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\DataProvider;

require_once __DIR__ . '/ImportFixture.php';

/**
 * Той самий файл не прикріплюється до товару вдруге.
 *
 * Сценарій: CSV-імпорт товарів створив рядок images `QX0000A001.jpg`
 * (файлу на диску ще нема), потім імпорт фото в режимі «Пропустити» записав файл
 * і вставив другий рядок із тим самим іменем — у товару дві однакові картинки.
 */
#[AllowMockObjectsWithoutExpectations]
class DuplicateAttachTest extends ImportFixture
{
    private const string SKU = 'QX0000A001';
    private const int PRODUCT = 501;

    public static function policies(): array
    {
        return [
            'skip' => [H::EXISTING_FILES_SKIP],
            'unique' => [H::EXISTING_FILES_UNIQUE],
            'overwrite' => [H::EXISTING_FILES_OVERWRITE],
        ];
    }

    public static function attachModes(): array
    {
        $out = [];
        foreach (self::policies() as $policyName => [$policy]) {
            $out["sku × {$policyName}"] = [H::ATTACH_FILENAME_SKU, $policy, self::SKU . '.jpg'];
            $out["id × {$policyName}"] = [H::ATTACH_FILENAME_PRODUCT_ID, $policy, self::PRODUCT . '.jpg'];
        }

        return $out;
    }

    private function seedProduct(): int
    {
        $this->addProduct(self::PRODUCT);
        $this->addVariant(self::SKU, self::PRODUCT);
        $rowId = $this->addImageRow(self::PRODUCT, self::SKU . '.jpg');
        $this->products[self::PRODUCT]->main_image_id = $rowId;

        return $rowId;
    }

    #[DataProvider('policies')]
    public function testCsvRowWithoutFileIsReusedNotDuplicated(string $policy): void
    {
        $rowId = $this->seedProduct();

        $this->import([$this->zip([self::SKU . '.jpg' => self::image(1)])], H::ATTACH_FILENAME_SKU, $policy);

        $rows = $this->imagesOf(self::PRODUCT);
        self::assertCount(1, $rows, 'рядок із CSV-імпорту мав бути перевикористаний');
        self::assertSame($rowId, $rows[0]->id);
        self::assertSame(self::image(1), file_get_contents($this->originals . self::SKU . '.jpg'), 'файл рядка CSV має з\'явитися на диску');
        self::assertSame(['already_attached'], $this->operations());
        self::assertStringContainsString('вже прикріплено до товару', $this->log[0]->message);
        self::assertSame('success', $this->log[0]->status);
        self::assertSame(self::PRODUCT, $this->log[0]->product_id);
    }

    #[DataProvider('policies')]
    public function testCsvRowWithSameFileOnDiskStaysSingle(string $policy): void
    {
        $this->seedProduct();
        file_put_contents($this->originals . self::SKU . '.jpg', self::image(1));

        $this->import([$this->zip([self::SKU . '.jpg' => self::image(1)])], H::ATTACH_FILENAME_SKU, $policy);

        self::assertCount(1, $this->imagesOf(self::PRODUCT));
        self::assertSame(['already_attached'], $this->operations());
        self::assertSame([self::SKU . '.jpg'], array_values(array_diff(scandir($this->originals), ['.', '..'])), 'зайвих копій файлу на диску не має бути');
    }

    #[DataProvider('attachModes')]
    public function testRepeatedImportKeepsOneRow(string $attachMode, string $policy, string $filename): void
    {
        $this->addProduct(self::PRODUCT);
        $this->addVariant(self::SKU, self::PRODUCT);

        $this->import([$this->zip([$filename => self::image(1)])], $attachMode, $policy);
        $this->import([$this->zip([$filename => self::image(1)])], $attachMode, $policy);

        $rows = $this->imagesOf(self::PRODUCT);
        self::assertCount(1, $rows);
        self::assertSame($rows[0]->id, $this->products[self::PRODUCT]->main_image_id);
        self::assertSame('attached', $this->operations()[0]);
        self::assertSame('already_attached', $this->operations()[1]);
    }

    /** Два однакові файли в одній пачці (наприклад, із різних тек ZIP) — один рядок. */
    #[DataProvider('policies')]
    public function testSameFileTwiceInOneUploadKeepsOneRow(string $policy): void
    {
        $this->addProduct(self::PRODUCT);
        $this->addVariant(self::SKU, self::PRODUCT);

        $this->import([$this->zip([
            'a/' . self::SKU . '.jpg' => self::image(1),
            'b/' . self::SKU . '.jpg' => self::image(1),
        ])], H::ATTACH_FILENAME_SKU, $policy);

        self::assertCount(1, $this->imagesOf(self::PRODUCT));
    }

    /**
     * Контроль: гард дивиться саме на пару product_id + filename. Той самий файл
     * в іншого товару — не дубль, рядок нашому товару мусить з'явитися.
     */
    #[DataProvider('policies')]
    public function testSameFilenameOnAnotherProductIsNotADuplicate(string $policy): void
    {
        $this->addProduct(self::PRODUCT);
        $this->addProduct(777);
        $this->addVariant(self::SKU, self::PRODUCT);
        $this->addImageRow(777, self::SKU . '.jpg');

        $this->import([$this->zip([self::SKU . '.jpg' => self::image(1)])], H::ATTACH_FILENAME_SKU, $policy);

        self::assertCount(1, $this->imagesOf(self::PRODUCT));
        self::assertCount(1, $this->imagesOf(777));
        self::assertSame(['attached'], $this->operations());
    }

    /**
     * `x00000001.jpg` і `X00000001.jpg` — два різні файли на Linux і можуть бути двома різними фото.
     * MySQL порівнює filename без урахування регістру, тож гард, що покладається
     * на WHERE filename = …, сплутав би їх і не прикріпив друге фото.
     */
    #[DataProvider('policies')]
    public function testFilenamesDifferingOnlyByCaseAreDifferentImages(string $policy): void
    {
        $this->addProduct(502);
        $this->addVariant('X00000001', 502);
        $this->addImageRow(502, 'x00000001.jpg');
        file_put_contents($this->originals . 'x00000001.jpg', self::image(1));

        $this->import([$this->zip(['X00000001.jpg' => self::image(2)])], H::ATTACH_FILENAME_SKU, $policy);

        $rows = $this->imagesOf(502);
        self::assertCount(2, $rows);
        self::assertStringStartsWith('X00000001', $rows[1]->filename);
        self::assertSame(['attached'], $this->operations());
        self::assertSame(self::image(1), file_get_contents($this->originals . 'x00000001.jpg'));
    }

    /** «Унікальне ім'я» лишає обидва фото, якщо вміст справді інший. */
    public function testUniqueKeepsDifferentContentAsSecondImage(): void
    {
        $this->seedProduct();
        file_put_contents($this->originals . self::SKU . '.jpg', self::image(1));

        $this->import([$this->zip([self::SKU . '.jpg' => self::image(2)])], H::ATTACH_FILENAME_SKU, H::EXISTING_FILES_UNIQUE);

        $rows = $this->imagesOf(self::PRODUCT);
        self::assertCount(2, $rows);
        self::assertMatchesRegularExpression('/^' . self::SKU . '_\d+_[0-9a-f]{16}\.jpg$/', $rows[1]->filename);
        self::assertSame(self::image(1), file_get_contents($this->originals . self::SKU . '.jpg'));
    }

    /** «Перезаписати» оновлює файл і викидає старі нарізки, а рядок лишає той самий. */
    public function testOverwriteRefreshesFileAndDropsResizedCopies(): void
    {
        $rowId = $this->seedProduct();
        file_put_contents($this->originals . self::SKU . '.jpg', self::image(1));
        $foreign = ['OTHER.100x100.jpg', self::SKU . '.v2.100x100.jpg', self::SKU . '_2.100x100.jpg'];
        foreach ([self::SKU . '.100x100.jpg', self::SKU . '.300x300w.jpg', self::SKU . '.100x100.jpg.webp', self::SKU . '.x200.jpg', self::SKU . '.100x100.center.top.jpg', ...$foreign] as $f) {
            file_put_contents($this->resized . $f, 'old');
        }

        $this->import([$this->zip([self::SKU . '.jpg' => self::image(2)])], H::ATTACH_FILENAME_SKU, H::EXISTING_FILES_OVERWRITE);

        self::assertSame(self::image(2), file_get_contents($this->originals . self::SKU . '.jpg'));
        self::assertSame([$rowId], array_map(static fn($r) => $r->id, $this->imagesOf(self::PRODUCT)));
        // `QX….v2.100x100.jpg` — нарізка іншого оригіналу `QX….v2.jpg`, її не чіпаємо.
        $left = array_values(array_diff(scandir($this->resized), ['.', '..']));
        sort($foreign);
        self::assertSame($foreign, $left);
        self::assertStringContainsString('перезаписано', $this->log[0]->message);
    }

    public function testSkipLeavesExistingFileUntouched(): void
    {
        $this->seedProduct();
        file_put_contents($this->originals . self::SKU . '.jpg', self::image(1));

        $this->import([$this->zip([self::SKU . '.jpg' => self::image(2)])], H::ATTACH_FILENAME_SKU, H::EXISTING_FILES_SKIP);

        self::assertSame(self::image(1), file_get_contents($this->originals . self::SKU . '.jpg'));
        self::assertCount(1, $this->imagesOf(self::PRODUCT));
        // Вміст інший і не застосований — це попередження, а не зелене «вже прикріплено».
        self::assertSame(['skipped_existing'], $this->operations());
        self::assertSame('warning', $this->log[0]->status);
    }

    public function testSkipWithDifferentContentTwiceInOneUploadWarnsOnSecond(): void
    {
        $this->addProduct(self::PRODUCT);
        $this->addVariant(self::SKU, self::PRODUCT);

        $this->import([$this->zip([
            'a/' . self::SKU . '.jpg' => self::image(1),
            'b/' . self::SKU . '.jpg' => self::image(2),
        ])], H::ATTACH_FILENAME_SKU, H::EXISTING_FILES_SKIP);

        self::assertCount(1, $this->imagesOf(self::PRODUCT));
        self::assertSame(['attached', 'skipped_existing'], $this->operations());
        self::assertSame('warning', $this->log[1]->status);
    }

    /**
     * CSV-імпорт пише images.filename так, як воно є в CSV, а імпорт фото зберігає файл
     * під санітизованою назвою. Рядок із сирою назвою треба полагодити, а не дублювати.
     */
    public static function rawCsvNames(): array
    {
        $out = [];
        foreach (self::policies() as $policyName => [$policy]) {
            $out["верхній регістр розширення × {$policyName}"] = ['ABC', 'ABC.JPG', 'ABC.jpg', $policy];
            $out["пробіл × {$policyName}"] = ['AB 12', 'AB 12.jpg', 'AB_12.jpg', $policy];
            $out["кирилиця × {$policyName}"] = ['фото', 'фото.jpg', 'foto.jpg', $policy];
        }

        return $out;
    }

    #[DataProvider('rawCsvNames')]
    public function testCsvRowWithRawNameIsRepairedNotDuplicated(string $sku, string $csvName, string $storedName, string $policy): void
    {
        $this->addProduct(self::PRODUCT);
        $this->addVariant($sku, self::PRODUCT);
        $rowId = $this->addImageRow(self::PRODUCT, $csvName);
        $this->products[self::PRODUCT]->main_image_id = $rowId;

        $this->import([$this->zip([$csvName => self::image(1)])], H::ATTACH_FILENAME_SKU, $policy);

        $rows = $this->imagesOf(self::PRODUCT);
        self::assertCount(1, $rows);
        self::assertSame($rowId, $rows[0]->id);
        self::assertSame($storedName, $rows[0]->filename);
        self::assertSame(self::image(1), file_get_contents($this->originals . $storedName));
        self::assertSame($rowId, $this->products[self::PRODUCT]->main_image_id);
        self::assertSame(['already_attached'], $this->operations());
    }

    /** Рядок із сирою назвою, файл якого реально лежить на диску, робочий — його не перейменовуємо. */
    public function testWorkingRawRowWithSameContentIsReusedAsIs(): void
    {
        $this->addProduct(self::PRODUCT);
        $this->addVariant('ABC', self::PRODUCT);
        $rowId = $this->addImageRow(self::PRODUCT, 'ABC.JPG');
        file_put_contents($this->originals . 'ABC.JPG', self::image(1));

        $this->import([$this->zip(['ABC.JPG' => self::image(1)])], H::ATTACH_FILENAME_SKU, H::EXISTING_FILES_SKIP);

        $rows = $this->imagesOf(self::PRODUCT);
        self::assertCount(1, $rows);
        self::assertSame('ABC.JPG', $rows[0]->filename);
        self::assertSame(['already_attached'], $this->operations());
        self::assertFalse(is_file($this->originals . 'ABC.jpg'));
        self::assertSame($rowId, $rows[0]->id);
    }

    /** Звернень до images/products стільки ж на 30 файлів, скільки на один. */
    public function testLookupsDoNotGrowWithFileCount(): void
    {
        $entries = [];
        for ($i = 1; $i <= 30; $i++) {
            $this->addProduct($i);
            $this->addVariant("SKU{$i}", $i);
            $this->addImageRow($i, "SKU{$i}.jpg");
            $entries["SKU{$i}.jpg"] = self::image($i);
        }

        $this->import([$this->zip($entries)], H::ATTACH_FILENAME_SKU, H::EXISTING_FILES_OVERWRITE);

        self::assertSame(30, count($this->images));
        self::assertLessThanOrEqual(1, $this->queries['images.findOne'] ?? 0);
        self::assertLessThanOrEqual(2, ($this->queries['images.find'] ?? 0));
        self::assertLessThanOrEqual(1, ($this->queries['products.findOne'] ?? 0) + ($this->queries['products.find'] ?? 0));
    }
}

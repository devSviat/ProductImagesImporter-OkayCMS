<?php

namespace Modules\Sviat\ProductImagesImporter;

use Okay\Core\Config;
use Okay\Core\EntityFactory;
use Okay\Modules\Sviat\ProductImagesImporter\Backend\Helpers\ProductImagesImportHelper as H;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\DataProvider;

require_once __DIR__ . '/ImportFixture.php';

/**
 * Режими прив'язки × політики наявних файлів × «товар не знайдено», а також
 * розбір ZIP, перевірка вмісту і пошук товару за SKU / ID.
 */
#[AllowMockObjectsWithoutExpectations]
class ImportModesTest extends ImportFixture
{
    private bool $treatAsUploaded = false;

    protected function makeHelper(Config $config, EntityFactory $factory): H
    {
        if (!$this->treatAsUploaded) {
            return parent::makeHelper($config, $factory);
        }

        // is_uploaded_file() у CLI завжди false — підміняємо лише цю перевірку.
        return new class ($config, $factory) extends H {
            protected function isUploadedFile(string $path): bool
            {
                return is_file($path);
            }
        };
    }

    // --- лише зберегти файли ------------------------------------------------

    public function testNoneModeSkipKeepsExistingFile(): void
    {
        file_put_contents($this->originals . 'A.jpg', self::image(1));

        $this->import([$this->zip(['A.jpg' => self::image(2), 'B.jpg' => self::image(3)])], H::ATTACH_NONE, H::EXISTING_FILES_SKIP);

        self::assertSame(self::image(1), file_get_contents($this->originals . 'A.jpg'));
        self::assertSame(self::image(3), file_get_contents($this->originals . 'B.jpg'));
        self::assertSame(['skipped_existing', 'saved'], $this->operations());
        self::assertSame([], $this->images);
    }

    public function testNoneModeOverwriteReplacesFile(): void
    {
        file_put_contents($this->originals . 'A.jpg', self::image(1));

        $this->import([$this->zip(['A.jpg' => self::image(2)])], H::ATTACH_NONE, H::EXISTING_FILES_OVERWRITE);

        self::assertSame(self::image(2), file_get_contents($this->originals . 'A.jpg'));
        self::assertSame(['overwritten'], $this->operations());
    }

    public function testNoneModeUniqueStoresUnderNewName(): void
    {
        file_put_contents($this->originals . 'A.jpg', self::image(1));

        $result = $this->import([$this->zip(['A.jpg' => self::image(2)])], H::ATTACH_NONE, H::EXISTING_FILES_UNIQUE);

        self::assertMatchesRegularExpression('/^A_\d+_[0-9a-f]{16}\.jpg$/', $result['success'][0]);
        self::assertSame(self::image(1), file_get_contents($this->originals . 'A.jpg'));
    }

    // --- товар не знайдено --------------------------------------------------

    public static function policies(): array
    {
        return [
            'skip' => [H::EXISTING_FILES_SKIP],
            'unique' => [H::EXISTING_FILES_UNIQUE],
            'overwrite' => [H::EXISTING_FILES_OVERWRITE],
        ];
    }

    #[DataProvider('policies')]
    public function testMissingProductSkipWritesNothing(string $policy): void
    {
        $this->import([$this->zip(['NOPE.jpg' => self::image(1)])], H::ATTACH_FILENAME_SKU, $policy, H::ON_MISSING_PRODUCT_SKIP);

        self::assertSame([], array_values(array_diff(scandir($this->originals), ['.', '..'])));
        self::assertSame(['skipped_missing_product'], $this->operations());
    }

    #[DataProvider('policies')]
    public function testMissingProductSaveKeepsFileWithoutRow(string $policy): void
    {
        $this->import([$this->zip(['NOPE.jpg' => self::image(1)])], H::ATTACH_FILENAME_PRODUCT_ID, $policy, H::ON_MISSING_PRODUCT_SAVE);

        self::assertCount(1, array_diff(scandir($this->originals), ['.', '..']));
        self::assertSame(['attach_skipped'], $this->operations());
        self::assertSame([], $this->images);
    }

    // --- main_image_id ------------------------------------------------------

    public function testMainImageIsSetOnlyWhenEmpty(): void
    {
        $this->addProduct(1);
        $this->addProduct(2, 555);
        $this->addProduct(3);
        $existing = $this->addImageRow(3, 'OLD.jpg');

        $this->import([$this->zip(['1_1.jpg' => self::image(1), '1_2.jpg' => self::image(2), '2.jpg' => self::image(3), '3.jpg' => self::image(4)])],
            H::ATTACH_FILENAME_PRODUCT_ID, H::EXISTING_FILES_SKIP, H::ON_MISSING_PRODUCT_SKIP, [H::FILENAME_KEY_RULE_UNDERSCORE_NUM]);

        self::assertSame($this->imagesOf(1)[0]->id, $this->products[1]->main_image_id);
        self::assertCount(2, $this->imagesOf(1));
        self::assertSame(555, $this->products[2]->main_image_id);
        self::assertSame($existing, $this->products[3]->main_image_id, 'головним лишається перше фото товару');
    }

    // --- пошук товару ------------------------------------------------------

    public function testSkuRepeatedOnVariantsOfOneProductIsNotAmbiguous(): void
    {
        $this->addProduct(1);
        $this->addVariant('ABC', 1);
        $this->addVariant('ABC', 1);

        $this->import([$this->zip(['ABC.jpg' => self::image(1)])], H::ATTACH_FILENAME_SKU, H::EXISTING_FILES_SKIP);

        self::assertCount(1, $this->imagesOf(1));
    }

    public function testSkuOnDifferentProductsIsAmbiguous(): void
    {
        $this->addProduct(1);
        $this->addProduct(2);
        $this->addVariant('ABC', 1);
        $this->addVariant('ABC', 2);

        $this->import([$this->zip(['ABC.jpg' => self::image(1)])], H::ATTACH_FILENAME_SKU, H::EXISTING_FILES_SKIP);

        self::assertSame([], $this->images);
        self::assertSame(['skipped_missing_product'], $this->operations());
        self::assertStringContainsString('Кілька', $this->log[0]->message);
    }

    /** MySQL знаходить SKU без урахування регістру — і модуль мусить його впізнати. */
    public function testSkuMatchIgnoresCaseAndTrailingSpace(): void
    {
        $this->addProduct(1);
        $this->addProduct(2);
        $this->addVariant('ab-12', 1);
        $this->addVariant('CD-34 ', 2);

        $this->import([$this->zip(['AB-12.jpg' => self::image(1), 'cd-34.jpg' => self::image(2)])], H::ATTACH_FILENAME_SKU, H::EXISTING_FILES_SKIP);

        self::assertCount(1, $this->imagesOf(1));
        self::assertCount(1, $this->imagesOf(2));
    }

    /** SKU `ab-12` і `AB-12` у різних товарах: точний збіг виграє, без точного — двозначність. */
    public function testExactCaseSkuWinsOverCaseInsensitiveMatches(): void
    {
        $this->addProduct(1);
        $this->addProduct(2);
        $this->addVariant('ab-12', 1);
        $this->addVariant('AB-12', 2);

        $this->import([$this->zip(['AB-12.jpg' => self::image(1), 'Ab-12.jpg' => self::image(2)])], H::ATTACH_FILENAME_SKU, H::EXISTING_FILES_SKIP);

        self::assertSame([], $this->imagesOf(1));
        self::assertSame(['AB-12.jpg'], array_map(static fn($r) => $r->filename, $this->imagesOf(2)));
        self::assertSame(['attached', 'skipped_missing_product'], $this->operations());
        self::assertStringContainsString('Кілька', $this->log[1]->message);
    }

    public function testLongestSkuWins(): void
    {
        $this->addProduct(1);
        $this->addProduct(2);
        $this->addVariant('AB', 1);
        $this->addVariant('AB-123456', 2);

        $this->import([$this->zip(['AB-123456-1.jpg' => self::image(1)])], H::ATTACH_FILENAME_SKU, H::EXISTING_FILES_SKIP,
            H::ON_MISSING_PRODUCT_SKIP, [H::FILENAME_KEY_RULE_DASH_NUM]);

        self::assertCount(1, $this->imagesOf(2));
        self::assertSame([], $this->imagesOf(1));
    }

    public function testProductIdAcceptsOnlyDigits(): void
    {
        $this->addProduct(10);
        $this->addProduct(1000);

        $this->import([$this->zip(['1e3.jpg' => self::image(1), '10.jpg' => self::image(2)])], H::ATTACH_FILENAME_PRODUCT_ID, H::EXISTING_FILES_SKIP);

        self::assertSame([], $this->imagesOf(1000), '«1e3» — не номер товару 1000');
        self::assertCount(1, $this->imagesOf(10));
    }

    public function testExternalIdWithSuffixRule(): void
    {
        $this->addProduct(5, null, 'ext-42');

        $this->import([$this->zip(['ext-42_1.jpg' => self::image(1)])], H::ATTACH_FILENAME_PRODUCT_ID, H::EXISTING_FILES_SKIP,
            H::ON_MISSING_PRODUCT_SKIP, [H::FILENAME_KEY_RULE_UNDERSCORE_NUM]);

        self::assertSame('ext-42_1.jpg', $this->imagesOf(5)[0]->filename);
    }

    // --- ZIP і вміст --------------------------------------------------------

    /** Службові файли macOS мають ту саму назву й розширення, але це не картинки. */
    public function testMacosResourceForksAreIgnored(): void
    {
        $this->import([$this->zip([
            'photos/A.jpg' => self::image(1),
            '__MACOSX/photos/._A.jpg' => "\x00\x05\x16\x07appledouble",
            'photos/.DS_Store' => 'junk',
        ])], H::ATTACH_NONE, H::EXISTING_FILES_OVERWRITE);

        self::assertSame(self::image(1), file_get_contents($this->originals . 'A.jpg'));
        self::assertSame(['saved'], $this->operations());
    }

    public function testNonImageContentIsRejectedAndExistingFileSurvives(): void
    {
        file_put_contents($this->originals . 'A.jpg', self::image(1));

        $this->import([$this->zip(['A.jpg' => '<?php echo 1;', 'B.png' => '<html>'])], H::ATTACH_NONE, H::EXISTING_FILES_OVERWRITE);

        self::assertSame(self::image(1), file_get_contents($this->originals . 'A.jpg'));
        self::assertFalse(is_file($this->originals . 'B.png'));
        self::assertSame(['rejected', 'rejected'], $this->operations());
        self::assertSame('error', $this->log[0]->status);
    }

    public function testZipEntryNameWithDoubleDotIsNotRejected(): void
    {
        $this->import([$this->zip(['photos/A..B.jpg' => self::image(1)])], H::ATTACH_NONE, H::EXISTING_FILES_SKIP);

        self::assertSame(['saved'], $this->operations());
    }

    public function testZipTraversalNamesLandInsideOriginals(): void
    {
        $this->import([$this->zip(['../../evil.jpg' => self::image(1)])], H::ATTACH_NONE, H::EXISTING_FILES_SKIP);

        self::assertFalse(is_file($this->root . 'evil.jpg'));
        self::assertFalse(is_file($this->root . 'files/evil.jpg'));
    }

    public function testOversizedZipEntryIsRejected(): void
    {
        $this->import([$this->zip(['BIG.jpg' => self::image(1) . str_repeat("\0", 11 * 1024 * 1024)])], H::ATTACH_NONE, H::EXISTING_FILES_SKIP);

        self::assertFalse(is_file($this->originals . 'BIG.jpg'));
        self::assertSame(['rejected'], $this->operations());
    }

    public function testUnsupportedFileInZipIsLogged(): void
    {
        $this->import([$this->zip(['notes.txt' => 'hi', 'A.jpg' => self::image(1)])], H::ATTACH_NONE, H::EXISTING_FILES_SKIP);

        self::assertSame(['rejected', 'saved'], $this->operations());
    }

    public function testCorruptZipIsLogged(): void
    {
        $path = $this->root . 'corrupt.zip';
        file_put_contents($path, 'not a zip');

        $this->import([['name' => 'corrupt.zip', 'tmp_name' => $path, 'error' => UPLOAD_ERR_OK, 'size' => 9]], H::ATTACH_NONE, H::EXISTING_FILES_SKIP);

        self::assertSame(['rejected'], $this->operations());
        self::assertSame('corrupt.zip', $this->log[0]->filename);
    }

    // --- назви файлів -------------------------------------------------------

    /** Кириличні назви раніше зводились до порожнього рядка й ставали `upload.jpg`. */
    public function testCyrillicNamesDoNotCollapseIntoOneFile(): void
    {
        $result = $this->import([$this->zip(['фото.jpg' => self::image(1), 'кружка.jpg' => self::image(2)])], H::ATTACH_NONE, H::EXISTING_FILES_OVERWRITE);

        self::assertCount(2, array_unique($result['success']));
        self::assertNotContains('upload.jpg', $result['success']);
    }

    // --- прямий аплоад ------------------------------------------------------

    public function testDirectUploadIsSavedAndAttached(): void
    {
        $this->treatAsUploaded = true;
        $this->addProduct(1);
        $this->addVariant('ABC', 1);
        $tmp = $this->root . 'php_tmp1';
        file_put_contents($tmp, self::image(1));

        $this->import([['name' => 'ABC.JPG', 'tmp_name' => $tmp, 'error' => UPLOAD_ERR_OK, 'size' => filesize($tmp)]], H::ATTACH_FILENAME_SKU, H::EXISTING_FILES_SKIP);

        self::assertSame('ABC.jpg', $this->imagesOf(1)[0]->filename);
        self::assertSame(self::image(1), file_get_contents($this->originals . 'ABC.jpg'));
    }

    /** Файл понад upload_max_filesize приходить без tmp_name — раніше він зникав без сліду. */
    public function testPhpUploadErrorIsLogged(): void
    {
        $this->import([['name' => 'HUGE.jpg', 'tmp_name' => '', 'error' => UPLOAD_ERR_INI_SIZE, 'size' => 0]], H::ATTACH_NONE, H::EXISTING_FILES_SKIP);

        self::assertSame(['rejected'], $this->operations());
        self::assertSame('HUGE.jpg', $this->log[0]->filename);
    }

    public function testUnknownDirectFormatIsLogged(): void
    {
        $this->treatAsUploaded = true;
        $tmp = $this->root . 'php_tmp2';
        file_put_contents($tmp, 'x');

        $this->import([['name' => 'doc.pdf', 'tmp_name' => $tmp, 'error' => UPLOAD_ERR_OK, 'size' => 1]], H::ATTACH_NONE, H::EXISTING_FILES_SKIP);

        self::assertSame(['rejected'], $this->operations());
    }
}

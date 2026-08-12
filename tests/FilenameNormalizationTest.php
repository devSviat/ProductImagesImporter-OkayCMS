<?php

namespace Modules\Sviat\ProductImagesImporter;

use Okay\Modules\Sviat\ProductImagesImporter\Backend\Helpers\ProductImagesImportHelper as Helper;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Зведення імені файлу до ключа, за яким картинка чіпляється до товару.
 * Постачальники віддають фото як `ABC-123_1.jpg`, `ABC-123 (2).jpg`,
 * `ABC-123(3).jpg` — усі три мусять зійтись до одного SKU, інакше друга й третя
 * картинки просто не знайдуть свій товар.
 *
 * Методи статичні й не мають конструктора — жодного моку не потрібно.
 */
class FilenameNormalizationTest extends TestCase
{
    /** @return string[] */
    private static function allRules(): array
    {
        return Helper::filenameKeyRuleOrder();
    }

    // --- нормалізація імені -------------------------------------------------

    /** @dataProvider suffixProvider */
    #[DataProvider('suffixProvider')]
    public function testEnabledRulesStripTheirSuffix(string $input, string $expected): void
    {
        self::assertSame($expected, Helper::normalizeFilenameBase($input, self::allRules()));
    }

    public static function suffixProvider(): array
    {
        return [
            'підкреслення з номером' => ['PHOTO-A_1', 'PHOTO-A'],
            'обидва хвости підряд'   => ['ABC-123_1', 'ABC'],
            'дужки з номером'        => ['ABC(2)', 'ABC'],
            'пробіл і дужки'         => ['ABC (3)', 'ABC'],
            'без суфікса'            => ['ABC', 'ABC'],
            'номер усередині'        => ['ABC_1_XYZ', 'ABC_1_XYZ'],
            'кирилиця'               => ['Товар-фото_2', 'Товар-фото'],
            'пробіли по краях'       => ['  ABC_1  ', 'ABC'],
        ];
    }

    /** Зрізання повторюється до нерухомої точки, а не за один прохід. */
    public function testSuffixesAreStrippedRepeatedlyUntilStable(): void
    {
        self::assertSame('ABC', Helper::normalizeFilenameBase('ABC(1)(2)(3)', self::allRules()));
        self::assertSame('ABC', Helper::normalizeFilenameBase('ABC-1_2', self::allRules()));
    }

    /**
     * Регресія: у канонічному порядку `paren_num` стоїть перед
     * `space_paren_num`, тож `(3)` зрізається першим і лишав хвостовий пробіл.
     * Усі правила прив'язані до кінця рядка (`$`), тому далі не спрацьовувало
     * вже нічого — цикл упирався в нерухому точку, а `ABC_1-2 (3).jpg` давав
     * ключ `ABC_1-2` замість `ABC`, і фото не знаходило свій товар.
     *
     * Полагоджено обрізанням хвоста після кожного правила, а не лише в кінці.
     */
    /** @dataProvider trailingSpaceProvider */
    #[DataProvider('trailingSpaceProvider')]
    public function testSuffixesAfterParenthesesAreStrippedToo(string $input): void
    {
        self::assertSame('ABC', Helper::normalizeFilenameBase($input, self::allRules()));

        // І той самий результат без paren_num — правила більше не залежать
        // одне від одного через лишений пробіл.
        $withoutParen = [
            Helper::FILENAME_KEY_RULE_UNDERSCORE_NUM,
            Helper::FILENAME_KEY_RULE_DASH_NUM,
            Helper::FILENAME_KEY_RULE_SPACE_PAREN_NUM,
        ];
        self::assertSame('ABC', Helper::normalizeFilenameBase($input, $withoutParen));
    }

    public static function trailingSpaceProvider(): array
    {
        return [
            'хвости після дужок'    => ['ABC_1-2 (3)'],
            'двоє дужок з пробілом' => ['ABC (3) (4)'],
            'усе разом'             => ['ABC (1)_2 (3)-4'],
        ];
    }

    /** Повторний прогін уже нормалізованого імені нічого не змінює. */
    /** @dataProvider suffixProvider */
    #[DataProvider('suffixProvider')]
    public function testNormalizationIsIdempotent(string $input, string $expected): void
    {
        $once  = Helper::normalizeFilenameBase($input, self::allRules());
        $twice = Helper::normalizeFilenameBase($once, self::allRules());

        self::assertSame($expected, $once);
        self::assertSame($once, $twice);
    }

    /**
     * `-123` — це номер, а не суфікс, коли правило dash_num вимкнене. Порядок
     * правил у наборі не має впливати на результат: sanitizeFilenameKeyRules()
     * усе одно приводить його до канонічного.
     */
    public function testOnlyEnabledRulesApply(): void
    {
        $onlyUnderscore = [Helper::FILENAME_KEY_RULE_UNDERSCORE_NUM];

        self::assertSame('ABC-123', Helper::normalizeFilenameBase('ABC-123_1', $onlyUnderscore));
        self::assertSame('ABC-123', Helper::normalizeFilenameBase('ABC-123', $onlyUnderscore));
        self::assertSame('ABC(2)', Helper::normalizeFilenameBase('ABC(2)', $onlyUnderscore));
    }

    /** Порожній набір правил вимикає нормалізацію повністю. */
    public function testEmptyRuleSetIsAPassthrough(): void
    {
        self::assertSame('ABC_1-2 (3)', Helper::normalizeFilenameBase('ABC_1-2 (3)', []));
    }

    /**
     * Ім'я, що складається з самого суфікса, не має зводитись до порожнього
     * ключа — інакше всі такі файли злиплись би в один товар.
     */
    public function testNameMadeEntirelyOfSuffixesCollapsesToEmpty(): void
    {
        // Фактична поведінка: залишається порожній рядок. Викликаючий код
        // мусить сам відсіяти такий ключ перед пошуком товару.
        self::assertSame('', Helper::normalizeFilenameBase('_1', self::allRules()));
        self::assertSame('', Helper::normalizeFilenameBase('   ', self::allRules()));
    }

    // --- кандидати на ключ пошуку -------------------------------------------

    /**
     * Регресія на реальну поставку: постачальник шле `SS-203758.jpg` і
     * `SS-203758-1.jpg`, де `SS-203758` — це і є SKU цілком. Схлопування до
     * нерухомої точки з'їдало його до `SS`, і жодне з двох фото не знаходило
     * товар. Тому резолв іде не за одним ключем, а за списком кандидатів
     * від найдовшого до найкоротшого — виграє перший, що знайшовся в БД.
     */
    /** @dataProvider candidateProvider */
    #[DataProvider('candidateProvider')]
    public function testCandidatesGoFromLongestToShortest(string $input, array $rules, array $expected): void
    {
        self::assertSame($expected, Helper::filenameKeyCandidates($input, $rules));
    }

    public static function candidateProvider(): array
    {
        $dash = [Helper::FILENAME_KEY_RULE_DASH_NUM];

        return [
            'SKU з дефісом і другим фото' => ['SS-203758-1', $dash, ['SS-203758-1', 'SS-203758', 'SS']],
            'SKU з дефісом без суфікса'   => ['SS-203758', $dash, ['SS-203758', 'SS']],
            'SKU без дефіса з суфіксом'   => ['C00631050-2', $dash, ['C00631050-2', 'C00631050']],
            'штрихкод без суфікса'        => ['480132102047', $dash, ['480132102047']],
            'правило вимкнене'            => ['SS-203758-1', [], ['SS-203758-1']],
            'кілька правил підряд'        => [
                'ABC-123_1',
                [Helper::FILENAME_KEY_RULE_UNDERSCORE_NUM, Helper::FILENAME_KEY_RULE_DASH_NUM],
                ['ABC-123_1', 'ABC-123', 'ABC'],
            ],
        ];
    }

    /** Сире ім'я завжди перший кандидат — інакше точний збіг з SKU не має шансу. */
    /** @dataProvider suffixProvider */
    #[DataProvider('suffixProvider')]
    public function testRawNameIsAlwaysTheFirstCandidate(string $input, string $expected): void
    {
        $candidates = Helper::filenameKeyCandidates($input, self::allRules());

        self::assertNotSame([], $candidates);
        self::assertSame(trim($input), $candidates[0]);
    }

    /** Порожні ключі шукати нема сенсу, а дублі роздували б батч-запит. */
    public function testCandidatesAreUniqueAndNeverEmpty(): void
    {
        foreach (['ABC (1)_2 (3)-4', 'ABC(1)(2)(3)', 'Товар-фото_2', '_1', '   '] as $input) {
            $candidates = Helper::filenameKeyCandidates($input, self::allRules());

            self::assertSame(array_values(array_unique($candidates)), $candidates, $input);
            self::assertSame([], array_filter($candidates, static fn ($c) => trim($c) === ''), $input);
        }
    }

    /**
     * Ім'я з самих суфіксів зводиться до порожнього — тоді єдиний кандидат
     * це саме ім'я, а не порожній список: хай пошук чесно не знайде товар.
     */
    public function testNameMadeEntirelyOfSuffixesKeepsItselfAsCandidate(): void
    {
        self::assertSame(['_1'], Helper::filenameKeyCandidates('_1', self::allRules()));
        self::assertSame([], Helper::filenameKeyCandidates('   ', self::allRules()));
    }

    // --- санітизація набору правил ------------------------------------------

    /**
     * Набір із форми приходить у довільному порядку, а normalizeFilenameBase
     * застосовує правила в канонічному. Санітизація мусить це вирівнювати,
     * інакше два однакові набори дали б різні ключі.
     */
    public function testRuleSetIsReorderedIntoCanonicalOrder(): void
    {
        $shuffled = array_reverse(self::allRules());

        self::assertSame(self::allRules(), Helper::sanitizeFilenameKeyRules($shuffled));
    }

    public function testRuleSetDropsUnknownAndNonStringEntries(): void
    {
        $raw = [
            Helper::FILENAME_KEY_RULE_DASH_NUM,
            'вигадане_правило',
            123,
            null,
            ['вкладений масив'],
            '  ' . Helper::FILENAME_KEY_RULE_UNDERSCORE_NUM . '  ',
            '',
        ];

        self::assertSame(
            [Helper::FILENAME_KEY_RULE_UNDERSCORE_NUM, Helper::FILENAME_KEY_RULE_DASH_NUM],
            Helper::sanitizeFilenameKeyRules($raw)
        );
    }

    public function testRuleSetDeduplicates(): void
    {
        $raw = array_fill(0, 5, Helper::FILENAME_KEY_RULE_PAREN_NUM);

        self::assertSame([Helper::FILENAME_KEY_RULE_PAREN_NUM], Helper::sanitizeFilenameKeyRules($raw));
    }

    // --- політики -----------------------------------------------------------

    /**
     * Політика по замовчуванню — «пропустити», а не «перезаписати»: невідоме
     * значення з форми не має тихо затирати вже завантажені фото.
     */
    /** @dataProvider invalidPolicyProvider */
    #[DataProvider('invalidPolicyProvider')]
    public function testUnknownExistingFilesPolicyFallsBackToSkip(?string $raw): void
    {
        self::assertSame(Helper::EXISTING_FILES_SKIP, Helper::sanitizeExistingFilesPolicy($raw));
    }

    /** @dataProvider invalidPolicyProvider */
    #[DataProvider('invalidPolicyProvider')]
    public function testUnknownMissingProductPolicyFallsBackToSkip(?string $raw): void
    {
        self::assertSame(Helper::ON_MISSING_PRODUCT_SKIP, Helper::sanitizeMissingProductPolicy($raw));
    }

    public static function invalidPolicyProvider(): array
    {
        return [
            'null'            => [null],
            'порожній рядок'  => [''],
            'вигадане'        => ['вигадане'],
            'інший регістр'   => ['OVERWRITE'],
        ];
    }

    public function testValidPoliciesArePreservedAndTrimmed(): void
    {
        self::assertSame(
            Helper::EXISTING_FILES_OVERWRITE,
            Helper::sanitizeExistingFilesPolicy('  ' . Helper::EXISTING_FILES_OVERWRITE . ' ')
        );
        self::assertSame(
            Helper::ON_MISSING_PRODUCT_SAVE,
            Helper::sanitizeMissingProductPolicy(Helper::ON_MISSING_PRODUCT_SAVE)
        );
    }
}

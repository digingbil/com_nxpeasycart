<?php
/**
 * @package     NXP Easy Cart
 * @subpackage  Tests
 *
 * @copyright   Copyright (C) 2024-2025 nexusplugins.com. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace Tests\Unit\Administrator\Service\Import\Adapter;

use Joomla\Component\Nxpeasycart\Administrator\Service\Import\Adapter\AbstractPlatformAdapter;
use PHPUnit\Framework\TestCase;

/**
 * Concrete test implementation of AbstractPlatformAdapter to expose protected methods.
 */
class TestableAdapter extends AbstractPlatformAdapter
{
    private string $weightUnit = 'kg';

    public function getName(): string
    {
        return 'testable';
    }

    public function getDisplayName(): string
    {
        return 'Testable Adapter';
    }

    public function getDefaultMapping(): array
    {
        return [];
    }

    public function getSignatureHeaders(): array
    {
        return ['test_header'];
    }

    public function normalizeRow(array $row, array $mapping): array
    {
        return $this->createEmptyNormalized();
    }

    public function getWeightUnit(): string
    {
        return $this->weightUnit;
    }

    public function setWeightUnit(string $unit): void
    {
        $this->weightUnit = $unit;
    }

    public function shouldGroupVariants(): bool
    {
        return true;
    }

    public function getGroupingColumn(): ?string
    {
        return 'product_id';
    }

    public function getCategorySeparator(): string
    {
        return ',';
    }

    // Expose protected methods for testing
    public function testParsePrice(string $value): float
    {
        return $this->parsePrice($value);
    }

    public function testParseStock(string $value): int
    {
        return $this->parseStock($value);
    }

    public function testParseWeight(string $value): float
    {
        return $this->parseWeight($value);
    }

    public function testParseBoolean(string $value): bool
    {
        return $this->parseBoolean($value);
    }

    public function testGenerateSlug(string $value): string
    {
        return $this->generateSlug($value);
    }

    public function testParseList(string $value, string $separator = ','): array
    {
        return $this->parseList($value, $separator);
    }

    public function testParseCategoryPath(string $value, string $separator = '>'): array
    {
        return $this->parseCategoryPath($value, $separator);
    }

    public function testParseImages(string $value): array
    {
        return $this->parseImages($value);
    }

    public function testParseEan(string $value): ?string
    {
        return $this->parseEan($value);
    }

    public function testValidateEanChecksum(string $ean): bool
    {
        return $this->validateEanChecksum($ean);
    }

    public function testParseDatetime(string $value): ?string
    {
        return $this->parseDatetime($value);
    }

    public function testSanitizeHtml(string $value): string
    {
        return $this->sanitizeHtml($value);
    }

    public function testTruncate(string $value, int $maxLength): string
    {
        return $this->truncate($value, $maxLength);
    }

    public function testInferOptionName(string $value): string
    {
        return $this->inferOptionName($value);
    }

    public function testCreateEmptyNormalized(): array
    {
        return $this->createEmptyNormalized();
    }
}

/**
 * Tests for the AbstractPlatformAdapter utility methods.
 *
 * @since 0.3.0
 */
final class AbstractPlatformAdapterTest extends TestCase
{
    private TestableAdapter $adapter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->adapter = new TestableAdapter();
    }

    // ========================================================================
    // PRICE PARSING TESTS
    // ========================================================================

    /**
     * @dataProvider standardPriceProvider
     */
    public function testParsePriceStandardFormats(string $input, float $expected): void
    {
        $result = $this->adapter->testParsePrice($input);
        $this->assertEqualsWithDelta($expected, $result, 0.001);
    }

    public static function standardPriceProvider(): array
    {
        return [
            'integer'                => ['29', 29.0],
            'decimal with dot'       => ['29.99', 29.99],
            'empty string'           => ['', 0.0],
            'with dollar sign'       => ['$29.99', 29.99],
            'with euro sign'         => ['€29.99', 29.99],
            'with pound sign'        => ['£29.99', 29.99],
            'with currency code'     => ['USD 29.99', 29.99],
            'negative price'         => ['-15.50', -15.50],
            'whitespace'             => ['  29.99  ', 29.99],
        ];
    }

    /**
     * @dataProvider europeanPriceProvider
     */
    public function testParsePriceEuropeanFormats(string $input, float $expected): void
    {
        $result = $this->adapter->testParsePrice($input);
        $this->assertEqualsWithDelta($expected, $result, 0.001);
    }

    public static function europeanPriceProvider(): array
    {
        return [
            'comma as decimal'              => ['24,99', 24.99],
            'dot thousand comma decimal'    => ['1.234,56', 1234.56],
            'large european format'         => ['12.345.678,99', 12345678.99],
        ];
    }

    /**
     * @dataProvider usThousandSeparatorProvider
     */
    public function testParsePriceUSThousandSeparator(string $input, float $expected): void
    {
        $result = $this->adapter->testParsePrice($input);
        $this->assertEqualsWithDelta($expected, $result, 0.001);
    }

    public static function usThousandSeparatorProvider(): array
    {
        return [
            'comma thousand separator'      => ['1,234.56', 1234.56],
            'large US format'               => ['12,345,678.99', 12345678.99],
            'thousand only'                 => ['1,000', 1000.0],
        ];
    }

    // ========================================================================
    // STOCK PARSING TESTS
    // ========================================================================

    /**
     * @dataProvider stockProvider
     */
    public function testParseStock(string $input, int $expected): void
    {
        $this->assertEquals($expected, $this->adapter->testParseStock($input));
    }

    public static function stockProvider(): array
    {
        return [
            'positive integer'  => ['45', 45],
            'zero'              => ['0', 0],
            'empty string'      => ['', 0],
            'null string'       => ['null', 0],
            'N/A string'        => ['N/A', 0],
            'negative (clamped)' => ['-5', 0],
            'decimal (truncated)' => ['45.5', 45],
            'with whitespace'   => ['  100  ', 100],
        ];
    }

    // ========================================================================
    // WEIGHT PARSING & CONVERSION TESTS
    // ========================================================================

    public function testParseWeightEmptyReturnsZero(): void
    {
        $this->assertEquals(0.0, $this->adapter->testParseWeight(''));
    }

    public function testParseWeightKilograms(): void
    {
        $this->adapter->setWeightUnit('kg');
        $result = $this->adapter->testParseWeight('1.5');
        $this->assertEqualsWithDelta(1.5, $result, 0.001);
    }

    public function testParseWeightGramsConversion(): void
    {
        $this->adapter->setWeightUnit('g');
        $result = $this->adapter->testParseWeight('180');
        $this->assertEqualsWithDelta(0.180, $result, 0.001);
    }

    public function testParseWeightPoundsConversion(): void
    {
        $this->adapter->setWeightUnit('lb');
        $result = $this->adapter->testParseWeight('2.2');
        $this->assertEqualsWithDelta(0.9979, $result, 0.001);
    }

    public function testParseWeightOuncesConversion(): void
    {
        $this->adapter->setWeightUnit('oz');
        $result = $this->adapter->testParseWeight('16');
        $this->assertEqualsWithDelta(0.454, $result, 0.001);
    }

    public function testParseWeightStripsNonNumeric(): void
    {
        $this->adapter->setWeightUnit('kg');
        $result = $this->adapter->testParseWeight('1.5 kg');
        $this->assertEqualsWithDelta(1.5, $result, 0.001);
    }

    // ========================================================================
    // BOOLEAN PARSING TESTS
    // ========================================================================

    /**
     * @dataProvider trueBooleanProvider
     */
    public function testParseBooleanTrueValues(string $input): void
    {
        $this->assertTrue($this->adapter->testParseBoolean($input));
    }

    public static function trueBooleanProvider(): array
    {
        return [
            '1'          => ['1'],
            'true'       => ['true'],
            'TRUE'       => ['TRUE'],
            'True'       => ['True'],
            'yes'        => ['yes'],
            'YES'        => ['YES'],
            'on'         => ['on'],
            'published'  => ['published'],
            'active'     => ['active'],
            'enabled'    => ['enabled'],
        ];
    }

    /**
     * @dataProvider falseBooleanProvider
     */
    public function testParseBooleanFalseValues(string $input): void
    {
        $this->assertFalse($this->adapter->testParseBoolean($input));
    }

    public static function falseBooleanProvider(): array
    {
        return [
            '0'          => ['0'],
            'false'      => ['false'],
            'no'         => ['no'],
            'off'        => ['off'],
            'empty'      => [''],
            'random'     => ['random'],
            'disabled'   => ['disabled'],
        ];
    }

    // ========================================================================
    // LIST PARSING TESTS
    // ========================================================================

    public function testParseListCommas(): void
    {
        $result = $this->adapter->testParseList('a, b, c');
        $this->assertEquals(['a', 'b', 'c'], $result);
    }

    public function testParseListPipes(): void
    {
        $result = $this->adapter->testParseList('a|b|c', '|');
        $this->assertEquals(['a', 'b', 'c'], $result);
    }

    public function testParseListTrimsWhitespace(): void
    {
        $result = $this->adapter->testParseList('  a  ,  b  ,  c  ');
        $this->assertEquals(['a', 'b', 'c'], $result);
    }

    public function testParseListFiltersEmpty(): void
    {
        $result = $this->adapter->testParseList('a,,b,  ,c');
        $this->assertEquals(['a', 'b', 'c'], $result);
    }

    public function testParseListEmptyString(): void
    {
        $result = $this->adapter->testParseList('');
        $this->assertEquals([], $result);
    }

    // ========================================================================
    // CATEGORY PATH PARSING TESTS
    // ========================================================================

    public function testParseCategoryPathSimple(): void
    {
        $result = $this->adapter->testParseCategoryPath('Clothing > T-Shirts');
        $this->assertEquals(['Clothing', 'T-Shirts'], $result);
    }

    public function testParseCategoryPathDeep(): void
    {
        $result = $this->adapter->testParseCategoryPath('Home > Kitchen > Appliances > Blenders');
        $this->assertEquals(['Home', 'Kitchen', 'Appliances', 'Blenders'], $result);
    }

    public function testParseCategoryPathNormalizesPipeSeparator(): void
    {
        $result = $this->adapter->testParseCategoryPath('Clothing|T-Shirts');
        $this->assertEquals(['Clothing', 'T-Shirts'], $result);
    }

    public function testParseCategoryPathNormalizesSlashSeparator(): void
    {
        $result = $this->adapter->testParseCategoryPath('Clothing/T-Shirts');
        $this->assertEquals(['Clothing', 'T-Shirts'], $result);
    }

    public function testParseCategoryPathTrimsWhitespace(): void
    {
        $result = $this->adapter->testParseCategoryPath('  Clothing  >  T-Shirts  ');
        $this->assertEquals(['Clothing', 'T-Shirts'], $result);
    }

    public function testParseCategoryPathEmpty(): void
    {
        $result = $this->adapter->testParseCategoryPath('');
        $this->assertEquals([], $result);
    }

    // ========================================================================
    // IMAGE PARSING TESTS
    // ========================================================================

    public function testParseImagesCommaSeparated(): void
    {
        $input = 'https://example.com/img1.jpg,https://example.com/img2.jpg';
        $result = $this->adapter->testParseImages($input);

        $this->assertEquals([
            'https://example.com/img1.jpg',
            'https://example.com/img2.jpg',
        ], $result);
    }

    public function testParseImagesNewlineSeparated(): void
    {
        $input = "https://example.com/img1.jpg\nhttps://example.com/img2.jpg";
        $result = $this->adapter->testParseImages($input);

        $this->assertEquals([
            'https://example.com/img1.jpg',
            'https://example.com/img2.jpg',
        ], $result);
    }

    public function testParseImagesFiltersInvalidUrls(): void
    {
        $input = 'https://example.com/img.jpg,not-a-url,another-invalid';
        $result = $this->adapter->testParseImages($input);

        $this->assertEquals(['https://example.com/img.jpg'], $result);
    }

    public function testParseImagesEmpty(): void
    {
        $result = $this->adapter->testParseImages('');
        $this->assertEquals([], $result);
    }

    // ========================================================================
    // EAN VALIDATION TESTS
    // ========================================================================

    /**
     * @dataProvider validEan13Provider
     */
    public function testParseEanValidEan13(string $ean): void
    {
        $result = $this->adapter->testParseEan($ean);
        $this->assertEquals($ean, $result);
    }

    public static function validEan13Provider(): array
    {
        return [
            'standard EAN-13'        => ['5901234123457'],
            'another valid EAN-13'   => ['4006381333937'],
            'third valid EAN-13'     => ['0012345678905'],
        ];
    }

    /**
     * @dataProvider validEan8Provider
     */
    public function testParseEanValidEan8(string $ean): void
    {
        $result = $this->adapter->testParseEan($ean);
        $this->assertEquals($ean, $result);
    }

    public static function validEan8Provider(): array
    {
        return [
            'standard EAN-8'  => ['96385074'],
            'another EAN-8'   => ['12345678'],
        ];
    }

    /**
     * @dataProvider invalidEanProvider
     */
    public function testParseEanInvalid(string $input): void
    {
        $this->assertNull($this->adapter->testParseEan($input));
    }

    public static function invalidEanProvider(): array
    {
        return [
            'empty'              => [''],
            'too short'          => ['123456'],
            'too long'           => ['12345678901234'],
            'wrong length'       => ['1234567890'],
            'contains letters'   => ['590123412345A'],
            'invalid checksum 1' => ['5901234123450'],
            'invalid checksum 2' => ['1234567890123'],
        ];
    }

    public function testParseEanStripsWhitespace(): void
    {
        $result = $this->adapter->testParseEan('  5901234123457  ');
        $this->assertEquals('5901234123457', $result);
    }

    public function testParseEanStripsDashes(): void
    {
        $result = $this->adapter->testParseEan('590-1234-1234-57');
        $this->assertEquals('5901234123457', $result);
    }

    public function testValidateEanChecksumCorrect(): void
    {
        $this->assertTrue($this->adapter->testValidateEanChecksum('5901234123457'));
    }

    public function testValidateEanChecksumIncorrect(): void
    {
        $this->assertFalse($this->adapter->testValidateEanChecksum('5901234123459'));
    }

    // ========================================================================
    // DATETIME PARSING TESTS
    // ========================================================================

    public function testParseDatetimeIsoFormat(): void
    {
        $result = $this->adapter->testParseDatetime('2025-12-01 00:00:00');
        $this->assertEquals('2025-12-01 00:00:00', $result);
    }

    public function testParseDatetimeDateOnly(): void
    {
        $result = $this->adapter->testParseDatetime('2025-12-01');
        $this->assertEquals('2025-12-01 00:00:00', $result);
    }

    public function testParseDatetimeUSFormat(): void
    {
        $result = $this->adapter->testParseDatetime('12/01/2025');
        $this->assertNotNull($result);
        $this->assertStringContainsString('2025', $result);
    }

    public function testParseDatetimeEmpty(): void
    {
        $this->assertNull($this->adapter->testParseDatetime(''));
    }

    public function testParseDatetimeInvalid(): void
    {
        $this->assertNull($this->adapter->testParseDatetime('not-a-date'));
    }

    // ========================================================================
    // STRING UTILITY TESTS
    // ========================================================================

    public function testTruncateShortString(): void
    {
        $result = $this->adapter->testTruncate('Short', 100);
        $this->assertEquals('Short', $result);
    }

    public function testTruncateLongString(): void
    {
        $result = $this->adapter->testTruncate('This is a very long string that needs truncation', 20);
        $this->assertEquals('This is a very lo...', $result);
        $this->assertEquals(20, mb_strlen($result));
    }

    public function testTruncateExactLength(): void
    {
        $result = $this->adapter->testTruncate('12345', 5);
        $this->assertEquals('12345', $result);
    }

    public function testSanitizeHtmlTrims(): void
    {
        $result = $this->adapter->testSanitizeHtml('   <p>Test</p>   ');
        $this->assertEquals('<p>Test</p>', $result);
    }

    public function testSanitizeHtmlDecodesEntities(): void
    {
        $result = $this->adapter->testSanitizeHtml('&amp; &lt; &gt; &quot;');
        $this->assertEquals('& < > "', $result);
    }

    // ========================================================================
    // OPTION NAME INFERENCE TESTS
    // ========================================================================

    /**
     * @dataProvider colorInferenceProvider
     */
    public function testInferOptionNameColors(string $color): void
    {
        $result = $this->adapter->testInferOptionName($color);
        $this->assertEquals('Color', $result);
    }

    public static function colorInferenceProvider(): array
    {
        return [
            'black'  => ['Black'],
            'white'  => ['White'],
            'red'    => ['Red'],
            'blue'   => ['Blue'],
            'green'  => ['Green'],
            'navy'   => ['navy'],
            'teal'   => ['TEAL'],
            'coral'  => ['coral'],
        ];
    }

    /**
     * @dataProvider sizeInferenceProvider
     */
    public function testInferOptionNameSizes(string $size): void
    {
        $result = $this->adapter->testInferOptionName($size);
        $this->assertEquals('Size', $result);
    }

    public static function sizeInferenceProvider(): array
    {
        return [
            'XS'     => ['XS'],
            'S'      => ['S'],
            'M'      => ['M'],
            'L'      => ['L'],
            'XL'     => ['XL'],
            'XXL'    => ['XXL'],
            '2XL'    => ['2XL'],
            'numeric' => ['42'],
            'cm size' => ['32cm'],
            'inch size' => ['32in'],
        ];
    }

    public function testInferOptionNameUnknownReturnsOption(): void
    {
        $result = $this->adapter->testInferOptionName('RandomValue');
        $this->assertEquals('Option', $result);
    }

    // ========================================================================
    // EMPTY NORMALIZED STRUCTURE TESTS
    // ========================================================================

    public function testCreateEmptyNormalizedHasProductArray(): void
    {
        $normalized = $this->adapter->testCreateEmptyNormalized();

        $this->assertArrayHasKey('product', $normalized);
        $this->assertArrayHasKey('title', $normalized['product']);
        $this->assertArrayHasKey('slug', $normalized['product']);
        $this->assertArrayHasKey('short_desc', $normalized['product']);
        $this->assertArrayHasKey('long_desc', $normalized['product']);
        $this->assertArrayHasKey('active', $normalized['product']);
        $this->assertArrayHasKey('featured', $normalized['product']);
        $this->assertArrayHasKey('product_type', $normalized['product']);
        $this->assertArrayHasKey('categories', $normalized['product']);
        $this->assertArrayHasKey('images', $normalized['product']);
        $this->assertArrayHasKey('original_id', $normalized['product']);
    }

    public function testCreateEmptyNormalizedHasVariantArray(): void
    {
        $normalized = $this->adapter->testCreateEmptyNormalized();

        $this->assertArrayHasKey('variant', $normalized);
        $this->assertArrayHasKey('sku', $normalized['variant']);
        $this->assertArrayHasKey('price', $normalized['variant']);
        $this->assertArrayHasKey('sale_price', $normalized['variant']);
        $this->assertArrayHasKey('sale_start', $normalized['variant']);
        $this->assertArrayHasKey('sale_end', $normalized['variant']);
        $this->assertArrayHasKey('currency', $normalized['variant']);
        $this->assertArrayHasKey('stock', $normalized['variant']);
        $this->assertArrayHasKey('weight', $normalized['variant']);
        $this->assertArrayHasKey('ean', $normalized['variant']);
        $this->assertArrayHasKey('is_digital', $normalized['variant']);
        $this->assertArrayHasKey('active', $normalized['variant']);
        $this->assertArrayHasKey('options', $normalized['variant']);
        $this->assertArrayHasKey('original_images', $normalized['variant']);
        $this->assertArrayHasKey('original_id', $normalized['variant']);
    }

    public function testCreateEmptyNormalizedHasCorrectDefaults(): void
    {
        $normalized = $this->adapter->testCreateEmptyNormalized();

        // Product defaults
        $this->assertEquals('', $normalized['product']['title']);
        $this->assertTrue($normalized['product']['active']);
        $this->assertFalse($normalized['product']['featured']);
        $this->assertEquals('physical', $normalized['product']['product_type']);
        $this->assertEquals([], $normalized['product']['categories']);
        $this->assertEquals([], $normalized['product']['images']);

        // Variant defaults
        $this->assertEquals('', $normalized['variant']['sku']);
        $this->assertEquals(0.0, $normalized['variant']['price']);
        $this->assertNull($normalized['variant']['sale_price']);
        $this->assertNull($normalized['variant']['sale_start']);
        $this->assertNull($normalized['variant']['sale_end']);
        $this->assertEquals('EUR', $normalized['variant']['currency']);
        $this->assertEquals(0, $normalized['variant']['stock']);
        $this->assertEquals(0.0, $normalized['variant']['weight']);
        $this->assertNull($normalized['variant']['ean']);
        $this->assertFalse($normalized['variant']['is_digital']);
        $this->assertTrue($normalized['variant']['active']);
        $this->assertEquals([], $normalized['variant']['options']);
        $this->assertEquals([], $normalized['variant']['original_images']);
    }

    // ========================================================================
    // VALIDATION TESTS
    // ========================================================================

    public function testValidateReturnsEmptyForValidRow(): void
    {
        $normalized = [
            'product' => ['title' => 'Test Product'],
            'variant' => ['price' => 29.99],
        ];

        $errors = $this->adapter->validate($normalized);
        $this->assertEmpty($errors);
    }

    public function testValidateReturnsErrorForEmptyTitle(): void
    {
        $normalized = [
            'product' => ['title' => ''],
            'variant' => ['price' => 29.99],
        ];

        $errors = $this->adapter->validate($normalized);
        $this->assertContains('Product title is empty', $errors);
    }

    public function testValidateReturnsErrorForNegativePrice(): void
    {
        $normalized = [
            'product' => ['title' => 'Test'],
            'variant' => ['price' => -5.00],
        ];

        $errors = $this->adapter->validate($normalized);
        $this->assertContains('Price cannot be negative', $errors);
    }

    // ========================================================================
    // DEFAULT CURRENCY TESTS
    // ========================================================================

    public function testSetDefaultCurrencyChangesDefaultCurrency(): void
    {
        $this->adapter->setDefaultCurrency('usd');

        $normalized = $this->adapter->testCreateEmptyNormalized();
        $this->assertEquals('USD', $normalized['variant']['currency']);
    }
}

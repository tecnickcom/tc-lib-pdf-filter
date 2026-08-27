<?php

/**
 * FilterTest.php
 *
 * @since     2011-05-23
 * @category  Library
 * @package   PdfFilter
 * @author    Nicola Asuni <info@tecnick.com>
 * @copyright 2011-2026 Nicola Asuni - Tecnick.com LTD
 * @license   https://www.gnu.org/copyleft/lesser.html GNU-LGPL v3 (see LICENSE)
 * @link      https://github.com/tecnickcom/tc-lib-pdf-filter
 *
 * This file is part of tc-lib-pdf-filter software library.
 */

namespace Test;

/**
 * Filter class test
 *
 * @since     2011-05-23
 * @category  Library
 * @package   PdfFilter
 * @author    Nicola Asuni <info@tecnick.com>
 * @copyright 2011-2026 Nicola Asuni - Tecnick.com LTD
 * @license   https://www.gnu.org/copyleft/lesser.html GNU-LGPL v3 (see LICENSE)
 * @link      https://github.com/tecnickcom/tc-lib-pdf-filter
 */
class FilterTest extends TestUtil
{
    protected function getTestObject(): \Com\Tecnick\Pdf\Filter\Filter
    {
        return new \Com\Tecnick\Pdf\Filter\Filter();
    }

    public function testEmptyFilter(): void
    {
        $filter = $this->getTestObject();
        $result = $filter->decode('', 'tc-lib-pdf-filter');
        $this->assertEquals('tc-lib-pdf-filter', $result);
    }

    public function testUnknownnFilter(): void
    {
        $filter = $this->getTestObject();

        $this->assertThrows(
            '\\' . \Com\Tecnick\Pdf\Filter\Exception::class,
            'unknown filter: Unknownn',
            static fn(): mixed => $filter->decode('Unknownn', 'YZ'),
        );
    }

    /**
     * A /Filter value read straight out of a PDF carries its leading solidus.
     */
    public function testDecodeAcceptsANameObjectWithSolidus(): void
    {
        $filter = $this->getTestObject();
        $payload = (string) gzcompress('tc-lib-pdf-filter');

        $this->assertSame('tc-lib-pdf-filter', $filter->decode('/FlateDecode', $payload));
        // the inline-image abbreviation takes a solidus too
        $this->assertSame('tc-lib-pdf-filter', $filter->decode('/Fl', $payload));
    }

    public function testAsciiHex(): void
    {
        $filter = $this->getTestObject();
        $code = '74 63 2D 6C 69 62 2D 70 64 66 2D 66 69 6C 74 65 72>';
        $result = $filter->decode('ASCIIHexDecode', $code);
        $this->assertEquals('tc-lib-pdf-filter', $result);

        $code = '74632D6C69622D7064662D66696C746572>';
        $result = $filter->decode('ASCIIHexDecode', $code);
        $this->assertEquals('tc-lib-pdf-filter', $result);

        // odd number of hex digits before EOD: a trailing 0 is appended to the
        // last digit (PDF 32000-1:2008 §7.4.2), so "9" becomes byte 0x90
        $code = '30 31 32 33 34 35 36 37 38 39 9>';
        $result = $filter->decode('ASCIIHexDecode', $code);
        $this->assertEquals('0123456789' . \chr(0x90), $result);
    }

    public function testAsciiHexEx(): void
    {
        $filter = $this->getTestObject();
        $code = '30 31 32 33 34 35 36 37 38 39 9';

        // without the EOD there is nothing to imply the missing final digit
        $this->assertThrows(
            '\\' . \Com\Tecnick\Pdf\Filter\Exception::class,
            'invalid code: odd number of hexadecimal digits without EOD',
            static fn(): mixed => $filter->decode('ASCIIHexDecode', $code),
        );
    }

    public function testAsciiHexException(): void
    {
        $filter = $this->getTestObject();

        $this->assertThrows(
            '\\' . \Com\Tecnick\Pdf\Filter\Exception::class,
            'invalid code: character outside the hexadecimal alphabet',
            static fn(): mixed => $filter->decode('ASCIIHexDecode', 'YZ 34 HJ>'),
        );
    }

    public function testAsciiEightFive(): void
    {
        $filter = $this->getTestObject();
        $code = 'FCQn=BjrZ5A7dE*Bl%m&EW~>';
        $result = $filter->decode('ASCII85Decode', $code);
        $this->assertEquals('tc-lib-pdf-filter', $result);
        $result = $filter->decode('ASCII85Decode', '~>');
        $this->assertEquals('', $result);
        $result = $filter->decode('ASCII85Decode', '<<~>');
        $this->assertEquals('U', $result);
        $result = $filter->decode('ASCII85Decode', 'z~>');
        $this->assertEquals("\000\000\000\000", $result);
        $result = $filter->decode(
            'ASCII85Decode',
            '  9Q+r_D\'3P3F*2=BA8c:&EZfF;F<G"/ATTIG@rH7+ARfgnFEMUH@:X(kBldcuDJ()\'Ch[t ',
        );
        $this->assertEquals('Lorem ipsum dolor sit amet, consectetur adipiscing elit', $result);
    }

    public function testAsciiEightFiveEx(): void
    {
        $filter = $this->getTestObject();

        $this->assertThrows(
            '\\' . \Com\Tecnick\Pdf\Filter\Exception::class,
            'invalid code: character outside the ASCII85 alphabet',
            static fn(): mixed => $filter->decode('ASCII85Decode', \chr(254)),
        );
    }

    public function testFlate(): void
    {
        $filter = $this->getTestObject();
        $code = "\x78\x9c\x2b\x49\xd6\xcd\xc9\x4c\xd2\x2d\x48\x49\xd3\x4d\xcb\xcc\x29\x49\x2d\x2\x0\x37\x64\x6\x56";
        $result = $filter->decode('FlateDecode', $code);
        $this->assertEquals('tc-lib-pdf-filter', $result);
    }

    public function testFlateRawDeflateFallback(): void
    {
        $filter = $this->getTestObject();
        $expected = 'tc-lib-pdf-filter';
        $code = (string) \gzdeflate($expected);
        $result = $filter->decode('FlateDecode', $code);
        $this->assertSame($expected, $result);
    }

    public function testFlateHeaderStrippedFallback(): void
    {
        $filter = $this->getTestObject();
        $expected = 'tc-lib-pdf-filter';
        $raw = (string) \gzdeflate($expected);
        $code = "\x78\x9c" . $raw;
        $result = $filter->decode('FlateDecode', $code);
        $this->assertSame($expected, $result);
    }

    public function testFlateGzipFallback(): void
    {
        $filter = $this->getTestObject();
        $expected = 'tc-lib-pdf-filter';
        $code = (string) \gzencode($expected);
        $result = $filter->decode('FlateDecode', $code);
        $this->assertSame($expected, $result);
    }

    public function testFlateEx(): void
    {
        $filter = $this->getTestObject();

        $this->assertThrows(
            '\\' . \Com\Tecnick\Pdf\Filter\Exception::class,
            'invalid code',
            static fn(): mixed => $filter->decode('FlateDecode', 'ABC'),
        );
    }

    public function testRunLength(): void
    {
        $filter = $this->getTestObject();
        $code = \chr(247) . 'A' . \chr(18) . ' tc-lib-pdf-filter ' . \chr(247) . 'B' . \chr(128);
        $result = $filter->decode('RunLengthDecode', $code);
        $this->assertEquals('AAAAAAAAAA tc-lib-pdf-filter BBBBBBBBBB', $result);
    }

    public function testCcittFaxEmptyInput(): void
    {
        // empty input must return '' regardless of whether Imagick is loaded
        $filter = $this->getTestObject();
        $this->assertSame('', $filter->decode('CCITTFaxDecode', ''));
    }

    public function testCcittFax(): void
    {
        if (!extension_loaded('imagick')) {
            $this->markTestSkipped('ext-imagick is not available');
        }

        // Group 4 stream for a 64x8 image, left half black and right half white
        $ccittData = "\x23\x60\xD5\xFF\xF8\x00\x80\x08";

        $filter = $this->getTestObject();

        try {
            $result = $filter->decode('CCITTFaxDecode', $ccittData, [
                'K' => -1,
                'Columns' => 64,
                'Rows' => 8,
            ]);
        } catch (\Com\Tecnick\Pdf\Filter\Exception $e) {
            if (str_contains($e->getMessage(), 'no decode delegate')) {
                $this->markTestSkipped('Imagick is available but the TIFF/CCITT decode delegate is missing');
            }

            throw $e;
        }

        $this->assertStringStartsWith("\x89PNG", $result);

        $image = new \Imagick();
        $image->readImageBlob($result);
        $this->assertSame(64, $image->getImageWidth());
        $this->assertSame(8, $image->getImageHeight());
        $this->assertSame('srgb(0,0,0)', $image->getImagePixelColor(5, 4)->getColorAsString());
        $this->assertSame('srgb(255,255,255)', $image->getImagePixelColor(50, 4)->getColorAsString());
    }

    public function testJbigTwoEmptyInput(): void
    {
        // empty input must return '' regardless of whether jbig2dec is installed
        $filter = $this->getTestObject();
        $this->assertSame('', $filter->decode('JBIG2Decode', ''));
    }

    public function testJbigTwo(): void
    {
        if (trim((string) shell_exec('command -v jbig2dec 2>/dev/null')) === '') {
            $this->markTestSkipped('jbig2dec is not installed');
        }

        // the input is not valid JBIG2, so jbig2dec fails
        $filter = $this->getTestObject();
        $this->assertThrows(
            '\\' . \Com\Tecnick\Pdf\Filter\Exception::class,
            'jbig2dec failed to decode the stream',
            static fn(): mixed => $filter->decode('JBIG2Decode', 'invalid-jbig2-data'),
        );
    }

    public function testDct(): void
    {
        // DCT streams are self-contained JPEG files; the filter is a pass-through
        $filter = $this->getTestObject();
        $jpeg = "\xFF\xD8\xFF\xE0\x00\x10JFIF\x00";
        $this->assertSame($jpeg, $filter->decode('DCTDecode', $jpeg));
        $this->assertSame('', $filter->decode('DCTDecode', ''));
    }

    public function testJpxEmptyInput(): void
    {
        // empty input must return '' regardless of whether Imagick is loaded
        $filter = $this->getTestObject();
        $this->assertSame('', $filter->decode('JPXDecode', ''));
    }

    public function testJpx(): void
    {
        if (!extension_loaded('imagick')) {
            $this->markTestSkipped('ext-imagick is not available');
        }

        // minimal 1x1 white JP2 fixture generated with Imagick
        $jp2 =
            "\x00\x00\x00\x0c\x6a\x50\x20\x20\x0d\x0a\x87\x0a\x00\x00\x00\x14\x66\x74\x79\x70\x6a\x70\x32\x20"
            . "\x00\x00\x00\x00\x6a\x70\x32\x20\x00\x00\x00\x2d\x6a\x70\x32\x68\x00\x00\x00\x16\x69\x68\x64\x72"
            . "\x00\x00\x00\x01\x00\x00\x00\x01\x00\x03\x07\x07\x00\x00\x00\x00\x00\x0f\x63\x6f\x6c\x72\x01\x00"
            . "\x00\x00\x00\x00\x10\x00\x00\x00\x8c\x6a\x70\x32\x63\xff\x4f\xff\x51\x00\x2f\x00\x00\x00\x00\x00"
            . "\x01\x00\x00\x00\x01\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x00\x01\x00\x00\x00\x01\x00\x00\x00"
            . "\x00\x00\x00\x00\x00\x00\x03\x07\x01\x01\x07\x01\x01\x07\x01\x01\xff\x52\x00\x0c\x00\x00\x00\x01"
            . "\x01\x00\x04\x04\x00\x01\xff\x5c\x00\x04\x40\x40\xff\x64\x00\x25\x00\x01\x43\x72\x65\x61\x74\x65"
            . "\x64\x20\x62\x79\x20\x4f\x70\x65\x6e\x4a\x50\x45\x47\x20\x76\x65\x72\x73\x69\x6f\x6e\x20\x32\x2e"
            . "\x35\x2e\x33\xff\x90\x00\x0a\x00\x00\x00\x00\x00\x14\x00\x01\xff\x93\xcf\xb4\x04\x00\x80\x80\xff\xd9";

        $filter = $this->getTestObject();
        $result = $filter->decode('JPXDecode', $jp2);
        // output is a PNG blob; verify it starts with the PNG signature
        $this->assertStringStartsWith("\x89PNG", $result);
    }

    public function testCrypt(): void
    {
        // without a Name the Crypt filter acts as Identity (pass-through)
        $filter = $this->getTestObject();
        $this->assertSame('data', $filter->decode('Crypt', 'data'));
        $this->assertSame('', $filter->decode('Crypt', ''));
    }

    public function testdecodeAll(): void
    {
        $filter = $this->getTestObject();
        $code = '46 43 51 6E 3D 42 6A 72 5A 35 41 37 64 45 2A 42 6C 25 6D 26 45 57 7E 3E>';
        $result = $filter->decodeAll(['ASCIIHexDecode', 'ASCII85Decode'], $code);
        $this->assertEquals('tc-lib-pdf-filter', $result);

        $code =
            '800D878221D1186E502888C8230241847C2C158F8B26C178AC7E29178CC5642191ACDE311609CD04'
            . 'D1C918784B398F05873150C0703731954A4442E9A86E4222988DE381C46C66283A8907452371F87D01>';
        $expected = "BT\n/F1 30 Tf 350 750 Td 20 TL\n1 Tr (Hello world) Tj \nET";
        $result = $filter->decodeAll(['ASCIIHexDecode', 'LZWDecode', 'ASCII85Decode'], $code);
        $this->assertEquals($expected, $result);
    }

    public function testDecodeAllWithNoFiltersReturnsTheDataUnchanged(): void
    {
        $filter = $this->getTestObject();

        $this->assertSame('tc-lib-pdf-filter', $filter->decodeAll([], 'tc-lib-pdf-filter'));
        $this->assertSame('tc-lib-pdf-filter', $filter->decodeAll([], 'tc-lib-pdf-filter', ['Predictor' => 12]));
    }

    public function testDecodeAllBroadcastsASingleDecodeParmsDictionary(): void
    {
        $filter = $this->getTestObject();
        $payload = (string) gzcompress(str_repeat('A', 512));

        // a dictionary (string keys) applies to every filter in the chain
        $this->assertSame(512, strlen($filter->decodeAll(['FlateDecode'], $payload, ['MaxOutputSize' => 512])));

        $this->assertThrows(
            '\\' . \Com\Tecnick\Pdf\Filter\Exception::class,
            'MaxOutputSize of 16 bytes',
            static fn(): mixed => $filter->decodeAll(['FlateDecode'], $payload, ['MaxOutputSize' => 16]),
        );
    }

    /**
     * A PDF dictionary is keyed by name objects, so a non-empty list is the
     * /DecodeParms array form, whatever its entries hold.
     */
    public function testDecodeAllTreatsAnyListAsPositionalDecodeParms(): void
    {
        $filter = $this->getTestObject();
        $payload = (string) gzcompress(str_repeat('A', 512));

        // the second entry is malformed; the first still caps FlateDecode
        $this->assertThrows(
            '\\' . \Com\Tecnick\Pdf\Filter\Exception::class,
            'MaxOutputSize of 16 bytes',
            static fn(): mixed => $filter->decodeAll(['FlateDecode'], $payload, [['MaxOutputSize' => 16], 'junk']),
        );

        // an entry that is not a dictionary leaves its filter without parameters
        $this->assertSame(512, strlen($filter->decodeAll(['FlateDecode'], $payload, ['junk'])));
    }

    public function testDecodeAllPositionalDecodeParmsMayBeShorterThanTheChain(): void
    {
        $filter = $this->getTestObject();
        $inner = (string) gzcompress(str_repeat('A', 512));
        $code = strtoupper(bin2hex($inner)) . '>';

        // one entry for two filters: FlateDecode simply gets no parameters
        $this->assertSame(512, strlen($filter->decodeAll(['ASCIIHexDecode', 'FlateDecode'], $code, [null])));
    }

    public function testDecodeAllAppliesPositionalDecodeParms(): void
    {
        $filter = $this->getTestObject();

        // /DecodeParms may be an array parallel to /Filter, one entry per filter:
        // here only the second filter (FlateDecode) gets a budget, and a null
        // entry leaves the corresponding filter with no parameters
        $inner = (string) gzcompress(str_repeat('A', 512));
        $code = strtoupper(bin2hex($inner)) . '>';

        $result = $filter->decodeAll(['ASCIIHexDecode', 'FlateDecode'], $code, [null, ['MaxOutputSize' => 512]]);
        $this->assertSame(str_repeat('A', 512), $result);

        // the same budget in the first position belongs to ASCIIHexDecode, which
        // ignores it, so Flate stays uncapped and the chain succeeds
        $uncapped = $filter->decodeAll(['ASCIIHexDecode', 'FlateDecode'], $code, [['MaxOutputSize' => 16], null]);
        $this->assertSame(512, strlen($uncapped));
    }

    public function testDecodeAllPositionalDecodeParmsCapTheMatchingFilter(): void
    {
        $filter = $this->getTestObject();
        $inner = (string) gzcompress(str_repeat('A', 512));
        $code = strtoupper(bin2hex($inner)) . '>';

        // in the second position the budget reaches FlateDecode, which enforces it
        $this->assertThrows(
            '\\' . \Com\Tecnick\Pdf\Filter\Exception::class,
            'MaxOutputSize of 16 bytes',
            static fn(): mixed => $filter->decodeAll(['ASCIIHexDecode', 'FlateDecode'], $code, [
                null,
                ['MaxOutputSize' => 16],
            ]),
        );
    }
}

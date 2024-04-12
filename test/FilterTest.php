<?php

/**
 * FilterTest.php
 *
 * @since     2011-05-23
 * @category  Library
 * @package   PdfFilter
 * @author    Nicola Asuni <info@tecnick.com>
 * @copyright 2011-2024 Nicola Asuni - Tecnick.com LTD
 * @license   http://www.gnu.org/copyleft/lesser.html GNU-LGPL v3 (see LICENSE.TXT)
 * @link      https://github.com/tecnickcom/tc-lib-pdf-filter
 *
 * This file is part of tc-lib-pdf-filter software library.
 */

namespace Test;

/**
 * Filter Test
 *
 * @since     2011-05-23
 * @category  Library
 * @package   PdfFilter
 * @author    Nicola Asuni <info@tecnick.com>
 * @copyright 2011-2024 Nicola Asuni - Tecnick.com LTD
 * @license   http://www.gnu.org/copyleft/lesser.html GNU-LGPL v3 (see LICENSE.TXT)
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

    public function testUnknownFilter(): void
    {
        $this->bcExpectException('\\' . \Com\Tecnick\Pdf\Filter\Exception::class);
        $filter = $this->getTestObject();
        $filter->decode('Unknown', 'YZ');
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

        $code = '30 31 32 33 34 35 36 37 38 39 9>';
        $result = $filter->decode('ASCIIHexDecode', $code);
        $this->assertEquals("0123456789\t", $result);
    }

    public function testAsciiHexEx(): void
    {
        $this->bcExpectException('\\' . \Com\Tecnick\Pdf\Filter\Exception::class);
        $filter = $this->getTestObject();
        $code = '30 31 32 33 34 35 36 37 38 39 9';
        $filter->decode('ASCIIHexDecode', $code);
    }

    public function testAsciiHexException(): void
    {
        $this->bcExpectException('\\' . \Com\Tecnick\Pdf\Filter\Exception::class);
        $filter = $this->getTestObject();
        $filter->decode('ASCIIHexDecode', 'YZ 34 HJ>');
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
            '  9Q+r_D\'3P3F*2=BA8c:&EZfF;F<G"/ATTIG@rH7+ARfgnFEMUH@:X(kBldcuDJ()\'Ch[t '
        );
        $this->assertEquals('Lorem ipsum dolor sit amet, consectetur adipiscing elit', $result);
    }

    public function testAsciiEightFiveEx(): void
    {
        $this->bcExpectException('\\' . \Com\Tecnick\Pdf\Filter\Exception::class);
        $filter = $this->getTestObject();
        $filter->decode('ASCII85Decode', chr(254));
    }

    public function testFlate(): void
    {
        $filter = $this->getTestObject();
        $code = "\x78\x9c\x2b\x49\xd6\xcd\xc9\x4c\xd2\x2d\x48\x49\xd3\x4d\xcb\xcc\x29\x49\x2d\x2\x0\x37\x64\x6\x56";
        $result = $filter->decode('FlateDecode', $code);
        $this->assertEquals('tc-lib-pdf-filter', $result);
    }

    public function testFlateEx(): void
    {
        $this->bcExpectException('\\' . \Com\Tecnick\Pdf\Filter\Exception::class);
        $filter = $this->getTestObject();
        $filter->decode('FlateDecode', 'ABC');
    }

    public function testRunLength(): void
    {
        $filter = $this->getTestObject();
        $code = chr(247) . 'A' . chr(18) . ' tc-lib-pdf-filter ' . chr(247) . 'B' . chr(128);
        $result = $filter->decode('RunLengthDecode', $code);
        $this->assertEquals('AAAAAAAAAA tc-lib-pdf-filter BBBBBBBBBB', $result);
    }

    public function testCcittFax(): void
    {
        $this->bcExpectException('\\' . \Com\Tecnick\Pdf\Filter\Exception::class);
        $filter = $this->getTestObject();
        $filter->decode('CCITTFaxDecode', 'data');
    }

    public function testJbigTwo(): void
    {
        $this->bcExpectException('\\' . \Com\Tecnick\Pdf\Filter\Exception::class);
        $filter = $this->getTestObject();
        $filter->decode('JBIG2Decode', 'data');
    }

    public function testDct(): void
    {
        $this->bcExpectException('\\' . \Com\Tecnick\Pdf\Filter\Exception::class);
        $filter = $this->getTestObject();
        $filter->decode('DCTDecode', 'data');
    }

    public function testJpx(): void
    {
        $this->bcExpectException('\\' . \Com\Tecnick\Pdf\Filter\Exception::class);
        $filter = $this->getTestObject();
        $filter->decode('JPXDecode', 'data');
    }

    public function testCrypt(): void
    {
        $this->bcExpectException('\\' . \Com\Tecnick\Pdf\Filter\Exception::class);
        $filter = $this->getTestObject();
        $filter->decode('Crypt', 'data');
    }

    public function testdecodeAll(): void
    {
        $filter = $this->getTestObject();
        $code = '46 43 51 6E 3D 42 6A 72 5A 35 41 37 64 45 2A 42 6C 25 6D 26 45 57 7E 3E>';
        $result = $filter->decodeAll(['ASCIIHexDecode', 'ASCII85Decode'], $code);
        $this->assertEquals('tc-lib-pdf-filter', $result);

        $code = '800D878221D1186E502888C8230241847C2C158F8B26C178AC7E29178CC5642191ACDE311609CD04'
            . 'D1C918784B398F05873150C0703731954A4442E9A86E4222988DE381C46C66283A8907452371F87D01>';
        $expected = "BT\n/F1 30 Tf 350 750 Td 20 TL\n1 Tr (Hello world) Tj \nET";
        $result = $filter->decodeAll(['ASCIIHexDecode', 'LZWDecode', 'ASCII85Decode'], $code);
        $this->assertEquals($expected, $result);
    }
}

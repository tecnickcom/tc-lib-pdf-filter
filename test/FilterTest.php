<?php
/**
 * FilterTest.php
 *
 * @since       2011-05-23
 * @category    Library
 * @package     PdfFilter
 * @author      Nicola Asuni <info@tecnick.com>
 * @copyright   2011-2015 Nicola Asuni - Tecnick.com LTD
 * @license     http://www.gnu.org/copyleft/lesser.html GNU-LGPL v3 (see LICENSE.TXT)
 * @link        https://github.com/tecnickcom/tc-lib-pdf-filter
 *
 * This file is part of tc-lib-pdf-filter software library.
 */

namespace Test;

use PHPUnit\Framework\TestCase;

/**
 * Filter Test
 *
 * @since       2011-05-23
 * @category    Library
 * @package     PdfFilter
 * @author      Nicola Asuni <info@tecnick.com>
 * @copyright   2011-2015 Nicola Asuni - Tecnick.com LTD
 * @license     http://www.gnu.org/copyleft/lesser.html GNU-LGPL v3 (see LICENSE.TXT)
 * @link        https://github.com/tecnickcom/tc-lib-pdf-filter
 */
class FilterTest extends TestUtil
{
    protected function getTestObject()
    {
        return new \Com\Tecnick\Pdf\Filter\Filter;
    }

    public function testEmptyFilter()
    {
        $testObj = $this->getTestObject();
        $result = $testObj->decode('', 'tc-lib-pdf-filter');
        $this->assertEquals('tc-lib-pdf-filter', $result);
    }

    public function testUnknownFilter()
    {
        $this->bcExpectException('\Com\Tecnick\Pdf\Filter\Exception');
        $testObj = $this->getTestObject();
        $testObj->decode('Unknown', 'YZ');
    }

    public function testAsciiHex()
    {
        $testObj = $this->getTestObject();
        $code = '74 63 2D 6C 69 62 2D 70 64 66 2D 66 69 6C 74 65 72>';
        $result = $testObj->decode('ASCIIHexDecode', $code);
        $this->assertEquals('tc-lib-pdf-filter', $result);
        
        $code = '74632D6C69622D7064662D66696C746572>';
        $result = $testObj->decode('ASCIIHexDecode', $code);
        $this->assertEquals('tc-lib-pdf-filter', $result);

        $code = '30 31 32 33 34 35 36 37 38 39 9>';
        $result = $testObj->decode('ASCIIHexDecode', $code);
        $this->assertEquals("0123456789\t", $result);
    }

    public function testAsciiHexEx()
    {
        $this->bcExpectException('\Com\Tecnick\Pdf\Filter\Exception');
        $testObj = $this->getTestObject();
        $code = '30 31 32 33 34 35 36 37 38 39 9';
        $testObj->decode('ASCIIHexDecode', $code);
    }

    public function testAsciiHexException()
    {
        $this->bcExpectException('\Com\Tecnick\Pdf\Filter\Exception');
        $testObj = $this->getTestObject();
        $testObj->decode('ASCIIHexDecode', 'YZ 34 HJ>');
    }
    
    public function testAsciiEightFive()
    {
        $testObj = $this->getTestObject();
        $code = '<~FCQn=BjrZ5A7dE*Bl%m&EW~>';
        $result = $testObj->decode('ASCII85Decode', $code);
        $this->assertEquals('tc-lib-pdf-filter', $result);
    }

    public function testAsciiEightFiveEx()
    {
        $this->bcExpectException('\Com\Tecnick\Pdf\Filter\Exception');
        $testObj = $this->getTestObject();
        $testObj->decode('ASCII85Decode', chr(254));
    }

    public function testFlate()
    {
        $testObj = $this->getTestObject();
        $code ="\x78\x9c\x2b\x49\xd6\xcd\xc9\x4c\xd2\x2d\x48\x49\xd3\x4d\xcb\xcc\x29\x49\x2d\x2\x0\x37\x64\x6\x56";
        $result = $testObj->decode('FlateDecode', $code);
        $this->assertEquals('tc-lib-pdf-filter', $result);
    }

    public function testFlateEx()
    {
        $this->bcExpectException('\Com\Tecnick\Pdf\Filter\Exception');
        $testObj = $this->getTestObject();
        $testObj->decode('FlateDecode', 'ABC');
    }
    
    public function testRunLength()
    {
        $testObj = $this->getTestObject();
        $code = chr(247).'A'.chr(18).' tc-lib-pdf-filter '.chr(247).'B'.chr(128);
        $result = $testObj->decode('RunLengthDecode', $code);
        $this->assertEquals('AAAAAAAAAA tc-lib-pdf-filter BBBBBBBBBB', $result);
    }

    public function testCcittFax()
    {
        $this->bcExpectException('\Com\Tecnick\Pdf\Filter\Exception');
        $testObj = $this->getTestObject();
        $testObj->decode('CCITTFaxDecode', '');
    }

    public function testJbigTwo()
    {
        $this->bcExpectException('\Com\Tecnick\Pdf\Filter\Exception');
        $testObj = $this->getTestObject();
        $testObj->decode('JBIG2Decode', '');
    }

    public function testDct()
    {
        $this->bcExpectException('\Com\Tecnick\Pdf\Filter\Exception');
        $testObj = $this->getTestObject();
        $testObj->decode('DCTDecode', '');
    }

    public function testJpx()
    {
        $this->bcExpectException('\Com\Tecnick\Pdf\Filter\Exception');
        $testObj = $this->getTestObject();
        $testObj->decode('JPXDecode', '');
    }

    public function testCrypt()
    {
        $this->bcExpectException('\Com\Tecnick\Pdf\Filter\Exception');
        $testObj = $this->getTestObject();
        $testObj->decode('Crypt', '');
    }
    
    public function testdecodeAll()
    {
        $testObj = $this->getTestObject();
        $code = '3C 7E 46 43 51 6E 3D 42 6A 72 5A 35 41 37 64 45 2A 42 6C 25 6D 26 45 57 7E 3E>';
        $result = $testObj->decodeAll(array('ASCIIHexDecode', 'ASCII85Decode'), $code);
        $this->assertEquals('tc-lib-pdf-filter', $result);

        $code = '800D878221D1186E502888C8230241847C2C158F8B26C178AC7E29178CC5642191ACDE311609CD04'
            .'D1C918784B398F05873150C0703731954A4442E9A86E4222988DE381C46C66283A8907452371F87D01>';
        $expected = "BT\n/F1 30 Tf 350 750 Td 20 TL\n1 Tr (Hello world) Tj \nET";
        $result = $testObj->decodeAll(array('ASCIIHexDecode', 'LZWDecode', 'ASCII85Decode'), $code);
        $this->assertEquals($expected, $result);
    }
}

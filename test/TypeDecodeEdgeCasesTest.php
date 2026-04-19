<?php

/**
 * TypeDecodeEdgeCasesTest.php
 *
 * @since     2026-04-19
 * @category  Library
 * @package   PdfFilter
 * @author    Nicola Asuni <info@tecnick.com>
 * @copyright 2011-2026 Nicola Asuni - Tecnick.com LTD
 * @license   https://www.gnu.org/copyleft/lesser.html GNU-LGPL v3 (see LICENSE.TXT)
 * @link      https://github.com/tecnickcom/tc-lib-pdf-filter
 *
 * This file is part of tc-lib-pdf-filter software library.
 */

namespace Test;

class TypeDecodeEdgeCasesTest extends TestUtil
{
    public function testTypeDecodeEmptyInput(): void
    {
        $types = [
            new \Com\Tecnick\Pdf\Filter\Type\AsciiEightFive(),
            new \Com\Tecnick\Pdf\Filter\Type\AsciiHex(),
            new \Com\Tecnick\Pdf\Filter\Type\CcittFax(),
            new \Com\Tecnick\Pdf\Filter\Type\Crypt(),
            new \Com\Tecnick\Pdf\Filter\Type\Dct(),
            new \Com\Tecnick\Pdf\Filter\Type\Flate(),
            new \Com\Tecnick\Pdf\Filter\Type\JbigTwo(),
            new \Com\Tecnick\Pdf\Filter\Type\Jpx(),
            new \Com\Tecnick\Pdf\Filter\Type\Lzw(),
            new \Com\Tecnick\Pdf\Filter\Type\RunLength(),
        ];

        foreach ($types as $type) {
            $this->assertSame('', $type->decode(''));
        }
    }

    public function testAsciiEightFiveInvalidZInsideGroup(): void
    {
        $this->bcExpectException('\\' . \Com\Tecnick\Pdf\Filter\Exception::class);
        $obj = new \Com\Tecnick\Pdf\Filter\Type\AsciiEightFive();
        $obj->decode('!z~>');
    }

    public function testAsciiEightFiveLastTupleCases(): void
    {
        $obj = new \Com\Tecnick\Pdf\Filter\Type\AsciiEightFive();

        // Ends with an incomplete 4-char tuple.
        $res4 = $obj->decode('!!!!~>');
        $this->assertSame(3, \strlen($res4));

        // Ends with an incomplete 2-char tuple.
        $res2 = $obj->decode('!!~>');
        $this->assertSame(1, \strlen($res2));

        // Ends with an incomplete 3-char tuple.
        $res3 = $obj->decode('!!!~>');
        $this->assertSame(2, \strlen($res3));
    }

    public function testAsciiEightFiveSingleTrailingCharThrows(): void
    {
        $this->bcExpectException('\\' . \Com\Tecnick\Pdf\Filter\Exception::class);
        $obj = new \Com\Tecnick\Pdf\Filter\Type\AsciiEightFive();
        $obj->decode('!~>');
    }

    public function testLzwProcessIndexOutsideDictionary(): void
    {
        $obj = new LzwProxy();

        $decoded = '';
        $bitstring = '000000000';
        $bitlen = 9;
        $dataLength = 9;
        $index = 300;
        $dictionary = [];
        for ($i = 0; $i < 256; ++$i) {
            $dictionary[$i] = \chr($i);
        }

        $dix = 258;
        $prevIndex = 65;
        $obj->callProcess($decoded, $bitstring, $bitlen, $dataLength, $index, $dictionary, $dix, $prevIndex);

        $this->assertSame('AA', $decoded);
        $this->assertSame('AA', $dictionary[258]);
        $this->assertSame(259, $dix);
    }

    public function testLzwProcessBitLengthThresholds(): void
    {
        $obj = new LzwProxy();

        $cases = [
            [510, 10],
            [1022, 11],
            [2046, 12],
        ];

        foreach ($cases as [$startDix, $expectedBitlen]) {
            $decoded = '';
            $bitstring = '000000000';
            $bitlen = 9;
            $dataLength = 9;
            $index = 65;
            $dictionary = [];
            for ($i = 0; $i < 256; ++$i) {
                $dictionary[$i] = \chr($i);
            }

            $dix = $startDix;
            $prevIndex = 66;

            $obj->callProcess($decoded, $bitstring, $bitlen, $dataLength, $index, $dictionary, $dix, $prevIndex);

            $this->assertSame($expectedBitlen, $bitlen);
        }
    }
}

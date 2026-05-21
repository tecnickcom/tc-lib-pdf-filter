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
    /**
     * @return array<int, string>
     */
    private function getInitialLzwDictionary(): array
    {
        $dictionary = [];
        for ($i = 0; $i < 256; ++$i) {
            $dictionary[$i] = \chr($i);
        }

        return $dictionary;
    }

    /**
     * @param array{bitlen: int, dix: int, prev_index: int, dictionary: array<int, string>, decoded: string} $state
     *
     * @return array{bitlen: int, dix: int, prev_index: int, dictionary: array<int, string>, decoded: string}
     */
    private function callLzwProcessIndex(\Com\Tecnick\Pdf\Filter\Type\Lzw $obj, int $index, array $state): array
    {
        /**
         * @var \Closure(\Com\Tecnick\Pdf\Filter\Type\Lzw, int, array{bitlen: int, dix: int, prev_index: int, dictionary: array<int, string>, decoded: string}): array{bitlen: int, dix: int, prev_index: int, dictionary: array<int, string>, decoded: string}|null $caller
         */
        $caller = \Closure::bind(
            /** @param array{bitlen: int, dix: int, prev_index: int, dictionary: array<int, string>, decoded: string} $lzwState */
            static fn(
                \Com\Tecnick\Pdf\Filter\Type\Lzw $target,
                int $code,
                array $lzwState,
            ): array => $target->processIndex($code, $lzwState),
            null,
            \Com\Tecnick\Pdf\Filter\Type\Lzw::class,
        );

        if ($caller === null) {
            return $state;
        }

        return $caller($obj, $index, $state);
    }

    private function getCcittTagValue(string $tiff, int $tagId): int
    {
        // The implementation writes: "II" + 4-byte IFD offset, so IFD starts at byte 6.
        $header = \unpack('vcount', \substr($tiff, 6, 2));
        $count = (int) ($header['count'] ?? 0);
        $cursor = 8;

        for ($i = 0; $i < $count; ++$i) {
            $entry = \unpack('Vtag/Vtype/Vitems/Vvalue', \substr($tiff, $cursor, 16));
            if ((int) ($entry['tag'] ?? 0) === $tagId) {
                return (int) ($entry['value'] ?? 0);
            }

            $cursor += 16;
        }

        return 0;
    }

    private function buildCcittHeader(\Com\Tecnick\Pdf\Filter\Type\CcittFax $obj, string $data): string
    {
        /** @var \Closure(\Com\Tecnick\Pdf\Filter\Type\CcittFax, string): string|null $builder */
        $builder = \Closure::bind(
            static fn(
                \Com\Tecnick\Pdf\Filter\Type\CcittFax $target,
                string $ccittData,
            ): string => $target->buildTiffHeader($ccittData),
            null,
            \Com\Tecnick\Pdf\Filter\Type\CcittFax::class,
        );

        if ($builder === null) {
            return '';
        }

        return $builder($obj, $data);
    }

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

    public function testLzwProcessIndexAtOrBeyondDictionarySize(): void
    {
        $obj = new \Com\Tecnick\Pdf\Filter\Type\Lzw();
        $state = [
            'bitlen' => 9,
            'dix' => 259,
            'prev_index' => 65,
            'dictionary' => $this->getInitialLzwDictionary(),
            'decoded' => '',
        ];

        $updated = $this->callLzwProcessIndex($obj, 300, $state);

        $this->assertSame('AA', $updated['decoded']);
        $this->assertSame('AA', $updated['dictionary'][259]);
        $this->assertSame(260, $updated['dix']);
    }

    public function testLzwProcessIndexBitLengthMatchBranches(): void
    {
        $obj = new \Com\Tecnick\Pdf\Filter\Type\Lzw();
        $cases = [
            [510, 10],
            [1022, 11],
            [2046, 12],
        ];

        foreach ($cases as [$startDix, $expectedBitlen]) {
            $state = [
                'bitlen' => 9,
                'dix' => $startDix,
                'prev_index' => 66,
                'dictionary' => $this->getInitialLzwDictionary(),
                'decoded' => '',
            ];

            $updated = $this->callLzwProcessIndex($obj, 65, $state);
            $this->assertSame($expectedBitlen, $updated['bitlen']);
        }
    }

    public function testCcittFaxConstructorNormalizesParams(): void
    {
        $ccittData = "\xAA\xBB";

        $asTrue = new \Com\Tecnick\Pdf\Filter\Type\CcittFax([
            'BlackIs1' => 'YeS',
            'K' => -1,
            'Columns' => 10,
            'Rows' => 0,
        ]);

        $tiffTrue = $this->buildCcittHeader($asTrue, $ccittData);
        $this->assertSame(3, $this->getCcittTagValue($tiffTrue, 259));
        $this->assertSame(1, $this->getCcittTagValue($tiffTrue, 262));
        $this->assertSame(10, $this->getCcittTagValue($tiffTrue, 256));
        $this->assertSame(2, $this->getCcittTagValue($tiffTrue, 257));

        $asFalse = new \Com\Tecnick\Pdf\Filter\Type\CcittFax([
            'BlackIs1' => ['unexpected'],
            'Columns' => 0,
            'Rows' => -5,
        ]);

        $tiffFalse = $this->buildCcittHeader($asFalse, $ccittData);
        $this->assertSame(4, $this->getCcittTagValue($tiffFalse, 259));
        $this->assertSame(0, $this->getCcittTagValue($tiffFalse, 262));
        $this->assertSame(1, $this->getCcittTagValue($tiffFalse, 256));
        $this->assertSame(16, $this->getCcittTagValue($tiffFalse, 257));
    }

    public function testCcittFaxConstructorAcceptsNumericAndBooleanBlackIs1(): void
    {
        $ccittData = "\x0F";

        $numericTrue = new \Com\Tecnick\Pdf\Filter\Type\CcittFax(['BlackIs1' => 2]);
        $tiffNumeric = $this->buildCcittHeader($numericTrue, $ccittData);
        $this->assertSame(1, $this->getCcittTagValue($tiffNumeric, 262));

        $boolFalse = new \Com\Tecnick\Pdf\Filter\Type\CcittFax(['BlackIs1' => false]);
        $tiffBool = $this->buildCcittHeader($boolFalse, $ccittData);
        $this->assertSame(0, $this->getCcittTagValue($tiffBool, 262));
    }
}

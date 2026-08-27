<?php

/**
 * PredictorTest.php
 *
 * @since     2026-08-27
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

use Com\Tecnick\Pdf\Filter\Filter;
use Com\Tecnick\Pdf\Filter\Predictor;

/**
 * Predictor class test
 *
 * @since     2026-08-27
 * @category  Library
 * @package   PdfFilter
 * @author    Nicola Asuni <info@tecnick.com>
 * @copyright 2011-2026 Nicola Asuni - Tecnick.com LTD
 * @license   https://www.gnu.org/copyleft/lesser.html GNU-LGPL v3 (see LICENSE)
 * @link      https://github.com/tecnickcom/tc-lib-pdf-filter
 */
class PredictorTest extends TestUtil
{
    /**
     * Encode rows with one PNG row filter (RFC 2083), the inverse of the decoder.
     *
     * @param array<int, string> $rows Raw rows.
     * @param int                $tag  Row algorithm tag (0 to 4).
     * @param int                $bpp  Bytes per pixel.
     */
    private function encodePng(array $rows, int $tag, int $bpp): string
    {
        $out = '';
        $prev = \str_repeat("\x00", \strlen($rows[0]));
        foreach ($rows as $row) {
            $out .= \chr($tag);
            $length = \strlen($row);
            for ($i = 0; $i < $length; ++$i) {
                $left = $i >= $bpp ? \ord($row[$i - $bpp]) : 0;
                $up = \ord($prev[$i]);
                $upLeft = $i >= $bpp ? \ord($prev[$i - $bpp]) : 0;
                $predicted = match ($tag) {
                    1 => $left,
                    2 => $up,
                    3 => \intdiv($left + $up, 2),
                    4 => $this->paeth($left, $up, $upLeft),
                    default => 0,
                };
                $out .= \chr((\ord($row[$i]) - $predicted) & 0xFF);
            }

            $prev = $row;
        }

        return $out;
    }

    private function paeth(int $left, int $up, int $upLeft): int
    {
        $estimate = $left + $up - $upLeft;
        $distLeft = \abs($estimate - $left);
        $distUp = \abs($estimate - $up);
        $distUpLeft = \abs($estimate - $upLeft);

        if ($distLeft <= $distUp && $distLeft <= $distUpLeft) {
            return $left;
        }

        return $distUp <= $distUpLeft ? $up : $upLeft;
    }

    /**
     * @return array<int, string>
     */
    private function sampleRows(): array
    {
        $rows = [];
        for ($y = 0; $y < 5; ++$y) {
            $row = '';
            for ($x = 0; $x < 4; ++$x) {
                $row .= \chr((($x * 13) + ($y * 7)) % 256) . \chr(($x * $y) % 256) . \chr(($x ^ ($y * 3)) % 256);
            }

            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * @throws \Com\Tecnick\Pdf\Filter\Exception
     */
    public function testPredictorOneAndBelowLeaveDataUnchanged(): void
    {
        $obj = new Predictor();
        $data = "\x01\x02\x03\x04";

        $this->assertSame($data, $obj->apply($data));
        $this->assertSame($data, $obj->apply($data, ['Predictor' => 1]));
        $this->assertSame('', $obj->apply('', ['Predictor' => 12]));
        // Predictor 1 leaves even a malformed geometry alone: nothing reads it
        $this->assertSame($data, $obj->apply($data, ['Predictor' => 1, 'BitsPerComponent' => 3]));
    }

    /**
     * Whether the parameters are malformed does not depend on how long the
     * stream turned out to be, so an empty one is validated just the same.
     *
     * @throws \Com\Tecnick\Pdf\Filter\Exception
     */
    public function testPredictorValidatesTheParametersOfAnEmptyStream(): void
    {
        $obj = new Predictor();

        $this->assertThrows(
            '\\' . \Com\Tecnick\Pdf\Filter\Exception::class,
            'unsupported Predictor: 77',
            static fn(): mixed => $obj->apply('', ['Predictor' => 77]),
        );

        $this->assertThrows(
            '\\' . \Com\Tecnick\Pdf\Filter\Exception::class,
            'unsupported BitsPerComponent: 3',
            static fn(): mixed => $obj->apply('', ['Predictor' => 12, 'BitsPerComponent' => 3]),
        );

        $this->assertThrows(
            '\\' . \Com\Tecnick\Pdf\Filter\Exception::class,
            'unsupported Colors: 70000',
            static fn(): mixed => $obj->apply('', ['Predictor' => 2, 'Colors' => 70000]),
        );

        // well-formed parameters still return the empty stream unchanged
        $this->assertSame('', $obj->apply('', ['Predictor' => 12, 'Colors' => 3, 'Columns' => 4]));
    }

    /**
     * @throws \Com\Tecnick\Pdf\Filter\Exception
     */
    public function testPngPredictorReversesEveryRowFilter(): void
    {
        $obj = new Predictor();
        $rows = $this->sampleRows();
        $expected = \implode('', $rows);

        foreach ([0, 1, 2, 3, 4] as $tag) {
            $this->assertSame($expected, $obj->apply($this->encodePng($rows, $tag, 3), [
                'Predictor' => 15,
                'Colors' => 3,
                'BitsPerComponent' => 8,
                'Columns' => 4,
            ]), 'row filter ' . $tag);
        }
    }

    /**
     * Sub-byte components give a one-byte prediction stride (RFC 2083).
     *
     * @throws \Com\Tecnick\Pdf\Filter\Exception
     */
    public function testPngPredictorHandlesSubByteComponents(): void
    {
        $obj = new Predictor();
        $rows = ["\x0F\xA5", "\x33\x11"];

        $this->assertSame(\implode('', $rows), $obj->apply($this->encodePng($rows, 4, 1), [
            'Predictor' => 10,
            'Colors' => 1,
            'BitsPerComponent' => 1,
            'Columns' => 16,
        ]));
    }

    /**
     * A PNG row shorter than the declared row length is truncated data.
     *
     * @throws \Com\Tecnick\Pdf\Filter\Exception
     */
    public function testPngPredictorRejectsATruncatedRow(): void
    {
        $obj = new Predictor();

        $this->assertThrows(
            '\\' . \Com\Tecnick\Pdf\Filter\Exception::class,
            'truncated predictor row',
            static fn(): mixed => $obj->apply("\x00\x01\x02", ['Predictor' => 12, 'Columns' => 4]),
        );

        // only row: tag byte plus 3 of the 4 row bytes
        $this->assertThrows(
            '\\' . \Com\Tecnick\Pdf\Filter\Exception::class,
            'truncated predictor row',
            static fn(): mixed => $obj->apply("\x00\x01\x02\x03", ['Predictor' => 12, 'Columns' => 4]),
        );

        // first row complete, second short by one byte
        $this->assertThrows(
            '\\' . \Com\Tecnick\Pdf\Filter\Exception::class,
            'truncated predictor row',
            static fn(): mixed => $obj->apply("\x00\x01\x02\x03\x04\x00\x01\x02\x03", [
                'Predictor' => 12,
                'Columns' => 4,
            ]),
        );
    }

    /**
     * A row whose bit width is not a whole number of bytes must round up: 3
     * 4-bit samples are 12 bits, which is 2 bytes per row, not 1.
     *
     * @throws \Com\Tecnick\Pdf\Filter\Exception
     */
    public function testPngPredictorRoundsTheRowLengthUpToWholeBytes(): void
    {
        $obj = new Predictor();

        $this->assertSame("\xAB\xC0\xBC\xD0", $obj->apply("\x00\xAB\xC0\x00\xBC\xD0", [
            'Predictor' => 12,
            'Colors' => 1,
            'BitsPerComponent' => 4,
            'Columns' => 3,
        ]));
    }

    /**
     * RFC 2083 breaks a Paeth tie towards the left neighbour first, then the
     * one above.
     *
     * @throws \Com\Tecnick\Pdf\Filter\Exception
     */
    public function testPngPredictorBreaksPaethTiesTowardsTheLeftNeighbour(): void
    {
        $obj = new Predictor();

        $params = ['Predictor' => 12, 'Colors' => 1, 'BitsPerComponent' => 8, 'Columns' => 2];

        // row above 02 03; on row 2 byte 1 has left = 0, up = 3, upLeft = 2, so
        // the estimate is 1 and left and up are both 1 away: left (0) wins
        $this->assertSame("\x02\x03\x00\x05", $obj->apply("\x00\x02\x03\x04\xFE\x05", $params));

        // row above 01 03; left = 0, up = 3, upLeft = 1, so left is 2 away while
        // up and upLeft are both 1 away: the second tie goes to up (3)
        $this->assertSame("\x01\x03\x00\x05", $obj->apply("\x00\x01\x03\x04\xFF\x02", $params));
    }

    /**
     * @throws \Com\Tecnick\Pdf\Filter\Exception
     */
    public function testPngPredictorRejectsAnUnknownRowTag(): void
    {
        $obj = new Predictor();

        $this->assertThrows(
            '\\' . \Com\Tecnick\Pdf\Filter\Exception::class,
            'unknown predictor row tag 5',
            static fn(): mixed => $obj->apply("\x05\x01\x02\x03\x04", ['Predictor' => 12, 'Columns' => 4]),
        );
    }

    /**
     * @throws \Com\Tecnick\Pdf\Filter\Exception
     */
    public function testTiffPredictorReversesHorizontalDifferencing(): void
    {
        $obj = new Predictor();

        // two rows of 3 RGB samples; each row is differenced against the sample
        // to its left, component by component
        $predicted = ["\x0A\x14\x1E\x01\x01\x01\x01\x01\x01", "\xFF\x00\x80\x02\x02\x02\x02\x02\x02"];
        $expected = ["\x0A\x14\x1E\x0B\x15\x1F\x0C\x16\x20", "\xFF\x00\x80\x01\x02\x82\x03\x04\x84"];

        $this->assertSame(\implode('', $expected), $obj->apply(\implode('', $predicted), [
            'Predictor' => 2,
            'Colors' => 3,
            'BitsPerComponent' => 8,
            'Columns' => 3,
        ]));
    }

    /**
     * @throws \Com\Tecnick\Pdf\Filter\Exception
     */
    public function testTiffPredictorReversesSixteenBitComponents(): void
    {
        $obj = new Predictor();

        // three 16-bit big-endian samples: 0x0100, +0x0002, +0xFFFF (wraps)
        $this->assertSame("\x01\x00\x01\x02\x01\x01", $obj->apply("\x01\x00\x00\x02\xFF\xFF", [
            'Predictor' => 2,
            'Colors' => 1,
            'BitsPerComponent' => 16,
            'Columns' => 3,
        ]));
    }

    /**
     * A 16-bit row of odd length cannot hold a whole component in its last byte,
     * so that byte is carried through untouched.
     *
     * @throws \Com\Tecnick\Pdf\Filter\Exception
     */
    public function testTiffPredictorLeavesAnOddTrailingByteUntouched(): void
    {
        $obj = new Predictor();

        // 0x0100 then +0x0002; the trailing 0xAA is half a component
        $this->assertSame("\x01\x00\x01\x02\xAA", $obj->apply("\x01\x00\x00\x02\xAA", [
            'Predictor' => 2,
            'Colors' => 1,
            'BitsPerComponent' => 16,
            'Columns' => 3,
        ]));
    }

    /**
     * The stride spans Colors components, so a 16-bit RGB sample is differenced
     * against the sample three components back, not the word before it.
     *
     * @throws \Com\Tecnick\Pdf\Filter\Exception
     */
    public function testTiffPredictorReversesSixteenBitMultiComponentSamples(): void
    {
        $obj = new Predictor();

        // row of two 2-component samples: 0x0010 0x0020 then +0x0001 +0x0002
        $this->assertSame("\x00\x10\x00\x20\x00\x11\x00\x22", $obj->apply("\x00\x10\x00\x20\x00\x01\x00\x02", [
            'Predictor' => 2,
            'Colors' => 2,
            'BitsPerComponent' => 16,
            'Columns' => 2,
        ]));
    }

    /**
     * Rows are split at the row length, so a final row shorter than that is
     * decoded on its own rather than folded into the one before it.
     *
     * @throws \Com\Tecnick\Pdf\Filter\Exception
     */
    public function testTiffPredictorHandlesAShortFinalRow(): void
    {
        $obj = new Predictor();

        // row 1 is 0A 01 01 -> 0A 0B 0C; row 2 holds a single byte, 0B
        $this->assertSame("\x0A\x0B\x0C\x0B", $obj->apply("\x0A\x01\x01\x0B", [
            'Predictor' => 2,
            'Colors' => 1,
            'BitsPerComponent' => 8,
            'Columns' => 3,
        ]));
    }

    /**
     * @throws \Com\Tecnick\Pdf\Filter\Exception
     */
    public function testTiffPredictorRejectsSubByteComponents(): void
    {
        $obj = new Predictor();

        $this->assertThrows(
            '\\' . \Com\Tecnick\Pdf\Filter\Exception::class,
            'TIFF Predictor 2 requires BitsPerComponent 8 or 16',
            static fn(): mixed => $obj->apply("\x01\x02", ['Predictor' => 2, 'BitsPerComponent' => 4]),
        );
    }

    /**
     * @throws \Com\Tecnick\Pdf\Filter\Exception
     */
    public function testPredictorRejectsAnUnsupportedBitDepth(): void
    {
        $obj = new Predictor();

        $this->assertThrows(
            '\\' . \Com\Tecnick\Pdf\Filter\Exception::class,
            'unsupported BitsPerComponent: 7',
            static fn(): mixed => $obj->apply("\x01\x02", ['Predictor' => 12, 'BitsPerComponent' => 7]),
        );
    }

    /**
     * PDF 32000-1:2008 Table 8 defines 1, 2 and 10 to 15; the rest are malformed.
     *
     * @throws \Com\Tecnick\Pdf\Filter\Exception
     */
    public function testPredictorRejectsValuesOutsideTheDefinedSet(): void
    {
        $obj = new Predictor();

        foreach ([3, 5, 9, 16, 99] as $predictor) {
            $this->assertThrows(
                '\\' . \Com\Tecnick\Pdf\Filter\Exception::class,
                'unsupported Predictor: ' . $predictor,
                static fn(): mixed => $obj->apply("\x00ABCDE", ['Predictor' => $predictor, 'Columns' => 5]),
            );
        }

        // 10 to 15 all mean "PNG, per-row tag", so they stay equivalent
        foreach ([10, 11, 12, 13, 14, 15] as $predictor) {
            $this->assertSame('ABCDE', $obj->apply("\x00ABCDE", ['Predictor' => $predictor, 'Columns' => 5]));
        }
    }

    /**
     * Colors and Columns size the row buffer: an oversized Columns is rejected
     * before the buffer is allocated, and a value past PHP_INT_MAX raises the
     * library exception rather than a TypeError out of the row arithmetic.
     *
     * @throws \Com\Tecnick\Pdf\Filter\Exception
     */
    public function testPredictorRejectsOutOfRangeRowGeometry(): void
    {
        $obj = new Predictor();

        // the row must be rejected before the row buffer is sized from Columns:
        // 200 million entries is a multi-gigabyte allocation and an uncatchable
        // fatal error, not the exception below
        $before = \memory_get_peak_usage(true);

        $this->assertThrows(
            '\\' . \Com\Tecnick\Pdf\Filter\Exception::class,
            'truncated predictor row',
            static fn(): mixed => $obj->apply("\x00A", ['Predictor' => 12, 'Columns' => 200000000]),
        );

        $this->assertLessThan(1048576, \memory_get_peak_usage(true) - $before);

        $this->assertThrows(
            '\\' . \Com\Tecnick\Pdf\Filter\Exception::class,
            'unsupported Colors: ' . \PHP_INT_MAX,
            static fn(): mixed => $obj->apply("\x00A", ['Predictor' => 12, 'Colors' => \PHP_INT_MAX]),
        );

        $this->assertThrows(
            '\\' . \Com\Tecnick\Pdf\Filter\Exception::class,
            'unsupported Columns: ' . \PHP_INT_MAX,
            static fn(): mixed => $obj->apply("\x00A", ['Predictor' => 2, 'Columns' => \PHP_INT_MAX]),
        );
    }

    /**
     * With one sample per row the prediction stride spans the whole row, so
     * every byte falls in the no-left-neighbour prologue.
     *
     * @throws \Com\Tecnick\Pdf\Filter\Exception
     */
    public function testPngPredictorHandlesAStrideSpanningTheWholeRow(): void
    {
        $obj = new Predictor();
        $rows = ["\x11\x22\x33\x44", "\x55\x66\x77\x88", "\xF0\x0F\xFF\x01"];

        foreach ([0, 1, 2, 3, 4] as $tag) {
            $this->assertSame(\implode('', $rows), $obj->apply($this->encodePng($rows, $tag, 4), [
                'Predictor' => 12,
                'Colors' => 4,
                'BitsPerComponent' => 8,
                'Columns' => 1,
            ]), 'row filter ' . $tag);
        }
    }

    /**
     * 16-bit components give a 6-byte prediction stride for RGB.
     *
     * @throws \Com\Tecnick\Pdf\Filter\Exception
     */
    public function testPngPredictorHandlesSixteenBitComponents(): void
    {
        $obj = new Predictor();
        $rows = [];
        for ($y = 0; $y < 4; ++$y) {
            $row = '';
            for ($x = 0; $x < (3 * 3); ++$x) {
                $row .= \chr((($x * 29) + ($y * 11)) % 256) . \chr((($x * 7) ^ $y) % 256);
            }

            $rows[] = $row;
        }

        foreach ([0, 1, 2, 3, 4] as $tag) {
            $this->assertSame(\implode('', $rows), $obj->apply($this->encodePng($rows, $tag, 6), [
                'Predictor' => 12,
                'Colors' => 3,
                'BitsPerComponent' => 16,
                'Columns' => 3,
            ]), 'row filter ' . $tag);
        }
    }

    /**
     * A lax PDF parser can hand the dictionary values through as strings, which
     * the documented int coercion has to absorb.
     *
     * @throws \Com\Tecnick\Pdf\Filter\Exception
     */
    public function testPredictorAcceptsStringTypedDictionaryValues(): void
    {
        $obj = new Predictor();

        $this->assertSame('ABCD', $obj->apply("\x00ABCD", [
            'Predictor' => '12',
            'Colors' => '1',
            'BitsPerComponent' => '8',
            'Columns' => '4',
        ]));

        // a non-numeric Predictor coerces to 0, which means "no prediction"
        $this->assertSame("\x00ABCD", $obj->apply("\x00ABCD", ['Predictor' => 'none']));
    }

    /**
     * Colors below 1 is malformed; the row geometry clamps it to a single
     * component rather than producing a zero-width stride.
     *
     * @throws \Com\Tecnick\Pdf\Filter\Exception
     */
    public function testPredictorClampsANonPositiveColorsToOne(): void
    {
        $obj = new Predictor();

        $expected = "\x0A\x0B\x0C";
        $predicted = "\x0A\x01\x01";

        foreach ([0, -3] as $colors) {
            $this->assertSame(
                $expected,
                $obj->apply($predicted, [
                    'Predictor' => 2,
                    'Colors' => $colors,
                    'BitsPerComponent' => 8,
                    'Columns' => 3,
                ]),
                'Colors=' . $colors,
            );
        }
    }

    /**
     * The predictor is part of the Flate and LZW filters (PDF 32000-1:2008, Table 8).
     *
     * @throws \Com\Tecnick\Pdf\Filter\Exception
     */
    public function testFlateAndLzwApplyThePredictor(): void
    {
        $filter = new Filter();
        $rows = $this->sampleRows();
        $expected = \implode('', $rows);
        $params = ['Predictor' => 12, 'Colors' => 3, 'BitsPerComponent' => 8, 'Columns' => 4];

        $predicted = $this->encodePng($rows, 2, 3);
        $this->assertSame($expected, $filter->decode('FlateDecode', (string) \gzcompress($predicted), $params));

        // the same payload through LZW: literal codes only, so no dictionary reuse
        $bits = '';
        $length = \strlen($predicted);
        for ($i = 0; $i < $length; ++$i) {
            $bits .= \sprintf('%09b', \ord($predicted[$i]));
        }

        $bits = \str_pad($bits, (int) (\ceil(\strlen($bits) / 8) * 8), '0');
        $packed = '';
        for ($i = 0; $i < \strlen($bits); $i += 8) {
            $packed .= \chr((int) \bindec(\substr($bits, $i, 8)));
        }

        $this->assertSame($expected, $filter->decode('LZWDecode', $packed, $params));
    }
}

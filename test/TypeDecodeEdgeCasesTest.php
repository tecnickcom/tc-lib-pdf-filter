<?php

/**
 * TypeDecodeEdgeCasesTest.php
 *
 * @since     2026-04-19
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
 * Filter decoder edge case test
 *
 * @since     2026-04-19
 * @category  Library
 * @package   PdfFilter
 * @author    Nicola Asuni <info@tecnick.com>
 * @copyright 2011-2026 Nicola Asuni - Tecnick.com LTD
 * @license   https://www.gnu.org/copyleft/lesser.html GNU-LGPL v3 (see LICENSE)
 * @link      https://github.com/tecnickcom/tc-lib-pdf-filter
 */
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
         * @var \Closure(\Com\Tecnick\Pdf\Filter\Type\Lzw, int, array{bitlen: int, dix: int, prev_index: int, dictionary: array<int, string>, decoded: string}): array{bitlen: int, dix: int, prev_index: int, dictionary: array<int, string>, decoded: string} $caller
         */
        $caller = \Closure::bind(
            /** @param array{bitlen: int, dix: int, prev_index: int, dictionary: array<int, string>, decoded: string} $lzwState */
            static function (\Com\Tecnick\Pdf\Filter\Type\Lzw $target, int $code, array $lzwState): array {
                $target->processIndex($code, $lzwState);

                return $lzwState;
            },
            null,
            \Com\Tecnick\Pdf\Filter\Type\Lzw::class,
        );

        return $caller($obj, $index, $state);
    }

    /**
     * Read one IFD entry of a TIFF built by CcittFax.
     *
     * @return array{type: int, count: int, value: int}|null Null when the tag is absent.
     */
    private function getCcittTag(string $tiff, int $tagId): ?array
    {
        // TIFF header: 2-byte order + 2-byte version + 4-byte IFD offset
        $ifdHeader = \unpack('Voffset', \substr($tiff, 4, 4));
        $ifdOffset = (int) ($ifdHeader['offset'] ?? 0);

        $header = \unpack('vcount', \substr($tiff, $ifdOffset, 2));
        $count = (int) ($header['count'] ?? 0);
        $cursor = $ifdOffset + 2;

        for ($i = 0; $i < $count; ++$i) {
            $entry = \unpack('vtag/vtype/Vitems/Vvalue', \substr($tiff, $cursor, 12));
            if ((int) ($entry['tag'] ?? 0) === $tagId) {
                return [
                    'type' => (int) ($entry['type'] ?? 0),
                    'count' => (int) ($entry['items'] ?? 0),
                    'value' => (int) ($entry['value'] ?? 0),
                ];
            }

            $cursor += 12;
        }

        return null;
    }

    /**
     * Value of a TIFF tag, or null when the tag is absent.
     *
     * A SHORT carries its value in the low two bytes of the 4-byte value field.
     */
    private function getCcittTagValue(string $tiff, int $tagId): ?int
    {
        $entry = $this->getCcittTag($tiff, $tagId);
        if ($entry === null) {
            return null;
        }

        return $entry['type'] === 3 ? $entry['value'] & 0xFFFF : $entry['value'];
    }

    private function buildCcittHeader(\Com\Tecnick\Pdf\Filter\Type\CcittFax $obj, string $data): string
    {
        /** @var \Closure(\Com\Tecnick\Pdf\Filter\Type\CcittFax, string): string $builder */
        $builder = \Closure::bind(
            static fn(
                \Com\Tecnick\Pdf\Filter\Type\CcittFax $target,
                string $ccittData,
            ): string => $target->buildTiffHeader($ccittData),
            null,
            \Com\Tecnick\Pdf\Filter\Type\CcittFax::class,
        );

        return $builder($obj, $data);
    }

    /**
     * Encode data with the PDF variant of LZW (PDF 32000-1:2008 §7.4.4).
     *
     * The emitted code width mirrors the decoder table, which trails the encoder
     * table by one entry: the decoder cannot add the entry for a code until it
     * has read the code that follows it.
     *
     * @param string $data          Data to encode.
     * @param int    $earlyChange   1 widens the code one entry early (PDF default), 0 does not.
     * @param bool   $clearWhenFull Whether to emit a clear code once the dictionary is full.
     */
    private function lzwEncode(string $data, int $earlyChange = 1, bool $clearWhenFull = true): string
    {
        $out = '';
        $accumulator = 0;
        $accumulated = 0;
        $bitlen = 9;
        // the closure below mutates these by reference
        /** @var int $decoderIndex */
        $decoderIndex = 258;
        /** @var bool $afterClear */
        $afterClear = true;
        $dictionary = [];
        $next = 258;

        $reset = static function () use (&$dictionary, &$next): void {
            $dictionary = [];
            for ($i = 0; $i < 256; ++$i) {
                // prefixed to keep a numeric byte from becoming an integer key
                $dictionary['k' . \chr($i)] = $i;
            }

            $next = 258;
        };
        $reset();

        $emit = static function (int $code) use (
            &$out,
            &$accumulator,
            &$accumulated,
            &$bitlen,
            &$decoderIndex,
            &$afterClear,
            $earlyChange,
        ): void {
            $accumulator = ($accumulator << $bitlen) | $code;
            $accumulated += $bitlen;
            while ($accumulated >= 8) {
                $accumulated -= 8;
                $out .= \chr(($accumulator >> $accumulated) & 0xFF);
            }

            $accumulator &= (1 << $accumulated) - 1;

            if ($code === 256) {
                $bitlen = 9;
                $decoderIndex = 258;
                $afterClear = true;
                return;
            }

            if ($afterClear) {
                // the first code after a clear adds no entry on the decoder side
                $afterClear = false;
                return;
            }

            if ($decoderIndex < 4096) {
                ++$decoderIndex;
                $bitlen = match ($decoderIndex + $earlyChange) {
                    2048 => 12,
                    1024 => 11,
                    512 => 10,
                    default => $bitlen,
                };
            }
        };

        $emit(256);
        $buffer = '';
        $length = \strlen($data);
        for ($i = 0; $i < $length; ++$i) {
            $char = $data[$i];
            if (isset($dictionary['k' . $buffer . $char])) {
                $buffer .= $char;
                continue;
            }

            $emit($dictionary['k' . $buffer]);
            if ($next < 4096) {
                $dictionary['k' . $buffer . $char] = $next;
                ++$next;
            } elseif ($clearWhenFull) {
                $emit(256);
                $reset();
            }

            $buffer = $char;
        }

        if ($buffer !== '') {
            $emit($dictionary['k' . $buffer]);
        }

        $emit(257);
        if ($accumulated > 0) {
            $out .= \chr(($accumulator << (8 - $accumulated)) & 0xFF);
        }

        return $out;
    }

    /**
     * Data varied enough that LZW keeps adding dictionary entries.
     */
    private function lzwPayload(int $length): string
    {
        $data = '';
        for ($i = 0; $i < $length; ++$i) {
            $data .= \chr((($i * 31) ^ \intdiv($i, 7)) % 256);
        }

        return $data;
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
        $obj = new \Com\Tecnick\Pdf\Filter\Type\AsciiEightFive();
        // two characters are a valid partial group, so only the "z shall not
        // appear inside a group" rule can reject this

        $this->assertThrows(
            '\\' . \Com\Tecnick\Pdf\Filter\Exception::class,
            'invalid code: z inside a group',
            static fn(): mixed => $obj->decode('!!z~>'),
        );

        // between complete groups it is legal and expands to four zero bytes
        $this->assertSame(\str_repeat("\x00", 8), $obj->decode('!!!!!z~>'));
    }

    /**
     * The EOD marker is optional: PDF producers routinely omit it, and anything
     * after it is not part of the stream.
     */
    public function testAsciiEightFiveTreatsTheEodMarkerAsOptional(): void
    {
        $obj = new \Com\Tecnick\Pdf\Filter\Type\AsciiEightFive();

        $this->assertSame('tc-lib-pdf-filter', $obj->decode('FCQn=BjrZ5A7dE*Bl%m&EW'));
        $this->assertSame("\x00\x00\x00\x00", $obj->decode('!!!!!~>ZZZ!!!'));
    }

    public function testAsciiEightFiveLastTupleCases(): void
    {
        $obj = new \Com\Tecnick\Pdf\Filter\Type\AsciiEightFive();

        // ends with an incomplete 4-char tuple
        $this->assertSame("\x00\x00\x00", $obj->decode('!!!!~>'));

        // ends with an incomplete 2-char tuple
        $this->assertSame("\x00", $obj->decode('!!~>'));

        // ends with an incomplete 3-char tuple
        $this->assertSame("\x00\x00", $obj->decode('!!!~>'));
    }

    /**
     * A partial final group is padded with 'u' (84), worth 85^d - 1 over the d
     * missing characters (PDF 32000-1:2008 §7.4.3). Padding with 85^d instead
     * carries into the last kept byte whenever the discarded byte would be 0xFF.
     */
    public function testAsciiEightFivePaddingDoesNotCarryIntoTheKeptBytes(): void
    {
        $obj = new \Com\Tecnick\Pdf\Filter\Type\AsciiEightFive();

        // '!!$!' is 3 * 85^2 = 21675; padded with one 'u' it is 0x54FF, so the
        // three kept bytes are 00 00 54, not 00 00 55
        $this->assertSame("\x00\x00\x54", $obj->decode('!!$!~>'));
        $this->assertSame("\x30\x41\xE2", $obj->decode('0L=^~>'));

        // two characters short, where the padding is worth 85^2 - 1
        $this->assertSame("\x70\xE3", $obj->decode('E9$~>'));

        // no two-character group can reach the boundary, so the widest one stands
        $this->assertSame("\xFF", $obj->decode('rr~>'));
    }

    public function testCcittFaxDefaultsToTheStandardFaxWidth(): void
    {
        // PDF 32000-1:2008 Table 11: Columns defaults to 1728, the standard fax width
        $tiff = $this->buildCcittHeader(new \Com\Tecnick\Pdf\Filter\Type\CcittFax(), "\xAA\xBB");

        $this->assertSame(1728, $this->getCcittTagValue($tiff, 256));
    }

    public function testAsciiEightFiveSingleTrailingCharThrows(): void
    {
        $obj = new \Com\Tecnick\Pdf\Filter\Type\AsciiEightFive();

        // a one-character final group encodes no whole byte
        $this->assertThrows(
            '\\' . \Com\Tecnick\Pdf\Filter\Exception::class,
            'invalid code: final group has a single character',
            static fn(): mixed => $obj->decode('!~>'),
        );

        // 85^4 * (c - 33) passes 2^32 from 't' upwards, but the group is invalid
        // because of its length, not its value, whichever character it holds
        foreach (['!', 'a', 't', 'u'] as $char) {
            $this->assertThrows(
                '\\' . \Com\Tecnick\Pdf\Filter\Exception::class,
                'invalid code: final group has a single character',
                static fn(): mixed => $obj->decode('87cUR' . $char . '~>'),
            );
        }
    }

    public function testAsciiEightFiveRejectsCharactersOutsideItsAlphabet(): void
    {
        $obj = new \Com\Tecnick\Pdf\Filter\Type\AsciiEightFive();

        $this->assertThrows(
            '\\' . \Com\Tecnick\Pdf\Filter\Exception::class,
            'invalid code: character outside the ASCII85 alphabet',
            static fn(): mixed => $obj->decode(\chr(254)),
        );
    }

    public function testFlateDecodeRawDeflatePayload(): void
    {
        $obj = new \Com\Tecnick\Pdf\Filter\Type\Flate();
        $expected = 'raw-deflate-fallback';
        $rawDeflate = (string) \gzdeflate($expected);

        $this->assertSame($expected, $obj->decode($rawDeflate));
    }

    public function testFlateDecodeHeaderStrippedFallback(): void
    {
        $obj = new \Com\Tecnick\Pdf\Filter\Type\Flate();
        $expected = 'header-stripped-fallback';
        $rawDeflate = (string) \gzdeflate($expected);
        // malformed zlib data: a header and a raw payload, without the Adler-32 trailer
        $payload = "\x78\x9c" . $rawDeflate;

        $this->assertSame($expected, $obj->decode($payload));
    }

    public function testFlateDecodeGzipWrappedPayload(): void
    {
        $obj = new \Com\Tecnick\Pdf\Filter\Type\Flate();
        $expected = 'gzip-fallback';
        $gzipPayload = (string) \gzencode($expected);

        $this->assertSame($expected, $obj->decode($gzipPayload));
    }

    public function testLzwProcessIndexAtDictionarySize(): void
    {
        $obj = new \Com\Tecnick\Pdf\Filter\Type\Lzw();
        $state = [
            'bitlen' => 9,
            'dix' => 259,
            'prev_index' => 65,
            'dictionary' => $this->getInitialLzwDictionary(),
            'decoded' => '',
        ];

        // the code names the entry being built (KwKwK)
        $updated = $this->callLzwProcessIndex($obj, 259, $state);

        $this->assertSame('AA', $updated['decoded']);
        $this->assertSame('AA', $updated['dictionary'][259]);
        $this->assertSame(260, $updated['dix']);
    }

    public function testLzwProcessIndexBeyondDictionarySize(): void
    {
        $obj = new \Com\Tecnick\Pdf\Filter\Type\Lzw();
        $state = [
            'bitlen' => 9,
            'dix' => 259,
            'prev_index' => 65,
            'dictionary' => $this->getInitialLzwDictionary(),
            'decoded' => '',
        ];

        // only an existing entry or the entry being built can be named
        $this->assertThrows(
            '\\' . \Com\Tecnick\Pdf\Filter\Exception::class,
            'invalid code',
            fn(): mixed => $this->callLzwProcessIndex($obj, 300, $state),
        );
    }

    public function testLzwRejectsUnknownCodeAfterClear(): void
    {
        $obj = new \Com\Tecnick\Pdf\Filter\Type\Lzw();

        // codes 256 (clear), 65, 500: 500 has no dictionary entry and is not the
        // entry being built, so the stream is malformed
        $this->assertThrows(
            '\\' . \Com\Tecnick\Pdf\Filter\Exception::class,
            'invalid code',
            fn(): mixed => $obj->decode($this->packLzwCodes([256, 65, 500])),
        );
    }

    public function testLzwDecodesStreamWithoutLeadingClearCode(): void
    {
        $obj = new \Com\Tecnick\Pdf\Filter\Type\Lzw();

        // 'ABAB': literals A, B then entry 258 ('AB'), with no leading clear code
        $this->assertSame('ABAB', $obj->decode($this->packLzwCodes([65, 66, 258])));
    }

    /**
     * A stream long enough to cross both code-width thresholds, so readBits() is
     * driven at 10, 11 and 12 bits.
     */
    public function testLzwRoundTripsAcrossTheCodeWidthTransitions(): void
    {
        $obj = new \Com\Tecnick\Pdf\Filter\Type\Lzw();
        $payload = $this->lzwPayload(6000);

        foreach ([1, 0] as $earlyChange) {
            $this->assertSame($payload, $obj->decode($this->lzwEncode($payload, $earlyChange), [
                'EarlyChange' => $earlyChange,
            ]), 'EarlyChange=' . $earlyChange);
        }
    }

    /**
     * A stream encoded by libtiff (tiffcp -c lzw, TIFF LZW being PDF LZW with
     * EarlyChange 1), long enough that a wrong code-width threshold
     * desynchronises the bit reader.
     */
    public function testLzwDecodesAnExternallyEncodedStream(): void
    {
        $obj = new \Com\Tecnick\Pdf\Filter\Type\Lzw();

        $this->assertSame(
            $this->lzwExternalPlaintext(),
            $obj->decode((string) \base64_decode($this->lzwExternalStream(), true)),
        );
    }

    /**
     * The 8192 bytes libtiff was given: a linear congruential sequence over a
     * four-symbol alphabet, so the dictionary fills fast enough to reach 12-bit
     * codes within a small fixture.
     */
    private function lzwExternalPlaintext(): string
    {
        $state = 0x2F6E2B1;
        $plain = '';
        for ($i = 0; $i < 8192; ++$i) {
            $state = (($state * 1103515245) + 12345) & 0x7FFF_FFFF;
            $plain .= \chr((($state >> 16) % 4) * 64);
        }

        return $plain;
    }

    /**
     * The LZW strip libtiff wrote for lzwExternalPlaintext(), base64 encoded.
     */
    private function lzwExternalStream(): string
    {
        return (
            ''
            . 'gCAYBAgjAACAhBAQAAYEGIENg0Mh8DYCAicGhMHhQAAEPi0Egkdh8ikkNjsRipAjkMk0WhsLhEDlUWiUCjsChEsmkil0DjkY'
            . 'g8LhsqoccoUrhkIj8qpkJhU+lkGiEMjkTgUbqVEnkmh9EmkEgUYl89rkOjcKmMyscWoVMitimFvsUbg9FiNMlV1t9Bl1oiUr'
            . 'kMnhVPqUSkFmokDpUhsNDlMkidGkN5isnytpv1sitCo8fqmeg9Vm0xsMd0M4z82vdaukZqsUrNJyWVoelilswd02GspWcm8U'
            . 'v0pmeKsGbiGUm+zhcgkFCrMykfF3N7hciwkJ0FymF5oMSmXbsFJ6Hek1/iMw1Uet+J4vnqsavMjyvW3vfm2SsNeo35n9SmCN'
            . 'L61CnqqkKfKsn6cLYvi7rijb/qYvjNJA0KHLenKqO0tzqpwu6vKUl71MCli6tMx6jNutKIKO0yPow5CUw+jSoNonTlo8mi+s'
            . '2xqrxI0KtNkqMExOnMiIi974JOlaoJmoLuQcn0WrrBTjN/GUXK4pbtp6xbhxU9LgRijywLaw6eKwuTgIK6cSodIkyqSt0kr/'
            . 'K0CR4i8xLLJklOM5bVuY+6sq7DkEQKrq6pG0zXwgsEnKtCFEqk46hsi08iN6m8NtVFiXJy49GJ9UDSv61CgwsijrLy/LJq8r'
            . 'LsPQjNETQ6Lkwi6tEJirC9QolsXQE0q0M26SS1rDC0RuxSaoTDq4JOq6lL4nSiMHNLDp3SaMwu7bVwrPVjxY19nrStq/s4na'
            . 'c1i3rEvm5qULbGDuqBKFgu4r9EJuvFnog4lpP3YKarRZ2ANnAaoP6xkSJjACtMUslkKNRLqx8wy5J1T1lR4vSaIdhCWK6wt4'
            . 'tO+SusDY0CUw+yXpM78DSG86zv6xyXJXc0fR+4E2VLlNOwtD8cStG6Z0M49MQle+ZR8uNlTQpMoyazDXpkjUZRLn2J49U6iv'
            . 'lGEwZHUONPLJd4oLn02qfBUbqjasWuCkWGK20NK0YzyZwFuUr05XT3PFsyxxAlNIrCzuzL0rlPuthjVP4/eO46tcK3TW911t'
            . 'VsRs5kie0/HkdcDxu4PDqFJJbh8A8Da2OvkvtcslVMCSgyMJuEzrPugm18QLFSqRZguNPFcdTILL1zz1KKW1Wq9FvjZ1nQwz'
            . 'fc8NXDKPO/2Nw7Yzl3O+aMPS9TNJ0wzCNlNfg3jKLSMIycfPnBaj5S5rGP/7Tib6gryeOnnrqa8y/1SsareaZc6q+kzGcPW9'
            . 'cn5oi5mFW+uU5xSDFI0R69BcDHkcOBgegk25l1QObMWVEoCiSSvASGVgrDkyRqZOehNaydkLqmeubAyyDmgQHgAceF5nnjnu'
            . 'ek3SARbmROLOAbU6jfFRNaT03AlDADNGXdYyNShznqoUSUmUxzzUSuLXAaNTp3S8P/XCpF4KOyzGkRkz1Cp/neGJO2aZfDKm'
            . 'gpNeksiMZ8EIGLMaocyzDGRnoPhHcvUKknFUPis0q7BWzJRK0nY6BZksG0MnARSZtz9r0MG1BTB7DXxcUQ7A07f4AoRj/HCR'
            . 'Egkxk/PUtyE7qTuFzKaXt06VEAR5YAg5PZqTPN9RgRcr7M23F3RYqk/Mu1IpMbG2NYhRVnmfLOwmAKXUTnGI8aIo5rUJwnPL'
            . 'HZijf1gy+QSp+QBU3gG5VKmNByUzDFxg266Dz/m0vaSmeGU8A1cQPLPNU9akW4JFe0khQ8hTlmQmavxCqkH4L6fAoJAcJ5at'
            . 'takgVC5JEJQ2isb5aZvogn1jqgc+5gJ/wPVQtZKEGDct0UEY02k3UpNyRvAw8qI5DxsXQadqh04RQnQG058KSk3FbWAo86io'
            . 'kSqCWTRU20qV0I2oYbxFq+iPx8T2p6aK0mvpiRkU9finYZSmp6dg1rhI+GVQnPlPptYKmCJwdc+KamkHRSMV9PpPYpKHoEy+'
            . 'larkPI6ngbVKy+35Gqb/TahyUyUHlPaWpa563cpJQCRNzKqYeM8kKrNzCTncLaIud+lyACyQHbQzCw8kJgw0lMb9GZyEkuhK'
            . 'LHg5rCVgk7Tu8kp1Y3YEvKQ9iARS3YyCQYepDCv6EW0dof08ViG00uX1B93LKVTx0jY1m0DU2sP+foU05EBHluihealIJ3Uj'
            . 'l7kY71g78DcXNR6Sg8UV1oGRr0vtHEemgzOTu3u8pZ02nYPOkRmcJDNFxbadxBbQJP23fck94EWlzqXPfLl8x9lZqSqSsZqN'
            . 'Mqx3elU4W+bHldMvWQdNUym7LSVTBH0+d4qovtRRRl3N9qdzPeTPeJND5wUkvdZhLdZ0Hnww7DDCCznitdaE/6t5QHrGTQse'
            . 'F+KlawI5iOk/F0LK1RMeA7Ss7WafFqMHjXKzgKB0mN0c6YsmY3WJRUxtzhv1lXqRFhOFuGIYqcSM2VPpjFTxQWmgVhFqE4Rs'
            . 'Xcfu/arz60PmWtfFOZmNF0ikkW5NhsuSrVpF2krCjLJcXI+lZlGzEoLMM0yxSfJWz/YTldlCelqXykjcWkBtaEJPn/MpadVX'
            . '3r8kfTBnCH1Conog/eSK9V7tbfNmakquIpoXUM/SS72r6GAN/UJLcYy+vOsJNVAhXlGneS3mIyEVo1MrhWYSlptk2swn60R2'
            . '1uI6YjL8aI65ozhp/aPM92uKoJ1pSvCCEFWF3IKMEmuHk88InMmk+CMy50atIdqyxTTFDJa2cK5g3BpH/m0WRAa/0rsztZmk'
            . 'XBW05GJTIxiUjgkpqwHxN3BA3UpHW3125J1hbEdUSLRdIlbDiJdWtO7nRysSabJ72kmRBKKVd2YdEgZsZxlemA1FE7FKx6Vq'
            . 'lkEhSSqkEdJ1iqjiqS/mVHClo/S93TEaxQMRVJTrCTkoglWg1/bYdmlQVhobpjx2tFfu8lJU9aUxdDNIxFAbGIlvKkhrBBTi'
            . 'XqSSb3POzVf+MmOxLf3OUsYMHgpDhLxyNFH031o21DmysrrKYMmm7l+2KmoeFfF+qossHFMXIZs0xTWMCqW91Lpw0IvWRo/3'
            . 'Ya2I+u1qonBbLUcRlOPaU44lmkyP7Xg1SOSaU22Egyho5kb51yIdeveR5+rK2xWfXOad5j78NiBcNfkf5wvWtq4XUEIvk9x4'
            . 'doKGhZT/sRY6+yN8BUpH0aDX7riCI2TnTq+52rCFOMCkEIbLxGMGUELIsD0MGkODnPNJ7qtoikOHQnPHKHnNZMruxpfI3J0u'
            . '3jHGpJzjDkzoMk4K8NKKODHnmksjYkgvRC1kcqzFwMHJJmZn1kpO9DkqwidmcwIPbCnIDnVkujcLaHKkbC2FLG3MomhnMpMu'
            . 'GrKDowEP2jMJ6KLPMnJjHs+lbFyP7LhGNlZvvEDP/rlJTJ/rLi1L+vgmjJYnnMZClmhMzGqNVl/l7qYGeqsEFvEEsw2ExnLD'
            . 'YGfDSquMbC6G6HIEqtiE5waMWqrPQGDiNCAg'
        );
    }

    /**
     * EarlyChange moves every width threshold by one code, so a stream read with
     * the wrong setting desynchronises rather than decoding to something plausible.
     */
    public function testLzwRejectsAStreamDecodedWithTheWrongEarlyChange(): void
    {
        $obj = new \Com\Tecnick\Pdf\Filter\Type\Lzw();
        $payload = $this->lzwPayload(6000);

        $this->assertThrows(
            '\\' . \Com\Tecnick\Pdf\Filter\Exception::class,
            'invalid code',
            fn(): mixed => $obj->decode($this->lzwEncode($payload, 1), ['EarlyChange' => 0]),
        );

        $this->assertThrows(
            '\\' . \Com\Tecnick\Pdf\Filter\Exception::class,
            'invalid code',
            fn(): mixed => $obj->decode($this->lzwEncode($payload, 0), ['EarlyChange' => 1]),
        );
    }

    /**
     * A clear code reached after the dictionary has grown must reset the width
     * along with the table.
     */
    public function testLzwResetsTheDictionaryOnAMidStreamClearCode(): void
    {
        $obj = new \Com\Tecnick\Pdf\Filter\Type\Lzw();
        // 12000 bytes fill the 4096-entry dictionary, so the encoder clears it
        $payload = $this->lzwPayload(12000);

        $this->assertSame($payload, $obj->decode($this->lzwEncode($payload, 1, true)));
    }

    /**
     * Without a clear code the table simply stops growing at 4096 entries: the
     * stream stays decodable with the entries already built.
     */
    public function testLzwKeepsDecodingOnceTheDictionaryIsFull(): void
    {
        $obj = new \Com\Tecnick\Pdf\Filter\Type\Lzw();
        $payload = $this->lzwPayload(12000);

        $this->assertSame($payload, $obj->decode($this->lzwEncode($payload, 1, false)));
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

        // K = -1 => Group 4 (T.6) => TIFF compression 4, no T4Options
        $tiffTrue = $this->buildCcittHeader($asTrue, $ccittData);
        $this->assertSame(4, $this->getCcittTagValue($tiffTrue, 259));
        // BlackIs1 = true => 0 bits are white => PhotometricInterpretation 0 (WhiteIsZero)
        $this->assertSame(0, $this->getCcittTagValue($tiffTrue, 262));
        $this->assertSame(10, $this->getCcittTagValue($tiffTrue, 256));
        $this->assertSame(2, $this->getCcittTagValue($tiffTrue, 257));
        $this->assertNull($this->getCcittTag($tiffTrue, 292));

        $asFalse = new \Com\Tecnick\Pdf\Filter\Type\CcittFax([
            'BlackIs1' => ['unexpected'],
            'Columns' => 0,
            'Rows' => -5,
        ]);

        // K = 0 (default) => Group 3 1-D => TIFF compression 3, T4Options = 0
        $tiffFalse = $this->buildCcittHeader($asFalse, $ccittData);
        $this->assertSame(3, $this->getCcittTagValue($tiffFalse, 259));
        // BlackIs1 = false (PDF default) => 0 bits are black => PhotometricInterpretation 1
        $this->assertSame(1, $this->getCcittTagValue($tiffFalse, 262));
        $this->assertSame(1, $this->getCcittTagValue($tiffFalse, 256));
        $this->assertSame(16, $this->getCcittTagValue($tiffFalse, 257));
        // Group 3 always carries T4Options; with K = 0 and no byte alignment it is 0
        $this->assertSame(0, $this->getCcittTagValue($tiffFalse, 292));
    }

    public function testCcittFaxGroup3TwoDimensionalSetsT4Options(): void
    {
        $ccittData = "\xAA\xBB";

        // K > 0 => Group 3 2-D => TIFF compression 3 with T4Options bit 0 set
        $mixed = new \Com\Tecnick\Pdf\Filter\Type\CcittFax(['K' => 4, 'Columns' => 8]);
        $tiff = $this->buildCcittHeader($mixed, $ccittData);
        $this->assertSame(3, $this->getCcittTagValue($tiff, 259));
        $this->assertSame(1, $this->getCcittTagValue($tiff, 292));
    }

    public function testCcittFaxConstructorAcceptsNumericAndBooleanBlackIs1(): void
    {
        $ccittData = "\x0F";

        $numericTrue = new \Com\Tecnick\Pdf\Filter\Type\CcittFax(['BlackIs1' => 2]);
        $tiffNumeric = $this->buildCcittHeader($numericTrue, $ccittData);
        $this->assertSame(0, $this->getCcittTagValue($tiffNumeric, 262));

        $boolFalse = new \Com\Tecnick\Pdf\Filter\Type\CcittFax(['BlackIs1' => false]);
        $tiffBool = $this->buildCcittHeader($boolFalse, $ccittData);
        $this->assertSame(1, $this->getCcittTagValue($tiffBool, 262));
    }

    public function testCcittFaxEncodedByteAlignSetsT4OptionsBit(): void
    {
        $ccittData = "\xAA\xBB";

        // EncodedByteAlign only maps onto Group 3 T4Options (bit 2)
        $aligned = new \Com\Tecnick\Pdf\Filter\Type\CcittFax(['K' => 0, 'EncodedByteAlign' => true]);
        $this->assertSame(4, $this->getCcittTagValue($this->buildCcittHeader($aligned, $ccittData), 292));

        // combined with K > 0 the two-dimensional bit is kept
        $both = new \Com\Tecnick\Pdf\Filter\Type\CcittFax(['K' => 4, 'EncodedByteAlign' => true]);
        $this->assertSame(5, $this->getCcittTagValue($this->buildCcittHeader($both, $ccittData), 292));

        // Group 4 has no equivalent T6Options bit, so tag 292 is absent entirely
        // rather than present with value 0
        $group4 = new \Com\Tecnick\Pdf\Filter\Type\CcittFax(['K' => -1, 'EncodedByteAlign' => true]);
        $this->assertNull($this->getCcittTag($this->buildCcittHeader($group4, $ccittData), 292));
    }

    /**
     * The strip and the two resolution rationals are appended after the IFD, so
     * their offsets depend on the tag count: 8 header bytes, then 2 + 12n + 4.
     * An odd-length payload is padded so the rationals stay word-aligned.
     */
    public function testCcittFaxTiffHeaderLaysOutTheOffsetsAfterTheIfd(): void
    {
        // Group 3 carries T4Options and Group 4 does not, so 10 tags against 9;
        // both payload lengths round up to the same word boundary
        foreach ([
            ["\xAA\xBB\xCC\xDD", 4],
            ["\xAA\xBB\xCC",     4],
        ] as [$ccittData, $padded]) {
            foreach ([[0, 10], [-1, 9]] as [$kParam, $tagCount]) {
                $this->assertCcittTiffLayout($ccittData, $padded, $kParam, $tagCount);
            }
        }
    }

    /**
     * Assert the strip and resolution layout of one TIFF built by CcittFax.
     *
     * @param string $ccittData Raw payload.
     * @param int    $padded    Payload length rounded up to a word boundary.
     * @param int    $kParam    DecodeParms K value.
     * @param int    $tagCount  Expected number of IFD entries.
     */
    private function assertCcittTiffLayout(string $ccittData, int $padded, int $kParam, int $tagCount): void
    {
        $length = \strlen($ccittData);
        $tiff = $this->buildCcittHeader(new \Com\Tecnick\Pdf\Filter\Type\CcittFax([
            'K' => $kParam,
            'Columns' => 8,
        ]), $ccittData);

        $label = 'K=' . $kParam . ' length=' . $length;
        $stripOffset = 8 + 2 + ($tagCount * 12) + 4;
        $this->assertSame($stripOffset, $this->getCcittTagValue($tiff, 273), $label);
        // StripByteCounts stays the true payload length, padding excluded
        $this->assertSame($length, $this->getCcittTagValue($tiff, 279), $label);
        $this->assertSame($stripOffset + $padded, $this->getCcittTagValue($tiff, 282), $label);
        $this->assertSame($stripOffset + $padded + 8, $this->getCcittTagValue($tiff, 283), $label);

        // TIFF 6.0 requires a value offset to be even
        $this->assertSame(0, ($stripOffset + $padded) % 2, $label);
        $this->assertSame(0, ($stripOffset + $padded + 8) % 2, $label);

        // the strip really is where StripOffsets says it is
        $this->assertSame($ccittData, \substr($tiff, $stripOffset, $length), $label);
        // XResolution and YResolution are RATIONALs of 72/1
        $this->assertSame(
            [72, 1],
            \array_values((array) \unpack('Vnum/Vden', \substr($tiff, $stripOffset + $padded, 8))),
            $label,
        );
        $this->assertSame(
            [72, 1],
            \array_values((array) \unpack('Vnum/Vden', \substr($tiff, $stripOffset + $padded + 8, 8))),
            $label,
        );
        // nothing follows the second rational
        $this->assertSame($stripOffset + $padded + 16, \strlen($tiff), $label);
    }

    /**
     * A TIFF reader takes only the low two bytes of the value field for a SHORT,
     * so a dimension past 65535 has to be written as a LONG.
     */
    public function testCcittFaxUsesALongFieldForDimensionsPastTheShortRange(): void
    {
        $narrow = $this->buildCcittHeader(new \Com\Tecnick\Pdf\Filter\Type\CcittFax([
            'K' => -1,
            'Columns' => 70000,
            'Rows' => 4,
        ]), "\xAA\xBB");
        $this->assertSame(4, $this->getCcittTag($narrow, 256)['type'] ?? 0);
        $this->assertSame(70000, $this->getCcittTagValue($narrow, 256));
        $this->assertSame(3, $this->getCcittTag($narrow, 257)['type'] ?? 0);
        $this->assertSame(4, $this->getCcittTagValue($narrow, 257));

        $tall = $this->buildCcittHeader(new \Com\Tecnick\Pdf\Filter\Type\CcittFax([
            'K' => -1,
            'Columns' => 64,
            'Rows' => 80000,
        ]), "\xAA\xBB");
        $this->assertSame(3, $this->getCcittTag($tall, 256)['type'] ?? 0);
        $this->assertSame(4, $this->getCcittTag($tall, 257)['type'] ?? 0);
        $this->assertSame(80000, $this->getCcittTagValue($tall, 257));

        // the height derived from the payload overflows a SHORT just as easily:
        // 100000 bytes over 8 columns is 100000 rows
        $derived = $this->buildCcittHeader(
            new \Com\Tecnick\Pdf\Filter\Type\CcittFax(['K' => -1, 'Columns' => 8]),
            \str_repeat("\xAA", 100000),
        );
        $this->assertSame(4, $this->getCcittTag($derived, 257)['type'] ?? 0);
        $this->assertSame(100000, $this->getCcittTagValue($derived, 257));
    }

    public function testCcittFaxDecodeParamsReplaceTheConstructorOnes(): void
    {
        $ccittData = "\xAA\xBB";
        $obj = new \Com\Tecnick\Pdf\Filter\Type\CcittFax(['Columns' => 8, 'K' => 0]);

        // decode() carries the DecodeParms of the Template contract, so the
        // parameters it is given win over the constructor ones. Whether Imagick
        // accepts the two-byte fixture depends on its delegates, but the only
        // tolerated failure is the documented one
        $tolerated = \extension_loaded('imagick')
            ? 'CCITTFaxDecode: Imagick failed to decode the stream:'
            : 'CCITTFaxDecode requires the Imagick PHP extension';

        try {
            $this->assertStringStartsWith("\x89PNG", $obj->decode($ccittData, [
                'Columns' => 32,
                'K' => -1,
                'BlackIs1' => true,
            ]));
        } catch (\Com\Tecnick\Pdf\Filter\Exception $e) {
            $this->assertStringContainsString($tolerated, $e->getMessage());
        }

        $tiff = $this->buildCcittHeader($obj, $ccittData);
        $this->assertSame(32, $this->getCcittTagValue($tiff, 256));
        $this->assertSame(4, $this->getCcittTagValue($tiff, 259));
        $this->assertSame(0, $this->getCcittTagValue($tiff, 262));
    }

    public function testCcittFaxDecodesReferenceGroup4StreamWithPdfPolarity(): void
    {
        if (!\extension_loaded('imagick')) {
            $this->markTestSkipped('ext-imagick is not available');
        }

        // Group 4 (T.6) stream for a 64x8 bi-level image, left half black and right
        // half white, taken from the strip of a libtiff-written Group 4 TIFF whose
        // PhotometricInterpretation is 1 - the polarity PDF expresses as BlackIs1 = false
        $ccittData = "\x23\x60\xD5\xFF\xF8\x00\x80\x08";

        $obj = new \Com\Tecnick\Pdf\Filter\Type\CcittFax([
            'K' => -1,
            'Columns' => 64,
            'Rows' => 8,
            'BlackIs1' => false,
        ]);

        try {
            $png = $obj->decode($ccittData);
        } catch (\Com\Tecnick\Pdf\Filter\Exception $e) {
            if (\str_contains($e->getMessage(), 'no decode delegate')) {
                $this->markTestSkipped('Imagick is available but the TIFF/CCITT decode delegate is missing');
            }

            throw $e;
        }

        $decoded = new \Imagick();
        $decoded->readImageBlob($png);

        $this->assertSame('srgb(0,0,0)', $decoded->getImagePixelColor(5, 4)->getColorAsString());
        $this->assertSame('srgb(255,255,255)', $decoded->getImagePixelColor(50, 4)->getColorAsString());

        // BlackIs1 = true inverts the same stream
        $inverted = new \Com\Tecnick\Pdf\Filter\Type\CcittFax([
            'K' => -1,
            'Columns' => 64,
            'Rows' => 8,
            'BlackIs1' => true,
        ]);

        $flipped = new \Imagick();
        $flipped->readImageBlob($inverted->decode($ccittData));

        $this->assertSame('srgb(255,255,255)', $flipped->getImagePixelColor(5, 4)->getColorAsString());
        $this->assertSame('srgb(0,0,0)', $flipped->getImagePixelColor(50, 4)->getColorAsString());
    }

    public function testCcittFaxDecodeWrapsImagickException(): void
    {
        if (!\extension_loaded('imagick')) {
            $this->markTestSkipped('ext-imagick is not available');
        }

        $obj = new class extends \Com\Tecnick\Pdf\Filter\Type\CcittFax {
            protected function newImagick(): \Imagick
            {
                throw new \ImagickException('forced imagick failure');
            }
        };

        try {
            $obj->decode("\xAA");
            $this->fail('Expected wrapped exception when Imagick throws');
        } catch (\Com\Tecnick\Pdf\Filter\Exception $e) {
            $this->assertStringContainsString('CCITTFaxDecode: Imagick failed to decode the stream:', $e->getMessage());
            $this->assertStringContainsString('forced imagick failure', $e->getMessage());
        }
    }

    public function testJpxDecodeWrapsImagickException(): void
    {
        if (!\extension_loaded('imagick')) {
            $this->markTestSkipped('ext-imagick is not available');
        }

        $obj = new class extends \Com\Tecnick\Pdf\Filter\Type\Jpx {
            protected function newImagick(): \Imagick
            {
                throw new \ImagickException('forced imagick failure');
            }
        };

        try {
            $obj->decode("\xAA");
            $this->fail('Expected wrapped exception when Imagick throws');
        } catch (\Com\Tecnick\Pdf\Filter\Exception $e) {
            $this->assertStringContainsString('JPXDecode: Imagick failed to decode the stream:', $e->getMessage());
            $this->assertStringContainsString('forced imagick failure', $e->getMessage());
        }
    }

    public function testRunLengthTruncatedRunDoesNotWarn(): void
    {
        $obj = new \Com\Tecnick\Pdf\Filter\Type\RunLength();

        // "\x02XYZ" copies 3 literal bytes; the trailing "\xC8" (200) is a run-length
        // marker with no following byte to repeat. The decoder must stop cleanly
        // instead of reading past the end of the string
        $errors = [];
        \set_error_handler(static function (int $_errno, string $errstr) use (&$errors): bool {
            $errors[] = $errstr;
            return true;
        });

        try {
            $result = $obj->decode("\x02XYZ\xC8");
        } finally {
            \restore_error_handler();
        }

        $this->assertSame('XYZ', $result);
        $this->assertSame([], $errors);
    }

    public function testRunLengthTruncatedLiteralRunReturnsWhatIsAvailable(): void
    {
        $obj = new \Com\Tecnick\Pdf\Filter\Type\RunLength();

        // "\x09" declares 10 literal bytes but only 3 follow. Decoding is tolerant
        // of a truncated stream and returns the bytes recovered so far
        $this->assertSame('ABC', $obj->decode("\x09ABC"));

        // the same holds for a stream that simply ends without the EOD marker
        $this->assertSame('ABC', $obj->decode("\x02ABC"));
    }

    public function testRunLengthStopsAtTheEodMarker(): void
    {
        $obj = new \Com\Tecnick\Pdf\Filter\Type\RunLength();

        // a length byte of 128 ends the stream: what follows it is not decoded
        $this->assertSame('XYZ', $obj->decode("\x02XYZ" . \chr(128) . "\x02ABC"));
    }

    /**
     * The length byte partitions at 128: 0 to 127 copy length + 1 literal bytes,
     * 128 is EOD, and 129 to 255 repeat the next byte 257 - length times.
     */
    public function testRunLengthHandlesTheLengthByteBoundaries(): void
    {
        $obj = new \Com\Tecnick\Pdf\Filter\Type\RunLength();

        // 0x00: the shortest literal run is one byte
        $this->assertSame('A', $obj->decode("\x00A\x80"));
        // 0x7F: the longest literal run is 128 bytes
        $this->assertSame(\str_repeat('A', 128), $obj->decode("\x7F" . \str_repeat('A', 128) . "\x80"));
        // 0x80: EOD as the very first byte decodes to nothing
        $this->assertSame('', $obj->decode("\x80"));
        // 0xFF: the shortest repeat run is two bytes
        $this->assertSame('AA', $obj->decode("\xFFA\x80"));
        // 0x81: the longest repeat run is 128 bytes
        $this->assertSame(\str_repeat('A', 128), $obj->decode("\x81A\x80"));
    }

    public function testRunLengthHonoursMaxOutputSize(): void
    {
        $obj = new \Com\Tecnick\Pdf\Filter\Type\RunLength();

        // "\x81A" repeats 'A' 128 times; two of them exceed a 200-byte budget
        $this->assertSame(\str_repeat('A', 256), $obj->decode("\x81A\x81A", ['MaxOutputSize' => 256]));

        $this->assertThrows(
            '\\' . \Com\Tecnick\Pdf\Filter\Exception::class,
            'decoded data exceeds MaxOutputSize of 200 bytes',
            static fn(): mixed => $obj->decode("\x81A\x81A", ['MaxOutputSize' => 200]),
        );
    }

    public function testRunLengthMaxOutputSizeAppliesToLiteralRuns(): void
    {
        $obj = new \Com\Tecnick\Pdf\Filter\Type\RunLength();

        $this->assertThrows(
            '\\' . \Com\Tecnick\Pdf\Filter\Exception::class,
            'decoded data exceeds MaxOutputSize of 2 bytes',
            static fn(): mixed => $obj->decode("\x02XYZ", ['MaxOutputSize' => 2]),
        );
    }

    public function testAsciiEightFiveRejectsGroupsAboveThirtyTwoBits(): void
    {
        $obj = new \Com\Tecnick\Pdf\Filter\Type\AsciiEightFive();

        // "s8W-!" is the largest legal group and encodes 0xFFFFFFFF
        $this->assertSame("\xFF\xFF\xFF\xFF", $obj->decode('s8W-!~>'));

        // "uuuuu" evaluates to 4437053124, past the 32-bit range a group can hold
        $this->assertThrows(
            '\\' . \Com\Tecnick\Pdf\Filter\Exception::class,
            'invalid code: group value above 32 bits',
            static fn(): mixed => $obj->decode('uuuuu~>'),
        );
    }

    public function testAsciiEightFiveRejectsOverflowingFinalPartialGroup(): void
    {
        $obj = new \Com\Tecnick\Pdf\Filter\Type\AsciiEightFive();

        // the padding pushes the four-character final group past 32 bits, which
        // is a different failure from the complete-group one above
        $this->assertThrows(
            '\\' . \Com\Tecnick\Pdf\Filter\Exception::class,
            'invalid code: final group value above 32 bits',
            static fn(): mixed => $obj->decode('uuuu~>'),
        );
    }

    public function testAsciiFiltersIgnoreNulAsWhiteSpace(): void
    {
        // PDF 32000-1:2008 Table 1 lists NUL among the white-space characters
        $hex = new \Com\Tecnick\Pdf\Filter\Type\AsciiHex();
        $this->assertSame('Hello', $hex->decode("4865\x006C6C6F>"));

        $a85 = new \Com\Tecnick\Pdf\Filter\Type\AsciiEightFive();
        $this->assertSame('Hello World!', $a85->decode("87cURD]i,\"Ebo80\x00~>"));
    }

    public function testAsciiHexDiscardsEverythingAfterTheEodMarker(): void
    {
        $obj = new \Com\Tecnick\Pdf\Filter\Type\AsciiHex();

        // only the digits before '>' are decoded; the rest is not even validated
        $this->assertSame('AB', $obj->decode('4142>ZZZZ'));
        $this->assertSame('', $obj->decode('>'));
        $this->assertSame('', $obj->decode("  \t\n"));
    }

    public function testAsciiHexAcceptsBothDigitCases(): void
    {
        $obj = new \Com\Tecnick\Pdf\Filter\Type\AsciiHex();

        $this->assertSame("\xFF\xAB", $obj->decode('ffab>'));
        $this->assertSame("\xFF\xAB", $obj->decode('FFAB>'));
        $this->assertSame("\xFF\xAB", $obj->decode('fFAb>'));
    }

    public function testAsciiHexDistinguishesItsFailures(): void
    {
        $obj = new \Com\Tecnick\Pdf\Filter\Type\AsciiHex();

        $this->assertThrows(
            '\\' . \Com\Tecnick\Pdf\Filter\Exception::class,
            'invalid code: odd number of hexadecimal digits without EOD',
            static fn(): mixed => $obj->decode('414'),
        );

        $this->assertThrows(
            '\\' . \Com\Tecnick\Pdf\Filter\Exception::class,
            'invalid code: character outside the hexadecimal alphabet',
            static fn(): mixed => $obj->decode('41XY>'),
        );
    }

    /**
     * preg_replace() returns null when PCRE gives up, which a long enough run of
     * white space reaches. Both ASCII decoders report that rather than treating
     * the null as an empty stream.
     *
     * The test runs in a separate process: the JIT does not honour the backtrack
     * limit, and turning it off only affects patterns compiled afterwards.
     *
     * @mago-expect lint:no-ini-set
     */
    #[\PHPUnit\Framework\Attributes\RunInSeparateProcess]
    #[\PHPUnit\Framework\Attributes\PreserveGlobalState(false)]
    public function testAsciiFiltersReportAFailedWhiteSpaceRemoval(): void
    {
        \ini_set('pcre.jit', '0');
        \ini_set('pcre.backtrack_limit', '1');

        $hex = new \Com\Tecnick\Pdf\Filter\Type\AsciiHex();
        $a85 = new \Com\Tecnick\Pdf\Filter\Type\AsciiEightFive();
        $spaces = \str_repeat(' ', 4096) . '41';

        $this->assertThrows(
            '\\' . \Com\Tecnick\Pdf\Filter\Exception::class,
            'invalid code: white-space removal failed',
            static fn(): mixed => $hex->decode($spaces . '>'),
        );

        $this->assertThrows(
            '\\' . \Com\Tecnick\Pdf\Filter\Exception::class,
            'invalid code: white-space removal failed',
            static fn(): mixed => $a85->decode($spaces . '~>'),
        );
    }

    public function testLzwDecodesSelfReferentialCodes(): void
    {
        $obj = new \Com\Tecnick\Pdf\Filter\Type\Lzw();

        // codes 256 (clear), 65 ('A'), 258 and 259 at 9 bits; 258 and 259 are
        // self-referential (KwKwK), each naming the entry being built, so they
        // expand to 'AA' and 'AAA'
        $this->assertSame('AAAAAA', $obj->decode($this->packLzwCodes([256, 65, 258, 259, 257])));
    }

    public function testLzwIgnoresTrailingPadBits(): void
    {
        $obj = new \Com\Tecnick\Pdf\Filter\Type\Lzw();

        // two 9-bit codes occupy 18 bits, so 6 zero bits pad the last byte;
        // they are not a code and must not add a third byte
        $this->assertSame('AB', $obj->decode($this->packLzwCodes([65, 66])));
    }

    public function testLzwHonoursMaxOutputSize(): void
    {
        $obj = new \Com\Tecnick\Pdf\Filter\Type\Lzw();
        $encoded = $this->packLzwCodes([256, 65, 258, 259, 257]);

        $this->assertSame('AAAAAA', $obj->decode($encoded, ['MaxOutputSize' => 6]));

        // the class alone cannot tell the budget failure from a malformed stream
        $this->assertThrows(
            '\\' . \Com\Tecnick\Pdf\Filter\Exception::class,
            'decoded data exceeds MaxOutputSize of 3 bytes',
            static fn(): mixed => $obj->decode($encoded, ['MaxOutputSize' => 3]),
        );
    }

    public function testFlateHonoursMaxOutputSize(): void
    {
        $obj = new \Com\Tecnick\Pdf\Filter\Type\Flate();
        $payload = (string) \gzcompress(\str_repeat('A', 4096));

        $this->assertSame(4096, \strlen($obj->decode($payload, ['MaxOutputSize' => 4096])));

        // a negative budget is not a zero-byte budget: it means unlimited
        $this->assertSame(4096, \strlen($obj->decode($payload, ['MaxOutputSize' => -1])));

        $this->assertThrows(
            '\\' . \Com\Tecnick\Pdf\Filter\Exception::class,
            'MaxOutputSize of 1024 bytes',
            static fn(): mixed => $obj->decode($payload, ['MaxOutputSize' => 1024]),
        );
    }

    /**
     * zlib reports a capped decode and a corrupt stream the same way, so the
     * message has to name both possibilities.
     */
    public function testFlateReportsACappedDecodeAndACorruptStreamTogether(): void
    {
        $obj = new \Com\Tecnick\Pdf\Filter\Type\Flate();

        $this->assertThrows(
            '\\' . \Com\Tecnick\Pdf\Filter\Exception::class,
            'invalid code, or decoded data exceeds MaxOutputSize of 10 bytes',
            static fn(): mixed => $obj->decode('ABC', ['MaxOutputSize' => 10]),
        );

        // without a budget there is only one possible cause
        $this->assertThrows(
            '\\' . \Com\Tecnick\Pdf\Filter\Exception::class,
            'invalid code',
            static fn(): mixed => $obj->decode('ABC'),
        );
    }

    public function testFlateRejectsATruncatedStreamRatherThanReturningAPrefix(): void
    {
        $obj = new \Com\Tecnick\Pdf\Filter\Type\Flate();
        $payload = (string) \gzcompress(\str_repeat('tc-lib-pdf-filter', 200));

        $this->assertThrows(
            '\\' . \Com\Tecnick\Pdf\Filter\Exception::class,
            'invalid code',
            static fn(): mixed => $obj->decode(\substr($payload, 0, 40)),
        );
    }

    public function testFlateDecodesAnEmptyPayload(): void
    {
        $obj = new \Com\Tecnick\Pdf\Filter\Type\Flate();

        // a zlib stream for the empty string is not itself empty, so it reaches
        // the decoder and the predictor
        $this->assertSame('', $obj->decode((string) \gzcompress('')));
    }

    /**
     * An empty stream takes a shortcut past the decoder, but not past the
     * DecodeParms check: the parameters are malformed either way.
     */
    public function testFlateAndLzwValidateTheParametersOfAnEmptyStream(): void
    {
        $flate = new \Com\Tecnick\Pdf\Filter\Type\Flate();
        $lzw = new \Com\Tecnick\Pdf\Filter\Type\Lzw();

        foreach ([$flate, $lzw] as $obj) {
            $label = \get_class($obj);

            $this->assertSame('', $obj->decode(''), $label);
            $this->assertSame('', $obj->decode('', ['Predictor' => 12, 'Columns' => 4]), $label);

            $this->assertThrows(
                '\\' . \Com\Tecnick\Pdf\Filter\Exception::class,
                'unsupported Predictor: 77',
                static fn(): mixed => $obj->decode('', ['Predictor' => 77]),
            );

            $this->assertThrows(
                '\\' . \Com\Tecnick\Pdf\Filter\Exception::class,
                'unsupported BitsPerComponent: 3',
                static fn(): mixed => $obj->decode('', ['Predictor' => 12, 'BitsPerComponent' => 3]),
            );
        }
    }

    public function testFlateMaxOutputSizeIsExact(): void
    {
        $obj = new \Com\Tecnick\Pdf\Filter\Type\Flate();
        $payload = (string) \gzcompress(\str_repeat('A', 100000));

        // zlib bounds its allocation rounds rather than the exact byte count, so
        // a budget just under the decoded size must still be refused
        $this->assertThrows(
            '\\' . \Com\Tecnick\Pdf\Filter\Exception::class,
            'exceeds MaxOutputSize of 99999 bytes',
            static fn(): mixed => $obj->decode($payload, ['MaxOutputSize' => 99999]),
        );
    }

    public function testCryptRejectsUnsupportedFilterNames(): void
    {
        $obj = new \Com\Tecnick\Pdf\Filter\Type\Crypt();

        $this->assertSame('data', $obj->decode('data'));
        $this->assertSame('data', $obj->decode('data', ['Name' => 'Identity']));
        $this->assertSame('data', $obj->decode('data', ['Name' => 'None']));

        // a parser that keeps the solidus of a PDF name object gets the same
        // answer here as it does from FilterType::fromLoose()
        $this->assertSame('data', $obj->decode('data', ['Name' => '/Identity']));
        $this->assertSame('data', $obj->decode('data', ['Name' => '/None']));

        $this->assertThrows(
            '\\' . \Com\Tecnick\Pdf\Filter\Exception::class,
            'unsupported Crypt filter name: StdCF',
            static fn(): mixed => $obj->decode('data', ['Name' => 'StdCF']),
        );

        // only one solidus is stripped, and the reported name is the one given
        $this->assertThrows(
            '\\' . \Com\Tecnick\Pdf\Filter\Exception::class,
            'unsupported Crypt filter name: //Identity',
            static fn(): mixed => $obj->decode('data', ['Name' => '//Identity']),
        );

        // names are case-sensitive, as PDF name objects are
        $this->assertThrows(
            '\\' . \Com\Tecnick\Pdf\Filter\Exception::class,
            'unsupported Crypt filter name: identity',
            static fn(): mixed => $obj->decode('data', ['Name' => 'identity']),
        );

        // a non-string Name is reported by type rather than interpolated
        $this->assertThrows(
            '\\' . \Com\Tecnick\Pdf\Filter\Exception::class,
            'unsupported Crypt filter name: int',
            static fn(): mixed => $obj->decode('data', ['Name' => 123]),
        );

        // a present but null Name is not the same as an absent one
        $this->assertThrows(
            '\\' . \Com\Tecnick\Pdf\Filter\Exception::class,
            'unsupported Crypt filter name: null',
            static fn(): mixed => $obj->decode('data', ['Name' => null]),
        );

        // PDF name objects are case sensitive, so only the exact spelling passes
        $this->assertThrows(
            '\\' . \Com\Tecnick\Pdf\Filter\Exception::class,
            'unsupported Crypt filter name: identity',
            static fn(): mixed => $obj->decode('data', ['Name' => 'identity']),
        );
    }

    /**
     * Pack LZW codes into a byte string at a fixed 9-bit width.
     *
     * @param array<int, int> $codes
     */
    private function packLzwCodes(array $codes): string
    {
        $bits = '';
        foreach ($codes as $code) {
            $bits .= \sprintf('%09b', $code);
        }

        $bits = \str_pad($bits, (int) (\ceil(\strlen($bits) / 8) * 8), '0');

        $out = '';
        for ($i = 0; $i < \strlen($bits); $i += 8) {
            $out .= \chr((int) \bindec(\substr($bits, $i, 8)));
        }

        return $out;
    }
}

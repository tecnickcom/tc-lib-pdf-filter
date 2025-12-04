<?php

/**
 * Lzw.php
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

namespace Com\Tecnick\Pdf\Filter\Type;

/**
 * Com\Tecnick\Pdf\Filter\Type\Lzw
 *
 * LZWDecode
 * Decompresses data encoded using the LZW (Lempel-Ziv-Welch) adaptive compression method,
 * reproducing the original text or binary data.
 *
 * @since     2011-05-23
 * @category  Library
 * @package   PdfFilter
 * @author    Nicola Asuni <info@tecnick.com>
 * @copyright 2011-2024 Nicola Asuni - Tecnick.com LTD
 * @license   http://www.gnu.org/copyleft/lesser.html GNU-LGPL v3 (see LICENSE.TXT)
 * @link      https://github.com/tecnickcom/tc-lib-pdf-filter
 */
class Lzw implements \Com\Tecnick\Pdf\Filter\Type\Template
{
    /**
     * Decode the data
     *
     * @param string $data Data to decode.
     *
     * @return string Decoded data string.
     */
    public function decode(string $data): string
    {
        if ($data === '') {
            return '';
        }

        // data length
        $data_length = \strlen($data);
        // convert string to binary string
        $bitstring = '';
        for ($i = 0; $i < $data_length; ++$i) {
            $bitstring .= \sprintf('%08b', \ord($data[$i]));
        }

        // get the number of bits
        $data_length = \strlen($bitstring);
        // initialize code length in bits
        $bitlen = 9;
        // initialize dictionary index
        $dix = 258;
        // initialize the dictionary (with the first 256 entries).
        $dictionary = [];
        for ($i = 0; $i < 256; ++$i) {
            $dictionary[$i] = \chr($i);
        }

        // previous val
        $prev_index = 0;
        $decoded = '';
        // while we encounter EOD marker (257), read code_length bits
        while (($data_length > 0) && (($index = (int) \bindec(\substr($bitstring, 0, $bitlen))) != 257)) {
            $this->process($decoded, $bitstring, $bitlen, $data_length, $index, $dictionary, $dix, $prev_index);
        }

        return $decoded;
    }

    /**
     * Internal processing
     *
     * @param array<int, string> $dictionary
     */
    protected function process(
        string &$decoded,
        string &$bitstring,
        int &$bitlen,
        int &$data_length,
        int &$index,
        array &$dictionary,
        int &$dix,
        int &$prev_index
    ): void {
        // remove read bits from string
        $bitstring = \substr($bitstring, $bitlen);
        // update number of bits
        $data_length -= $bitlen;
        if ($index == 256) { // clear-table marker
            // reset code length in bits
            $bitlen = 9;
            // reset dictionary index
            $dix = 258;
            $prev_index = 256;
            // reset the dictionary (with the first 256 entries).
            $dictionary = [];
            for ($i = 0; $i < 256; ++$i) {
                $dictionary[$i] = \chr($i);
            }
        } elseif ($prev_index == 256) {
            // first entry
            $decoded .= $dictionary[$index];
            $prev_index = $index;
        } else {
            // check if index exist in the dictionary
            if ($index < $dix) {
                // index exist on dictionary
                $decoded .= $dictionary[$index];
                $dic_val = $dictionary[$prev_index] . $dictionary[$index][0];
                // store current index
                $prev_index = $index;
            } else {
                // index do not exist on dictionary
                $dic_val = $dictionary[$prev_index] . $dictionary[$prev_index][0];
                $decoded .= $dic_val;
            }

            // update dictionary
            $dictionary[$dix] = $dic_val;
            ++$dix;
            // change bit length by case
            if ($dix == 2047) {
                $bitlen = 12;
            } elseif ($dix == 1023) {
                $bitlen = 11;
            } elseif ($dix == 511) {
                $bitlen = 10;
            }
        }
    }
}

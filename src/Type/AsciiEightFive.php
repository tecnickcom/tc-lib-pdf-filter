<?php

/**
 * AsciiEightFive.php
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

use Com\Tecnick\Pdf\Filter\Exception as PPException;

/**
 * Com\Tecnick\Pdf\Filter\Type\AsciiEightFive
 *
 * ASCII85
 * Decodes data encoded in an ASCII base-85 representation, reproducing the original binary data.
 *
 * @since     2011-05-23
 * @category  Library
 * @package   PdfFilter
 * @author    Nicola Asuni <info@tecnick.com>
 * @copyright 2011-2024 Nicola Asuni - Tecnick.com LTD
 * @license   http://www.gnu.org/copyleft/lesser.html GNU-LGPL v3 (see LICENSE.TXT)
 * @link      https://github.com/tecnickcom/tc-lib-pdf-filter
 */
class AsciiEightFive implements \Com\Tecnick\Pdf\Filter\Type\Template
{
    /**
     * Decode the data
     *
     * @param string $data Data to decode.
     *
     * @return string Decoded data string.
     *
     * @throws \Com\Tecnick\Pdf\Filter\Exception
     *
     * @SuppressWarnings("PHPMD.CyclomaticComplexity")
     */
    public function decode(string $data): string
    {
        if ($data === '') {
            return '';
        }

        // all white-space characters shall be ignored
        $data = preg_replace('/[\s]+/', '', $data);
        if ($data === null) {
            throw new PPException('invalid code');
        }

        // check for EOD: 2-character sequence ~> (7Eh)(3Eh)
        $eod = strpos($data, '~>');
        if ($eod !== false) {
            // remove EOD and following characters (if any)
            $data = substr($data, 0, $eod);
        }

        // data length
        $data_length = strlen($data);
        // check for invalid characters
        if (preg_match('/[^\x21-\x75,\x7A]/', $data) > 0) {
            throw new PPException('invalid code');
        }

        // z sequence
        $zseq = chr(0) . chr(0) . chr(0) . chr(0);
        // position inside a group of 4 bytes (0-3)
        $group_pos = 0;
        $tuple = 0;
        $pow85 = [(85 * 85 * 85 * 85), (85 * 85 * 85), (85 * 85), 85, 1];
        $decoded = '';
        // for each byte
        for ($i = 0; $i < $data_length; ++$i) {
            // get char value
            $char = ord($data[$i]);
            if ($char == 122) { // 'z'
                if ($group_pos == 0) {
                    $decoded .= $zseq;
                } else {
                    throw new PPException('invalid code');
                }
            } else {
                // the value represented by a group of 5 characters should never be greater than 2^32 - 1
                $tuple += (($char - 33) * $pow85[$group_pos]);
                if ($group_pos == 4) {
                    $decoded .= chr($tuple >> 24) . chr($tuple >> 16) . chr($tuple >> 8) . chr($tuple);
                    $tuple = 0;
                    $group_pos = 0;
                } else {
                    ++$group_pos;
                }
            }
        }

        if ($group_pos > 1) {
            $tuple += $pow85[($group_pos - 1)] ?? 0;
        }

        return $decoded . $this->getLastTuple($group_pos, $tuple);
    }

    /**
     * Get last tuple
     *
     * @return string Decoded data string.
     */
    protected function getLastTuple(int $group_pos, int $tuple): string
    {
        // last tuple (if any)
        return match ($group_pos) {
            4 => chr($tuple >> 24) . chr($tuple >> 16) . chr($tuple >> 8),
            3 => chr($tuple >> 24) . chr($tuple >> 16),
            2 => chr($tuple >> 24),
            1 => throw new PPException('invalid code'),
            default => '',
        };
    }
}

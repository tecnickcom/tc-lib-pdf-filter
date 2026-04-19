<?php

/**
 * LzwProxy.php
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

class LzwProxy extends \Com\Tecnick\Pdf\Filter\Type\Lzw
{
    /**
     * @param array<int, string> $dictionary
     */
    public function callProcess(
        string &$decoded,
        string &$bitstring,
        int &$bitlen,
        int &$data_length,
        int &$index,
        array &$dictionary,
        int &$dix,
        int &$prev_index
    ): void {
        $this->process($decoded, $bitstring, $bitlen, $data_length, $index, $dictionary, $dix, $prev_index);
    }
}

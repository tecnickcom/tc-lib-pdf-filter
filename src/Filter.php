<?php

/**
 * Filter.php
 *
 * @since     2011-05-23
 * @category  Library
 * @package   PdfFilter
 * @author    Nicola Asuni <info@tecnick.com>
 * @copyright 2011-2023 Nicola Asuni - Tecnick.com LTD
 * @license   http://www.gnu.org/copyleft/lesser.html GNU-LGPL v3 (see LICENSE.TXT)
 * @link      https://github.com/tecnickcom/tc-lib-pdf-filter
 *
 * This file is part of tc-lib-pdf-filter software library.
 */

namespace Com\Tecnick\Pdf\Filter;

use Com\Tecnick\Pdf\Filter\Exception as PPException;

/**
 * Com\Tecnick\Pdf\Filter\Filter
 *
 * PHP class for decoding common PDF filters (PDF 32000-2008 - 7.4 Filters)
 *
 * @since     2011-05-23
 * @category  Library
 * @package   PdfFilter
 * @author    Nicola Asuni <info@tecnick.com>
 * @copyright 2011-2023 Nicola Asuni - Tecnick.com LTD
 * @license   http://www.gnu.org/copyleft/lesser.html GNU-LGPL v3 (see LICENSE.TXT)
 * @link      https://github.com/tecnickcom/tc-lib-pdf-filter
 */
class Filter
{
    /**
     * Decode data using the specified filter type.
     *
     * @param string $filter Filter name.
     * @param string $data   Data to decode.
     *
     * @return string  Decoded data string.
     */
    public function decode(string $filter, string $data): string
    {
        if ($filter === '') {
            return $data;
        }

        $obj = match ($filter) {
            'ASCIIHexDecode' => new Type\AsciiHex(),
            'ASCII85Decode' => new Type\AsciiEightFive(),
            'LZWDecode' => new Type\Lzw(),
            'FlateDecode' => new Type\Flate(),
            'RunLengthDecode' => new Type\RunLength(),
            'CCITTFaxDecode' => new Type\CcittFax(),
            'JBIG2Decode' => new Type\JbigTwo(),
            'DCTDecode' => new Type\Dct(),
            'JPXDecode' => new Type\Jpx(),
            'Crypt' => new Type\Crypt(),
            default => throw new PPException('unknown filter: ' . $filter),
        };

        return $obj->decode($data);
    }

    /**
     * Decode the input data using multiple filters
     *
     * @param array<string>  $filters Array of decoding filters to apply in order
     * @param string $data    Data to decode.
     *
     * @return string Decoded data
     */
    public function decodeAll(array $filters, string $data): string
    {
        foreach ($filters as $filter) {
            $data = $this->decode($filter, $data);
        }

        return $data;
    }
}

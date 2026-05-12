<?php

declare(strict_types=1);

/**
 * Flate.php
 *
 * @since     2011-05-23
 * @category  Library
 * @package   PdfFilter
 * @author    Nicola Asuni <info@tecnick.com>
 * @copyright 2011-2026 Nicola Asuni - Tecnick.com LTD
 * @license   https://www.gnu.org/copyleft/lesser.html GNU-LGPL v3 (see LICENSE.TXT)
 * @link      https://github.com/tecnickcom/tc-lib-pdf-filter
 *
 * This file is part of tc-lib-pdf-filter software library.
 */

namespace Com\Tecnick\Pdf\Filter\Type;

use Com\Tecnick\Pdf\Filter\Exception as PPException;

/**
 * Com\Tecnick\Pdf\Filter\Type\Flate
 *
 * Flatee
 * Decompresses data encoded using the zlib/deflate compression method,
 * reproducing the original text or binary data.
 *
 * @since     2011-05-23
 * @category  Library
 * @package   PdfFilter
 * @author    Nicola Asuni <info@tecnick.com>
 * @copyright 2011-2026 Nicola Asuni - Tecnick.com LTD
 * @license   https://www.gnu.org/copyleft/lesser.html GNU-LGPL v3 (see LICENSE.TXT)
 * @link      https://github.com/tecnickcom/tc-lib-pdf-filter
 */
class Flate implements \Com\Tecnick\Pdf\Filter\Type\Template
{
    /**
     * Decode the data
     *
     * @param string $data   Data to decode.
     * @param array<string, mixed> $params Optional filter parameters.
     *
     * @return string Decoded data string.
     *
     * @throws \Com\Tecnick\Pdf\Filter\Exception
     */
    public function decode(string $data, array $params = []): string
    {
        if ($data === '') {
            return '';
        }

        $handler = static fn(): bool => true;

        \set_error_handler($handler);
        try {
            // initialize string to return
            $decoded = \gzuncompress($data);
        } finally {
            \restore_error_handler();
        }

        if ($decoded === false) {
            throw new PPException('invalid code');
        }

        return $decoded;
    }
}

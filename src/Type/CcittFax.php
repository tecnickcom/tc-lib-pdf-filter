<?php

/**
 * CcittFax.php
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
 * Com\Tecnick\Pdf\Filter\Type\CcittFax
 *
 * CCITTFaxDecode filter (PDF 32000-2008 §7.4.6).
 * Decompresses bi-level (1-bit-per-pixel) image data encoded with CCITT
 * facsimile standards (ITU-T T.4 Group 3 or T.6 Group 4).
 *
 * This implementation builds a TIFF container around the raw CCITT bitstream
 * and uses Imagick to decode it. TIFF natively supports CCITT Group 3 and
 * Group 4 compression, so this avoids having to implement the Huffman tables
 * and bit-stream reader in pure PHP.
 *
 * DecodeParms defaults:
 * - K: 0 (Group 4; positive = Group 3 mixed; negative = Group 3 2D)
 * - Columns: 1728 (standard fax width)
 * - Rows: 0 (use until end-of-data)
 * - BlackIs1: false (white-run first)
 *
 * Suggested PHP extension: ext-imagick
 *
 * @since     2011-05-23
 * @category  Library
 * @package   PdfFilter
 * @author    Nicola Asuni <info@tecnick.com>
 * @copyright 2011-2026 Nicola Asuni - Tecnick.com LTD
 * @license   https://www.gnu.org/copyleft/lesser.html GNU-LGPL v3 (see LICENSE.TXT)
 * @link      https://github.com/tecnickcom/tc-lib-pdf-filter
 */
class CcittFax implements \Com\Tecnick\Pdf\Filter\Type\Template
{
    /**
     * @var int CCITT compression group (3 or 4); affects TIFF header.
     */
    private int $group;

    /**
     * @var int Image width in pixels.
     */
    private int $columns;

    /**
     * @var int Image height in pixels; 0 = unknown / auto-detect.
     */
    private int $rows;

    /**
     * @var bool Whether 1 bits represent black (true) or white (false).
     */
    private bool $blackIs1;

    /**
     * Constructor.
     *
     * @param array<mixed> $params DecodeParms dictionary (optional).
     *   - 'K' (int): Compression mode. 0 = Group 4; negative = Group 3 2D; positive = Group 3 mixed.
     *   - 'Columns' (int): Image width (default 1728).
     *   - 'Rows' (int): Image height; 0 = unknown (default 0).
     *   - 'BlackIs1' (bool): Whether 1 bits are black (default false).
     */
    public function __construct(array $params = [])
    {
        $defaults = [
            'K' => 0,
            'Columns' => 1728,
            'Rows' => 0,
            'BlackIs1' => false,
        ];
        $config = array_replace($defaults, array_intersect_key($params, $defaults));

        /** @var int $kValue */
        $kValue = $config['K'];
        $kParam = (int) $kValue;
        $this->group = $kParam < 0 ? 3 : 4;
        /** @var int $colValue */
        $colValue = $config['Columns'];
        $this->columns = (int) $colValue;
        /** @var int $rowValue */
        $rowValue = $config['Rows'];
        $this->rows = (int) $rowValue;
        /** @var bool $blackValue */
        $blackValue = $config['BlackIs1'];
        $this->blackIs1 = (bool) $blackValue;
    }

    /**
     * Decode the data.
     *
     * Builds a TIFF container around the CCITT bitstream and uses Imagick
     * to decode it.
     *
     * @param string              $data   Raw CCITT-compressed image data.
     * @param array<string, mixed> $params Optional filter parameters (unused; params set via constructor).
     *
     * @return string Decoded PNG image bytes.
     *
     * @throws PPException if Imagick is not available or decoding fails.
     */
    public function decode(string $data, array $params = []): string
    {
        if ($data === '') {
            return '';
        }

        if (!extension_loaded('imagick')) {
            throw new PPException('CCITTFaxDecode requires the Imagick PHP extension (ext-imagick)');
        }

        try {
            $tiff = $this->buildTiffHeader($data);

            $imagick = new \Imagick();
            $imagick->readImageBlob($tiff);
            $imagick->setImageFormat('png');

            return $imagick->getImageBlob();
        } catch (\ImagickException $e) {
            throw new PPException('CCITTFaxDecode: Imagick failed to decode the stream: ' . $e->getMessage());
        }
    }

    /**
     * Build a minimal TIFF container around the CCITT data.
     *
     * @param string $ccittData Raw CCITT-compressed data.
     *
     * @return string Binary TIFF data.
     */
    private function buildTiffHeader(string $ccittData): string
    {
        // TIFF little-endian header
        $tiff = "II" . pack('V', 8); // Byte order + offset to first IFD

        // Collect tags
        $tags = [];

        // Tag 256 (ImageWidth)
        $tags[] = pack('VVVV', 256, 3, 1, $this->columns);

        // Tag 257 (ImageLength / height)
        $height = $this->rows > 0 ? $this->rows : ceil(strlen($ccittData) * 8 / $this->columns);
        $tags[] = pack('VVVV', 257, 3, 1, $height);

        // Tag 258 (BitsPerSample) = 1
        $tags[] = pack('VVVV', 258, 3, 1, 1);

        // Tag 259 (Compression): 4 = Group 4, 3 = Group 3
        $compression = ($this->group === 3) ? 3 : 4;
        $tags[] = pack('VVVV', 259, 3, 1, $compression);

        // Tag 262 (PhotometricInterpretation): 0 = white, 1 = black
        $photometric = $this->blackIs1 ? 1 : 0;
        $tags[] = pack('VVVV', 262, 3, 1, $photometric);

        // Tag 273 (StripOffsets): points to image data
        $stripOffset = 8 + (2 + count($tags) * 12 + 4);
        $tags[] = pack('VVVV', 273, 4, 1, $stripOffset);

        // Tag 279 (StripByteCounts)
        $tags[] = pack('VVVV', 279, 4, 1, strlen($ccittData));

        // Tag 282 (XResolution)
        $tags[] = pack('VVVV', 282, 5, 1, $stripOffset + strlen($ccittData));

        // Tag 283 (YResolution)
        $tags[] = pack('VVVV', 283, 5, 1, $stripOffset + strlen($ccittData) + 8);

        // Write IFD
        $tiff .= pack('v', count($tags)); // Number of tags
        $tiff .= implode('', $tags);
        $tiff .= pack('V', 0); // Next IFD offset (0 = end)

        // Append image data
        $tiff .= $ccittData;

        // Append resolution (72 dpi)
        $tiff .= pack('VV', 72, 1); // XResolution: 72/1
        $tiff .= pack('VV', 72, 1); // YResolution: 72/1

        return $tiff;
    }
}

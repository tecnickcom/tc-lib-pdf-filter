<?php

/**
 * JbigTwo.php
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
 * Com\Tecnick\Pdf\Filter\Type\JbigTwo
 *
 * JBIG2Decode filter (PDF 32000-2008 §7.4.9).
 * Decompresses JBIG2-encoded bi-level image data by shelling out to the
 * jbig2dec CLI tool (https://jbig2dec.sourceforge.net/), which must be
 * installed and on PATH. If the tool is unavailable a PPException is thrown.
 *
 * Suggested system package: jbig2dec
 *
 * @since     2011-05-23
 * @category  Library
 * @package   PdfFilter
 * @author    Nicola Asuni <info@tecnick.com>
 * @copyright 2011-2026 Nicola Asuni - Tecnick.com LTD
 * @license   https://www.gnu.org/copyleft/lesser.html GNU-LGPL v3 (see LICENSE.TXT)
 * @link      https://github.com/tecnickcom/tc-lib-pdf-filter
 */
class JbigTwo implements \Com\Tecnick\Pdf\Filter\Type\Template
{
    /**
     * Decode the data.
     *
     * Requires the jbig2dec CLI tool to be installed and on PATH.
     *
     * @param string              $data   Data to decode.
     * @param array<string, mixed> $params Optional filter parameters.
     *
     * @return string Decoded data string.
     *
     * @throws PPException if jbig2dec is not found or exits with an error.
     */
    public function decode(string $data, array $params = []): string
    {
        if ($data === '') {
            return '';
        }

        $binary = (string) shell_exec('command -v jbig2dec 2>/dev/null');
        if (trim($binary) === '') {
            throw new PPException(
                'JBIG2Decode requires the jbig2dec CLI tool to be installed and on PATH'
            );
        }

        $inFile = tempnam(sys_get_temp_dir(), 'jbig2in_');
        $outFile = tempnam(sys_get_temp_dir(), 'jbig2out_');

        if ($inFile === false || $outFile === false) {
            throw new PPException('JBIG2Decode: failed to create temporary files');
        }

        try {
            file_put_contents($inFile, $data);
            $result = $this->runJbig2dec($inFile, $outFile);
        } finally {
            @unlink($inFile);
            @unlink($outFile);
        }

        return $result;
    }

    /**
     * Run jbig2dec to decode the input file into the output file.
     *
     * @param string $inFile  Path to the input JBIG2 data file.
     * @param string $outFile Path to the output file.
     *
     * @return string Decoded data.
     *
     * @throws PPException if jbig2dec fails to launch, exits non-zero, or output cannot be read.
     */
    private function runJbig2dec(string $inFile, string $outFile): string
    {
        $cmd = sprintf(
            'jbig2dec -e -o %s %s 2>/dev/null',
            escapeshellarg($outFile),
            escapeshellarg($inFile)
        );

        $pipes = [];
        $proc = proc_open($cmd, [], $pipes);
        if ($proc === false) {
            throw new PPException('JBIG2Decode: failed to launch jbig2dec');
        }

        $exitCode = proc_close($proc);

        if ($exitCode !== 0 || !file_exists($outFile)) {
            throw new PPException('JBIG2Decode: jbig2dec failed to decode the stream');
        }

        $result = file_get_contents($outFile);

        if ($result === false) {
            throw new PPException('JBIG2Decode: failed to read jbig2dec output');
        }

        return $result;
    }
}

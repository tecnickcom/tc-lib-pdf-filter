<?php

/**
 * TypeRuntimeBranchTest.php
 *
 * @since     2026-04-30
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
 * Filter decoder runtime branch test
 *
 * @since     2026-04-30
 * @category  Library
 * @package   PdfFilter
 * @author    Nicola Asuni <info@tecnick.com>
 * @copyright 2011-2026 Nicola Asuni - Tecnick.com LTD
 * @license   https://www.gnu.org/copyleft/lesser.html GNU-LGPL v3 (see LICENSE)
 * @link      https://github.com/tecnickcom/tc-lib-pdf-filter
 */
class TypeRuntimeBranchTest extends TestUtil
{
    private function readUInt16(string $data, int $offset, bool $littleEndian): int
    {
        $bytes = substr($data, $offset, 2);
        if (strlen($bytes) !== 2) {
            return 0;
        }

        $byte0 = ord($bytes[0]);
        $byte1 = ord($bytes[1]);

        if ($littleEndian) {
            return $byte0 | ($byte1 << 8);
        }

        return ($byte0 << 8) | $byte1;
    }

    private function readUInt32(string $data, int $offset, bool $littleEndian): int
    {
        $bytes = substr($data, $offset, 4);
        if (strlen($bytes) !== 4) {
            return 0;
        }

        $byte0 = ord($bytes[0]);
        $byte1 = ord($bytes[1]);
        $byte2 = ord($bytes[2]);
        $byte3 = ord($bytes[3]);

        if ($littleEndian) {
            return $byte0 | ($byte1 << 8) | ($byte2 << 16) | ($byte3 << 24);
        }

        return ($byte0 << 24) | ($byte1 << 16) | ($byte2 << 8) | $byte3;
    }

    private function extractTiffStrip(string $tiffBlob): string
    {
        $byteOrder = substr($tiffBlob, 0, 2);
        $littleEndian = $byteOrder === 'II';
        if (!$littleEndian && $byteOrder !== 'MM') {
            return '';
        }

        $ifdOffset = $this->readUInt32($tiffBlob, 4, $littleEndian);
        if ($ifdOffset <= 0) {
            return '';
        }

        $numTags = $this->readUInt16($tiffBlob, $ifdOffset, $littleEndian);
        $cursor = $ifdOffset + 2;
        $stripOffset = 0;
        $stripByteCount = 0;

        for ($i = 0; $i < $numTags; ++$i) {
            $tag = $this->readUInt16($tiffBlob, $cursor, $littleEndian);
            $type = $this->readUInt16($tiffBlob, $cursor + 2, $littleEndian);
            $count = $this->readUInt32($tiffBlob, $cursor + 4, $littleEndian);
            $value = $this->readUInt32($tiffBlob, $cursor + 8, $littleEndian);

            if ($count === 1 && $tag === 273) {
                $stripOffset = $type === 3 ? $value & 0xFFFF : $value;
            }

            if ($count === 1 && $tag === 279) {
                $stripByteCount = $type === 3 ? $value & 0xFFFF : $value;
            }

            $cursor += 12;
        }

        if ($stripOffset <= 0 || $stripByteCount <= 0) {
            return '';
        }

        return substr($tiffBlob, $stripOffset, $stripByteCount);
    }

    protected function setUp(): void
    {
        parent::setUp();
        \Com\Tecnick\Pdf\Filter\Type\RuntimeShim::reset();
    }

    protected function tearDown(): void
    {
        \Com\Tecnick\Pdf\Filter\Type\RuntimeShim::reset();
        parent::tearDown();
    }

    public function testJpxMissingImagickPathViaShim(): void
    {
        \Com\Tecnick\Pdf\Filter\Type\RuntimeShim::$enabled = true;
        \Com\Tecnick\Pdf\Filter\Type\RuntimeShim::$imagickLoaded = false;

        $obj = new \Com\Tecnick\Pdf\Filter\Type\Jpx();

        $this->assertThrows(
            '\\' . \Com\Tecnick\Pdf\Filter\Exception::class,
            'JPXDecode requires the Imagick PHP extension',
            static fn(): mixed => $obj->decode('not-empty'),
        );
    }

    public function testCcittFaxMissingImagickPathViaShim(): void
    {
        \Com\Tecnick\Pdf\Filter\Type\RuntimeShim::$enabled = true;
        \Com\Tecnick\Pdf\Filter\Type\RuntimeShim::$imagickLoaded = false;

        $obj = new \Com\Tecnick\Pdf\Filter\Type\CcittFax();

        $this->assertThrows(
            '\\' . \Com\Tecnick\Pdf\Filter\Exception::class,
            'CCITTFaxDecode requires the Imagick PHP extension',
            static fn(): mixed => $obj->decode('not-empty'),
        );
    }

    public function testCcittFaxSuccessPathWithGeneratedCcittData(): void
    {
        if (!\extension_loaded('imagick')) {
            $this->markTestSkipped('ext-imagick is not available');
        }

        $img = new \Imagick();
        $img->newImage(1, 1, 'white');
        $img->setImageType(\Imagick::IMGTYPE_BILEVEL);
        $img->setImageDepth(1);
        $img->setImageCompression(\Imagick::COMPRESSION_GROUP4);
        $img->setImageFormat('tiff');

        $tiffBlob = $img->getImageBlob();
        $ccittData = $this->extractTiffStrip($tiffBlob);
        $this->assertNotSame('', $ccittData);

        $obj = new \Com\Tecnick\Pdf\Filter\Type\CcittFax([
            'K' => 0,
            'Columns' => 1,
            'Rows' => 1,
            'BlackIs1' => false,
        ]);

        try {
            $decoded = $obj->decode($ccittData);
        } catch (\Com\Tecnick\Pdf\Filter\Exception $e) {
            if (str_contains($e->getMessage(), 'no decode delegate')) {
                $this->markTestSkipped('Imagick is available but TIFF/CCITT decode delegate is missing');
            }

            throw $e;
        }

        $this->assertStringStartsWith("\x89PNG", $decoded);
    }

    /**
     * JbigTwo::decode() throws the same class from six places, so the message is
     * what tells the missing-tool path apart from the tool-failed one.
     */
    public function testJbigTwoMissingToolPathViaShim(): void
    {
        \Com\Tecnick\Pdf\Filter\Type\RuntimeShim::$enabled = true;
        // "command -v jbig2dec" finds nothing
        \Com\Tecnick\Pdf\Filter\Type\RuntimeShim::$shellExecOutput = '';

        $obj = new \Com\Tecnick\Pdf\Filter\Type\JbigTwo();

        $this->assertThrows(
            '\\' . \Com\Tecnick\Pdf\Filter\Exception::class,
            'JBIG2Decode requires the jbig2dec CLI tool to be installed and on PATH',
            static fn(): mixed => $obj->decode('payload'),
        );
    }

    public function testJbigTwoTempFileCreationFailure(): void
    {
        \Com\Tecnick\Pdf\Filter\Type\RuntimeShim::$enabled = true;
        \Com\Tecnick\Pdf\Filter\Type\RuntimeShim::$shellExecOutput = '/usr/bin/jbig2dec';
        \Com\Tecnick\Pdf\Filter\Type\RuntimeShim::$tempnamFail = true;

        $obj = new \Com\Tecnick\Pdf\Filter\Type\JbigTwo();

        $this->assertThrows(
            '\\' . \Com\Tecnick\Pdf\Filter\Exception::class,
            'failed to create temporary files',
            static fn(): mixed => $obj->decode('payload'),
        );
    }

    public function testJbigTwoLaunchFailure(): void
    {
        \Com\Tecnick\Pdf\Filter\Type\RuntimeShim::$enabled = true;
        \Com\Tecnick\Pdf\Filter\Type\RuntimeShim::$shellExecOutput = '/usr/bin/jbig2dec';
        \Com\Tecnick\Pdf\Filter\Type\RuntimeShim::$tempnamSequence = ['/tmp/jbig2in_mock', '/tmp/jbig2out_mock'];
        \Com\Tecnick\Pdf\Filter\Type\RuntimeShim::$procOpenFail = true;

        $obj = new \Com\Tecnick\Pdf\Filter\Type\JbigTwo();

        $this->assertThrows(
            '\\' . \Com\Tecnick\Pdf\Filter\Exception::class,
            'failed to launch jbig2dec',
            static fn(): mixed => $obj->decode('payload'),
        );
    }

    public function testJbigTwoExitCodeFailure(): void
    {
        \Com\Tecnick\Pdf\Filter\Type\RuntimeShim::$enabled = true;
        \Com\Tecnick\Pdf\Filter\Type\RuntimeShim::$shellExecOutput = '/usr/bin/jbig2dec';
        \Com\Tecnick\Pdf\Filter\Type\RuntimeShim::$tempnamSequence = ['/tmp/jbig2in_mock', '/tmp/jbig2out_mock'];
        \Com\Tecnick\Pdf\Filter\Type\RuntimeShim::$procCloseCode = 1;

        $obj = new \Com\Tecnick\Pdf\Filter\Type\JbigTwo();

        $this->assertThrows(
            '\\' . \Com\Tecnick\Pdf\Filter\Exception::class,
            'jbig2dec failed to decode the stream',
            static fn(): mixed => $obj->decode('payload'),
        );
    }

    public function testJbigTwoEmptyOutputFailure(): void
    {
        \Com\Tecnick\Pdf\Filter\Type\RuntimeShim::$enabled = true;
        \Com\Tecnick\Pdf\Filter\Type\RuntimeShim::$shellExecOutput = '/usr/bin/jbig2dec';
        \Com\Tecnick\Pdf\Filter\Type\RuntimeShim::$tempnamSequence = ['/tmp/jbig2in_mock', '/tmp/jbig2out_mock'];
        \Com\Tecnick\Pdf\Filter\Type\RuntimeShim::$procCloseCode = 0;
        // tempnam() already created the output file, so "no output" surfaces as empty content
        \Com\Tecnick\Pdf\Filter\Type\RuntimeShim::$fileGetContents = '';

        $obj = new \Com\Tecnick\Pdf\Filter\Type\JbigTwo();

        $this->assertThrows(
            '\\' . \Com\Tecnick\Pdf\Filter\Exception::class,
            'jbig2dec produced no output',
            static fn(): mixed => $obj->decode('payload'),
        );
    }

    public function testJbigTwoOutputReadFailure(): void
    {
        \Com\Tecnick\Pdf\Filter\Type\RuntimeShim::$enabled = true;
        \Com\Tecnick\Pdf\Filter\Type\RuntimeShim::$shellExecOutput = '/usr/bin/jbig2dec';
        \Com\Tecnick\Pdf\Filter\Type\RuntimeShim::$tempnamSequence = ['/tmp/jbig2in_mock', '/tmp/jbig2out_mock'];
        \Com\Tecnick\Pdf\Filter\Type\RuntimeShim::$procCloseCode = 0;
        \Com\Tecnick\Pdf\Filter\Type\RuntimeShim::$fileGetContents = false;

        $obj = new \Com\Tecnick\Pdf\Filter\Type\JbigTwo();

        $this->assertThrows(
            '\\' . \Com\Tecnick\Pdf\Filter\Exception::class,
            'failed to read jbig2dec output',
            static fn(): mixed => $obj->decode('payload'),
        );
    }

    public function testJbigTwoSuccessPathViaShim(): void
    {
        \Com\Tecnick\Pdf\Filter\Type\RuntimeShim::$enabled = true;
        \Com\Tecnick\Pdf\Filter\Type\RuntimeShim::$shellExecOutput = '/usr/bin/jbig2dec';
        \Com\Tecnick\Pdf\Filter\Type\RuntimeShim::$tempnamSequence = ['/tmp/jbig2in_mock', '/tmp/jbig2out_mock'];
        \Com\Tecnick\Pdf\Filter\Type\RuntimeShim::$procCloseCode = 0;
        \Com\Tecnick\Pdf\Filter\Type\RuntimeShim::$fileGetContents = 'decoded-ok';

        $obj = new \Com\Tecnick\Pdf\Filter\Type\JbigTwo();
        $result = $obj->decode('payload');

        $this->assertSame('decoded-ok', $result);
        $this->assertSame(
            ['/tmp/jbig2in_mock', '/tmp/jbig2out_mock'],
            \Com\Tecnick\Pdf\Filter\Type\RuntimeShim::$unlinked,
        );
    }

    /**
     * tempnam() creates the file it names, so a failure on the second call must
     * still remove the one the first call left on disk.
     */
    public function testJbigTwoOutputTempFileCreationFailureRemovesTheInputFile(): void
    {
        \Com\Tecnick\Pdf\Filter\Type\RuntimeShim::$enabled = true;
        \Com\Tecnick\Pdf\Filter\Type\RuntimeShim::$shellExecOutput = '/usr/bin/jbig2dec';
        \Com\Tecnick\Pdf\Filter\Type\RuntimeShim::$tempnamSequence = ['/tmp/jbig2in_mock', false];

        $obj = new \Com\Tecnick\Pdf\Filter\Type\JbigTwo();

        $this->assertThrows(
            '\\' . \Com\Tecnick\Pdf\Filter\Exception::class,
            'failed to create temporary files',
            static fn(): mixed => $obj->decode('payload'),
        );

        $this->assertSame(['/tmp/jbig2in_mock'], \Com\Tecnick\Pdf\Filter\Type\RuntimeShim::$unlinked);
    }

    public function testJbigTwoInputWriteFailure(): void
    {
        \Com\Tecnick\Pdf\Filter\Type\RuntimeShim::$enabled = true;
        \Com\Tecnick\Pdf\Filter\Type\RuntimeShim::$shellExecOutput = '/usr/bin/jbig2dec';
        \Com\Tecnick\Pdf\Filter\Type\RuntimeShim::$tempnamSequence = ['/tmp/jbig2in_mock', '/tmp/jbig2out_mock'];
        \Com\Tecnick\Pdf\Filter\Type\RuntimeShim::$putResult = false;

        $obj = new \Com\Tecnick\Pdf\Filter\Type\JbigTwo();

        $this->assertThrows(
            '\\' . \Com\Tecnick\Pdf\Filter\Exception::class,
            'failed to write temporary input file',
            static fn(): mixed => $obj->decode('payload'),
        );
    }

    public function testJbigTwoGlobalsSuccessPathViaShim(): void
    {
        \Com\Tecnick\Pdf\Filter\Type\RuntimeShim::$enabled = true;
        \Com\Tecnick\Pdf\Filter\Type\RuntimeShim::$shellExecOutput = '/usr/bin/jbig2dec';
        \Com\Tecnick\Pdf\Filter\Type\RuntimeShim::$tempnamSequence = [
            '/tmp/jbig2in_mock',
            '/tmp/jbig2out_mock',
            '/tmp/jbig2glob_mock',
        ];
        \Com\Tecnick\Pdf\Filter\Type\RuntimeShim::$procCloseCode = 0;
        \Com\Tecnick\Pdf\Filter\Type\RuntimeShim::$fileGetContents = 'decoded-ok';

        $obj = new \Com\Tecnick\Pdf\Filter\Type\JbigTwo();
        $result = $obj->decode('payload', ['JBIG2Globals' => 'shared-segments']);

        $this->assertSame('decoded-ok', $result);
        // the globals temp file is created and cleaned up alongside the in/out files
        $this->assertSame(
            ['/tmp/jbig2in_mock', '/tmp/jbig2out_mock', '/tmp/jbig2glob_mock'],
            \Com\Tecnick\Pdf\Filter\Type\RuntimeShim::$unlinked,
        );

        $commands = \Com\Tecnick\Pdf\Filter\Type\RuntimeShim::$procCommands;
        $this->assertCount(1, $commands);
        // -e selects embedded-stream mode, which is the form a PDF JBIG2Decode
        // stream takes: without it no real stream decodes
        $this->assertStringContainsString('jbig2dec -e ', $commands[0]);
        // the decoded page goes to the output file...
        $this->assertStringContainsString("-o '/tmp/jbig2out_mock'", $commands[0]);
        // ...and the globals stream must precede the page stream
        $this->assertLessThan(
            (int) \strpos($commands[0], "'/tmp/jbig2in_mock'"),
            (int) \strpos($commands[0], "'/tmp/jbig2glob_mock'"),
        );
    }

    public function testJbigTwoGlobalsTempFileCreationFailure(): void
    {
        \Com\Tecnick\Pdf\Filter\Type\RuntimeShim::$enabled = true;
        \Com\Tecnick\Pdf\Filter\Type\RuntimeShim::$shellExecOutput = '/usr/bin/jbig2dec';
        // the third tempnam() call - the one for the globals file - fails
        \Com\Tecnick\Pdf\Filter\Type\RuntimeShim::$tempnamSequence = [
            '/tmp/jbig2in_mock',
            '/tmp/jbig2out_mock',
            false,
        ];

        $obj = new \Com\Tecnick\Pdf\Filter\Type\JbigTwo();

        $this->assertThrows(
            '\\' . \Com\Tecnick\Pdf\Filter\Exception::class,
            'failed to create temporary files',
            static fn(): mixed => $obj->decode('payload', ['JBIG2Globals' => 'shared-segments']),
        );
    }

    public function testJbigTwoGlobalsWriteFailure(): void
    {
        \Com\Tecnick\Pdf\Filter\Type\RuntimeShim::$enabled = true;
        \Com\Tecnick\Pdf\Filter\Type\RuntimeShim::$shellExecOutput = '/usr/bin/jbig2dec';
        \Com\Tecnick\Pdf\Filter\Type\RuntimeShim::$tempnamSequence = [
            '/tmp/jbig2in_mock',
            '/tmp/jbig2out_mock',
            '/tmp/jbig2glob_mock',
        ];
        // the input file is written first; only the globals write fails
        \Com\Tecnick\Pdf\Filter\Type\RuntimeShim::$putSequence = [1, false];

        $obj = new \Com\Tecnick\Pdf\Filter\Type\JbigTwo();

        $this->assertThrows(
            '\\' . \Com\Tecnick\Pdf\Filter\Exception::class,
            'failed to write temporary globals file',
            static fn(): mixed => $obj->decode('payload', ['JBIG2Globals' => 'shared-segments']),
        );

        // writeGlobals() throws before returning the path, so decode()'s own
        // finally cannot see it: the file is cleaned up there or not at all
        $this->assertContains('/tmp/jbig2glob_mock', \Com\Tecnick\Pdf\Filter\Type\RuntimeShim::$unlinked);
    }

    public function testJbigTwoEmptyGlobalsIsTreatedAsNoGlobals(): void
    {
        \Com\Tecnick\Pdf\Filter\Type\RuntimeShim::$enabled = true;
        \Com\Tecnick\Pdf\Filter\Type\RuntimeShim::$shellExecOutput = '/usr/bin/jbig2dec';
        \Com\Tecnick\Pdf\Filter\Type\RuntimeShim::$tempnamSequence = [
            '/tmp/jbig2in_mock',
            '/tmp/jbig2out_mock',
            '/tmp/jbig2glob_mock',
        ];
        \Com\Tecnick\Pdf\Filter\Type\RuntimeShim::$procCloseCode = 0;
        \Com\Tecnick\Pdf\Filter\Type\RuntimeShim::$fileGetContents = 'decoded-ok';

        $obj = new \Com\Tecnick\Pdf\Filter\Type\JbigTwo();
        $this->assertSame('decoded-ok', $obj->decode('payload', ['JBIG2Globals' => '']));

        // no globals file is created, so none is passed on the command line
        $this->assertSame(
            ['/tmp/jbig2in_mock', '/tmp/jbig2out_mock'],
            \Com\Tecnick\Pdf\Filter\Type\RuntimeShim::$unlinked,
        );
        $commands = \Com\Tecnick\Pdf\Filter\Type\RuntimeShim::$procCommands;
        $this->assertStringNotContainsString('jbig2glob_mock', $commands[0]);
    }
}

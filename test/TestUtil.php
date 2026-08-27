<?php

/**
 * TestUtil.php
 *
 * @since     2020-12-19
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

use PHPUnit\Framework\TestCase;

/**
 * Shared test case base class
 *
 * @since     2020-12-19
 * @category  Library
 * @package   PdfFilter
 * @author    Nicola Asuni <info@tecnick.com>
 * @copyright 2011-2026 Nicola Asuni - Tecnick.com LTD
 * @license   https://www.gnu.org/copyleft/lesser.html GNU-LGPL v3 (see LICENSE)
 * @link      https://github.com/tecnickcom/tc-lib-pdf-filter
 */
class TestUtil extends TestCase
{
    public function bcExpectException(string $exception): void
    {
        if (!\is_a($exception, \Throwable::class, true)) {
            throw new \InvalidArgumentException('Expected a Throwable class-string');
        }

        parent::expectException($exception);
    }

    /**
     * Assert that the callback throws the given exception with a message
     * containing the given fragment.
     *
     * @param string   $exception       Expected exception class name.
     * @param string   $messageFragment Substring the message must contain.
     * @param callable $callback        Code expected to throw.
     */
    public function assertThrows(string $exception, string $messageFragment, callable $callback): void
    {
        if (!\is_a($exception, \Throwable::class, true)) {
            throw new \InvalidArgumentException('Expected a Throwable class-string');
        }

        try {
            $callback();
        } catch (\Throwable $thrown) {
            $this->assertInstanceOf($exception, $thrown);
            $this->assertStringContainsString($messageFragment, $thrown->getMessage());
            return;
        }

        $this->fail('expected ' . $exception . ' with a message containing: ' . $messageFragment);
    }
}

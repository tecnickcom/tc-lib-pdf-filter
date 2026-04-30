<?php

/**
 * TypeRuntimeBranchShim.php
 *
 * @since     2026-04-30
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

/**
 * Runtime shims for deterministic branch coverage in decoder tests.
 */
final class RuntimeShim
{
    public static bool $enabled = false;

    public static ?bool $imagickLoaded = null;

    public static ?string $shellExecOutput = null;

    public static bool $tempnamFail = false;

    /**
     * @var array<int, string|false>
     */
    public static array $tempnamSequence = [];

    public static bool $procOpenFail = false;

    public static int $procCloseCode = 0;

    public static bool $fileExists = true;

    /** @var string|false */
    public static $fileGetContents = 'decoded-data';

    /** @var int|false */
    public static $putResult = 1;

    /**
     * @var array<int, string>
     */
    public static array $unlinked = [];

    public static function reset(): void
    {
        self::$enabled = false;
        self::$imagickLoaded = null;
        self::$shellExecOutput = null;
        self::$tempnamFail = false;
        self::$tempnamSequence = [];
        self::$procOpenFail = false;
        self::$procCloseCode = 0;
        self::$fileExists = true;
        self::$fileGetContents = 'decoded-data';
        self::$putResult = 1;
        self::$unlinked = [];
    }
}

function extension_loaded(string $name): bool
{
    if (RuntimeShim::$enabled && $name === 'imagick' && RuntimeShim::$imagickLoaded !== null) {
        return RuntimeShim::$imagickLoaded;
    }

    return \extension_loaded($name);
}

/**
 * @return string|false|null
 */
function shell_exec(?string $command)
{
    if (RuntimeShim::$enabled && RuntimeShim::$shellExecOutput !== null) {
        return RuntimeShim::$shellExecOutput;
    }

    return \shell_exec((string) $command);
}

/**
 * @return string|false
 */
function tempnam(string $directory, string $prefix)
{
    if (RuntimeShim::$enabled) {
        if (RuntimeShim::$tempnamFail) {
            return false;
        }

        if (RuntimeShim::$tempnamSequence !== []) {
            return array_shift(RuntimeShim::$tempnamSequence);
        }
    }

    return \tempnam($directory, $prefix);
}

/**
 * @param array<int, list<string>|resource> $descriptorSpec
 * @param mixed                              $pipes
 * @param array<string, mixed>|null          $envVars
 * @param array<string, bool>|null           $options
 *
 * @return resource|false
 */
function proc_open(
    string $command,
    array $descriptorSpec,
    &$pipes,
    ?string $cwd = null,
    ?array $envVars = null,
    ?array $options = null
) {
    if (RuntimeShim::$enabled) {
        if (RuntimeShim::$procOpenFail) {
            return false;
        }

        $pipes = [\fopen('php://temp', 'r')];
        return \fopen('php://temp', 'r');
    }

    return \proc_open($command, $descriptorSpec, $pipes, $cwd, $envVars, $options);
}

/**
 * @param resource $process
 */
function proc_close($process): int
{
    if (RuntimeShim::$enabled) {
        if (is_resource($process)) {
            \fclose($process);
        }

        return RuntimeShim::$procCloseCode;
    }

    return \proc_close($process);
}

function file_exists(string $filename): bool
{
    if (RuntimeShim::$enabled) {
        return RuntimeShim::$fileExists;
    }

    return \file_exists($filename);
}

/**
 * @param resource|null $context
 *
 * @return string|false
 */
function file_get_contents(
    string $filename,
    bool $useIncludePath = false,
    $context = null,
    int $offset = 0,
    ?int $length = null
) {
    if (RuntimeShim::$enabled) {
        return RuntimeShim::$fileGetContents;
    }

    if ($length === null) {
        return \file_get_contents($filename, $useIncludePath, $context, $offset);
    }

    $length = max(0, $length);

    return \file_get_contents($filename, $useIncludePath, $context, $offset, $length);
}

/**
 * @param mixed         $data
 * @param resource|null $context
 *
 * @return int|false
 */
function file_put_contents(string $filename, $data, int $flags = 0, $context = null)
{
    if (RuntimeShim::$enabled) {
        return RuntimeShim::$putResult;
    }

    return \file_put_contents($filename, $data, $flags, $context);
}

function unlink(string $filename): bool
{
    if (RuntimeShim::$enabled) {
        RuntimeShim::$unlinked[] = $filename;
        return true;
    }

    return \unlink($filename);
}

<?php

namespace AlibabaCloud\Credentials\Utils;

use AlibabaCloud\Credentials\Credential;
use org\bovigo\vfs\vfsStream;
use Closure;

/**
 * Class Helper
 *
 * @package AlibabaCloud\Credentials\Utils
 */
class Helper
{
    /**
     * @param array $arrays
     *
     * @return array
     */
    public static function merge(array $arrays)
    {
        $result = [];
        foreach ($arrays as $array) {
            foreach ($array as $key => $value) {
                if (is_int($key)) {
                    $result[] = $value;
                    continue;
                }

                if (isset($result[$key]) && is_array($result[$key])) {
                    $result[$key] = self::merge(
                        [$result[$key], $value]
                    );
                    continue;
                }

                $result[$key] = $value;
            }
        }

        return $result;
    }

    /**
     * @param      $filename
     *
     * @return bool
     */
    public static function inOpenBasedir($filename)
    {
        $open_basedir = ini_get('open_basedir');
        if (!$open_basedir) {
            return true;
        }
        if (0 === strpos($filename, vfsStream::SCHEME)) {
            // 虚拟文件忽略
            return true;
        }

        $dirs = explode(PATH_SEPARATOR, $open_basedir);

        return empty($dirs) || self::inDir($filename, $dirs);
    }

    /**
     * @param string $filename
     * @param array  $dirs
     *
     * @return bool
     */
    public static function inDir($filename, array $dirs)
    {
        foreach ($dirs as $dir) {
            if ($dir[strlen($dir) - 1] !== DIRECTORY_SEPARATOR) {
                $dir .= DIRECTORY_SEPARATOR;
            }

            if (0 === strpos($filename, $dir)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return bool
     */
    public static function isWindows()
    {
        return PATH_SEPARATOR === ';';
    }

    /**
     * @param $key
     *
     * @return bool|mixed
     */
    public static function envNotEmpty($key)
    {
        $value = self::env($key, false);
        if ($value) {
            return $value;
        }

        return false;
    }

    /**
     * Gets the value of an environment variable.
     *
     * @param string $key
     * @param mixed  $default
     *
     * @return mixed
     */
    public static function env($key, $default = null)
    {
        $value = getenv($key);

        if ($value === false) {
            return self::value($default);
        }

        if (self::envSubstr($value)) {
            return substr($value, 1, -1);
        }

        return self::envConversion($value);
    }

    /**
     * Return the default value of the given value.
     *
     * @param mixed $value
     *
     * @return mixed
     */
    public static function value($value)
    {
        return $value instanceof Closure ? $value() : $value;
    }

    /**
     * @param $value
     *
     * @return bool
     */
    public static function envSubstr($value)
    {
        return ($valueLength = strlen($value)) > 1
            && strpos($value, '"') === 0
            && $value[$valueLength - 1] === '"';
    }

    /**
     * @param $value
     *
     * @return bool|string|null
     */
    public static function envConversion($value)
    {
        $key = strtolower($value);

        if ($key === 'null' || $key === '(null)') {
            return null;
        }

        $list = [
            'true'    => true,
            '(true)'  => true,
            'false'   => false,
            '(false)' => false,
            'empty'   => '',
            '(empty)' => '',
        ];

        return isset($list[$key]) ? $list[$key] : $value;
    }

    /**
     * Gets the environment's HOME directory.
     *
     * @return null|string
     */
    public static function getHomeDirectory()
    {
        if (getenv('HOME')) {
            return getenv('HOME');
        }

        return (getenv('HOMEDRIVE') && getenv('HOMEPATH'))
            ? getenv('HOMEDRIVE') . getenv('HOMEPATH')
            : null;
    }

    /**
     * Split process_command into argv with quote support.
     * Allows quoted Windows paths such as "C:\Program Files\tool.exe".
     *
     * On Unix, escape rules follow POSIX shlex: outside quotes, '\' escapes the
     * next char; inside double quotes, '\' only escapes '"', '\', '$' and '`';
     * backslash-newline is a line continuation (both removed) outside single
     * quotes; inside single quotes, all characters are literal.
     *
     * On Windows, '\' is a path separator and is treated as a literal (except
     * '\"' inside double quotes), so unquoted paths like C:\tools\cred.exe keep
     * their backslashes.
     *
     * @param string    $command
     * @param bool|null $windows defaults to the current platform
     *
     * @return array
     */
    public static function splitProcessCommand($command, $windows = null)
    {
        if ($windows === null) {
            $windows = DIRECTORY_SEPARATOR === '\\';
        }
        $input = trim((string) $command);
        if ($input === '') {
            throw new \RuntimeException('process_command is empty');
        }

        $args = [];
        $current = '';
        $inSingle = false;
        $inDouble = false;
        // Tracks that a token has started even if it is empty, so quoted empty
        // arguments like `tool "" arg` keep their empty argv element.
        $hasToken = false;
        $len = strlen($input);

        for ($i = 0; $i < $len; $i++) {
            $c = $input[$i];
            if ($inSingle) {
                if ($c === "'") {
                    $inSingle = false;
                } else {
                    $current .= $c;
                }
                continue;
            }
            if ($inDouble) {
                if ($c === '"') {
                    $inDouble = false;
                    continue;
                }
                if ($c === '\\' && $i + 1 < $len) {
                    $next = $input[$i + 1];
                    if ($windows) {
                        // On Windows only \" is an escape inside double quotes.
                        if ($next === '"') {
                            $current .= $next;
                            $i++;
                            continue;
                        }
                    } elseif ($next === "\n") {
                        // Backslash-newline is a line continuation: both removed.
                        $i++;
                        continue;
                    } elseif ($next === '"' || $next === '\\' || $next === '$' || $next === '`') {
                        $current .= $next;
                        $i++;
                        continue;
                    }
                }
                $current .= $c;
                continue;
            }
            if ($c === '\\') {
                if ($windows) {
                    // Path separator — keep literal.
                    $hasToken = true;
                    $current .= $c;
                    continue;
                }
                if ($i + 1 >= $len) {
                    throw new \RuntimeException('invalid process_command: trailing backslash');
                }
                if ($input[$i + 1] === "\n") {
                    // Backslash-newline is a line continuation: both removed.
                    $i++;
                    continue;
                }
                $hasToken = true;
                $current .= $input[++$i];
                continue;
            }
            if ($c === "'") {
                $inSingle = true;
                $hasToken = true;
                continue;
            }
            if ($c === '"') {
                $inDouble = true;
                $hasToken = true;
                continue;
            }
            if (ctype_space($c)) {
                if ($hasToken) {
                    $args[] = $current;
                    $current = '';
                    $hasToken = false;
                }
                continue;
            }
            $hasToken = true;
            $current .= $c;
        }

        if ($inSingle || $inDouble) {
            throw new \RuntimeException('invalid process_command: unclosed quote');
        }
        if ($hasToken) {
            $args[] = $current;
        }
        if (empty($args) || $args[0] === '') {
            throw new \RuntimeException('process_command is empty');
        }

        return $args;
    }

    /**
     * @param mixed ...$parameters
     *
     * @codeCoverageIgnore
     */
    public static function dd(...$parameters)
    {
        dump(...$parameters);
        exit;
    }

    /**
     * Snake to camel case.
     *
     * @param string $str
     *
     * @return string
     */
    public static function snakeToCamelCase($str)
    {
        $components = explode('_', $str);
        $camelCaseStr = $components[0];
        for ($i = 1; $i < count($components); $i++) {
            $camelCaseStr .= ucfirst($components[$i]);
        }
        return $camelCaseStr;
    }

    /**
     * Get user agent.
     *
     * @param string $userAgent
     *
     * @return string
     */
    public static function getUserAgent()
    {
        return sprintf('AlibabaCloud (%s; %s) PHP/%s Credentials/%s TeaDSL/1', PHP_OS, \PHP_SAPI, PHP_VERSION, Credential::VERSION);
    }

    /**
     * @param array $arrays
     * @param string $key
     *
     * @return mix
     */
    public static function unsetReturnNull(array $arrays, $key)
    {
        if(isset($arrays[$key])) {
            return $arrays[$key];
        }
        return null;
    }
}

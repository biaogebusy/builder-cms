<?php declare(strict_types=1);

namespace PhpTuf\ComposerStager\API\Filesystem\Service;

use PhpTuf\ComposerStager\API\Path\Value\PathInterface;
use PhpTuf\ComposerStager\API\Process\Service\OutputCallbackInterface;
use PhpTuf\ComposerStager\API\Process\Service\ProcessInterface;

/**
 * Provides basic utilities for interacting with the file system.
 *
 * Developer's note: This interface and its method names should correspond as much as possible to
 * PHP's built-in filesystem functions at {@see https://www.php.net/manual/en/book.filesystem.php}.
 *
 * @see \PhpTuf\ComposerStager\API\Path\Value\PathInterface
 *   For path string functionality that doesn't touch the filesystem.
 *
 * @package Filesystem
 *
 * @api This interface is subject to our backward compatibility promise and may be safely depended upon.
 */
interface FilesystemInterface
{
    /**
     * Determines whether the given path exists.
     *
     * @param \PhpTuf\ComposerStager\API\Path\Value\PathInterface $path
     *   A path to test.
     *
     * @see https://www.php.net/manual/en/function.file-exists.php
     */
    public function fileExists(PathInterface $path): bool;

    /**
     * Determines whether the given path is a directory.
     *
     * Like PHP's built-in
     * {@see https://www.php.net/manual/en/function.is-dir.php `is_dir()`}
     * function, if the given path is a symbolic or hard link, then the link
     * will be resolved and checked. In other words, even if the path is a link,
     * if the target is a directory, this method will return true. If the
     * distinction matters to you, check {@see FilesystemInterface::isLink()}
     * first.
     *
     * @param \PhpTuf\ComposerStager\API\Path\Value\PathInterface $path
     *   A path to test.
     *
     * @return bool
     *   Returns true if the path exists and is or points to a directory.
     */
    public function isDir(PathInterface $path): bool;

    /**
     * Determines whether the given path is a regular file.
     *
     * Like PHP's built-in
     * {@see https://www.php.net/manual/en/function.is-file.php `is_file()`}
     * function, if the given path is a symbolic or hard link, then the link
     * will be resolved and checked. In other words, even if the path is a link,
     * if the target is a regular file, this method will return true. If the
     * distinction matters to you, check {@see FilesystemInterface::isLink()}
     * first.
     *
     * @param \PhpTuf\ComposerStager\API\Path\Value\PathInterface $path
     *   A path to test.
     *
     * @return bool
     *   Returns true if the path exists and is or points to a regular file.
     */
    public function isFile(PathInterface $path): bool;

    /**
     * Determines whether the given path is a hard link.
     *
     * Symbolic links (symlinks) are distinct from hard links and do not count.
     *
     * @param \PhpTuf\ComposerStager\API\Path\Value\PathInterface $path
     *   A path to test.
     *
     * @return bool
     *   Returns true if the filename exists and is a hard link (not a symlink)
     *   false otherwise.
     */
    public function isHardLink(PathInterface $path): bool;

    /**
     * Determines whether the given path is a link.
     *
     * Symbolic links (symlinks) and hard links both count.
     *
     * @param \PhpTuf\ComposerStager\API\Path\Value\PathInterface $path
     *   A path to test.
     *
     * @return bool
     *   Returns true if the filename exists and is a link, false otherwise.
     *
     * @see https://www.php.net/manual/en/function.is-link.php
     */
    public function isLink(PathInterface $path): bool;

    /**
     * Determines whether the given path is a symbolic link.
     *
     * Hard links are distinct from symbolic links (symlinks) and do not count.
     *
     * @param \PhpTuf\ComposerStager\API\Path\Value\PathInterface $path
     *   A path to test.
     *
     * @return bool
     *   Returns true if the filename exists and is a symlink (not a hard link),
     *   false otherwise.
     */
    public function isSymlink(PathInterface $path): bool;

    /**
     * Determines whether the given path is writable.
     *
     * @param \PhpTuf\ComposerStager\API\Path\Value\PathInterface $path
     *   A path to test.
     *
     * @see https://www.php.net/manual/en/function.is-writable.php
     */
    public function isWritable(PathInterface $path): bool;

    /**
     * Recursively creates a directory at the given path.
     *
     * This differs from PHP's built-in `mkdir()` function in that this method
     * will not fail is a directory already exists at the given path.
     *
     * @param \PhpTuf\ComposerStager\API\Path\Value\PathInterface $path
     *   The directory to create.
     *
     * @throws \PhpTuf\ComposerStager\API\Exception\IOException
     *   If the directory cannot be created.
     *
     * @see https://www.php.net/manual/en/function.mkdir.php
     */
    public function mkdir(PathInterface $path): void;

    /**
     * Returns the target of a symbolic link.
     *
     * Hard links are not included and will throw an exception. Consider using
     * ::isSymlink() first.
     *
     * Note: PHP does not distinguish between absolute and relative links on
     * Windows, so the returned path object there will be based on a canonicalized,
     * absolute path string. In other words, ALL link paths on Windows will
     * behave like absolute links, whether they really are or not.
     *
     * @param \PhpTuf\ComposerStager\API\Path\Value\PathInterface $path
     *   The link path.
     *
     * @throws \PhpTuf\ComposerStager\API\Exception\IOException
     *   If the path is not a symbolic link (symlink) or cannot be read. Hard
     *   links are distinct from symlinks and will still throw an exception.
     *
     * @see https://www.php.net/manual/en/function.readlink.php
     */
    public function readLink(PathInterface $path): PathInterface;

    /**
     * Recursively deletes a file or directory at the given path.
     *
     * @param \PhpTuf\ComposerStager\API\Path\Value\PathInterface $path
     *   A path to delete.
     * @param \PhpTuf\ComposerStager\API\Process\Service\OutputCallbackInterface|null $callback
     *   An optional PHP callback to run whenever there is process output.
     * @param int<0, max> $timeout
     *   An optional process timeout (maximum runtime) in seconds. If set to
     *   zero (0), no time limit is imposed.
     *
     * @throws \PhpTuf\ComposerStager\API\Exception\IOException
     *   If the path cannot be deleted.
     *
     * @see https://www.php.net/manual/en/function.rmdir.php
     * @see https://www.php.net/manual/en/function.unlink.php
     */
    public function rm(
        PathInterface $path,
        ?OutputCallbackInterface $callback = null,
        int $timeout = ProcessInterface::DEFAULT_TIMEOUT,
    ): void;

    /**
     * Sets the access and modification time of a file at the given path.
     *
     * Attempts to set the access and modification times of the file to the
     * value given in mtime. Note that the access time is always modified,
     * regardless of the number of parameters.
     *
     * If the file does not exist, it will be created.
     *
     * @param \PhpTuf\ComposerStager\API\Path\Value\PathInterface $path
     *   The path to touch.
     * @param int|null $mtime
     *   The touch time. If `mtime` is `null`, the current system `time()` is used.
     * @param int|null $atime
     *   If not `null`, the access time of the given path is set to the value
     *   of `atime`. Otherwise, it is set to the value passed to the `mtime`
     *   parameter. If both are `null`, the current system time is used.
     *
     * @throws \PhpTuf\ComposerStager\API\Exception\IOException
     *   If the operation fails.
     * @throws \PhpTuf\ComposerStager\API\Exception\LogicException
     *   If a directory already exists at the given path.
     *
     * @see https://www.php.net/manual/en/function.touch.php
     */
    public function touch(PathInterface $path, ?int $mtime = null, ?int $atime = null): void;
}

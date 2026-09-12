<?php declare(strict_types=1);

namespace PhpTuf\ComposerStager\API\Precondition\Service;

/**
 * Asserts that there are no hard links in the codebase.
 *
 * Note: the target of a hard link is effectively a link just as much as the source, because it, too, represents one of
 * multiple links to the same inode. Therefore, it too must be excluded from API operations if it is not to fail
 * this preconditions.
 *
 * This includes both the active and staging directories.
 *
 * It doesn't matter whether the given directories actually exist. In order to isolate failures and avoid redundancy,
 * that question is left to its own preconditions. Except in the event of an IO error (which will throw an exception
 * according to the relevant interface), this one cares about literally nothing else if it doesn't actually find a
 * hard link.
 *
 * This interface exists solely to facilitate autowiring dependencies through type hinting.
 *
 * @see https://symfony.com/doc/current/service_container/autowiring.html
 *
 * @package Precondition
 *
 * @api This interface is subject to our backward compatibility promise and may be safely depended upon.
 */
interface NoHardLinksExistInterface extends PreconditionInterface
{
}

<?php declare(strict_types=1);

namespace PhpTuf\ComposerStager\Internal\Precondition\Service;

use PhpTuf\ComposerStager\API\Environment\Service\EnvironmentInterface;
use PhpTuf\ComposerStager\API\Precondition\Service\StagingDirExistsInterface;
use PhpTuf\ComposerStager\API\Precondition\Service\StagingDirIsReadyInterface;
use PhpTuf\ComposerStager\API\Precondition\Service\StagingDirIsWritableInterface;
use PhpTuf\ComposerStager\API\Translation\Factory\TranslatableFactoryInterface;
use PhpTuf\ComposerStager\API\Translation\Value\TranslatableInterface;

/**
 * @package Precondition
 *
 * @internal Don't depend directly on this class. It may be changed or removed at any time without notice.
 */
final class StagingDirIsReady extends AbstractPreconditionsTree implements StagingDirIsReadyInterface
{
    public function __construct(
        EnvironmentInterface $environment,
        TranslatableFactoryInterface $translatableFactory,
        StagingDirExistsInterface $stagingDirExists,
        StagingDirIsWritableInterface $stagingDirIsWritable,
    ) {
        parent::__construct($environment, $translatableFactory, $stagingDirExists, $stagingDirIsWritable);
    }

    public function getName(): TranslatableInterface
    {
        return $this->t('Staging directory is ready');
    }

    public function getDescription(): TranslatableInterface
    {
        return $this->t('The preconditions for using the staging directory.');
    }

    protected function getFulfilledStatusMessage(): TranslatableInterface
    {
        return $this->t('The staging directory is ready to use.');
    }
}

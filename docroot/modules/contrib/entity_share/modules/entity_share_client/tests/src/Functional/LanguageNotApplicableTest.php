<?php

declare(strict_types = 1);

namespace Drupal\Tests\entity_share_client\Functional;

use Drupal\Core\Language\LanguageInterface;

/**
 * General functional test class for language not applicable scenarios.
 *
 * @group entity_share
 * @group entity_share_client
 */
class LanguageNotApplicableTest extends LanguageNotSpecifiedTest {

  /**
   * {@inheritdoc}
   */
  protected $lockedLanguage = LanguageInterface::LANGCODE_NOT_APPLICABLE;

}

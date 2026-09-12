<?php

declare(strict_types=1);

namespace Drupal\diff_test\Plugin\diff\Layout;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\diff\Attribute\DiffLayoutBuilder;
use Drupal\diff\DiffLayoutBase;

/**
 * Test diff layout with a trivial build method.
 */
#[DiffLayoutBuilder(
  id: 'test',
  label: new TranslatableMarkup('Test layout'),
  description: new TranslatableMarkup('This is a test diff layout.'),
)]
class TestDiffLayout extends DiffLayoutBase {

  /**
   * {@inheritdoc}
   */
  public function build(ContentEntityInterface $left_revision, ContentEntityInterface $right_revision, ContentEntityInterface $entity): array {
    return [
      '#type' => 'markup',
      '#markup' => $this->t('This is a test diff layout.'),
    ];
  }

}

<?php

declare(strict_types=1);

namespace Drupal\diff\Form;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;

/**
 * Provides a form for the node revision overview page.
 */
class RevisionOverviewForm extends EntityRevisionOverviewForm {

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'revision_overview_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, NodeInterface|ContentEntityInterface|null $entity = NULL): array {
    $build = parent::buildForm($form, $form_state, $entity);
    $build['#attached']['library'][] = 'node/drupal.node.admin';
    $form_state->set('workspace_safe', TRUE);
    return $build;
  }

  /**
   * {@inheritdoc}
   */
  protected function buildRevertRevisionLink(ContentEntityInterface $revision, string|int $defaultRevisionId, string $langcode, bool $has_translations): ?array {
    $url = $has_translations ?
      Url::fromRoute('node.revision_revert_translation_confirm', [
        'node' => $revision->id(),
        'node_revision' => $revision->getRevisionId(),
        'langcode' => $langcode,
      ]) :
      Url::fromRoute('node.revision_revert_confirm', [
        'node' => $revision->id(),
        'node_revision' => $revision->getRevisionId(),
      ]);
    if (!$url->access()) {
      return NULL;
    }
    return [
      'title' => (int) $revision->getRevisionId() < (int) $defaultRevisionId ? $this->t('Revert') : $this->t('Set as current revision'),
      'url' => $url,
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function buildDeleteRevisionLink(ContentEntityInterface $revision, string $langcode): ?array {
    $url = Url::fromRoute('node.revision_delete_confirm', [
      'node' => $revision->id(),
      'node_revision' => $revision->getRevisionId(),
      'langcode' => $langcode,
    ]);
    if (!$url->access()) {
      return NULL;
    }
    return [
      'title' => $this->t('Delete'),
      'url' => $url,
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function buildRedirectUrl(string $entity_type, mixed $entity_id, mixed $revision_id_left, mixed $revision_id_right): Url {
    return Url::fromRoute(
      'diff.revisions_diff',
      [
        $entity_type => $entity_id,
        'left_revision' => $revision_id_left,
        'right_revision' => $revision_id_right,
        'filter' => $this->diffLayoutManager->getDefaultLayout(),
      ],
    );
  }

}

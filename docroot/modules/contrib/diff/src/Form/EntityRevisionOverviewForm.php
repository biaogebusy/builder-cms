<?php

declare(strict_types=1);

namespace Drupal\diff\Form;

use Drupal\Component\Utility\Xss;
use Drupal\content_moderation\ModerationInformationInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\DependencyInjection\AutowireTrait;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\RevisionableStorageInterface;
use Drupal\Core\Entity\RevisionLogInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Link;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\diff\DiffLayoutManager;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Provides a form for the entity revision overview page.
 */
class EntityRevisionOverviewForm extends FormBase {

  use AutowireTrait;

  /**
   * Constructs an EntityRevisionOverviewForm object.
   */
  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected AccountInterface $currentUser,
    protected DateFormatterInterface $dateFormatter,
    protected RendererInterface $renderer,
    protected LanguageManagerInterface $languageManager,
    #[Autowire('@plugin.manager.diff.layout')]
    protected DiffLayoutManager $diffLayoutManager,
    protected ?ModerationInformationInterface $moderationInformation = NULL,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'entity_revision_overview_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?ContentEntityInterface $entity = NULL): array {
    if ($entity === NULL) {
      throw new NotFoundHttpException();
    }
    $entity_type_id = $entity->getEntityTypeId();
    $entity_storage = $this->entityTypeManager->getStorage($entity_type_id);
    if (!$entity_storage instanceof RevisionableStorageInterface) {
      throw new NotFoundHttpException();
    }

    $langcode = $entity->language()->getId();
    $langname = $entity->language()->getName();
    $languages = $entity->getTranslationLanguages();
    $has_translations = (\count($languages) > 1);

    $vids = $this->getRevisionIds($entity, $entity_storage);
    $revision_count = \count($vids);

    $build = [];
    if ($has_translations) {
      $build['#title'] = $this->t('@langname revisions for %title', [
        '@langname' => $langname,
        '%title' => $entity->label(),
      ]);
    }
    else {
      $build['#title'] = $this->t('Revisions for %title', [
        '%title' => $entity->label(),
      ]);
    }

    $build['entity_id'] = [
      '#type' => 'hidden',
      '#value' => $entity->id(),
    ];
    $build['entity_type'] = [
      '#type' => 'hidden',
      '#value' => $entity_type_id,
    ];

    $table_header = [];
    $table_header['revision'] = $this->t('Revision information');

    // Allow comparisons only if there are 2 or more revisions.
    $table_caption = '';
    if ($revision_count > 1) {
      $table_caption = $this->t('Use the radio buttons in the table below to select two revisions to compare. Then click the "Compare selected revisions" button to generate the comparison.');
      $table_header += [
        'select_column_one' => $this->t('Source revision'),
        'select_column_two' => $this->t('Target revision'),
      ];
    }
    $table_header['operations'] = $this->t('Operations');

    // Submit button for the form.
    $compare_revision_submit = [
      '#type' => 'submit',
      '#button_type' => 'primary',
      '#value' => $this->t('Compare selected revisions'),
      '#attributes' => ['class' => ['diff-button']],
    ];

    // For more than 5 revisions, add a submit button on top of the screen.
    if ($revision_count > 5) {
      $build['submit_top'] = $compare_revision_submit;
    }

    // Add the table listing the revisions.
    $revisions_table_key = $entity_type_id . '_revisions_table';
    $build[$revisions_table_key] = [
      '#type' => 'table',
      '#caption' => $table_caption,
      '#header' => $table_header,
      '#attributes' => ['class' => ['diff-revisions']],
    ];
    $build[$revisions_table_key]['#attached']['library'][] = 'diff/diff.general';
    $build[$revisions_table_key]['#attached']['drupalSettings']['diffRevisionRadios'] = $this->getDiffConfig()->get('general_settings.radio_behavior');

    $defaultRevisionId = $entity->getRevisionId();
    $current_revision_displayed = FALSE;
    /** @var \Drupal\Core\Entity\ContentEntityInterface[] $revisions */
    $revisions = $entity_storage->loadMultipleRevisions($vids);
    foreach ($vids as $vid) {
      $revision = $revisions[$vid];

      $revision_user = NULL;
      $revision_date = $this->t('Unknown');

      if ($revision instanceof RevisionLogInterface) {
        $revision_user = $revision->getRevisionUser();
        $creation_time = $revision->getRevisionCreationTime();
        if ($creation_time !== NULL) {
          $revision_date = $this->dateFormatter->format($creation_time, 'short');
        }
        else {
          $revision_date = $this->t('Date unavailable');
        }
      }

      $username = [
        '#theme' => 'username',
        '#account' => $revision_user,
      ];

      $is_current_revision = $revision->isDefaultRevision() || (!$current_revision_displayed && $revision->wasDefaultRevision());
      $link = $entity->toLink($revision_date);
      if ($is_current_revision) {
        $current_revision_displayed = TRUE;
      }
      elseif ($entity->hasLinkTemplate('revision')) {
        $link = Link::createFromRoute($revision_date, 'entity.' . $entity_type_id . '.revision', [$entity_type_id => $entity->id(), "{$entity_type_id}_revision" => $vid]);
      }

      $row = [
        'revision' => $this->buildRevision($link, $username, $revision),
      ];
      $is_default_vid = $vid == $defaultRevisionId;
      if ($revision_count > 1) {
        $row['select_column_one'] = $this->buildSelectColumn('radios_left', $vid, $is_default_vid ? FALSE : $vids[1]);
        $row['select_column_two'] = $this->buildSelectColumn('radios_right', $vid, $is_default_vid ? $vid : FALSE);
      }
      if ($is_default_vid) {
        $row['operations'] = [
          '#prefix' => '<em>',
          '#markup' => $this->t('Current revision'),
          '#suffix' => '</em>',
          '#attributes' => [
            'class' => ['revision-current'],
          ],
        ];
        $row['#attributes'] = [
          'class' => ['revision-current'],
        ];
      }
      else {
        $row['operations'] = [
          '#type' => 'operations',
          '#links' => $this->getOperationLinks($revision, $defaultRevisionId, $langcode, $has_translations),
        ];
      }
      $build[$revisions_table_key][] = $row;
    }

    // Allow comparisons only if there are 2 or more revisions.
    if ($revision_count > 1) {
      $build['submit'] = $compare_revision_submit;
    }
    $build['pager'] = [
      '#type' => 'pager',
    ];

    return $build;
  }

  /**
   * Get operations for an entity revision.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $revision
   *   The revision to generate operations for.
   * @param string|int $defaultRevisionId
   *   The revision id of the default revision.
   * @param string $langcode
   *   The language of the revision.
   * @param bool $has_translations
   *   Does the revision have translations?
   *
   * @return array
   *   An array of operation links.
   */
  protected function getOperationLinks(ContentEntityInterface $revision, string|int $defaultRevisionId, string $langcode, bool $has_translations): array {
    // Removes links which are inaccessible or not rendered.
    return \array_filter([
      $this->buildRevertRevisionLink($revision, $defaultRevisionId, $langcode, $has_translations),
      $this->buildDeleteRevisionLink($revision, $langcode),
    ]);
  }

  /**
   * Builds a link to revert an entity revision.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $revision
   *   The revision to generate a link for.
   * @param string|int $defaultRevisionId
   *   The revision id of the default revision.
   * @param string $langcode
   *   The language of the revision.
   * @param bool $has_translations
   *   Does the revision have translations?
   *
   * @return array|NULL
   *   A link to revert a revision, or NULL if the user does not have access.
   */
  protected function buildRevertRevisionLink(ContentEntityInterface $revision, string|int $defaultRevisionId, string $langcode, bool $has_translations): ?array {
    if (!$revision->hasLinkTemplate('revision-revert-form')) {
      return NULL;
    }
    $url = $revision->toUrl('revision-revert-form');
    if (!$url->access()) {
      return NULL;
    }
    return [
      'title' => (int) $revision->getRevisionId() < (int) $defaultRevisionId ? $this->t('Revert') : $this->t('Set as current revision'),
      'url' => $url,
    ];
  }

  /**
   * Builds a link to delete an entity revision.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $revision
   *   The revision to generate a link for.
   * @param string $langcode
   *   The language of the revision.
   *
   * @return array|null
   *   A link render array, or NULL if the user does not have access.
   */
  protected function buildDeleteRevisionLink(ContentEntityInterface $revision, string $langcode): ?array {
    if (!$revision->hasLinkTemplate('revision-delete-form')) {
      return NULL;
    }
    $url = $revision->toUrl('revision-delete-form');
    if (!$url->access()) {
      return NULL;
    }
    return [
      'title' => $this->t('Delete'),
      'url' => $url,
    ];
  }

  /**
   * Returns diff module configs object.
   */
  protected function getDiffConfig(): ImmutableConfig {
    return $this->config('diff.settings');
  }

  /**
   * Gets a list of entity revision IDs for a specific entity.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity.
   * @param \Drupal\Core\Entity\RevisionableStorageInterface $entity_storage
   *   The entity storage handler.
   *
   * @return array
   *   The entity revision IDs.
   */
  protected function getRevisionIds(ContentEntityInterface $entity, RevisionableStorageInterface $entity_storage): array {
    $entityType = $entity->getEntityType();
    $translatable = $entityType->isTranslatable();
    $query = $entity_storage->getQuery()
      // Access to the content has already been verified. Disable query-level
      // access checking so that revisions for unpublished content still
      // appear.
      ->accessCheck(FALSE)
      ->allRevisions()
      ->condition($entityType->getKey('id'), $entity->id())
      ->sort($entityType->getKey('revision'), 'DESC')
      ->pager($this->getDiffConfig()->get('general_settings.revision_pager_limit'));
    // Only show revisions that are affected by the language that is being
    // displayed.
    if ($translatable) {
      $query->condition($entityType->getKey('langcode'), $entity->language()->getId())
        ->condition($entityType->getKey('revision_translation_affected'), '1');
    }
    $result = $query->execute();
    return \array_keys($result);
  }

  /**
   * Set and return configuration for revision.
   *
   * @param \Drupal\Core\Link $link
   *   Link attribute.
   * @param array $username
   *   Username render array.
   * @param \Drupal\Core\Entity\ContentEntityInterface $revision
   *   Revision parameter for getRevisionDescription function.
   *
   * @return array
   *   Configuration for revision.
   */
  protected function buildRevision(Link $link, $username, ContentEntityInterface $revision): array {
    return [
      '#type' => 'inline_template',
      '#template' => '{% trans %}{{ date }} by {{ username }}{% endtrans %}{% if message %}<p class="revision-log">{{ message }}</p>{% endif %}',
      '#context' => [
        'date' => $link->toString(),
        'username' => $this->renderer->renderInIsolation($username),
        'message' => [
          '#markup' => $this->getRevisionDescription($revision),
          '#allowed_tags' => Xss::getAdminTagList(),
        ],
      ],
    ];
  }

  /**
   * Gets the revision description of the revision.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $revision
   *   The current revision.
   *
   * @return string
   *   The revision log message.
   */
  protected function getRevisionDescription(ContentEntityInterface $revision): string {
    $revision_summary = '';
    // Check if the revision has a revision log message.
    if ($revision instanceof RevisionLogInterface) {
      $revision_log_message = $revision->getRevisionLogMessage();
      if ($revision_log_message !== NULL) {
        $revision_summary = Xss::filter($revision_log_message);
      }
    }

    // Add workflow/content moderation state information.
    if ($state = $this->getModerationState($revision)) {
      $revision_summary .= " ($state)";
    }

    return $revision_summary;
  }

  /**
   * Gets the revision's content moderation state, if available.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity revision.
   *
   * @return string|false
   *   Returns the label of the moderation state, if available, otherwise FALSE.
   */
  protected function getModerationState(ContentEntityInterface $entity): string|bool {
    if ($this->moderationInformation !== NULL && $this->moderationInformation->isModeratedEntity($entity)) {
      // @phpstan-ignore-next-line
      if ($state = $entity->get('moderation_state')->value) {
        $workflow = $this->moderationInformation->getWorkflowForEntity($entity);
        return $workflow->getTypePlugin()->getState($state)->label();
      }
    }

    return FALSE;
  }

  /**
   * Set column attributes and return radio form array.
   */
  protected function buildSelectColumn(string $name, string|int $return_val, string|bool|int $default_val): array {
    $title = $name === 'radios_left' ?
      $this->t('Source revision: @vid', ['@vid' => $return_val]) :
      $this->t('Target revision: @vid', ['@vid' => $return_val]);
    return [
      '#type' => 'radio',
      '#title' => $title,
      '#title_display' => 'invisible',
      '#name' => $name,
      '#return_value' => $return_val,
      '#default_value' => $default_val,
    ];
  }

  /**
   * Builds the redirect Url for comparing two revisions.
   *
   * @param string $entity_type
   *   The entity type.
   * @param mixed $entity_id
   *   The entity ID.
   * @param mixed $revision_id_left
   *   The ID of the left revision.
   * @param mixed $revision_id_right
   *   The ID of the right revision.
   *
   * @return \Drupal\Core\Url
   *   The redirect Url.
   */
  protected function buildRedirectUrl(string $entity_type, mixed $entity_id, mixed $revision_id_left, mixed $revision_id_right): Url {
    return Url::fromRoute(
      'entity.' . $entity_type . '.revisions_diff',
      [
        $entity_type => $entity_id,
        'left_revision' => $revision_id_left,
        'right_revision' => $revision_id_right,
        'filter' => $this->diffLayoutManager->getDefaultLayout(),
      ],
    );
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    $input = $form_state->getUserInput();

    $revisions_table_key = $form_state->getValue('entity_type') . '_revisions_table';
    $revisions = $form_state->getValue($revisions_table_key);
    if (!\is_countable($revisions) || \count($revisions) <= 1) {
      $form_state->setErrorByName($revisions_table_key, $this->t('Multiple revisions are needed for comparison.'));
      return;
    }
    if (!isset($input['radios_left']) || !isset($input['radios_right'])) {
      $form_state->setErrorByName($revisions_table_key, $this->t('Select two revisions to compare.'));
      return;
    }
    if ($input['radios_left'] === $input['radios_right']) {
      // @todo Radio-boxes selection resets if there are errors.
      $form_state->setErrorByName($revisions_table_key, $this->t('Select different revisions to compare.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $input = $form_state->getUserInput();
    $revision_id_left = $input['radios_left'];
    $revision_id_right = $input['radios_right'];
    $entity_id = $input['entity_id'];
    $entity_type = $input['entity_type'];

    // Always place the older revision on the left side of the comparison
    // and the newer revision on the right side (however revisions can be
    // compared both ways if we manually change the order of the parameters).
    if ($revision_id_left > $revision_id_right) {
      $aux = $revision_id_left;
      $revision_id_left = $revision_id_right;
      $revision_id_right = $aux;
    }
    // Builds the redirect Url.
    $redirect_url = $this->buildRedirectUrl($entity_type, $entity_id, $revision_id_left, $revision_id_right);
    $form_state->setRedirectUrl($redirect_url);
  }

}

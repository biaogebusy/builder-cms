<?php

declare(strict_types=1);

namespace Drupal\diff;

use Drupal\Component\Plugin\PluginBase;
use Drupal\Component\Utility\Xss;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\RevisionLogInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\GeneratedLink;
use Drupal\Core\Link;
use Drupal\Core\Mail\MailFormatHelper;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\diff\Controller\PluginRevisionController;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base class for diff layout plugins.
 */
abstract class DiffLayoutBase extends PluginBase implements DiffLayoutInterface, ContainerFactoryPluginInterface {

  use StringTranslationTrait;

  /**
   * Constructs a DiffLayoutBase object.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected ConfigFactoryInterface $configFactory,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected DiffEntityParser $entityParser,
    protected DateFormatterInterface $date,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->configuration += $this->defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('config.factory'),
      $container->get('entity_type.manager'),
      $container->get('diff.entity_parser'),
      $container->get('date.formatter'),
    );
  }

  /**
   * Build the revision link for a revision.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $revision
   *   A revision where to add a link.
   *
   * @return \Drupal\Core\GeneratedLink
   *   Header link for a revision in the table.
   */
  protected function buildRevisionLink(ContentEntityInterface $revision): GeneratedLink {
    if ($revision instanceof RevisionLogInterface) {
      $revision_date = $this->date->format($revision->getRevisionCreationTime(), 'short');
      return Link::fromTextAndUrl($revision_date, $revision->toUrl('revision'))->toString();
    }
    return Link::fromTextAndUrl($revision->label(), $revision->toUrl('revision'))->toString();
  }

  /**
   * Build the revision link for the compared revisions.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $left_revision
   *   Left revision that is compared.
   * @param \Drupal\Core\Entity\ContentEntityInterface $right_revision
   *   Right revision that is compared.
   *
   * @return array
   *   Header link for a revision in the revision comparison display.
   */
  public function buildRevisionsData(ContentEntityInterface $left_revision, ContentEntityInterface $right_revision): array {
    $right_revision = $this->buildRevisionData($right_revision);
    $right_revision['#prefix'] = '<div class="diff-revision__items-group">';
    $right_revision['#suffix'] = '</div>';

    $left_revision = $this->buildRevisionData($left_revision);
    $left_revision['#prefix'] = '<div class="diff-revision__items-group">';
    $left_revision['#suffix'] = '</div>';

    // Show the revisions that are compared.
    return [
      'header' => [
        'diff_revisions' => [
          '#type' => 'item',
          '#title' => $this->t('Comparing'),
          '#wrapper_attributes' => ['class' => 'diff-revision'],
          'items' => [
            '#prefix' => '<div class="diff-revision__items">',
            '#suffix' => '</div>',
            'right_revision' => $right_revision,
            'left_revision' => $left_revision,
          ],
        ],
      ],
    ];
  }

  /**
   * Build the revision link for a revision.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $revision
   *   Left revision that is compared.
   *
   * @return array
   *   Revision data about author, creation date and log.
   */
  protected function buildRevisionData(ContentEntityInterface $revision): array {
    if ($revision instanceof RevisionLogInterface) {
      $revision_log = $revision->getRevisionLogMessage();

      $revision_link['date'] = [
        '#type' => 'link',
        '#title' => $this->date->format($revision->getRevisionCreationTime(), 'short'),
        '#url' => $revision->toUrl('revision'),
        '#prefix' => '<div class="diff-revision__item diff-revision__item-date">',
        '#suffix' => '</div>',
      ];

      $revision_link['author'] = [
        '#theme' => 'username',
        '#account' => $revision->getRevisionUser(),
        '#prefix' => '<div class="diff-revision__item diff-revision__item-author">',
        '#suffix' => '</div>',
      ];

      if ($revision_log) {
        $revision_link['message'] = [
          '#type' => 'markup',
          '#prefix' => '<div class="diff-revision__item diff-revision__item-message">',
          '#suffix' => '</div>',
          '#markup' => Xss::filter($revision_log),
        ];
      }
    }
    else {
      $revision_link['label'] = [
        '#type' => 'link',
        '#title' => $revision->label(),
        '#url' => $revision->toUrl('revision'),
        '#prefix' => '<div class="diff-revision__item diff-revision__item-date">',
        '#suffix' => '</div>',
      ];
    }
    return $revision_link;
  }

  /**
   * Build the filter navigation for the diff comparison.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity.
   * @param \Drupal\Core\Entity\ContentEntityInterface $left_revision
   *   Revision from the left side.
   * @param \Drupal\Core\Entity\ContentEntityInterface $right_revision
   *   Revision from the right side.
   * @param string $layout
   *   The layout plugin selected.
   * @param string $active_filter
   *   The active filter.
   *
   * @return array
   *   The filter options.
   */
  protected function buildFilterNavigation(ContentEntityInterface $entity, ContentEntityInterface $left_revision, ContentEntityInterface $right_revision, $layout, $active_filter): array {
    // Build the view modes filter.
    $options['raw'] = [
      'title' => $this->t('Raw'),
      'url' => PluginRevisionController::diffRoute($entity,
        $left_revision->getRevisionId(),
        $right_revision->getRevisionId(),
        $layout,
        ['filter' => 'raw'],
      ),
    ];

    $options['strip_tags'] = [
      'title' => $this->t('Strip tags'),
      'url' => PluginRevisionController::diffRoute($entity,
         $left_revision->getRevisionId(),
         $right_revision->getRevisionId(),
         $layout,
         ['filter' => 'strip_tags'],
      ),
    ];

    $filter = $options[$active_filter];
    unset($options[$active_filter]);
    \array_unshift($options, $filter);

    $build['options'] = [
      '#type' => 'operations',
      '#links' => $options,
    ];
    return $build;
  }

  /**
   * Applies a markdown function to a string.
   *
   * @param string $markdown
   *   Key of the markdown function to be applied to the items.
   *   One of drupal_html_to_text, filter_xss, filter_xss_all.
   * @param string $items
   *   String to be processed.
   *
   * @return array|string
   *   Result after markdown was applied on $items.
   */
  protected function applyMarkdown($markdown, $items): array|string {
    if (!$markdown) {
      return $items;
    }

    if ($markdown == 'drupal_html_to_text') {
      return \trim(MailFormatHelper::htmlToText($items), "\n");
    }
    elseif ($markdown == 'filter_xss') {
      return \trim(Xss::filter($items), "\n");
    }
    elseif ($markdown == 'filter_xss_all') {
      return \trim(Xss::filter($items, []), "\n");
    }
    else {
      return $items;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getConfiguration() {
    return $this->configFactory->getEditable('diff.layout_plugins');
  }

  /**
   * {@inheritdoc}
   */
  public function setConfiguration(array $configuration) {
    $config = $this->configFactory->getEditable('diff.layout_plugins');
    $config->set($this->pluginId, $configuration);
    $config->save();
  }

}

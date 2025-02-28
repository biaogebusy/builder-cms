<?php

namespace Drupal\webform_views\Plugin\views\field;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Display rendered entity which a webform submission was submitted to.
 *
 * @ViewsField("webform_submission_submitted_to_rendered_entity")
 */
class WebformSubmissionSubmittedToRenderedEntity extends FieldPluginBase {

  use WebformSubmissionSubmittedToTrait;

  /**
   * Constructs a new WebformSubmissionSubmittedToRenderedEntity object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param array $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_manager
   *   The entity type manager.
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   The language manager.
   */
  public function __construct(array $configuration, $plugin_id, array $plugin_definition, EntityTypeManagerInterface $entity_manager, LanguageManagerInterface $language_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->entityManager = $entity_manager;
    $this->languageManager = $language_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('language_manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defineOptions() {
    $options = parent::defineOptions();
    // TODO: At the moment, I just cannot think of any reliable way to let users
    // choose any other view_mode but the default. The complexity here is that
    // we do not know the entity type ahead of time, as it's stored in DB and
    // varies from one webform submission to another.
    $options['view_mode'] = ['default' => 'default'];

    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function clickSortable() {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $values) {
    $build = [];

    $source_entity = $this->getSourceEntity($values);

    if (!$source_entity) {
      return $build;
    }

    $source_entity = $this->getEntityTranslation($source_entity, $values);
    $access = $source_entity->access('view', NULL, TRUE);
    $build['#access'] = $access;
    if ($access->isAllowed()) {
      $view_builder = $this->entityManager->getViewBuilder($this->getEntityTypeId());
      $build += $view_builder->view($source_entity, $this->options['view_mode']);

      $cache = CacheableMetadata::createFromObject($source_entity);
      $cache->applyTo($build);
    }

    return $build;
  }

}

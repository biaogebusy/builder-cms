<?php

namespace Drupal\webform_views\Plugin\views\field;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Display entity label which a webform submission was submitted to.
 *
 * @ViewsField("webform_submission_submitted_to_label")
 */
class WebformSubmissionSubmittedToLabel extends FieldPluginBase {

  use WebformSubmissionSubmittedToTrait;

  /**
   * Constructs a new WebformSubmissionSubmittedToLabel object.
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

    $options['link'] = ['default' => TRUE];

    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);

    $form['link'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Link to the source entity'),
      '#description' => $this->t('Whether to output this field as a link to the source entity.'),
      '#default_value' => $this->options['link'],
    ];
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

    if (isset($source_entity)) {
      $access = $source_entity->access('view', NULL, TRUE);
      $build['#access'] = $access;
      if ($access->isAllowed()) {
        if ($this->options['link']) {
          $build['entity_label'] = $source_entity->toLink()->toRenderable();
        }
        else {
          $build['entity_label'] = [
            '#plain_text' => $source_entity->label(),
          ];
        }
        $cache = CacheableMetadata::createFromObject($source_entity);
        $cache->applyTo($build);
      }
    }
    return $build;
  }

}

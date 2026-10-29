<?php

namespace Drupal\xinshi_views\Plugin\views\style;

use Drupal\Component\Render\MarkupInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\EntityPublishedInterface;
use Drupal\content_moderation\ModerationInformationInterface;
use Drupal\rest\Plugin\views\row\DataFieldRow;
use Drupal\rest\Plugin\views\style\Serializer;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * The style plugin for serialized output formats.
 *
 * @ingroup views_style_plugins
 *
 * @ViewsStyle(
 *   id = "xinshi_pager_serializer",
 *   title = @Translation("Pager serializer"),
 *   help = @Translation("Serializes views row data and pager using the Serializer component."),
 *   display_types = {"data"}
 * )
 */
class PagerSerializer extends Serializer {

  /** The optional moderation service for workflow state labels. */
  protected ?ModerationInformationInterface $moderation = NULL;

  /** {@inheritdoc} */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    if ($container->has('content_moderation.moderation_information')) {
      $instance->moderation = $container->get('content_moderation.moderation_information');
    }
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function render() {
    $rows = [];
    // If the Data Entity row plugin is used, this will be an array of entities
    // which will pass through Serializer to one of the registered Normalizers,
    // which will transform it to arrays/scalars. If the Data field row plugin
    // is used, $rows will not contain objects and will pass directly to the
    // Encoder.
    foreach ($this->view->result as $row_index => $row) {
      $this->view->row_index = $row_index;
      $data = $this->view->rowPlugin->render($row);
      $entity = $row->_entity ?? NULL;
      if (is_array($data) && $entity instanceof EntityPublishedInterface
        && $entity->getEntityTypeId() === 'node' && $entity->bundle() === 'landing_page') {
        $langcode = $data['langcode'] ?? NULL;
        // Publication follows the raw language code, not a translated display label.
        if ($this->view->rowPlugin instanceof DataFieldRow) {
          foreach ($this->view->field as $id => $field) {
            if ($this->view->rowPlugin->getFieldKeyAlias($id) === 'langcode') {
              $langcode = $field->getValue($row);
              break;
            }
          }
        }
        // Views rendered fields contain markup objects; entity language keys require strings.
        if ($langcode instanceof MarkupInterface) {
          $langcode = (string) $langcode;
        }
        if (is_string($langcode) && $langcode !== '' && $entity->hasTranslation($langcode)) {
          $entity = $entity->getTranslation($langcode);
        }
        $entity_access = $entity->access('view', NULL, TRUE);
        $access = $entity_access
          ->andIf($entity->get('status')->access('view', NULL, TRUE));
        $metadata = CacheableMetadata::createFromRenderArray($this->view->element)
          ->addCacheableDependency($entity)->addCacheableDependency($access);
        if ($access->isAllowed()) {
          $data['published'] = $entity->isPublished();
        }
        if ($this->moderation?->isModeratedEntity($entity)) {
          $workflow = $this->moderation->getWorkflowForEntity($entity);
          $items = $entity->get('moderation_state');
          $state_access = $entity_access->andIf($items->access('view', NULL, TRUE));
          $metadata->addCacheableDependency($workflow)->addCacheableDependency($state_access)
            ->addCacheContexts(['languages:language_interface']);
          $state_id = $items->value;
          if ($state_access->isAllowed() && is_string($state_id) && $workflow->getTypePlugin()->hasState($state_id)) {
            $data['moderation_state'] = $state_id;
            $data['moderation_state_label'] = (string) $workflow->getTypePlugin()->getState($state_id)->label();
          }
        }
        $metadata->applyTo($this->view->element);
      }
      $rows[] = $data;
    }
    unset($this->view->row_index);

    // Get the content type configured in the display or fallback to the
    // default.
    if ((empty($this->view->live_preview))) {
      $content_type = $this->displayHandler->getContentType();
    } else {
      $content_type = !empty($this->options['formats']) ? reset($this->options['formats']) : 'json';
    }

    $pager = $this->view->pager;

    $class = $pager ? get_class($pager) : FALSE;
    $current_page = $pager ? $pager->getCurrentPage() : 0;
    $items_per_page = $pager ? $pager->getItemsPerPage() : 0;
    $total_items = $pager ? $pager->getTotalItems() : 0;
    $total_pages = 0;
    if ($class && !in_array($class, ['Drupal\views\Plugin\views\pager\None', 'Drupal\views\Plugin\views\pager\Some'])) {
      $total_pages = $pager ? $pager->getPagerTotal() : 0;
    }

    $result = [
      'rows' => $rows,
      'pager' => [
        'current_page' => $current_page,
        'total_items' => intval($total_items),
        'total_pages' => $total_pages,
        'items_per_page' => $items_per_page,
      ],
    ];
    return $this->serializer->serialize($result, $content_type, ['views_style_plugin' => $this]);
  }

}

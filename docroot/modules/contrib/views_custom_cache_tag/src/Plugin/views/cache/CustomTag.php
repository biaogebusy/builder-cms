<?php

/**
 * @file
 * Contains \Drupal\views_custom_cache_tag\Plugin\views\cache\CustomTag.
 */

namespace Drupal\views_custom_cache_tag\Plugin\views\cache;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerTrait;
use Drupal\views\Plugin\views\cache\Tag;

/**
 * Simple caching of query results for Views displays.
 *
 * @ingroup views_cache_plugins
 *
 * @ViewsCache(
 *   id = "custom_tag",
 *   title = @Translation("Custom Tag based"),
 *   help = @Translation("Tag based caching of data. Caches will persist until any related cache tags are invalidated.")
 * )
 */
class CustomTag extends Tag {

  use MessengerTrait;

  /**
   * Overrides Drupal\views\Plugin\Plugin::$usesOptions.
   */
  protected $usesOptions = TRUE;

  /**
   * {@inheritdoc}
   */
  public function summaryTitle() {
    return $this->t('Custom Tag');
  }

  /**
   * {@inheritdoc}
   */
  public function defineOptions() {
    $options = parent::defineOptions();
    $options['custom_tag'] = array('default' => '');
    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);

    $form['custom_tag'] = array(
      '#type' => 'textarea',
      '#title' => $this->t('Custom tag list'),
      '#description' => $this->t('Custom tag list, separated by new lines. Caching based on custom cache tag must be manually cleared using custom code. You can use Twig (to explode a multi-value contextual filter into multiple tags for example) as long as the result delivers each tag in a separate line.'),
      '#default_value' => $this->options['custom_tag'],
    );

    // Setup the tokens for fields.
    $optgroup_arguments = (string) t('Arguments');

    foreach ($this->view->display_handler->getHandlers('argument') as $arg => $handler) {
      $options[$optgroup_arguments]["{{ arguments.$arg }}"] = $this->t('@argument title', array('@argument' => $handler->adminLabel()));
      $options[$optgroup_arguments]["{{ raw_arguments.$arg }}"] = $this->t('@argument input', array('@argument' => $handler->adminLabel()));
    }

    // We have some options, so make a list.
    if (!empty($options)) {
      $output['description'] = [
        '#markup' => '<p>' . $this->t("The following replacement tokens are available for this field. Note that due to rendering order, you cannot use fields that come after this field; if you need a field not listed here, rearrange your fields.") . '</p>',
      ];
      foreach (array_keys($options) as $type) {
        if (!empty($options[$type])) {
          $items = array();
          foreach ($options[$type] as $key => $value) {
            $items[] = $key . ' == ' . $value;
          }
          $item_list = array(
            '#theme' => 'item_list',
            '#items' => $items,
          );
          $output['list'] = $item_list;
        }
      }
      $form['tokens'] = $output;
    }


  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags() {
    $tags = parent::getCacheTags();

    // Remove the the list cache tags for the entity types listed in this view.
    // @see CachePluginBase::getCacheTags().
    $entity_information = $this->view->getQuery()->getEntityTableInfo();
    if (!empty($entity_information)) {
      // Add the list cache tags for each entity type used by this view.
      foreach ($entity_information as $table => $metadata) {
        $remove = \Drupal::entityTypeManager()->getDefinition($metadata['entity_type'])->getListCacheTags();
        $tags = array_diff($tags, $remove);
      }
    }

    $custom_tags = $this->view->getStyle()->tokenizeValue($this->options['custom_tag'], 0);
    $custom_tags = preg_split('/\r\n|[\r\n]/', $custom_tags);
    $custom_tags = array_map('trim', $custom_tags);
    $custom_tags = array_filter($custom_tags);
    return Cache::mergeTags($custom_tags, $tags);
  }

  /**
   * {@inheritdoc}
   */
  public function cacheExpire($type) {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function cacheGet($type) {
    $result = parent::cacheGet($type);

    // This can be used to debug/test the views cache result.
    if ($type == 'results' && !$result && \Drupal::state()->get('views_custom_cache_tag.execute_debug', FALSE)) {
      $this->messenger()->addMessage('Executing view ' . $this->view->storage->id() . ':' . $this->view->current_display . ':' . implode(',', $this->view->args) . ' (' . implode(',', $this->view->getCacheTags()) . ')');
    }
    return $result;
  }

}

<?php

namespace Drupal\rest_views_search_api\Plugin\views\field;

use Drupal\Core\Form\FormStateInterface;
use Drupal\rest_views\SerializedData;
use Drupal\search_api\Plugin\views\field\SearchApiEntityField;

/**
 * Display Search API Content Datasource field data in a serialized display.
 *
 * @ViewsField("search_api_field_export")
 *
 * @see \Drupal\rest_views\EntityFieldExport.
 */
class SearchApiEntityFieldExport extends SearchApiEntityField {

  /**
   * {@inheritdoc}
   *
   * @throws \Exception
   */
  public function renderItems($items): SerializedData {
    if (!empty($items)) {
      $items = $this->prepareItemsByDelta($items);

      // Render only those items that are renderable arrays.
      foreach ($items as $i => $item) {
        if (is_array($item)) {
          $items[$i] = $this->renderer->render($item);
        }
      }
      $data = $this->multiple ? $items : reset($items);
    }
    else {
      // Render an empty field as an empty array or a null value.
      $data = $this->multiple ? [] : NULL;
    }

    // Wrap the output in a data object to protect it from rendering.
    return SerializedData::create($data);
  }

  /**
   * {@inheritdoc}
   */
  public function render_item($count, $item) {
    $rendered = $item['rendered'];
    if (isset($rendered['#type']) && $rendered['#type'] === 'data') {
      return $rendered['#data'];
    }
    return parent::render_item($count, $item);
  }

  /**
   * {@inheritdoc}
   */
  public function renderText($alter) {
    if (isset($this->last_render) && $this->last_render instanceof SerializedData) {
      return $this->last_render;
    }
    return parent::renderText($alter);
  }

  /**
   * {@inheritdoc}
   */
  public function multiple_options_form(&$form, FormStateInterface $form_state): void {
    // Initialize removed settings to avoid notices. Unset them afterward.
    $this->options['multi_type'] = $this->options['separator'] = NULL;
    parent::multiple_options_form($form, $form_state);

    // The export field does not concatenate items.
    unset($form['multi_type'], $form['separator'], $this->options['multi_type'], $this->options['separator']);
  }

  /**
   * {@inheritdoc}
   */
  public function defineOptions(): array {
    $options = parent::defineOptions();
    unset($options['multi_type'], $options['separator']);
    return $options;
  }

}

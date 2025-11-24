<?php

namespace Drupal\rest_views\Plugin\views\field;

use Drupal\Component\Render\MarkupInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\rest_views\SerializedData;
use Drupal\views\Plugin\views\field\EntityField;

/**
 * Display entity field data in a serialized display.
 *
 * @ViewsField("field_export")
 */
class EntityFieldExport extends EntityField {

  /**
   * {@inheritdoc}
   *
   * @throws \Exception
   */
  public function renderItems($items) {
    if (!empty($items)) {
      $items = $this->prepareItemsByDelta($items);

      // Render only those items that are renderable arrays.
      foreach ($items as $i => $item) {
        if (is_array($items[$i])) {
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
  // phpcs:ignore
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
  public function renderText($alter): MarkupInterface|string|SerializedData|null {
    if (isset($this->last_render) && $this->last_render instanceof SerializedData) {
      return $this->last_render;
    }
    return parent::renderText($alter);
  }

  /**
   * {@inheritdoc}
   */
  // phpcs:ignore
  public function multiple_options_form(&$form, FormStateInterface $formState): void {
    // Initialize removed settings to avoid notices. Unset them afterward.
    $this->options['multi_type'] = $this->options['separator'] = NULL;
    parent::multiple_options_form($form, $formState);

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

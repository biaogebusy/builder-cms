<?php

namespace Drupal\workspace\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\Plugin\Field\FieldWidget\OptionsSelectWidget;
use Drupal\Core\Form\FormStateInterface;

/**
 * Plugin implementation of the 'options_filters_select' widget.
 *
 * @FieldWidget(
 *   id = "options_filters_select",
 *   label = @Translation("Filters select list"),
 *   field_types = {
 *     "entity_reference",
 *   },
 *   multiple_values = TRUE
 * )
 */
class OptionsFiltersWidget extends OptionsSelectWidget {

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $field_name = $items->getName();
    if (in_array($field_name, ['pull_replication_settings', 'push_replication_settings'])) {
      $base_class = get_parent_class(get_parent_class($this));
      $element = $base_class::formElement($items, $delta, $element, $form, $form_state);
      $current_user = \Drupal::currentUser();
      if ($current_user->hasPermission('access replication settings fields')) {
        return parent::formElement($items, $delta, $element, $form, $form_state);
      };

      $definition = $items->getEntity()->get($field_name);
      $replication_settings_id = $definition->target_id;
      $settings = $definition->getSettings();
      $target_type = $settings['target_type'];
      if ($replication_settings_id && $target_type && $replication_settings = \Drupal::entityTypeManager()->getStorage($target_type)->load($replication_settings_id)) {
        $element += [
          '#type' => 'item',
          '#markup' => '<div class="color-warning">' . $this->t('Note: you do not have permission to modify this. Current value: ') . '<strong>' . $replication_settings->label() . '</strong></div>',
        ];
      }
      elseif (empty($replication_settings_id)) {
        $element += [
          '#type' => 'item',
          '#markup' => '<div class="color-warning">' . $this->t('Note: you do not have permission to modify this. Current value: ') . '<strong>' . $this->t('No any values set for this field') . '</strong></div>',
        ];
      }
      else {
        $element += [];
      }
    }

    return parent::formElement($items, $delta, $element, $form, $form_state);
  }

}

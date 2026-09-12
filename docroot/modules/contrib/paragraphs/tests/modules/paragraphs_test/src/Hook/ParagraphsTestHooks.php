<?php

namespace Drupal\paragraphs_test\Hook;

use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\paragraphs\ParagraphInterface;
use Drupal\paragraphs\Plugin\Field\FieldWidget\ParagraphsWidget;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Hook implementations for paragraphs_test.
 */
class ParagraphsTestHooks {
  use StringTranslationTrait;

  /**
   * Implements hook_paragraphs_widget_actions_alter().
   */
  #[Hook('paragraphs_widget_actions_alter')]
  public function paragraphsWidgetActionsAlter(&$widget_actions, &$context) {
    if (!$context['allow_reference_changes']) {
      return;
    }
    if (\Drupal::state()->get('paragraphs_test_dropbutton')) {
      $widget_actions['dropdown_actions']['test_button'] = ParagraphsWidget::expandButton([
        '#type' => 'submit',
        '#value' => $this->t('Add to library'),
        '#delta' => 0,
        '#name' => 'field_paragraphs_test',
        '#weight' => 504,
        '#paragraphs_mode' => 'remove',
      ]);
    }
  }

  /**
   * Implements hook_ENTITY_TYPE_view().
   */
  #[Hook('paragraph_view')]
  public static function paragraphView(array &$build, ParagraphInterface $entity, EntityViewDisplayInterface $display, $view_mode) {
    if (!\Drupal::state('paragraphs_test_parent')) {
      return;
    }
    $parent_type = $entity->get('parent_type')->value;
    $parent_id = $entity->get('parent_id')->value;
    $parent_field_name = $entity->get('parent_field_name')->value;
    \Drupal::messenger()->addStatus("Parent: {$parent_type}/{$parent_id}/{$parent_field_name}", TRUE);
  }

  /**
   * Implements hook_field_widget_single_element_WIDGET_TYPE_form_alter().
   */
  #[Hook('field_widget_single_element_entity_reference_paragraphs_form_alter')]
  public static function fieldWidgetSingleElementEntityReferenceParagraphsFormAlter(&$element, &$form_state, $context) {
    if ($element['#paragraph_type'] == 'altered_paragraph') {
      $element['subform']['field_text']['widget'][0]['#title'] = 'Altered title';
    }
  }

}

<?php

namespace Drupal\field_widget_actions_test\Plugin\FieldWidgetAction;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\field_widget_actions\Attribute\FieldWidgetAction;
use Drupal\field_widget_actions\FieldWidgetActionBase;

/**
 * The 'Suggest texts for field' action.
 */
#[FieldWidgetAction(
  id: 'suggest_texts_for_textfield',
  label: new TranslatableMarkup('Suggest texts for field'),
  widget_types: [
    'string_textfield',
    'string_textarea',
    'text_textfield',
    'text_textarea',
    'text_textarea_with_summary',
  ],
  field_types: [
    'string',
    'string_long',
    'text',
    'text_long',
    'text_with_summary',
  ],
  category: new TranslatableMarkup('Test Actions'),
)]
class SuggestTextsTestAction extends FieldWidgetActionBase implements ContainerFactoryPluginInterface {

  /**
   * {@inheritdoc}
   */
  public function getAjaxCallback(): ?string {
    return 'generateSuggestions';
  }

  /**
   * AJAX callback to return suggestions.
   */
  public function generateSuggestions(array &$form, FormStateInterface $form_state): AjaxResponse {
    $selector = $this->getSuggestionsTarget($form, $form_state);
    $suggestions = [
      'Banana',
      'Apple',
      'Kiwi',
    ];
    return $this->returnSuggestions($suggestions, $selector);
  }

}

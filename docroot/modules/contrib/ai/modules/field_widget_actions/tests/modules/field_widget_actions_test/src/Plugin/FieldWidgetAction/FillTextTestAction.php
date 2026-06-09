<?php

namespace Drupal\field_widget_actions_test\Plugin\FieldWidgetAction;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\field_widget_actions\Ajax\FillEditorCommand;
use Drupal\field_widget_actions\Ajax\FillSimpleFieldCommand;
use Drupal\field_widget_actions\Attribute\FieldWidgetAction;
use Drupal\field_widget_actions\FieldWidgetFormActionBase;

/**
 * The 'Fill plain text field' action.
 */
#[FieldWidgetAction(
  id: 'fill_textfield',
  label: new TranslatableMarkup('Fill text field'),
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
class FillTextTestAction extends FieldWidgetFormActionBase implements ContainerFactoryPluginInterface {

  /**
   * {@inheritdoc}
   */
  public function buildModalForm(array $form, FormStateInterface $form_state, ContentEntityInterface|NULL $entity): array {
    $form['new_text'] = [
      '#type' => 'textfield',
      '#title' => $this->t('New text'),
      '#size' => 20,
      '#default_value' => 'New text ' . rand(0, 99),
      '#required' => TRUE,
    ];
    $form['count'] = [
      '#type' => 'number',
      '#title' => $this->t('How many times to repeat the text'),
      '#size' => 20,
      '#default_value' => 3,
      '#required' => TRUE,
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  protected function submitModalFormFillFields(array $form, FormStateInterface $form_state, AjaxResponse $response): AjaxResponse {
    $context_data = $form_state->get('field_widget_action_context_data');
    $target_element = $context_data['target_element'];
    $selector = '[name="' . $target_element['#name'] . '"]';
    $value = str_repeat($form_state->getValue('new_text'), $form_state->getValue('count'));
    if (!empty($target_element['#format'])) {
      $response->addCommand(new FillEditorCommand($selector, $value));
    }
    else {
      $response->addCommand(new FillSimpleFieldCommand($selector, $value));
    }
    return $response;
  }

}

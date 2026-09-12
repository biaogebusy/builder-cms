<?php

namespace Drupal\webform_cards\Hook;

use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\webform\Utility\WebformArrayHelper;
use Drupal\webform\WebformInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Hook\Attribute\Hook;

/**
 * Hook implementations for webform_cards.
 */
class WebformCardsHooks {
  use StringTranslationTrait;

  /**
   * Implements hook_entity_base_field_info().
   */
  #[Hook('entity_base_field_info')]
  public function entityBaseFieldInfo(EntityTypeInterface $entity_type) {
    if ($entity_type->id() === 'webform_submission') {
      $fields = [];
      $fields['current_card'] = BaseFieldDefinition::create('string')->setLabel(t('Current card'))->setDescription(t('The current card.'))->setSetting('max_length', 128);
      return $fields;
    }
  }

  /* ************************************************************************** */
  // Menu hook.
  /* ************************************************************************** */

  /**
   * Implements hook_menu_local_actions_alter().
   */
  #[Hook('menu_local_actions_alter')]
  public function menuLocalActionsAlter(&$local_actions) {
    if (!\Drupal::moduleHandler()->moduleExists('webform_ui')) {
      unset($local_actions['entity.webform_ui.element.card']);
    }
  }

  /* ************************************************************************** */
  // Form alter hooks.
  /* ************************************************************************** */

  /**
   * Implements hook_webform_submission_form_alter().
   */
  #[Hook('webform_submission_form_alter')]
  public function webformSubmissionFormAlter(array &$form, FormStateInterface $form_state, $form_id) {
    /** @var \Drupal\webform\WebformSubmissionForm $form_object */
    $form_object = $form_state->getFormObject();
    /** @var \Drupal\webform\WebformSubmissionInterface $webform_submission */
    $webform_submission = $form_object->getEntity();
    /** @var \Drupal\webform\WebformInterface $webform */
    $webform = $webform_submission->getWebform();
    /** @var \Drupal\webform_cards\WebformCardsManagerInterface $webform_cards_manager */
    $webform_cards_manager = \Drupal::service('webform_cards.manager');
    // Check if the webform has cards.
    $has_cards = $webform_cards_manager->hasCards($webform);
    if (!$has_cards) {
      return;
    }
    // Check if operation is edit all.
    if ($form_object->getOperation() === 'edit_all') {
      return;
    }
    // Display quick form submit when testing webform cards.
    if ($form_object->getOperation() === 'test' && \Drupal::request()->getMethod() === 'GET') {
      $form_id = Html::getId($form_object->getFormId());
      $build = [
        '#type' => 'link',
        '#url' => Url::fromRoute('<none>', [], [
          'fragment' => $form_id,
        ]),
        '#title' => $this->t('Submit %title form', [
          '%title' => $webform->label(),
        ]),
        '#attributes' => [
          'class' => [
            'js-webform-card-test-submit-form',
          ],
        ],
      ];
      \Drupal::messenger()->addWarning(\Drupal::service('renderer')->render($build));
      $form['#attached']['library'][] = 'webform_cards/webform_cards.test';
    }
    // Add cards JavaScript.
    $form['#attached']['library'][] = 'webform_cards/webform_cards';
    // Track the current card which is used when saving and loading drafts.
    $current_card = $webform_submission->current_card->value ?: '';
    $form['current_card'] = ['#type' => 'hidden', '#default_value' => $current_card];
    // Add .webform-cards class to form with 'webform_card' elements.
    $form['#attributes']['class'][] = 'webform-cards';
    // Remove .js-webform-disable-autosubmit class when auto-forward is enabled.
    if ($webform->getSetting('wizard_auto_forward', TRUE)) {
      WebformArrayHelper::removeValue($form['#attributes']['class'], 'js-webform-disable-autosubmit');
    }
    // Track the current page.
    $current_page = $form_state->get('current_page');
    $form['#attributes']['data-current-page'] = $current_page;
    // Add settings as data-* attributes.
    $setting_names = [
          // Update wizard/cards progress bar's pages based on conditions.
      'wizard_progress_states',
          // Link to previous pages in progress bar.
      'wizard_progress_link',
          // Link to previous pages in preview.
      'wizard_preview_link',
          // Include confirmation page in progress.
      'wizard_confirmation',
          // Update wizard/cards progress bar's pages based on conditions.
      'wizard_progress_states',
          // Link to previous pages in progress bar.
      'wizard_progress_link',
          // Auto forward to next page when the page is completed.
      'wizard_auto_forward',
          // Hide the next button when auto-forwarding.
      'wizard_auto_forward_hide_next_button',
          // Navigate between cards using left or right arrow keys.
      'wizard_keyboard',
          // Link to previous pages in preview.
      'wizard_preview_link',
          // Include confirmation page in progress.
      'wizard_confirmation',
          // Track wizard/cards progress in the URL.
      'wizard_track',
          // Display show/hide all wizard/cards pages link.
      'wizard_toggle',
          // Wizard/cards show all elements label.
      'wizard_toggle_show_label',
          // Wizard/cards show all elements label.
      'wizard_toggle_hide_label',
          // Ajax effect.
      'ajax_effect',
          // Ajax speed.
      'ajax_speed',
          // Ajax scroll top.
      'ajax_scroll_top',
    ];
    foreach ($setting_names as $setting_name) {
      if ($value = $webform->getSetting($setting_name, TRUE)) {
        $attribute_name = str_replace('wizard_', '', $setting_name);
        $attribute_name = 'data-' . str_replace('_', '-', $attribute_name);
        $form['#attributes'][$attribute_name] = $value;
      }
    }
    // Add progress bar.
    if ($current_page !== WebformInterface::PAGE_CONFIRMATION) {
      $pages = $webform_cards_manager->buildPages($webform);
      if (!in_array($current_page, [
        WebformInterface::PAGE_PREVIEW,
        WebformInterface::PAGE_CONFIRMATION,
      ])) {
        $current_page = $current_card ?: key($pages);
      }
      $form['progress'] = [
        '#theme' => 'webform_progress',
        '#webform' => $webform,
        '#webform_submission' => $webform_submission,
        '#pages' => $pages,
        '#current_page' => $current_page,
        '#operation' => $form_object->getOperation(),
        '#weight' => -20,
      ];
    }
    // Don't alter the preview page but apply conditional logic the pages..
    if ($current_page === WebformInterface::PAGE_PREVIEW) {
      // Unset JavaScript behaviors for webform wizard pages.
      // @see Drupal.behaviors.webformWizardPagesLink
      // @see \Drupal\webform\WebformSubmissionForm::pagesElement
      if (NestedArray::keyExists($form, ['pages', '#attached', 'library'])) {
        WebformArrayHelper::removeValue($form['pages']['#attached']['library'], 'webform/webform.wizard.pages');
      }
      $form['progress']['#pages'] = $webform_cards_manager->applyConditions($pages, $webform_submission);
      return;
    }
    // Add previous and next buttons to form actions.
    $form['actions']['cards_prev'] = [
      '#type' => 'submit',
      '#value' => $webform->getSetting('wizard_prev_button_label', TRUE),
      '#attributes' => [
        'class' => [
          'webform-button--previous',
          'webform-cards-button--previous',
        ],
      ],
      '#weight' => 0,
          // Cards and previews previous button labels can have the same value.
          // Issue #1342066 Document that buttons with the same #value need a unique
          // #name for the Form API to distinguish them, or change the Form API to
          // assign unique #names automatically.
      '#name' => $webform->id() . '_card_previous_button',
    ];
    $form['actions']['cards_next'] = [
      '#type' => 'submit',
      '#value' => $webform->getSetting('wizard_next_button_label', TRUE),
      '#attributes' => [
        'class' => [
          'webform-button--next',
          'webform-cards-button--next',
        ],
      ],
      '#weight' => 1,
    ];
    // Add 'data-webform-unsaved-ignore' attribute to forms with unsaved
    // data warning.
    // @see webform.form.unsaved.js
    if ($webform->getSetting('form_unsaved', TRUE)) {
      $form['actions']['cards_prev']['#attributes']['data-webform-unsaved-ignore'] = TRUE;
      $form['actions']['cards_next']['#attributes']['data-webform-unsaved-ignore'] = TRUE;
    }
    // Process the submitted values before they are stored.
    $form['#entity_builders'][] = 'webform_cards_webform_submission_builder';
  }

  /* ************************************************************************** */
  // Theming.
  /* ************************************************************************** */

  /**
   * Implements hook_theme().
   */
  #[Hook('theme')]
  public function theme() {
    $info = ['webform_card' => ['render element' => 'element']];
    return $info;
  }

}

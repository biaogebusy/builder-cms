<?php

namespace Drupal\webform\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\webform\Utility\WebformElementHelper;

/**
 * Hook implementations for webform.
 */
class WebformThemeHooks {
  /* ************************************************************************** */
  // Theme hooks.
  /* ************************************************************************** */

  /**
   * Implements hook_theme().
   */
  #[Hook('theme')]
  public function theme() {
    $info = [
      'webform_help' => [
        'variables' => [
          'info' => [],
        ],
      ],
      'webform_help_video_youtube' => [
        'variables' => [
          'youtube_id' => NULL,
          'autoplay' => TRUE,
        ],
      ],
      'webform_help_support' => [
        'variables' => [
          'account' => [],
          'membership' => [],
          'contribution' => [],
        ],
      ],
      'webform' => [
        'render element' => 'element',
      ],
      'webform_actions' => [
        'render element' => 'element',
      ],
      'webform_access_denied' => [
        'variables' => [
          'message' => '',
          'attributes' => [],
          'webform' => NULL,
        ],
      ],
      'webform_handler_action_summary' => [
        'variables' => [
          'settings' => NULL,
          'handler' => NULL,
        ],
      ],
      'webform_handler_debug_summary' => [
        'variables' => [
          'settings' => NULL,
          'handler' => NULL,
        ],
      ],
      'webform_handler_email_summary' => [
        'variables' => [
          'settings' => NULL,
          'handler' => NULL,
        ],
      ],
      'webform_handler_remote_post_summary' => [
        'variables' => [
          'settings' => NULL,
          'handler' => NULL,
        ],
      ],
      'webform_handler_settings_summary' => [
        'variables' => [
          'settings' => NULL,
          'handler' => NULL,
        ],
      ],
      'webform_variant_override_summary' => [
        'variables' => [
          'settings' => NULL,
          'variant' => NULL,
        ],
      ],
      'webform_confirmation' => [
        'variables' => [
          'webform' => NULL,
          'source_entity' => NULL,
          'webform_submission' => NULL,
        ],
      ],
      'webform_required' => [
        'variables' => [
          'label' => NULL,
        ],
      ],
      'webform_submission' => [
        'render element' => 'elements',
      ],
      'webform_submission_form' => [
        'render element' => 'form',
      ],
      'webform_submission_navigation' => [
        'variables' => [
          'webform_submission' => NULL,
        ],
      ],
      'webform_submission_information' => [
        'variables' => [
          'webform_submission' => NULL,
          'source_entity' => NULL,
          'open' => TRUE,
        ],
      ],
      'webform_submission_data' => [
        'render element' => 'elements',
      ],
      'webform_element_base_html' => [
        'variables' => [
          'element' => [],
          'value' => NULL,
          'webform_submission' => NULL,
          'options' => [],
        ],
      ],
      'webform_element_base_text' => [
        'variables' => [
          'element' => [],
          'value' => NULL,
          'webform_submission' => NULL,
          'options' => [],
        ],
      ],
      'webform_container_base_html' => [
        'variables' => [
          'element' => [],
          'value' => NULL,
          'webform_submission' => NULL,
          'options' => [],
        ],
      ],
      'webform_container_base_text' => [
        'variables' => [
          'element' => [],
          'value' => NULL,
          'webform_submission' => NULL,
          'options' => [],
        ],
      ],
      'webform_element_help' => [
        'variables' => [
          'help' => NULL,
          'help_title' => '',
          'attributes' => [],
        ],
      ],
      'webform_element_more' => [
        'variables' => [
          'more' => NULL,
          'more_title' => '',
          'attributes' => [],
        ],
      ],
      'webform_element_managed_file' => [
        'variables' => [
          'element' => [],
          'value' => NULL,
          'webform_submission' => NULL,
          'options' => [],
          'file' => NULL,
        ],
      ],
      'webform_element_audio_file' => [
        'variables' => [
          'element' => [],
          'value' => NULL,
          'webform_submission' => NULL,
          'options' => [],
          'file' => NULL,
        ],
      ],
      'webform_element_document_file' => [
        'variables' => [
          'element' => [],
          'value' => NULL,
          'webform_submission' => NULL,
          'options' => [],
          'file' => NULL,
        ],
      ],
      'webform_element_image_file' => [
        'variables' => [
          'element' => [],
          'value' => NULL,
          'webform_submission' => NULL,
          'options' => [],
          'file' => NULL,
          'style_name' => NULL,
          'format' => NULL,
        ],
      ],
      'webform_element_video_file' => [
        'variables' => [
          'element' => [],
          'value' => NULL,
          'webform_submission' => NULL,
          'options' => [],
          'file' => NULL,
        ],
      ],
      'webform_email_html' => [
        'variables' => [
          'subject' => '',
          'body' => '',
          'webform_submission' => NULL,
          'handler' => NULL,
        ],
      ],
      'webform_email_message_html' => [
        'variables' => [
          'message' => '',
          'webform_submission' => NULL,
          'handler' => NULL,
        ],
      ],
      'webform_email_message_text' => [
        'variables' => [
          'message' => '',
          'webform_submission' => NULL,
          'handler' => NULL,
        ],
      ],
      'webform_html_editor_markup' => [
        'variables' => [
          'markup' => NULL,
          'allowed_tags' => [],
        ],
      ],
      'webform_horizontal_rule' => [
        'render element' => 'element',
      ],
      'webform_message' => [
        'render element' => 'element',
      ],
      'webform_section' => [
        'render element' => 'element',
      ],
      'webform_composite_address' => [
        'render element' => 'element',
      ],
      'webform_composite_contact' => [
        'render element' => 'element',
      ],
      'webform_composite_location' => [
        'render element' => 'element',
      ],
      'webform_composite_link' => [
        'render element' => 'element',
      ],
      'webform_composite_name' => [
        'render element' => 'element',
      ],
      'webform_composite_telephone' => [
        'render element' => 'element',
      ],
      'webform_codemirror' => [
        'variables' => [
          'code' => NULL,
          'type' => 'text',
        ],
      ],
      'webform_progress' => [
        'variables' => [
          'webform' => NULL,
          'webform_submission' => NULL,
          'current_page' => NULL,
          'operation' => NULL,
          'pages' => [],
        ],
      ],
      'webform_progress_bar' => [
        'variables' => [
          'webform' => NULL,
          'webform_submission' => NULL,
          'current_page' => NULL,
          'operation' => NULL,
          'max_pages' => 10,
          'pages' => [],
        ],
      ],
      'webform_progress_tracker' => [
        'variables' => [
          'webform' => NULL,
          'webform_submission' => NULL,
          'current_page' => NULL,
          'operation' => NULL,
          'max_pages' => 10,
          'pages' => [],
        ],
      ],
    ];
    // Since any rendering of a webform is going to require 'webform.theme.inc'
    // we are going to just add it to every template.
    foreach ($info as &$template) {
      $template['file'] = 'includes/webform.theme.template.inc';
    }
    return $info;
  }

  /**
   * Implements hook_theme_registry_alter().
   */
  #[Hook('theme_registry_alter')]
  public function themeRegistryAlter(&$theme_registry) {
    // Allow attributes to be defined for status messages so that #states
    // can be added to messages.
    // @see \Drupal\webform\Element\WebformMessage
    if (!isset($theme_registry['status_messages']['variables']['attributes'])) {
      $theme_registry['status_messages']['variables']['attributes'] = [];
    }
  }

  /* ************************************************************************** */
  // Webform theme suggestions.
  // Generate using _webform_devel_hook_theme_suggestions_generate();
  /* ************************************************************************** */

  /**
   * Implements hook_theme_suggestions_HOOK().
   */
  #[Hook('theme_suggestions_webform')]
  public function themeSuggestionsWebform(array $variables) {
    return _webform_theme_suggestions($variables, 'webform');
  }

  /**
   * Implements hook_theme_suggestions_HOOK().
   */
  #[Hook('theme_suggestions_webform_confirmation')]
  public function themeSuggestionsWebformConfirmation(array $variables) {
    return _webform_theme_suggestions($variables, 'webform_confirmation');
  }

  /**
   * Implements hook_theme_suggestions_HOOK().
   */
  #[Hook('theme_suggestions_webform_preview')]
  public function themeSuggestionsWebformPreview(array $variables) {
    return _webform_theme_suggestions($variables, 'webform_preview');
  }

  /**
   * Implements hook_theme_suggestions_HOOK().
   */
  #[Hook('theme_suggestions_webform_submission')]
  public function themeSuggestionsWebformSubmission(array $variables) {
    return _webform_theme_suggestions($variables, 'webform_submission');
  }

  /**
   * Implements hook_theme_suggestions_HOOK().
   */
  #[Hook('theme_suggestions_webform_submission_form')]
  public function themeSuggestionsWebformSubmissionForm(array $variables) {
    return _webform_theme_suggestions($variables, 'webform_submission_form');
  }

  /**
   * Implements hook_theme_suggestions_HOOK().
   */
  #[Hook('theme_suggestions_webform_submission_navigation')]
  public function themeSuggestionsWebformSubmissionNavigation(array $variables) {
    return _webform_theme_suggestions($variables, 'webform_submission_navigation');
  }

  /**
   * Implements hook_theme_suggestions_HOOK().
   */
  #[Hook('theme_suggestions_webform_submission_information')]
  public function themeSuggestionsWebformSubmissionInformation(array $variables) {
    return _webform_theme_suggestions($variables, 'webform_submission_information');
  }

  /**
   * Implements hook_theme_suggestions_HOOK().
   */
  #[Hook('theme_suggestions_webform_submission_data')]
  public function themeSuggestionsWebformSubmissionData(array $variables) {
    return _webform_theme_suggestions($variables, 'webform_submission_data');
  }

  /**
   * Implements hook_theme_suggestions_HOOK().
   */
  #[Hook('theme_suggestions_webform_element_base_html')]
  public function themeSuggestionsWebformElementBaseHtml(array $variables) {
    return _webform_theme_suggestions($variables, 'webform_element_base_html');
  }

  /**
   * Implements hook_theme_suggestions_HOOK().
   */
  #[Hook('theme_suggestions_webform_element_base_text')]
  public function themeSuggestionsWebformElementBaseText(array $variables) {
    return _webform_theme_suggestions($variables, 'webform_element_base_text');
  }

  /**
   * Implements hook_theme_suggestions_HOOK().
   */
  #[Hook('theme_suggestions_webform_container_base_html')]
  public function themeSuggestionsWebformContainerBaseHtml(array $variables) {
    return _webform_theme_suggestions($variables, 'webform_container_base_html');
  }

  /**
   * Implements hook_theme_suggestions_HOOK().
   */
  #[Hook('theme_suggestions_webform_container_base_text')]
  public function themeSuggestionsWebformContainerBaseText(array $variables) {
    return _webform_theme_suggestions($variables, 'webform_container_base_text');
  }

  /**
   * Implements hook_theme_suggestions_HOOK().
   */
  #[Hook('theme_suggestions_webform_email_html')]
  public function themeSuggestionsWebformEmailHtml(array $variables) {
    return _webform_theme_suggestions($variables, 'webform_email_html');
  }

  /**
   * Implements hook_theme_suggestions_HOOK().
   */
  #[Hook('theme_suggestions_webform_email_message_html')]
  public function themeSuggestionsWebformEmailMessageHtml(array $variables) {
    return _webform_theme_suggestions($variables, 'webform_email_message_html');
  }

  /**
   * Implements hook_theme_suggestions_HOOK().
   */
  #[Hook('theme_suggestions_webform_email_message_text')]
  public function themeSuggestionsWebformEmailMessageText(array $variables) {
    return _webform_theme_suggestions($variables, 'webform_email_message_text');
  }

  /**
   * Implements hook_theme_suggestions_HOOK().
   */
  #[Hook('theme_suggestions_webform_progress')]
  public function themeSuggestionsWebformProgress(array $variables) {
    return _webform_theme_suggestions($variables, 'webform_progress');
  }

  /**
   * Implements hook_theme_suggestions_HOOK().
   */
  #[Hook('theme_suggestions_webform_progress_bar')]
  public function themeSuggestionsWebformProgressBar(array $variables) {
    return _webform_theme_suggestions($variables, 'webform_progress_bar');
  }

  /**
   * Implements hook_theme_suggestions_HOOK().
   */
  #[Hook('theme_suggestions_webform_progress_tracker')]
  public function themeSuggestionsWebformProgressTracker(array $variables) {
    return _webform_theme_suggestions($variables, 'webform_progress_tracker');
  }

  /**
   * Implements hook_theme_suggestions_HOOK().
   */
  #[Hook('theme_suggestions_radios')]
  public function themeSuggestionsRadios(array $variables) {
    return _webform_theme_suggestions_options($variables, 'radios');
  }

  /**
   * Implements hook_theme_suggestions_HOOK().
   */
  #[Hook('theme_suggestions_checkboxes')]
  public function themeSuggestionsCheckboxes(array $variables) {
    return _webform_theme_suggestions_options($variables, 'checkboxes');
  }

  /**
   * Implements hook_theme_suggestions_HOOK().
   */
  #[Hook('theme_suggestions_form_element')]
  public function themeSuggestionsFormElement(array $variables) {
    if (!WebformElementHelper::isWebformElement($variables['element'])) {
      return [];
    }
    $element = $variables['element'];
    $suggestions = [];
    // Add webform type suggestion.
    if (isset($element['#type'])) {
      $suggestions[] = 'form_element__webform_' . $element['#type'];
    }
    // Add radio/checkbox displayed as a button suggestion.
    // @sse webform_process_options()
    if (!empty($element['#option_display']) && $element['#option_display'] === 'button') {
      $suggestions[] = 'form_element__webform_' . $element['#type'] . '_' . $element['#option_display'];
    }
    return $suggestions;
  }

}

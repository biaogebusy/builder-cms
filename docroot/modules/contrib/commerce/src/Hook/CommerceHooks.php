<?php

namespace Drupal\commerce\Hook;

use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Field\FieldTypePluginManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Render\Element;
use Drupal\Core\Render\RendererInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireServiceClosure;

/**
 * Hook implementations for Commerce.
 */
class CommerceHooks {

  /**
   * Constructs a new CommerceHooks object.
   *
   * @param \Drupal\Core\Field\FieldTypePluginManagerInterface $fieldTypeManager
   *   The field type plugin manager.
   * @param \Closure $inboxMessageFetcher
   *   Closure that returns the inbox message fetcher.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $moduleHandler
   *   The module handler.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer.
   */
  public function __construct(
    protected readonly FieldTypePluginManagerInterface $fieldTypeManager,
    #[AutowireServiceClosure('commerce.inbox_message_fetcher')]
    protected \Closure $inboxMessageFetcher,
    protected readonly ModuleHandlerInterface $moduleHandler,
    protected readonly RendererInterface $renderer,
  ) {
  }

  /**
   * Implements hook_cron().
   */
  #[Hook('cron')]
  public function cron(): void {
    ($this->inboxMessageFetcher)()->fetch();
  }

  /**
   * Implements hook_mail().
   */
  #[Hook('mail')]
  public function mail($key, &$message, $params): void {
    if (isset($params['headers'])) {
      $message['headers'] = array_merge($message['headers'], $params['headers']);
    }
    if (!empty($params['from'])) {
      $message['from'] = $params['from'];
    }
    $message['subject'] = $params['subject'];

    $message['body'][] = $this->renderer->renderInIsolation($params['body']);
  }

  /**
   * Implements hook_page_attachments_alter().
   */
  #[Hook('page_attachments_alter')]
  public function pageAttachmentsAlter(array &$attachments): void {
    if (isset($attachments['#attached']['html_head'])) {
      foreach ($attachments['#attached']['html_head'] as &$parts) {
        if (!isset($parts[1]) || $parts[1] !== 'system_meta_generator') {
          continue;
        }
        $parts[0]['#attributes']['content'] .= '; Commerce 3';
      }
    }
  }

  /**
   * Implements hook_field_widget_info_alter().
   *
   * Exposes the commerce_plugin_item widgets for each of the field type's
   * derivatives, since core does not do it automatically.
   */
  #[Hook('field_widget_info_alter')]
  public function fieldWidgetInfoAlter(array &$info): void {
    foreach (['commerce_plugin_select', 'commerce_plugin_radios'] as $widget) {
      if (isset($info[$widget])) {
        foreach ($this->fieldTypeManager->getDefinitions() as $key => $definition) {
          if ($definition['id'] == 'commerce_plugin_item') {
            $info[$widget]['field_types'][] = $key;
          }
        }
      }
    }
  }

  /**
   * Implements hook_field_formatter_info_alter().
   *
   * Exposes the commerce_plugin_item_default formatter for each of the field
   * type's derivatives, since core does not do it automatically.
   */
  #[Hook('field_formatter_info_alter')]
  public function fieldFormatterInfoAlter(array &$info): void {
    if (isset($info['commerce_plugin_item_default'])) {
      foreach ($this->fieldTypeManager->getDefinitions() as $key => $definition) {
        if ($definition['id'] == 'commerce_plugin_item') {
          $info['commerce_plugin_item_default']['field_types'][] = $key;
        }
      }
    }
  }

  /**
   * Implements hook_field_widget_single_element_form_alter().
   *
   * Base fields have a description that's used for two very different
   * purposes:
   * - To describe the field in the Views UI and other parts of the system.
   * - As user-facing help text shown on field widgets.
   * The text is rarely suitable for both, and in most cases feels redundant
   * as user-facing help text. Hence we remove it from that context, but only
   * if
   * the definition didn't specify otherwise via our display_description
   * setting.
   */
  #[Hook('field_widget_single_element_form_alter')]
  public function fieldWidgetSingleElementFormAlter(array &$element, FormStateInterface $form_state, array $context): void {
    $field_definition = $context['items']->getFieldDefinition();
    if (!($field_definition instanceof BaseFieldDefinition)) {
      // Not a base field.
      return;
    }
    if (!str_starts_with($field_definition->getTargetEntityTypeId() ?? '', 'commerce_')) {
      // Not a Commerce entity type.
      return;
    }
    if ($field_definition->getSetting('display_description')) {
      // The definition requested that the description stays untouched.
      return;
    }

    $element['#description'] = '';
    // Many widgets are nested one level deeper.
    $children = Element::getVisibleChildren($element);
    if (count($children) == 1) {
      $child = reset($children);
      $element[$child]['#description'] = '';
    }
  }

  /**
   * Implements hook_form_alter().
   */
  #[Hook('form_alter')]
  public function formAlter(array &$form, FormStateInterface $form_state, $form_id): void {
    if ($form_state->get('has_commerce_inline_forms')) {
      $this->alterInlineForms($form, $form_state, $form);
    }
  }

  /**
   * Invokes inline form alter hooks for the given element's inline forms.
   *
   * @param array $element
   *   The form element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   * @param array $complete_form
   *   The complete form structure.
   */
  public function alterInlineForms(array &$element, FormStateInterface $form_state, array &$complete_form): void {
    foreach (Element::children($element) as $key) {
      if (isset($element[$key]['#inline_form'])) {
        $inline_form = &$element[$key];
        /** @var \Drupal\commerce\Plugin\Commerce\InlineForm\InlineFormInterface $plugin */
        $plugin = $inline_form['#inline_form'];
        // Invoke hook_commerce_inline_form_alter() and
        // hook_commerce_inline_form_PLUGIN_ID_alter() implementations.
        $hooks = [
          'commerce_inline_form',
          'commerce_inline_form_' . $plugin->getPluginId(),
        ];
        $this->moduleHandler->alter($hooks, $inline_form, $form_state, $complete_form);
      }

      $this->alterInlineForms($element[$key], $form_state, $complete_form);
    }
  }

}

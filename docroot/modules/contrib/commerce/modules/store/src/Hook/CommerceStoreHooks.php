<?php

namespace Drupal\commerce_store\Hook;

use Drupal\commerce_store\CurrentStoreInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Hook implementations for Commerce Store.
 */
class CommerceStoreHooks {

  use StringTranslationTrait;

  /**
   * Constructs a new CommerceStoreHooks object.
   *
   * @param \Drupal\commerce_store\CurrentStoreInterface $currentStore
   *   The current store.
   */
  public function __construct(
    protected readonly CurrentStoreInterface $currentStore,
  ) {

  }

  /**
   * Implements hook_mail_alter().
   *
   * Sets the default "from" address to the current store email.
   */
  #[Hook('mail_alter')]
  public function mailAlter(&$message): void {
    if (str_starts_with($message['id'], 'commerce_') && empty($message['params']['from'])) {
      $current_store = $this->currentStore->getStore();
      if ($current_store) {
        $message['from'] = $current_store->getEmailFromHeader();
      }
    }
  }

  /**
   * Implements hook_field_widget_single_element_form_alter().
   *
   * Sets the default "from" address to the current store email.
   */
  #[Hook('field_widget_single_element_form_alter')]
  public function fieldWidgetSingleElementFormAlter(&$element, $form_state, $context): void {
    /** @var \Drupal\Core\Field\BaseFieldDefinition $field_definition */
    $field_definition = $context['items']->getFieldDefinition();
    $field_name = $field_definition->getName();
    $entity_type = $field_definition->getTargetEntityTypeId();
    $widget_name = $context['widget']->getPluginId();
    if ($field_name == 'billing_countries' && $entity_type == 'commerce_store' && $widget_name == 'options_select') {
      $element['#options']['_none'] = $this->t('- All countries -');
      $element['#size'] = 5;
    }
    elseif ($field_name == 'path' && $entity_type == 'commerce_store' && $widget_name == 'path') {
      $element['alias']['#description'] = $this->t('The alternative URL for this store. Use a relative path. For example, "/my-store".');
    }
  }

  /**
   * Implements hook_field_group_content_element_keys_alter().
   *
   * Allow stores to render fields groups defined from Fields UI.
   */
  #[Hook('field_group_content_element_keys_alter')]
  public function fieldGroupContentElementKeysAlter(&$keys): void {
    $keys['commerce_store'] = 'store';
  }

  /**
   * Implements hook_gin_content_form_routes().
   */
  #[Hook('gin_content_form_routes')]
  public function ginContentFormRoutes(): array {
    return [
      'entity.commerce_store.edit_form',
      'entity.commerce_store.add_form',
    ];
  }

}

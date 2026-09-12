<?php

namespace Drupal\ai_ckeditor\Hook;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\KeyValueStore\KeyValueExpirableFactoryInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Contains ai_ckeditor module hooks implementations.
 */
class AiCKEditorHooks {

  use StringTranslationTrait;

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected AccountProxyInterface $accountProxy,
    protected KeyValueExpirableFactoryInterface $keyValueExpirableFactory,
  ) {
  }

  /**
   * Implements hook_form_FORM_ID_alter() for system_modules_uninstall_confirm_form.
   */
  #[Hook('form_system_modules_uninstall_confirm_form_alter')]
  public function formSystemModulesUninstallConfirmFormAlter(array &$form, FormStateInterface $form_state, string $form_id): void {
    $modules = $this->keyValueExpirableFactory->get('modules_uninstall')->get($this->accountProxy->id()) ?? [];
    if (!in_array('ai_ckeditor', $modules)) {
      return;
    }

    $toolbar_items = ['aickeditor', 'ai_balloon_menu'];
    /** @var \Drupal\editor\EditorInterface[] $editors */
    $editors = $this->entityTypeManager->getStorage('editor')->loadMultiple();
    $affected = [];
    foreach ($editors as $editor) {
      if ($editor->getEditor() !== 'ckeditor5') {
        continue;
      }
      $settings = $editor->getSettings();
      $items = $settings['toolbar']['items'] ?? [];
      if (!empty($items) && array_intersect($items, $toolbar_items)) {
        $affected[] = $editor->label();
      }
    }

    if (!empty($affected)) {
      $element = [
        '#type' => 'details',
        '#title' => $this->t('Editor configurations that will be updated'),
        '#open' => TRUE,
        'description' => [
          '#markup' => '<p>' . $this->t('The following editor configurations contain AI CKEditor toolbar items that will be removed:') . '</p>',
        ],
        'list' => [
          '#theme' => 'item_list',
          '#items' => $affected,
        ],
      ];

      // Insert before the 'description' element so all information appears
      // before the "Would you like to continue?" prompt.
      $pos = array_search('description', array_keys($form));
      if ($pos !== FALSE) {
        $form = array_merge(
          array_slice($form, 0, $pos, TRUE),
          ['ai_ckeditor_affected_editors' => $element],
          array_slice($form, $pos, NULL, TRUE)
        );
      }
      else {
        $form['ai_ckeditor_affected_editors'] = $element;
      }
    }
  }

}

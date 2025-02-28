<?php

namespace Drupal\xinshi_editor\Form;

use Drupal\panels_ipe\Form\PanelsIPEBlockContentForm as IPEPanelsIPEBlockContentForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;

/**
 * {@inheritdoc}
 */
class PanelsIPEBlockContentForm extends IPEPanelsIPEBlockContentForm {

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    // $form_state->disableCache();
    if ($this->entity->isNew()) {
      $this->entity->set('info', $this->entity->type->entity->label());
    }
    if($this->entity->hasField('uid')) {
      $this->entity->set('uid', $this->currentUser()->id());
      $form['uid']['#access'] = FALSE;
    }
    $form = parent::form($form, $form_state);

    unset($form['revision_information']['#group']);

    if (!$this->entity->isNew() && $this->entity->hasField('shared_type')) {
      $form['shared_type']['#access'] = FALSE;
    }
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);

    $storage = $form_state->getStorage();

    if (!empty($storage['panels_storage_type'])) {
      $panels_storage_type = $storage['panels_storage_type'];
      $panels_storage_id = $storage['panels_storage_id'];
      $block_uuid = $storage['block_uuid'];
      $panels_display = \Drupal::service('panels.storage_manager')->load($panels_storage_type, $panels_storage_id);
      if ($block_uuid) {
        try {
          $wrapper_block = $panels_display->getBlock($block_uuid);
        }
        catch (\Exception $e) {
          $wrapper_block = NULL;
        }
      }
      else {
        $wrapper_block = NULL;
      }
      if ($wrapper_block) {
        $wrapper_config = $wrapper_block->getConfiguration();
        if ($panels_display->getStorageType() == 'panelizer_field') {
          $wrapper_config['vid'] = $this->entity->getRevisionId();
          $panels_display->updateBlock($wrapper_config['uuid'], $wrapper_config);
          $tempstore = \Drupal::service('tempstore.shared')->get('panels_ipe');
          $tempstore->set($panels_display->getTempStoreId(), $panels_display->getConfiguration());

          $form['#attached']['drupalSettings']['panels_ipe']['unsaved_changes'] = TRUE;
        }
      }
    }

    if ($form_state->getValue('is_new')) {
      switch ($form_state->getValue(['shared_type', 0, 'value'])) {
        case 'private':
          $category = t('Custom');
          break;

        case 'own':
          $category = t('Shared Content(Own)');
          break;

        case 'global':
          $category = t('Shared Content');
          break;
      }
      $plugin_id = 'block_content:' . $this->entity->uuid();
      $definition = \Drupal::service('plugin.manager.block')->getDefinition($plugin_id);
      $form['#attached']['drupalSettings']['panels_ipe']['new_block_content'] = [
        'plugin_id' => $plugin_id,
        'label' => $definition['admin_label'],
        'category' => $definition['category'],
        'id' => $definition['id'],
        'provider' => $definition['provider'],
      ];

      $form['#attached']['drupalSettings']['panels_ipe']['new_block_content_category'] = $category;
    }
    if(!empty( $form['#attached']['drupalSettings']['panels_ipe']['new_block_content'])) {
      $form['#attached']['drupalSettings']['panels_ipe']['builder_new_block_content'] = $form['#attached']['drupalSettings']['panels_ipe']['new_block_content'];
    }

    return $form;
  }

}

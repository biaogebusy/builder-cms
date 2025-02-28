<?php

namespace Drupal\xinshi_editor\Controller;

use Drupal\panels_ipe\Controller\PanelsIPEPageController as PanelsIPEPageControllerBase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\AppendCommand;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Contains all JSON endpoints required for Panels IPE + Page Manager.
 */
class PanelsIPEPageController extends PanelsIPEPageControllerBase {

  /**
   * {@inheritdoc}
   */
  public function getBlockContentTypes($panels_storage_type, $panels_storage_id) {

    $data = [];
    // Assemble our relevant data.
    $types = $this->entityTypeManager()->getStorage('block_content_type')->loadMultiple();

    if(\Drupal::moduleHandler()->moduleExists('component_hub')) {
      $page_type = '';
      if(preg_match('/^\*([^:]+):(\d+)/', $panels_storage_id, $matches)) {
        $entity = $this->entityTypeManager()->getStorage($matches[1])->load($matches[2]);
        $page_type = $matches[1].':'.$entity->bundle();
      }
      $type = \Drupal::service('plugin.manager.content_widget');

      /** @var \Drupal\block_content\BlockContentTypeInterface $definition */
      foreach ($types as $id => $definition) {
        $images = [];
        $plugin = $type->getPluginByEntityName('block_content:'.$id);

        if($plugin) {
          if($plugin->get('hidden') && is_array($plugin->get('hidden'))) {
            if(in_array($page_type, $plugin->get('hidden'))) {
              continue;
            }
          }
          // Preview image
          $preview = $plugin->getPreviewImage();
          if($preview) {
            $images []= $preview;
          }
          $data[] = [
            'id' => $definition->id(),
            'revision' => $definition->shouldCreateNewRevision(),
            'label' => $definition->label(),
            'description' => $definition->getDescription(),
            'images' => $images,
            'category' => $plugin->get('category'),
            'weight' => 0,
          ];
        }
      }
    }
    if(\Drupal::moduleHandler()->moduleExists('entity_theme_engine')) {
      $widgetService = \Drupal::service('entity_theme_engine.entity_widget_service');
      $widgets = $widgetService->getAllWidgets();

      /** @var \Drupal\block_content\BlockContentTypeInterface $definition */
      foreach ($types as $id => $definition) {
        $images = [];
        $key = "block_content:{$id}";
        if(!empty($widgets[$key])) {
          $widget = $widgets[$key];
          $preview = $widget->getPreview();
          if($preview) {
            $images []= base_path() . $preview;
          }
          $data[] = [
            'id' => $definition->id(),
            'revision' => $definition->shouldCreateNewRevision(),
            'label' => $definition->label(),
            'description' => $definition->getDescription(),
            'images' => $images,
            'category' => $widget->getCategory(),
            'weight' => 0,
          ];
        }
      }
    }
    if($this->entityTypeManager()->hasDefinition('block_type_attribute')){
      $block_type_attribute_storage = $this->entityTypeManager()->getStorage('block_type_attribute');
      /** @var \Drupal\block_content\BlockContentTypeInterface $definition */
      foreach ($types as $id => $definition) {
        $images = [];
        $block_type_attributes = $block_type_attribute_storage->loadByProperties(['block_type' => $id, 'type' => 'block_type_attribute']);
        if ($block_type_attributes) {
          $block_type_attribute = reset($block_type_attributes);
          $images = $block_type_attribute->getImageUrls();

          $data[] = [
            'id' => $definition->id(),
            'revision' => $definition->shouldCreateNewRevision(),
            'label' => $definition->label(),
            'description' => $definition->getDescription(),
            'images' => $images,
            'category' => empty($block_type_attribute->category) ? "" : $block_type_attribute->category->target_id,
            'weight' => empty($block_type_attribute->weight) ? 0 : (int) $block_type_attribute->weight->value,
          ];
        }
      }
    }

    // Return a structured JSON response for our Backbone App.
    return new JsonResponse($data);
  }

  /**
   * {@inheritdoc}
   */
  public function getBlockContentForm($panels_storage_type, $panels_storage_id, $type, $block_uuid = NULL, $block_content_uuid = NULL) {
    $storage = $this->entityTypeManager()->getStorage('block_content');

    // Create or load a new block of the given type.
    if ($block_content_uuid) {
      $block_list = $storage->loadByProperties(['uuid' => $block_content_uuid]);
      $block = array_shift($block_list);

      $operation = 'update';
    }
    else {
      $block = $storage->create([
        'type' => $type,
      ]);

      $operation = 'create';
    }

    // Check Block Content entity access for the current operation.
    if (!$block->access($operation)) {
      throw new AccessDeniedHttpException();
    }

    // Grab our Block Content Entity form handler.
    $form = $this->entityFormBuilder()->getForm($block, 'panels_ipe', [
      'panels_storage_type' => $panels_storage_type,
      'panels_storage_id' => $panels_storage_id,
      'block_uuid' => $block_uuid,
      'block_content_uuid' => $block_content_uuid,
    ]);

    // Return the rendered form as a proper Drupal AJAX response.
    $response = new AjaxResponse();
    $command = new AppendCommand('.ipe-block-form', $form);
    $response->addCommand($command);
    return $response;
  }

  /**
   * {@inheritdoc}
   *
   * @see \Drupal\panels_ipe\Controller\PanelsIPEPageController::getBlockPluginsData()
   */
  public function getBlockPluginsData($panels_storage_type, $panels_storage_id) {
    $disable_blocks = \Drupal::service('config.factory')->get('xinshi_editor.panelizer_settings')->get('disable_blocks');
    // Base blocks
    $panels_display = $this->loadPanelsDisplay($panels_storage_type, $panels_storage_id);
    $definitions = $this->blockManager->getDefinitionsForContexts($panels_display->getContexts());
    $blocks = [];
    foreach ($definitions as $plugin_id => $definition) {
      // Don't add broken Blocks.
      if ($plugin_id == 'broken') {
        continue;
      }
      if(in_array($definition['provider'], ['ctools', 'ctools_block'])) {
        continue;
      }
      if($disable_blocks && in_array($definition['provider'], $disable_blocks)) {
        continue;
      }
      if($plugin_id == 'system_powered_by_block') {
        continue;
      }
      $blocks[] = [
        'plugin_id' => $plugin_id,
        'label' => $definition['admin_label'],
        'category' => $definition['category'],
        'id' => $definition['id'],
        'provider' => $definition['provider'],
      ];
    }

    // Shared blocks
    $shared_blocks = \Drupal::entityTypeManager()->getStorage('block_content')->loadByProperties(['shared_type' => 'global']);

    // Assemble our relevant data.
    foreach ($shared_blocks as $block) {
      $plugin_id = 'block_content:' . $block->uuid();
      if ($this->blockManager->hasDefinition($plugin_id)) {
        $definition = $this->blockManager->getDefinition($plugin_id);
        // Don't add broken Blocks.
        if ($plugin_id == 'broken') {
          continue;
        }
        // Get block type's category.
        $block_type_category = [];
        $block_type_attribute_storage = $this->entityTypeManager()->getStorage('block_type_attribute');
        $block_type_attributes = $block_type_attribute_storage->loadByProperties(['block_type' => $block->bundle(), 'type' => 'block_type_attribute']);
        if ($block_type_attributes) {
          $block_type_attribute = reset($block_type_attributes);
          if ($block_type_attribute->category) {
            $storage = \Drupal::service('entity_type.manager')->getStorage('taxonomy_term');
            $term = $storage->load($block_type_attribute->category->target_id);
            $block_type_category = [
              'id' => $term->id(),
              'name' => $term->name->value,
            ];
          }
        }

        $blocks[] = [
          'plugin_id' => $plugin_id,
          'label' => $definition['admin_label'],
          // 'category' => $definition['category'],
          'category' => t('Shared Content'),
          'id' => $definition['id'],
          'provider' => $definition['provider'],
          'block_type_category' => $block_type_category,
        ];
      }

    }

    // Trigger hook_panels_ipe_blocks_alter(). Allows other modules to change
    // the list of blocks that are visible.
    \Drupal::moduleHandler()->alter('panels_ipe_blocks', $blocks);
    // We need to re-index our return value, in case a hook unset a block.
    $blocks = array_values($blocks);
    return $blocks;
  }

}

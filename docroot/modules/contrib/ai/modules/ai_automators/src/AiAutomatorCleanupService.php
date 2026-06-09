<?php

namespace Drupal\ai_automators;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;

/**
 * Service to clean up field widget actions when automators are deleted.
 */
class AiAutomatorCleanupService {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The logger.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * Constructs a new AiAutomatorCleanupService.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger factory.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, LoggerChannelFactoryInterface $logger_factory) {
    $this->entityTypeManager = $entity_type_manager;
    $this->logger = $logger_factory->get('ai_automators');
  }

  /**
   * Clean up field widget actions associated with a deleted automator.
   *
   * @param \Drupal\ai_automators\AiAutomatorInterface $automator
   *   The automator entity being deleted.
   */
  public function cleanupActionsForAutomator(AiAutomatorInterface $automator) {
    $automator_id = $automator->id();

    // Find all entity form displays that might contain field widget actions
    // referencing this automator.
    $form_display_storage = $this->entityTypeManager
      ->getStorage('entity_form_display');
    $form_displays = $form_display_storage->loadMultiple();

    foreach ($form_displays as $form_display) {
      $changed = FALSE;
      $components = $form_display->getComponents();

      foreach ($components as $component_name => $component) {
        if (empty($component['third_party_settings']['field_widget_actions'])) {
          continue;
        }

        $actions = $component['third_party_settings']['field_widget_actions'];
        $original_count = count($actions);

        // Filter out actions that reference the deleted automator.
        $actions = array_filter($actions, function ($action_config) use ($automator_id) {
          return !$this->actionReferencesAutomator($action_config, $automator_id);
        });

        // Check if any actions were removed.
        if (count($actions) < $original_count) {
          $component['third_party_settings']['field_widget_actions'] = $actions;
          $form_display->setComponent($component_name, $component);
          $changed = TRUE;

          $removed_count = $original_count - count($actions);
          $this->logger->info('Removed @count field widget action(s) from @field_name due to automator @automator_id deletion', [
            '@count' => $removed_count,
            '@field_name' => $component_name,
            '@automator_id' => $automator_id,
          ]);
        }
      }

      if ($changed) {
        $form_display->save();
      }
    }
  }

  /**
   * Check if an action configuration references a specific automator.
   *
   * @param array $action_config
   *   The action configuration array.
   * @param string $automator_id
   *   The automator ID to check for.
   *
   * @return bool
   *   TRUE if the action references the automator, FALSE otherwise.
   */
  protected function actionReferencesAutomator(array $action_config, string $automator_id): bool {
    // Check the primary location where automator_id is stored.
    if (!empty($action_config['settings']['automator_id']) &&
        $action_config['settings']['automator_id'] === $automator_id) {
      return TRUE;
    }

    // Check other possible locations where automator reference might be stored.
    $possible_keys = ['automator', 'automator_reference'];
    foreach ($possible_keys as $key) {
      if (isset($action_config[$key]) && $action_config[$key] === $automator_id) {
        return TRUE;
      }
      if (isset($action_config['settings'][$key]) && $action_config['settings'][$key] === $automator_id) {
        return TRUE;
      }
    }

    return FALSE;
  }

}

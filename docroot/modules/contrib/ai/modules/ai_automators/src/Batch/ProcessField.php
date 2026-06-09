<?php

namespace Drupal\ai_automators\Batch;

use Drupal\ai_automators\Exceptions\AiAutomatorRequestErrorException;
use Drupal\ai_automators\Exceptions\AiAutomatorResponseErrorException;
use Drupal\ai_automators\Exceptions\AiAutomatorRuleNotFoundException;

/**
 * Processing a field in batch mode.
 */
class ProcessField {

  /**
   * Save the field.
   *
   * @param array $data
   *   The data needed.
   */
  public static function saveField(array $data) {
    $logger = \Drupal::logger('ai_automator');
    try {
      // Get new entity, to not overwrite.
      $newEntity = \Drupal::entityTypeManager()->getStorage($data['entity']->getEntityTypeId())->load($data['entity']->id());
      $entity = \Drupal::service('ai_automator.rule_runner')->generateResponse($newEntity, $data['fieldDefinition'], $data['automatorConfig']);
      // Turn off the hook.
      ai_automators_entity_can_save_toggle(FALSE);
      // Resave.
      $entity->save();
      // Turn on the hook.
      ai_automators_entity_can_save_toggle(TRUE);
      $logger->info("Saved via batch job the entity %id of type %entity_type on field %field_name", [
        '%id' => $data['entity']->id(),
        '%entity_type' => $data['entity']->getEntityTypeId(),
        '%field_name' => $data['fieldDefinition']->getName(),
      ]);
      return;
    }
    catch (AiAutomatorRuleNotFoundException $e) {
      $logger->warning('A rule was not found, message %message', [
        '%message' => $e->getMessage(),
      ]);
    }
    catch (AiAutomatorRequestErrorException $e) {
      $logger->warning('A request error happened, message %message', [
        '%message' => $e->getMessage(),
      ]);
    }
    catch (AiAutomatorResponseErrorException $e) {
      $logger->warning('A response was not correct, message %message', [
        '%message' => $e->getMessage(),
      ]);
    }
    catch (\Exception $e) {
      $logger->warning('A general error happened why trying to interpolate, message %message', [
        '%message' => $e->getMessage(),
      ]);
    }
    \Drupal::messenger()->addWarning($e->getMessage());
  }

}

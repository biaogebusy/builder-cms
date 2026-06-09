<?php

declare(strict_types=1);

namespace Drupal\ai_content_suggestions;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Helper to alter Content Entity Forms to allow an LLM to interact with them.
 */
final class AiContentSuggestionsFormAlter implements AiContentSuggestionsFormAlterInterface {

  /**
   * Constructor.
   */
  public function __construct(
    protected AiContentSuggestionsPluginManager $pluginManager,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected AccountProxyInterface $currentUser,
    protected ConfigFactoryInterface $configFactory,
  ) {

  }

  /**
   * {@inheritdoc}
   */
  public function alter(array &$form, FormStateInterface $form_state): void {
    $form['#cache']['contexts'][] = 'user.permissions';
    $form['#cache']['contexts'][] = 'ai_content_suggestions_plugins';
    $form['#cache']['contexts'][] = 'ai_providers';
    if ($this->currentUser->hasPermission('access ai content suggestion tools')) {
      /** @var \Drupal\Core\Entity\ContentEntityFormInterface $form_object */
      $form_object = $form_state->getFormObject();
      $entity = $form_object->getEntity();
      if (!$this->isEnabledForCurrentEntity($entity)) {
        return;
      }
      foreach ($this->pluginManager->getDefinitions() as $id => $definition) {
        /** @var \Drupal\ai_content_suggestions\AiContentSuggestionsInterface $plugin */
        if ($plugin = $this->pluginManager->createInstance($id, $definition)) {
          if ($plugin->isEnabled()) {
            /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
            $entity = $form_object->getEntity();
            $plugin->alterForm($form, $form_state, $this->getAllTextFields($entity, $form));
          }
        }
      }
    }
  }

  /**
   * Check if AI suggestions are enabled for the current entity.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The content entity on the form.
   *
   * @return bool
   *   TRUE if AI suggestions should be shown for this entity, FALSE otherwise.
   */
  public function isEnabledForCurrentEntity(ContentEntityInterface $entity): bool {
    $config = $this->configFactory->get('ai_content_suggestions.settings');
    $entity_type_id = $entity->getEntityTypeId();
    $bundle = $entity->bundle();
    $entity_types = $config->get('entity_types') ?? [];
    if (!in_array($entity_type_id, array_keys($entity_types), TRUE)) {
      return FALSE;
    }
    $mode = $entity_types[$entity_type_id]['mode'] ?? 'enable';
    $bundles = $entity_types[$entity_type_id]['bundles'] ?? [];
    if ($mode === 'enable') {
      return in_array($bundle, $bundles);
    }
    return !in_array($bundle, $bundles);
  }

  /**
   * {@inheritdoc}
   */
  public static function getPluginResponse(array $form, FormStateInterface $form_state): array {
    $return = [];

    if ($trigger = $form_state->getTriggeringElement()) {
      if (isset($trigger['#plugin'])) {

        $plugin_id = $trigger['#plugin'];

        /** @var \Drupal\ai_content_suggestions\AiContentSuggestionsPluginManager $plugin_manager */
        $plugin_manager = \Drupal::service('plugin.manager.ai_content_suggestions');
        if ($definition = $plugin_manager->getDefinition($plugin_id)) {

          /** @var \Drupal\ai_content_suggestions\AiContentSuggestionsInterface $plugin */
          if ($plugin = $plugin_manager->createInstance($plugin_id, $definition)) {
            $plugin->updateFormWithResponse($form, $form_state);

            $return = $form[$plugin_id]['response'];
          }
        }
      }
    }

    return $return;
  }

  /**
   * Get a list of all string and text fields on the current node.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The content entity on the form.
   * @param array $form
   *   The form array.
   *
   * @return array
   *   List of all valid field options.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function getAllTextFields(ContentEntityInterface $entity, array $form): array {
    $fields = $entity->getFieldDefinitions();
    $options = [];

    foreach ($fields as $field) {
      $field_name = $field->getName();

      if (array_key_exists($field_name, $form) && !in_array($field->getName(), ['revision_log', 'revision_log_message'])) {
        if (in_array($field->getType(), ['text_with_summary', 'text_long', 'string', 'string_long'])) {
          // @todo How to skip special fields?
          if (in_array($field->getName(), ['revision_log', 'revision_log_message'])) {
            continue;
          }

          $label = $field->getLabel();

          if ($label instanceof TranslatableMarkup) {
            $label = $label->render();
          }

          $options[$field->getName()] = $label;
        }
        elseif ($field->getType() == 'entity_reference_revisions') {
          $name = $field->getName();
          $key = 0;

          if (isset($form[$name]['widget'])) {
            $paragraph = NULL;

            while (array_key_exists($key, $form[$name]['widget'])) {
              if (isset($form[$name]['widget'][$key]['#paragraph_type'])) {
                $bundle = $form[$name]['widget'][$key]['#paragraph_type'];

                // If we already have a paragraph of the correct type, just
                // reuse it.
                $paragraph = ($paragraph && $paragraph->bundle() == $bundle) ? $paragraph : $this->entityTypeManager->getStorage('paragraph')
                  ->create([
                    'type' => $bundle,
                  ]);

                foreach ($this->getAllTextFields($paragraph, $form[$name]['widget'][$key]['subform']) as $machine => $label) {
                  $identifier = implode(':', [$name, $key, 'subform', $machine]);
                  $options[$identifier] = $field->getLabel() . ' (' . $key . ') > ' . $label;
                }
              }

              $key++;
            }
          }
        }
      }
    }

    return $options;
  }

}

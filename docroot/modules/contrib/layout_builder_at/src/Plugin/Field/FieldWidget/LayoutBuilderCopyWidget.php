<?php

namespace Drupal\layout_builder_at\Plugin\Field\FieldWidget;

use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Field\Attribute\FieldWidget;
use Drupal\Core\Field\FieldFilteredMarkup;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\block_content\BlockContentInterface;
use Drupal\layout_builder\Plugin\SectionStorage\OverridesSectionStorage;
use Drupal\layout_builder\SectionComponent;

/**
 * A widget to display the copy widget form.
 *
 * @internal
 *   Plugin classes are internal.
 */
#[FieldWidget(
  id: "layout_builder_at_copy",
  label: new TranslatableMarkup("Layout Builder Asymmetric Translation"),
  description: new TranslatableMarkup("A field widget for Layout Builder. This exposes a checkbox on the entity form to copy the blocks on translation."),
  field_types: [
    "layout_section",
  ],
  multiple_values: FALSE,
)]
class LayoutBuilderCopyWidget extends WidgetBase {

  /**
   * Gets the available appearance options.
   *
   * @return array
   *   An associative array of appearance options, where keys are option values
   *   and values are their corresponding labels.
   */
  protected function options(): array {
    return [
      'unchecked' => $this->t('Unchecked'),
      'checked' => $this->t('Checked'),
      'checked_hidden' => $this->t('Checked and hidden'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings(): array {
    return [
      'appearance' => 'unchecked',
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state): array {
    $element['appearance'] = [
      '#type' => 'select',
      '#title' => $this->t('Checkbox appearance'),
      '#options' => $this->options(),
      '#default_value' => $this->getSetting('appearance'),
    ];
    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary(): array {
    $summary = [];
    $summary[] = $this->t('Appearance: @checked', ['@checked' => $this->options()[$this->getSetting('appearance')]]);
    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function form(FieldItemListInterface $items, array &$form, FormStateInterface $form_state, $get_delta = NULL): array {
    $field_name = $this->fieldDefinition->getName();
    $parents = $form['#parents'];

    // Store field information in $form_state.
    if (!static::getWidgetState($parents, $field_name, $form_state)) {
      $field_state = [
        'items_count' => count($items),
        'array_parents' => [],
      ];
      static::setWidgetState($parents, $field_name, $form_state, $field_state);
    }

    // Collect widget elements.
    $elements = [];

    $delta = 0;
    $element = [
      '#title' => $this->fieldDefinition->getLabel(),
      '#description' => FieldFilteredMarkup::create(\Drupal::token()->replace($this->fieldDefinition->getDescription())),
    ];
    $element = $this->formSingleElement($items, $delta, $element, $form, $form_state);

    if ($element) {
      if (isset($get_delta)) {
        // If we are processing a specific delta value for a field where the
        // field module handles multiples, set the delta in the result.
        $elements[$delta] = $element;
      }
      else {
        // For fields that handle their own processing, we cannot make
        // assumptions about how the field is structured, just merge in the
        // returned element.
        $elements = $element;
      }
    }

    // Populate the 'array_parents' information in $form_state->get('field')
    // after the form is built, so that we catch changes in the form structure
    // performed in alter() hooks.
    $elements['#after_build'][] = [get_class($this), 'afterBuild'];
    $elements['#field_name'] = $field_name;
    $elements['#field_parents'] = $parents;
    // Enforce the structure of submitted values.
    $elements['#parents'] = array_merge($parents, [$field_name]);
    // Most widgets need their internal structure preserved in submitted values.
    $elements += ['#tree' => TRUE];

    return [
      // Aid in theming of widgets by rendering a classified container.
      '#type' => 'container',
      // Assign a different parent, to keep the main id for the widget itself.
      '#parents' => array_merge($parents, [$field_name . '_wrapper']),
      '#attributes' => [
        'class' => [
          'field--type-' . Html::getClass($this->fieldDefinition->getType()),
          'field--name-' . Html::getClass($field_name),
          'field--widget-' . Html::getClass($this->getPluginId()),
        ],
      ],
      'widget' => $elements,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state): array {

    $entity = $items->getEntity();
    $access = FALSE;

    if ($entity instanceof ContentEntityInterface) {
      $access = $entity->isNewTranslation() && !$entity->isDefaultTranslation();
    }

    $element['#layout_builder_at_access'] = $access;

    $checked = FALSE;
    $v = $this->getSetting('appearance');
    if ($v == 'checked' || $v == 'checked_hidden') {
      $checked = TRUE;
    }
    $element['value'] = $element + [
      '#access' => TRUE,
      '#type' => 'checkbox',
      '#default_value' => $checked,
      '#title' => $this->t('Copy blocks into translation'),
    ];

    if ($v == 'checked_hidden') {
      $element['value']['#access'] = FALSE;
    }

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function extractFormValues(FieldItemListInterface $items, array $form, FormStateInterface $form_state): void {
    // @todo This isn't resilient to being set twice, during validation and
    //   save https://www.drupal.org/project/drupal/issues/2833682.
    if (!$form_state->isValidationComplete()) {
      return;
    }

    $field_name = $this->fieldDefinition->getName();

    // We can only copy if the field is set and access is TRUE.
    if (isset($form[$field_name]['widget']['#layout_builder_at_access']) && !$form[$field_name]['widget']['#layout_builder_at_access']) {
      return;
    }

    // Extract the values from $form_state->getValues().
    $path = array_merge($form['#parents'], [$field_name]);
    $key_exists = NULL;
    $values = NestedArray::getValue($form_state->getValues(), $path, $key_exists);

    $values = $this->massageFormValues($values, $form, $form_state);
    if (isset($values['value']) && $values['value']) {

      // Replicate.
      /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
      /** @var \Drupal\Core\Entity\ContentEntityInterface $default_entity */
      $entity = $items->getEntity();

      $sourceLanguage = NULL;
      if ($form_state->hasValue('source_langcode')) {
        $sourceLanguageArray = $form_state->getValue('source_langcode');
        if (isset($sourceLanguageArray['source'])) {
          $sourceLanguage = $sourceLanguageArray['source'];
        }
      }

      $default_entity = is_null($sourceLanguage) ? $entity->getUntranslated() : $entity->getTranslation($sourceLanguage);

      /** @var \Drupal\layout_builder\Entity\LayoutEntityDisplayInterface $layout */
      $layout = $default_entity->get(OverridesSectionStorage::FIELD_NAME);
      $uuid = \Drupal::service('uuid');

      /** @var \Drupal\layout_builder\Section[] $sections */
      $sections = $layout->getSections();
      $new_sections = [];
      foreach ($sections as $delta => $section) {
        $cloned_section = clone $section;

        // Remove components from the cloned section.
        foreach ($cloned_section->getComponents() as $c) {
          $cloned_section->removeComponent($c->getUuid());
        }

        // Sort the components by weight.
        $components = $section->getComponents();
        uasort($components, function (SectionComponent $a, SectionComponent $b) {
          return $a->getWeight() > $b->getWeight() ? 1 : -1;
        });

        foreach ($components as $component) {
          $add_component = TRUE;
          $cloned_component = clone $component;
          $configuration = $component->get('configuration');

          // Replicate inline block content.
          if ($this->isInlineBlock($configuration['id'])) {

            /** @var \Drupal\block_content\BlockContentInterface $block */
            /** @var \Drupal\block_content\BlockContentInterface $replicated_block */
            $block = \Drupal::service('entity_type.manager')->getStorage('block_content')->loadRevision($configuration['block_revision_id']);
            $replicated_block = $this->cloneEntity('block_content', $block->id());
            if ($replicated_block) {
              $values_to_keep = [];
              $language_to_keep = $entity->language()->getId();
              if ($replicated_block->hasTranslation($entity->language()->getId())) {
                // Extract values of translation to keep.
                $values_to_keep = $replicated_block->getTranslation($language_to_keep)->toArray();
                // Only attempt to remove the translation if it's not the
                // default translation.
                if ($replicated_block->language()->getId() !== $language_to_keep) {
                  try {
                    // The removed translation needs to be saved or we can not
                    // change the language to that.
                    $replicated_block->removeTranslation($language_to_keep);
                    $replicated_block->save();
                  }
                  catch (\InvalidArgumentException $e) {
                    // If we can't remove the translation, just continue with
                    // the process. This can happen when trying to remove the
                    // default language translation.
                    \Drupal::logger('layout_builder_at')->notice(
                      'Could not remove translation @lang: @message',
                      [
                        '@lang' => $language_to_keep,
                        '@message' => $e->getMessage(),
                      ]
                    );
                  }
                }
              }
              $replicated_block->set('langcode', $language_to_keep);
              // Copy translatable field values.
              foreach ($values_to_keep as $field_name => $field_values) {
                if ($replicated_block->getFieldDefinition($field_name)->isTranslatable()
                    && !in_array($field_name, ['default_langcode'])) {
                  $replicated_block->set($field_name, $field_values);
                }
              }
              // Remove other translations that are not the language we're
              // keeping and not the default language.
              foreach ($replicated_block->getTranslationLanguages() as $translation_language) {
                $translation_id = $translation_language->getId();
                if ($translation_id !== $language_to_keep && $translation_id !== $replicated_block->language()->getId()) {
                  try {
                    $replicated_block->removeTranslation($translation_id);
                  }
                  catch (\InvalidArgumentException $e) {
                    // If we can't remove the translation, just skip it.
                    \Drupal::logger('layout_builder_at')->notice('Could not remove translation @lang: @message', [
                      '@lang' => $translation_id,
                      '@message' => $e->getMessage(),
                    ]);
                  }
                }
              }
              $replicated_block->save();
              $configuration = $this->updateComponentConfiguration($configuration, $replicated_block);
              $cloned_component->setConfiguration($configuration);

              // Store usage.
              \Drupal::service('inline_block.usage')->addUsage($replicated_block->id(), $entity);
            }
            else {
              $add_component = FALSE;
              $this->messenger()->addMessage($this->t('The inline block "@label" was not duplicated.', ['@label' => $block->label()]));
            }
          }

          // Add component.
          if ($add_component) {
            $cloned_component->set('uuid', $uuid->generate());
            $cloned_section->appendComponent($cloned_component);
          }
        }

        $new_sections[] = $cloned_section;
      }

      $items->setValue($new_sections);
    }
    else {
      $items->setValue(NULL);
    }
  }

  /**
   * Replicates an entity by cloning it.
   *
   * @param string $entity_type_id
   *   The entity type ID of the entity to be cloned.
   * @param int|string $entity_id
   *   The ID of the entity to be cloned.
   *
   * @return \Drupal\Core\Entity\EntityInterface|null
   *   The cloned entity if successful, or NULL on failure.
   */
  protected function cloneEntity(string $entity_type_id, mixed $entity_id): ?EntityInterface {
    $clone = NULL;

    try {
      /** @var \Drupal\Core\Entity\EntityInterface $entity */
      /** @var \Drupal\Core\Entity\EntityInterface $clone */
      $entity = \Drupal::service('entity_type.manager')->getStorage($entity_type_id)->load($entity_id);
      $clone = $entity->createDuplicate();

      /** @var \Drupal\Core\Field\FieldDefinitionInterface[] $field_definitions */
      $field_definitions = \Drupal::service('entity_field.manager')->getFieldDefinitions($entity->getEntityTypeId(), $entity->bundle());
      foreach ($field_definitions as $definition) {

        // Support for Entity reference revisions.
        if ($definition->getFieldStorageDefinition()->getType() == 'entity_reference_revisions') {
          $new_values = [];
          /** @var \Drupal\Core\Entity\EntityInterface[] $references */
          $references = $clone->get($definition->getName())->referencedEntities();
          if (!empty($references)) {
            foreach ($references as $reference) {
              $reference_clone = $reference->createDuplicate();
              $reference_clone->save();
              $new_values[] = [
                'target_id' => $reference_clone->id(),
                'target_revision_id' => $reference_clone->getRevisionId(),
              ];
            }

            if (!empty($new_values)) {
              $clone->set($definition->getName(), $new_values);
            }
          }
        }
      }
    }
    catch (\Exception $e) {
      \Drupal::logger('layout_builder_at')->error('Error cloning entity: @message', ['@message' => $e->getMessage()]);
    }

    return $clone;
  }

  /**
   * Does the block id represent an inline block.
   *
   * @param string $block_id
   *   The block id.
   *
   * @return bool
   *   True if this is an inline block else false.
   */
  protected function isInlineBlock(string $block_id): bool {
    return str_starts_with($block_id, 'inline_block:');
  }

  /**
   * Modify the supplied component configuration based on modified block.
   *
   * @param array $configuration
   *   The Layout Builder component configuration array.
   * @param \Drupal\block_content\BlockContentInterface $replicated_block
   *   The cloned block.
   *
   * @return array
   *   A modified configuration array.
   */
  protected function updateComponentConfiguration(array $configuration, BlockContentInterface $replicated_block): array {
    $configuration['block_id'] = $replicated_block->id();
    $configuration['block_revision_id'] = $replicated_block->getRevisionId();
    // Add support for block UUID export.
    // @see https://www.drupal.org/node/3180702
    if (!empty($configuration['block_uuid'])) {
      $configuration['block_uuid'] = $replicated_block->uuid();
    }
    return $configuration;
  }

}

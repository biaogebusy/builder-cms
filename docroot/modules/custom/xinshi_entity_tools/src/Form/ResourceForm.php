<?php

declare(strict_types=1);

namespace Drupal\xinshi_entity_tools\Form;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\xinshi_entity_tools\Entity\EntityToolResource;
use Drupal\xinshi_entity_tools\ResourceCatalog;

/** Explicitly selects ordinary content resources; it never grants role permissions. */
final class ResourceForm extends EntityForm {

  /** {@inheritdoc} */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);
    $resource = $this->entity;
    // Form API builds the entity repeatedly during the same submission.
    if (!$form_state->has('entity_tools_next_version')) {
      $form_state->set('entity_tools_next_version', $resource->isNew() ? 1 : $resource->get('version') + 1);
    }
    $form['notice'] = ['#markup' => $this->t('Selected content may be sent to the AI model and saved in ordinary conversations. Select ordinary content only; do not expose secrets or protected reports. Entity and field permissions still apply.')];
    $form['label'] = ['#type' => 'textfield', '#title' => $this->t('Label'),
      '#default_value' => $resource->label(), '#required' => TRUE, '#maxlength' => 255];
    $form['id'] = ['#type' => 'machine_name', '#default_value' => $resource->id(),
      '#maxlength' => 64, '#disabled' => !$resource->isNew(),
      '#machine_name' => ['exists' => [EntityToolResource::class, 'load']]];
    $types = [];
    foreach ($this->entityTypeManager->getStorage('node_type')->loadMultiple() as $type) {
      $types[$type->id()] = $type->label();
    }
    $bundle = $form_state->getValue('bundle') ?? $resource->get('bundle') ?? array_key_first($types);
    $form['bundle'] = ['#type' => 'select', '#title' => $this->t('Content type'),
      '#options' => $types, '#default_value' => $bundle, '#required' => TRUE,
      '#disabled' => !$resource->isNew(), '#ajax' => ['callback' => '::fieldsAjax', 'wrapper' => 'entity-tool-fields']];
    $options = [];
    if ($bundle && isset($types[$bundle])) {
      $definitions = \Drupal::service('entity_field.manager')->getFieldDefinitions('node', $bundle);
      foreach ($definitions as $name => $field) {
        if ((!$field->getFieldStorageDefinition()->isBaseField() || $name === 'title') &&
          !$field->isComputed() && !$field->isReadOnly() &&
          $field->getFieldStorageDefinition()->getCardinality() === 1 &&
          in_array($field->getType(), ResourceCatalog::TYPES, TRUE) && !$field->getSetting('allowed_values_function')) {
          $options[$name] = $this->t('@label (@name)', ['@label' => $field->getLabel(), '@name' => $name]);
        }
      }
    }
    $form['selection'] = ['#type' => 'container', '#attributes' => ['id' => 'entity-tool-fields']];
    foreach (['fields' => $this->t('Readable fields'), 'write_fields' => $this->t('Writable fields')] as $key => $title) {
      $form['selection'][$key] = ['#type' => 'checkboxes', '#title' => $title, '#options' => $options,
        '#default_value' => $resource->get($key) ?: ($key === 'fields' ? ['title'] : []),
        '#description' => $this->t('Only single-value supported fields appear. Writable fields must also be readable.')];
    }
    $languages = [];
    foreach (\Drupal::languageManager()->getLanguages() as $code => $language) {
      $languages[$code] = $language->getName();
    }
    $form['languages'] = ['#type' => 'checkboxes', '#title' => $this->t('Content languages'),
      '#options' => $languages, '#default_value' => $resource->get('languages'), '#required' => TRUE];
    $formats = [];
    foreach ($this->entityTypeManager->getStorage('filter_format')->loadMultiple() as $format) {
      if ($format->status()) {
        $formats[$format->id()] = $format->label();
      }
    }
    $form['text_format'] = ['#type' => 'select', '#title' => $this->t('Text format for draft writes'),
      '#options' => $formats, '#default_value' => $resource->get('text_format'), '#required' => TRUE,
      '#description' => $this->t('Authors also need permission to use this format.')];
    $form['allow_create'] = ['#type' => 'checkbox', '#title' => $this->t('Allow draft creation'),
      '#default_value' => $resource->get('allow_create')];
    $form['allow_update'] = ['#type' => 'checkbox', '#title' => $this->t('Allow draft revision updates'),
      '#default_value' => $resource->get('allow_update'),
      '#description' => $this->t('Only unpublished content with one translation and no pending revision can be updated.')];
    $form['status'] = ['#type' => 'checkbox', '#title' => $this->t('Enable this resource'),
      '#default_value' => !$resource->isNew() && $resource->status()];
    return $form;
  }

  /** Rebuilds field choices after the administrator selects a content type. */
  public function fieldsAjax(array &$form, FormStateInterface $form_state): array {
    return $form['selection'];
  }

  /** {@inheritdoc} */
  public function buildEntity(array $form, FormStateInterface $form_state) {
    $entity = parent::buildEntity($form, $form_state);
    foreach (['fields', 'write_fields', 'languages'] as $key) {
      $entity->set($key, array_values(array_filter($form_state->getValue($key, []))));
    }
    foreach (['allow_create', 'allow_update', 'status'] as $key) {
      $entity->set($key, (bool) $form_state->getValue($key));
    }
    $entity->set('version', $form_state->get('entity_tools_next_version'));
    return $entity;
  }

  /** {@inheritdoc} */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);
    try {
      \Drupal::service('xinshi_entity_tools.resources')->validate($this->buildEntity($form, $form_state));
    }
    catch (\InvalidArgumentException $error) {
      $form_state->setErrorByName('fields', $this->t('Invalid resource configuration: @reason', ['@reason' => $error->getMessage()]));
    }
  }

  /** {@inheritdoc} */
  public function save(array $form, FormStateInterface $form_state) {
    $this->entity->save();
    $this->messenger()->addStatus($this->t('The resource has been saved. Previous proposals use their original resource version.'));
    $form_state->setRedirect('entity.entity_tool_resource.collection');
  }

}

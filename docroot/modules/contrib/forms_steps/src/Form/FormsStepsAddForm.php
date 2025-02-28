<?php

declare(strict_types=1);

namespace Drupal\forms_steps\Form;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides a form to add a Steps collection.
 */
class FormsStepsAddForm extends EntityForm {

  /**
   * Constructs a new Forms Steps form.
   */
  public function __construct() {
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state): array {
    /** @var \Drupal\forms_steps\Entity\FormsSteps $entity */
    $entity = $this->entity;

    $form['settings'] = [
      '#type' => 'details',
      '#title' => $this->t('Settings'),
      '#open' => $entity->isNew(),
    ];

    $form['settings']['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Label'),
      '#default_value' => $entity->label(),
      '#required' => TRUE,
    ];

    $form['settings']['id'] = [
      '#type' => 'machine_name',
      '#description' => $this->t('A unique machine-readable name. Can only contain lowercase letters, numbers, and underscores.'),
      '#disabled' => !$entity->isNew(),
      '#default_value' => $entity->id(),
      '#machine_name' => [
        'exists' => [
          $this, 'exists',
        ],
        'replace_pattern' => '([^a-z0-9_]+)|(^custom$)',
        'source' => [
          'settings', 'label',
        ],
        'error' => $this->t('The machine-readable name must be unique, and can only contain lowercase letters, numbers, and underscores. Additionally, it can not be the reserved word "custom".'),
      ],
    ];

    $form['settings']['description'] = [
      '#type' => 'textarea',
      '#default_value' => $entity->getDescription(),
      '#description' => $this->t('Enter a description for this Forms Steps.'),
      '#title' => $this->t('Description'),
    ];

    return parent::form($form, $form_state);
  }

  /**
   * Machine name exists callback.
   *
   * @param string $id
   *   The machine name ID.
   *
   * @return bool
   *   TRUE if an entity with the same name already exists, FALSE otherwise.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function exists(string $id): bool {
    $type = $this->entity->getEntityTypeId();
    return (bool) $this->entityTypeManager->getStorage($type)->load($id);
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    parent::save($form, $form_state);

    $this->messenger()->addMessage($this->t('Forms Steps %label has been created.', ['%label' => $this->entity->label()]));
    $form_state->setRedirect('entity.forms_steps.edit_form', ['forms_steps' => $this->entity->id()]);
  }

}

<?php

namespace Drupal\entity_theme_engine\Form;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Entity\ContentEntityType;
use Drupal\Core\Cache\Cache;

/**
 * Class EntityWidgetForm.
 *
 * @package Drupal\entity_theme_engine\Form
 */
class EntityWidgetForm extends EntityForm {


  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);

    /**
     * @var \Drupal\entity_theme_engine\Entity\EntityWidget $entity_widget
     */
    $entity_widget = $this->entity;
    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Label'),
      '#maxlength' => 255,
      '#default_value' => $entity_widget->label(),
      '#description' => $this->t("Label for the entity widget."),
      '#required' => TRUE,
    ];

    $form['id'] = [
      '#type' => 'machine_name',
      '#default_value' => $entity_widget->id(),
      '#machine_name' => [
        'exists' => '\Drupal\entity_theme_engine\Entity\EntityWidget::load',
      ],
      '#disabled' => !$entity_widget->isNew(),
    ];
    $entity_types = \Drupal::entityTypeManager()->getDefinitions();
    $type_options = [];
    foreach ($entity_types as $key => $type) {
      if($type instanceof ContentEntityType) {
        if(is_string($type->getLabel())) {
          $type_options[$key] = $type->getLabel();
        } else {
          $type_options[$key] = ucfirst($type->getLabel()->render());
        }
      }
    }
    asort($type_options);
    $form['entity_type'] = [
      '#type' => 'select',
      '#title' => $this->t('Entity type'),
      '#required' => TRUE,
      '#default_value' => $entity_widget->getWidgetEntityType()?:'block_content',
      '#options' => $type_options,
    ];
    $form['bundle'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Entity Bundle'),
      '#required' => TRUE,
      '#default_value' => $entity_widget->getBundle()?:'',
    ];
    $form['create_bundle'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Auto create entity bundle'),
      '#default_value' => FALSE,
    ];
    $form['advanced'] = [
      '#type' => 'details',
      '#title' => 'Advanced',
    ];
    $form['advanced']['display'] = [
      '#type' => 'textfield',
      '#required' => TRUE,
      '#title' => $this->t('Display Mode'),
      '#maxlength' => 255,
      '#default_value' => $entity_widget->getDisplay(),
      '#description' => $this->t("The display mode this widget apply for."),
    ];
    $form['advanced']['theme'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Theme'),
      '#maxlength' => 255,
      '#default_value' => $entity_widget->getTheme(),
      '#description' => $this->t("The theme this widget apply for, Leave blank for all."),
    ];
    $form['advanced']['library'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Attached library'),
      '#maxlength' => 255,
      '#default_value' => $entity_widget->getLibrary(),
      '#description' => $this->t("The library name attached on this widget."),
    ];
    $form['advanced']['category'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Category'),
      '#maxlength' => 255,
      '#default_value' => $entity_widget->getCategory(),
      '#description' => $this->t("The custom category of this widget."),
    ];
    $form['advanced']['preview'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Preview image path'),
      '#maxlength' => 255,
      '#default_value' => $entity_widget->getPreview(),
      '#description' => $this->t("The Widget preview image path."),
    ];
    $form['advanced']['attach'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Attach Context Entity'),
      '#default_value' => $entity_widget->isAttach(),
    ];
    $form['enabled'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enabled'),
      '#default_value' => $entity_widget->isEnabled(),
    ];
    $form['template'] = [
      '#type' => 'text_format',
      '#format' => \Drupal::config('entity_theme_engine.settings')->get('text_format')?:'plain_text',
      '#rows' => 40,
      '#title' => $this->t('Twig Template'),
      '#default_value' => $entity_widget->getTemplate(),
      '#description' => $this->t("Widget twig template, can use entity fields as twig variables."),
      '#required' => TRUE,
    ];
    return $form;
  }
  /**
   * {@inheritdoc}
   */
  protected function actions(array $form, FormStateInterface $form_state) {
    $actions = parent::actions($form, $form_state);

    $actions['save_continue'] = $actions['submit'];
    $actions['save_continue']['#value'] = $this->t('Save and Continue Editing');
    $actions['save_continue']['#name'] = 'save_continue';
    return $actions;
  }
  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $form_state->setValue('template', $form_state->getValue(['template','value'],""));
  }
  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $entity_widget = $this->entity;
    $status = $entity_widget->save();

    switch ($status) {
      case SAVED_NEW:
        \Drupal::messenger()->addMessage($this->t('Created the %label entity widget.', [
          '%label' => $entity_widget->label(),
        ]));
        break;

      default:
        \Drupal::messenger()->addMessage($this->t('Saved the %label entity widget.', [
          '%label' => $entity_widget->label(),
        ]));
    }
    if($form_state->getValue('create_bundle', FALSE)) {
      $entityType = \Drupal::entityTypeManager()->getDefinition($entity_widget->getWidgetEntityType());
      if($bundleTypeId = $entityType->getBundleEntityType()) {
        $storage = \Drupal::entityTypeManager()->getStorage($bundleTypeId);
        $bundleType = \Drupal::entityTypeManager()->getDefinition($bundleTypeId);
        $bundle = $storage->load($entity_widget->getBundle());
        if($bundle) {
          if($bundle->label() != $entity_widget->label()) {
            $bundle->set($bundleType->getKey('label'),$entity_widget->label());
            $bundle->save();
          }
        }else {
          $values = [
            $bundleType->getKey('id') => $entity_widget->getBundle(),
            $bundleType->getKey('label') => $entity_widget->label(),
            'description' => "Entity Widget: {$entity_widget->label()}",
          ];
          $bundle = $storage->create($values);
          $bundle->save();
        }
      }
    }
    Cache::invalidateTags($entity_widget->getCacheTags());
    $trigger = $form_state->getTriggeringElement();
    if (isset($trigger['#name']) && $trigger['#name'] != 'save_continue') {
      $form_state->setRedirectUrl($entity_widget->toUrl('collection'));
    }
    else {
      $form_state->setRebuild();
    }

  }

}

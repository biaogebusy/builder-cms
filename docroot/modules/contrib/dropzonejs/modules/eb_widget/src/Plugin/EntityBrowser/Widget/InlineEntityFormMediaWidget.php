<?php

namespace Drupal\dropzonejs_eb_widget\Plugin\EntityBrowser\Widget;

use Drupal\Core\Entity\EntityDisplayRepositoryInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;
use Drupal\dropzonejs\Events\DropzoneMediaEntityCreateEvent;
use Drupal\dropzonejs\Events\Events;
use Drupal\entity_browser\WidgetBase;
use Drupal\inline_entity_form\Element\InlineEntityForm;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides an Entity Browser widget that uploads and edit new files.
 *
 * @EntityBrowserWidget(
 *   id = "dropzonejs_media_entity_inline_entity_form",
 *   label = @Translation("Media Entity DropzoneJS with edit"),
 *   description = @Translation("Adds DropzoneJS upload integration that saves Media entities and allows to edit them."),
 *   auto_select = FALSE
 * )
 */
class InlineEntityFormMediaWidget extends MediaEntityDropzoneJsEbWidget {

  /**
   * The entity display repository.
   *
   * @var \Drupal\Core\Entity\EntityDisplayRepositoryInterface
   */
  protected $entityDisplayRepository;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $widget = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $widget->setEntityDisplayRepository($container->get('entity_display.repository'));

    return $widget;
  }

  /**
   * Set the entity display repository service.
   *
   * @param \Drupal\Core\Entity\EntityDisplayRepositoryInterface $entityDisplayRepository
   *   The entity display repository service.
   */
  protected function setEntityDisplayRepository(EntityDisplayRepositoryInterface $entityDisplayRepository) {
    $this->entityDisplayRepository = $entityDisplayRepository;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return array_merge(parent::defaultConfiguration(), [
      'form_mode' => 'default',
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function getForm(array &$original_form, FormStateInterface $form_state, array $additional_widget_parameters) {
    $form = parent::getForm($original_form, $form_state, $additional_widget_parameters);

    // @todo Remove this when/if EB provides a way to define dependencies.
    if (!$this->moduleHandler->moduleExists('inline_entity_form')) {
      return [
        '#type' => 'container',
        'error' => [
          '#markup' => $this->t('Missing requirement: in order to use this widget you have to install Inline entity form module first'),
        ],
      ];
    }

    $form['#attached']['library'][] = 'dropzonejs_eb_widget/ief_edit';
    $form['edit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Edit'),
      '#attributes' => [
        'class' => ['js-hide'],
      ],
      '#ajax' => [
        'wrapper' => 'ief-dropzone-upload',
        'callback' => [static::class, 'onEdit'],
        'effect' => 'fade',
      ],
      '#submit' => [
        [$this, 'submitEdit'],
      ],
    ];

    $form['entities']['#prefix'] = '<div id="ief-dropzone-upload">';
    $form['entities']['#suffix'] = '</div>';

    $form += ['entities' => []];
    if ($entities = $form_state->get('uploaded_entities')) {
      foreach ($entities as $entity) {
        /** @var \Drupal\Core\Entity\EntityInterface $entity */
        $form['entities'][$entity->uuid()] = [
          '#type' => 'inline_entity_form',
          '#entity_type' => $entity->getEntityTypeId(),
          '#bundle' => $entity->bundle(),
          '#default_value' => $entity,
          '#form_mode' => $this->configuration['form_mode'],
        ];
      }
    }

    if (!empty(Element::children($form['entities']))) {
      // Make it possible to select those submitted entities.
      $pos = array_search('dropzonejs-disable-submit', $original_form['#attributes']['class']);
      if ($pos !== FALSE) {
        unset($original_form['#attributes']['class'][$pos]);
      }
    }

    $form['actions']['submit'] += ['#submit' => []];

    return $form;
  }

  /**
   * Submit callback for the edit button.
   *
   * @param array $form
   *   Form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Form object.
   */
  public function submitEdit(array $form, FormStateInterface $form_state) {
    $form_state->setRebuild(TRUE);

    $media_entities = $this->prepareEntities($form, $form_state);
    foreach ($media_entities as $id => $media_entity) {
      /** @var \Drupal\file\Entity\File $file */
      // Files have to saved before they can be viewed in the IEF form.
      $source_field = $this->getType()->getSource()->getSourceFieldDefinition($this->getType())->getName();
      $file = $media_entity->$source_field->entity;
      $file->save();
      $media_entity->$source_field->target_id = $file->id();
      if (method_exists($media_entity, 'prepareSave')) {
        $media_entity->prepareSave();
      }

      /** @var \Drupal\dropzonejs\Events\DropzoneMediaEntityCreateEvent $event */
      $event = $this->eventDispatcher->dispatch(new DropzoneMediaEntityCreateEvent($media_entity, $file, $form, $form_state, $form), Events::MEDIA_ENTITY_PRECREATE);
      $media_entities[$id] = $event->getMediaEntity();
    }

    $form_state->set('uploaded_entities', $media_entities);
  }

  /**
   * Ajax callback triggered when hitting the edit button.
   *
   * @param array $form
   *   The form.
   *
   * @return array
   *   Returns the entire form.
   */
  public static function onEdit(array $form) {
    return $form['widget']['entities'];
  }

  /**
   * {@inheritdoc}
   */
  public function validate(array &$form, FormStateInterface $form_state) {
    // Skip the DropzoneJsEbWidget specific validations.
    WidgetBase::validate($form, $form_state);
  }

  /**
   * Prepares entities from the form.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return \Drupal\media\MediaInterface[]
   *   The prepared media entities.
   */
  protected function prepareEntitiesFromForm(array $form, FormStateInterface $form_state) {
    $media_entities = [];
    foreach (Element::children($form['widget']['entities']) as $key) {
      /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
      $entity = $form['widget']['entities'][$key]['#entity'];
      $inline_entity_form_handler = InlineEntityForm::getInlineFormHandler($entity->getEntityTypeId());
      $inline_entity_form_handler->entityFormSubmit($form['widget']['entities'][$key], $form_state);
      $media_entities[] = $entity;
    }
    return $media_entities;
  }

  /**
   * {@inheritdoc}
   */
  public function submit(array &$element, array &$form, FormStateInterface $form_state) {
    $media_entities = $this->prepareEntitiesFromForm($form, $form_state);

    foreach ($media_entities as $id => $media_entity) {
      $source_field = $media_entity->getSource()->getConfiguration()['source_field'];
      $file = $media_entity->{$source_field}->entity;
      /** @var \Drupal\dropzonejs\Events\DropzoneMediaEntityCreateEvent $event */
      $event = $this->eventDispatcher->dispatch(new DropzoneMediaEntityCreateEvent($media_entity, $file, $form, $form_state, $element), Events::MEDIA_ENTITY_CREATE);
      $media_entity = $event->getMediaEntity();
      $media_entity->save();
      $media_entities[$id] = $media_entity;
    }

    if (!empty(array_filter($media_entities))) {
      $this->selectEntities($media_entities, $form_state);
      $this->clearFormValues($element, $form_state);
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function clearFormValues(array &$element, FormStateInterface $form_state) {
    parent::clearFormValues($element, $form_state);
    // Clear uploaded_entities to get a clean form in multi-step selection.
    $form_state->set('uploaded_entities', []);
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);
    $form['form_mode'] = [
      '#type' => 'select',
      '#title' => $this->t('Form mode'),
      '#options' => $this->entityDisplayRepository->getFormModeOptions('media'),
      '#default_value' => $this->configuration['form_mode'],
    ];
    return $form;
  }

}

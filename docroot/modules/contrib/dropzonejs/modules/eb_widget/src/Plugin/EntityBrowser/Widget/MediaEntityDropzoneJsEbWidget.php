<?php

namespace Drupal\dropzonejs_eb_widget\Plugin\EntityBrowser\Widget;

use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\dropzonejs\Events\DropzoneMediaEntityCreateEvent;
use Drupal\dropzonejs\Events\Events;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides an Entity Browser widget that uploads media entities.
 *
 * Widget will upload files and attach them to the media entity of type that is
 * defined in the configuration.
 *
 * @EntityBrowserWidget(
 *   id = "dropzonejs_media_entity",
 *   label = @Translation("Media Entity DropzoneJS"),
 *   description = @Translation("Adds DropzoneJS upload integration that saves Media entities."),
 *   auto_select = TRUE
 * )
 */
class MediaEntityDropzoneJsEbWidget extends DropzoneJsEbWidget {

  /**
   * Module handler service.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $widget = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $widget->setModuleHandler($container->get('module_handler'));

    return $widget;
  }

  /**
   * Set the module handler service.
   *
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $moduleHandler
   *   The module handler service.
   */
  protected function setModuleHandler(ModuleHandlerInterface $moduleHandler) {
    $this->moduleHandler = $moduleHandler;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return array_merge(parent::defaultConfiguration(), [
      'media_type' => '',
      'inherit_settings' => TRUE,
    ]);
  }

  /**
   * Returns the media type that this widget creates.
   *
   * @return \Drupal\media\MediaTypeInterface
   *   Media type.
   */
  protected function getType() {
    return $this->entityTypeManager
      ->getStorage('media_type')
      ->load($this->configuration['media_type']);
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);

    $form['media_type'] = [
      '#type' => 'select',
      '#title' => $this->t('Media type'),
      '#required' => TRUE,
      '#description' => $this->t('The type of media entity to create from the uploaded file(s).'),
    ];

    $media_type = $this->getType();
    if ($media_type) {
      $form['media_type']['#default_value'] = $media_type->id();
    }

    $media_types = $this->entityTypeManager->getStorage('media_type')->loadMultiple();

    if (!empty($media_types)) {
      foreach ($media_types as $media_type) {
        $form['media_type']['#options'][$media_type->id()] = $media_type->label();
      }
    }
    else {
      $form['media_type']['#disabled'] = TRUE;
      $form['media_type']['#description'] = $this->t('You must @create_media_type before using this widget.', [
        '@create_media_type' => Link::createFromRoute($this->t('create a media type'), 'media.add')->toString(),
      ]);
    }

    $form['inherit_settings'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Inherit settings from the media type'),
      '#default_value' => $this->configuration['inherit_settings'],
      '#weight' => 20,
      '#attributes' => ['class' => ['dropzonejs-inherit-settings']],
    ];

    $states = [
      'visible' => [
        '.dropzonejs-inherit-settings' => ['checked' => FALSE],
      ]
    ];

    // Remove these config options as these are propagated from the field.
    $form['max_filesize']['#weight'] = 21;
    $form['max_filesize']['#states'] = $states;
    $form['extensions']['#weight'] = 22;
    $form['extensions']['#states'] = $states;
    $form['upload_location']['#weight'] = 23;
    $form['upload_location']['#states'] = $states;

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies() {
    $dependencies = parent::calculateDependencies();

    // Depend on the media type this widget creates.
    $media_type = $this->getType();
    $dependencies[$media_type->getConfigDependencyKey()][] = $media_type->getConfigDependencyName();
    $dependencies['module'][] = 'media';

    return $dependencies;
  }

  /**
   * {@inheritdoc}
   */
  public function prepareEntities(array $form, FormStateInterface $form_state) {
    $entities = [];
    $media_type = $this->getType();

    foreach (parent::prepareEntities($form, $form_state) as $file) {
      $entities[] = $this->entityTypeManager->getStorage('media')->create([
        'bundle' => $media_type->id(),
        $media_type->getSource()->getConfiguration()['source_field'] => $file,
        'uid' => $this->currentUser->id(),
        'status' => TRUE,
        'type' => $media_type->getSource()->getPluginId(),
      ]);
    }

    return $entities;
  }

  /**
   * {@inheritdoc}
   */
  public function submit(array &$element, array &$form, FormStateInterface $form_state) {
    /** @var \Drupal\media\MediaInterface[] $media_entities */
    $media_entities = $this->prepareEntities($form, $form_state);

    foreach ($media_entities as $id => $media_entity) {
      $source_field = $this->getType()->getSource()->getConfiguration()['source_field'];
      $file = $media_entity->$source_field->entity;
      /** @var \Drupal\dropzonejs\Events\DropzoneMediaEntityCreateEvent $event */
      $event = $this->eventDispatcher->dispatch(new DropzoneMediaEntityCreateEvent($media_entity, $file, $form, $form_state, $element), Events::MEDIA_ENTITY_CREATE);
      $media_entity = $event->getMediaEntity();
      $source_field = $media_entity->getSource()->getConfiguration()['source_field'];
      // If we don't save file at this point Media entity creates another file
      // entity with same uri for the thumbnail. That should probably be fixed
      // in Media entity, but this workaround should work for now.
      $media_entity->$source_field->entity->save();
      $media_entity->save();
      $media_entities[$id] = $media_entity;
    }

    $this->selectEntities($media_entities, $form_state);
    $this->clearFormValues($element, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  protected function handleWidgetContext($widget_context) {
    // Copied default implementation to prevent the parent method from
    foreach ($this->defaultConfiguration() as $key => $value) {
      if (isset($widget_context[$key]) && isset($this->configuration[$key])) {
        $this->configuration[$key] = $widget_context[$key];
      }
    }
    if ($this->configuration['inherit_settings']) {
      $bundle = $this->getType();
      $source = $bundle->getSource();
      $field = $source->getSourceFieldDefinition($bundle);
      $field_storage = $field->getFieldStorageDefinition();
      $this->configuration['upload_location'] = $field_storage->getSettings()['uri_scheme'] . '://' . $field->getSettings()['file_directory'];
      $this->configuration['max_filesize'] = $field->getSettings()['max_filesize'];
      $this->configuration['extensions'] = $field->getSettings()['file_extensions'];
    }
  }

}

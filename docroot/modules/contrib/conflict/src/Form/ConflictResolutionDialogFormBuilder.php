<?php

namespace Drupal\conflict\Form;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\CloseDialogCommand;
use Drupal\Core\Ajax\CloseModalDialogCommand;
use Drupal\Core\Ajax\OpenModalDialogCommand;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\EntityReferenceFieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;

class ConflictResolutionDialogFormBuilder {

  use DependencySerializationTrait;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * ConflictResolutionFormBuilder constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * Adds the conflict resolution overview to the form.
   *
   * @param $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function processForm(&$form, FormStateInterface $form_state) {
    if ($form_state->get('conflict.build_conflict_resolution_form') || $form_state->get('conflict.processing')) {
      $form['conflict_overview_form'] = [
        '#type' => 'container',
        '#title' => t('Conflict resolution'),
        '#id' => 'conflict-overview-form',
        '#attributes' => ['title' => t('Conflict resolution')],
      ];
      $form['conflict_overview_form']['description'] = [
        '#type' => 'container',
        '#markup' => t('The content has either been modified by another user, or you have already submitted modifications. In order to save your changes a manual conflict resolution should be performed by clicking on "Resolve conflicts".'),
        '#attributes' => ['class' => ['conflict-overview-form-description']]
      ];

      $header = [
        ['data' => t('Conflicts')]
      ];
      $rows = [];
      $conflict_paths = $form_state->get('conflict.paths');
      foreach (array_keys($conflict_paths) as $path) {
        $path_titles = [];
        $path_entities = static::getEntitiesForPropertyPath($path, $form_state);
        foreach ($path_entities as $path_entity) {
          $path_titles[] = $path_entity->getEntityType()->getLabel() . ': "' . $path_entity->label() . '"';
        }

        $path_title = [
          '#type' => 'details',
          '#title' => end($path_titles),
          '#open' => FALSE,
          'preview' => static::buildNestedItemList($path_titles, TRUE, t('Conflict path')),
        ];

        $rows[] = [
          'data' => [['data' => $path_title]],
        ];
      }
      $form['conflict_overview_form']['conflicts'] = [
        '#type' => 'table',
        '#header' => $header,
        '#rows' => $rows,
      ];
      $form['conflict_overview_form']['resolve_conflicts'] = [
        '#type' => 'submit',
        '#value' => t('Resolve conflicts'),
        '#name' => 'conflict-resolve-conflicts',
        '#limit_validation_errors' => [],
        '#validate' => [],
        '#submit' => [[$this, 'resolveConflictsSubmit']],
        '#ajax' => [
          'callback' => [$this, 'resolveConflictsAjax'],
        ],
      ];
      $form['conflict_overview_form']['reset_changes'] = [
        '#type' => 'button',
        '#value' => t('Start over'),
        '#name' => 'conflict-reset-changes',
      ];

      $form['conflict_overview_form']['#attached']['library'][] = 'conflict/drupal.conflict_resolution';
    }
  }

  /**
   * Submit handler for starting the conflict resolution.
   *
   * @param $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function resolveConflictsSubmit($form, FormStateInterface $form_state) {
    // First entity to process.
    if ($form_state->get('conflict.build_conflict_resolution_form')) {
      // Reset the flag for building the overview form.
      $form_state->set('conflict.build_conflict_resolution_form', FALSE);
      $form_state->set('conflict.processing', TRUE);
      $form_state->set('conflict.first_processing', TRUE);
    }
    // Subsequent entities to process.
    else {
      $form_state->set('conflict.first_processing', FALSE);
    }
    $conflict_paths = $form_state->get('conflict.paths');
    if ($conflict_paths) {
      reset($conflict_paths);
      $path = key($conflict_paths);
      unset($conflict_paths[$path]);
      $form_state->set('conflict.paths', $conflict_paths);
      $form_state->set('conflict.processing_path', $path);
    }
    else {
      // Finish the conflict resolution for the entity that has been just
      // processed through the user interaction.
      $path = $form_state->get('conflict.processing_path');
      $path_entities = static::getEntitiesForPropertyPath($path, $form_state);
      $path_entity = end($path_entities);
      $path_parents = explode('.', $path);
      $this->getEntityConflictHandler($path_entity->getEntityTypeId())
        ->finishConflictResolution($path_entity, $path_parents, $form_state);

      $form_state->set('conflict.processing', FALSE);
      $form_state->set('conflict.processing_path', NULL);
    }

    $form_state->setCached();
  }

  /**
   * Ajax callback returning the UI for conflict resolution.
   *
   * @param $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   The ajax response containing the conflict resolution UI.
   */
  public function resolveConflictsAjax($form, FormStateInterface $form_state) {
    $response = new AjaxResponse();

    if ($form_state->get('conflict.first_processing')) {
      $response->addCommand(new CloseDialogCommand('#conflict-overview-form', TRUE));
    }

    $path = $form_state->get('conflict.processing_path');
    if (!is_null($path)) {
      $build = [];

      $path_titles = [];
      $path_entities = static::getEntitiesForPropertyPath($path, $form_state);
      foreach ($path_entities as $path_entity) {
        $path_titles[] = $path_entity->getEntityType()->getLabel() . ': "' . $path_entity->label() . '"';
      }
      $path_entity = end($path_entities);

      $conflict_ui_title = t('Resolving conflicts in: @entity_type "@entity"', ['@entity_type' => $path_entity->getEntityType()->getLabel(), '@entity' => $path_entity->label()]);

      $build['conflict_path_preview'] = [
        '#type' => 'details',
        '#title' => t('Show conflict path'),
        '#open' => FALSE,
        'preview' => static::buildNestedItemList($path_titles),
      ];

      $build['conflict_resolution_resolve_conflicts'] = [
        '#type' => 'submit',
        '#name' => 'conflict_resolution_resolve_conflicts',
        '#value' => empty($form_state->get('conflict.paths')) ? t('Finish conflict resolution') : t('Go to the next conflict'),
        '#weight' => 1000,
        '#attributes' => ['class' => ['conflict-resolve-conflicts']]
      ];

      $this->getEntityConflictUIResolver($path_entity->getEntityTypeId())
        ->addConflictResolution($path, $form_state, $path_entity, $build, $response);

      $options = [
        'dialogClass' => 'conflict-resolution-dialog conflict-resolution-dialog-step',
        'closeOnEscape' => FALSE,
        'resizable' => TRUE,
        'draggable' => TRUE,
        'width' => 'auto'
      ];
      $cmd = new OpenModalDialogCommand($conflict_ui_title, $build, $options);
      $response->addCommand($cmd);
    }
    else {
      $close_modal_cmd = new CloseModalDialogCommand();
      $response->addCommand($close_modal_cmd);
    }

    // @todo exchange the original entity's hash through a command

    return $response;
  }

  /**
   * Builds a title for the given property path.
   *
   * @param $path
   *   The property path.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return array
   *   The render array for building the markup for the given path.
   *
   * @throws \Exception
   *   An exception will be thrown in case the structure is not supported or it
   *   is not correct.
   */
  protected static function getTitleForPropertyPath($path, FormStateInterface $form_state) {
    $titles = [];
    $parents = $path ? explode('.', $path) : [];
    /** @var \Drupal\Core\Entity\EntityFormInterface $form_object */
    $form_object = $form_state->getFormObject();
    $element = $form_object->getEntity();
    $langcode = $element->language()->getId();
    $titles[] = $element->getEntityType()->getLabel() . ': "' . $element->label() . '"';

    while ($parents) {
      $next_element_identifier = array_shift($parents);
      if ($element instanceof ContentEntityInterface) {
        if (!$element->hasField($next_element_identifier)) {
          throw new \Exception('Not supported structure.');
        }
        $element = $element->get($next_element_identifier);
      }
      elseif ($element instanceof EntityReferenceFieldItemListInterface) {
        if (!isset($element[$next_element_identifier])) {
          throw new \Exception('Not supported structure.');
        }

        /** @var \Drupal\Core\Field\FieldItemInterface $field_item */
        $field_item = $element->get($next_element_identifier);
        $properties = $field_item->getProperties(TRUE);
        if (!isset($properties['entity'])) {
          throw new \Exception('Not supported structure.');
        }

        /** @var \Drupal\Core\Entity\ContentEntityInterface $element */
        $element = $field_item->entity;
        if ($element->hasTranslation($langcode)) {
          $element = $element->getTranslation($langcode);
        }
        elseif (count($element->getTranslationLanguages()) > 1) {
          throw new \Exception('Not supported structure.');
        }
        $titles[] = $element->getEntityType()->getLabel() . ': "' . $element->label() . '"';
      }
      else {
        throw new \Exception('Not supported structure.');
      }
    }
    $title = [
      '#type' => 'nested_entity_list',
      '#titles' => $titles,
    ];


    return $title;
  }

  /**
   * Builds the render array for the nested item list of titles.
   *
   * @param array $titles
   *   An array containing the titles.
   * @param bool $root_call
   *   (optional) Whether this is a root call. As this is a recursive function
   *   it requires a way to differ between the first and subsequent calls.
   *   Defaults to TRUE.
   * @param string|NULL $root_title
   *   (optional) The title to use for the most upper (root) list. Defaults to
   *   NULL.
   *
   * @return array
   *   A render array containing a nested item list of titles.
   */
  protected static function buildNestedItemList($titles, $root_call = TRUE, $root_title = NULL) {
    if ($titles) {
      $title = array_shift($titles);
      if ($root_call) {
        if ($titles) {
          $render = [
            [
              '#theme' => 'item_list',
              '#list_type' => 'ul',
              '#title' => $root_title,
              '#items' => [
                [
                  ['#markup' => $title,],
                  ['titles' => static::buildNestedItemList($titles, FALSE),],
                ],
              ],
            ],
          ];
        }
        else {
          $render = [
            [
              '#theme' => 'item_list',
              '#list_type' => 'ul',
              '#title' => $root_title,
              '#items' => [
                ['#markup' => $title]
              ],
            ],
          ];
        }
      }
      elseif ($titles) {
        $render = [
          ['#markup' => $title,],
          [
            '#theme' => 'item_list',
            '#list_type' => 'ul',
            '#items' => [
              'titles' => static::buildNestedItemList($titles, FALSE)
            ],
          ],
        ];
      }
      else {
        $render = [
          '#markup' => $title,
        ];
      }
    }
    else {
      $render = [];
    }
    return $render;
  }

  /**
   * Returns the entity for the given property path.
   *
   * @param $path
   *   The property path.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return \Drupal\Core\Entity\EntityInterface[]
   *   The entities across the path.
   *
   * @throws \Exception
   *   An exception will be thrown in case the structure is not supported or it
   *   is not correct.
   */
  protected static function getEntitiesForPropertyPath($path, FormStateInterface $form_state) {
    $parents = $path ? explode('.', $path) : [];
    /** @var \Drupal\Core\Entity\EntityFormInterface $form_object */
    $form_object = $form_state->getFormObject();
    $element = $form_object->getEntity();
    $langcode = $element->language()->getId();

    $entities = [$element];
    while ($parents) {
      $next_element_identifier = array_shift($parents);
      if ($element instanceof ContentEntityInterface) {
        if (!$element->hasField($next_element_identifier)) {
          throw new \Exception('Not supported structure.');
        }
        $element = $element->get($next_element_identifier);
      }
      elseif ($element instanceof EntityReferenceFieldItemListInterface) {
        if (!isset($element[$next_element_identifier])) {
          throw new \Exception('Not supported structure.');
        }
        /** @var \Drupal\Core\Field\FieldItemInterface $field_item */
        $field_item = $element->get($next_element_identifier);
        $properties = $field_item->getProperties(TRUE);
        if (!isset($properties['entity'])) {
          throw new \Exception('Not supported structure.');
        }

        /** @var \Drupal\Core\Entity\ContentEntityInterface $element */
        $element = $field_item->entity;
        if ($element->hasTranslation($langcode)) {
          $element = $element->getTranslation($langcode);
        }
        elseif (count($element->getTranslationLanguages()) > 1) {
          throw new \Exception('Not supported structure.');
        }
        $entities[] = $element;
      }
      else {
        throw new \Exception('Not supported structure.');
      }
    }
    if (!$element instanceof EntityInterface) {
      throw new \Exception('Not supported structure.');
    }
    return $entities;
  }

  /**
   * Returns the entity conflict handler for the given entity type.
   *
   * @param string $entity_type_id
   *   The entity type ID.
   *
   * @return \Drupal\conflict\Entity\EntityConflictHandlerInterface
   */
  protected function getEntityConflictHandler($entity_type_id) {
    return $this->entityTypeManager->getHandler($entity_type_id, 'conflict.resolution_handler');
  }

  /**
   * Returns the entity conflict UI resolver for the given entity type.
   *
   * @param string $entity_type_id
   *   The entity type ID.
   *
   * @return \Drupal\conflict\Entity\ConflictUIResolverHandlerInterface
   */
  protected function getEntityConflictUIResolver($entity_type_id) {
    return $this->entityTypeManager->getHandler($entity_type_id, 'conflict_ui_resolver');
  }

}

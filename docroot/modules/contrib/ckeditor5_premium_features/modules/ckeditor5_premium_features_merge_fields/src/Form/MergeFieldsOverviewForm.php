<?php

/*
 * Copyright (c) 2003-2026, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */

declare(strict_types=1);

namespace Drupal\ckeditor5_premium_features_merge_fields\Form;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Form for the Merge Fields overview page.
 */
class MergeFieldsOverviewForm extends FormBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The renderer.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * The request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * Constructs a MergeFieldsOverviewForm object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The request stack.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, RendererInterface $renderer, RequestStack $request_stack) {
    $this->entityTypeManager = $entity_type_manager;
    $this->renderer = $renderer;
    $this->requestStack = $request_stack;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('renderer'),
      $container->get('request_stack')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'merge_fields_overview_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    // Load all groups and items.
    $group_storage = $this->entityTypeManager->getStorage('merge_fields_group');
    $item_storage = $this->entityTypeManager->getStorage('merge_fields_item');

    $groups = $group_storage->loadMultiple();
    $items = $item_storage->loadMultiple();

    // Sort groups by weight.
    uasort($groups, function ($a, $b) {
      return $a->getWeight() <=> $b->getWeight();
    });

    // Sort items by weight.
    uasort($items, function ($a, $b) {
      return $a->getWeight() <=> $b->getWeight();
    });

    // Add empty group for ungrouped items.
    $groups['_other'] = new \Drupal\ckeditor5_premium_features_merge_fields\Entity\MergeFieldsGroup([
      'id' => '_other',
      'label' => $this->t('Other'),
      'weight' => 1000,
    ], 'merge_fields_group');

    $form['description'] = [
      '#type' => 'item',
      '#markup' => $this->t('Drag and drop items to rearrange them. Groups are root items and cannot be nested. Items are leaves and must be assigned to a group.'),
    ];

    $form['header_actions']['add_group'] = [
      '#type' => 'link',
      '#title' => $this->t('Add Group'),
      '#url' => Url::fromRoute('entity.merge_fields_group.add_form'),
      '#attributes' => [
        'class' => ['button', 'button--primary'],
      ],
    ];

    $form['header_actions']['add_item'] = [
      '#type' => 'link',
      '#title' => $this->t('Add Item'),
      '#url' => Url::fromRoute('entity.merge_fields_item.add_form'),
      '#attributes' => [
        'class' => ['button', 'button--primary'],
      ],
    ];

    // Create the table.
    $form['table-row'] = [
      '#type' => 'table',
      '#header' => [
        '',
        $this->t('Name'),
        $this->t('Merge Field Id'),
        $this->t('Token'),
        $this->t('Type'),
        $this->t('Weight'),
        $this->t('Parent'),
        $this->t('Operations'),
      ],
      '#empty' => $this->t('No merge fields available.'),
      '#tabledrag' => [
        [
          'action' => 'match',
          'relationship' => 'parent',
          'group' => 'row-pid',
          'source' => 'row-id',
          'hidden' => TRUE,
          'limit' => FALSE,
        ],
        [
          'action' => 'order',
          'relationship' => 'sibling',
          'group' => 'row-weight',
        ],
      ],
    ];

    // Add groups to the table as root items.
    foreach ($groups as $group_id => $group) {
      $form['table-row'][$group_id] = [
        '#attributes' => [
          'class' => ['draggable', 'tabledrag-root'],
        ],
        '#weight' => $group->getWeight(),
      ];

      //Empty column for the drag handle.
      $form['table-row'][$group_id]['empty']['#markup'] = '';

      // Name column.
      $form['table-row'][$group_id]['name'] = [
        '#markup' => '<strong>' . $group->label() . '</strong>',
      ];

      // Merge Field ID column (empty for groups).
      $form['table-row'][$group_id]['merge_field_id'] = [
        '#markup' => '-',
      ];

      // Token column (empty for groups).
      $form['table-row'][$group_id]['token'] = [
        '#markup' => '-',
      ];

      // Type column (group).
      $form['table-row'][$group_id]['type'] = [
        '#markup' => '-',
      ];

      // Weight column.
      $form['table-row'][$group_id]['weight'] = [
        '#type' => 'weight',
        '#title' => $this->t('Weight for @title', ['@title' => $group->label()]),
        '#title_display' => 'invisible',
        '#default_value' => $group->getWeight(),
        '#attributes' => ['class' => ['row-weight']],
      ];

      // Parent column (hidden for groups).
      $form['table-row'][$group_id]['parent']['pid'] = [
        '#type' => 'hidden',
        '#default_value' => '',
        '#attributes' => ['class' => ['row-pid']],
      ];

      // ID column (hidden).
      $form['table-row'][$group_id]['parent']['id'] = [
        '#type' => 'hidden',
        '#default_value' => $group_id,
        '#attributes' => ['class' => ['row-id']],
      ];

      // Operations column.
      if ($group_id !== '_other') {
        $form['table-row'][$group_id]['operations'] = [
          '#type' => 'operations',
          '#links' => [
            'edit' => [
              'title' => $this->t('Edit'),
              'url' => Url::fromRoute('entity.merge_fields_group.edit_form', ['merge_fields_group' => $group_id]),
            ],
            'delete' => [
              'title' => $this->t('Delete'),
              'url' => Url::fromRoute('entity.merge_fields_group.delete_form', ['merge_fields_group' => $group_id]),
            ],
          ],
        ];

      }

      // Add items belonging to this group as leaf items.
      foreach ($items as $item_id => $item) {
        if ($item->getGroup() === $group_id || (empty($item->getGroup()) && $group_id === '_other')) {
          $this->addItemToTable($form['table-row'], $item, $item_id, $groups);
        }
      }
    }

    // Add buttons.
    $form['actions'] = [
      '#type' => 'actions',
    ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save Order'),
      '#button_type' => 'primary',
    ];

    // Attach the Drupal tabledrag library.
    $form['#attached']['library'][] = 'core/drupal.tabledrag';

    return $form;
  }

  /**
   * Helper function to add an item to the table.
   *
   * @param array &$table
   *   The table render array.
   * @param \Drupal\ckeditor5_premium_features_merge_fields\Entity\MergeFieldsItemInterface $item
   *   The merge fields item.
   * @param string $item_id
   *   The item ID.
   * @param array $groups
   *   Array of available groups.
   */
  protected function addItemToTable(array &$table, $item, $item_id, array $groups) {
    $indentation = [
      '#theme' => 'indentation',
      '#size' => 1,
    ];

    $table[$item_id] = [
      '#attributes' => [
        'class' => ['draggable', 'tabledrag-leaf'],
      ],
      '#weight' => $item->getWeight(),
    ];

    //Empty column for the drag handle.
    $table[$item_id]['empty']['#markup'] = $this->renderer->render($indentation);

    // Name column.
    $table[$item_id]['name'] = [
      '#markup' => '<span>&nbsp;&nbsp;&nbsp;&nbsp;</span>' . $item->label(),
    ];

    // Merge Field ID column.
    $table[$item_id]['merge_field_id'] = [
      '#markup' => $item->id(),
    ];

    // Token column.
    $table[$item_id]['token'] = [
      '#markup' => $item->getToken(),
    ];

    // Type column.
    $table[$item_id]['type'] = [
      '#markup' => $item->getType(),
    ];

    // Weight column.
    $table[$item_id]['weight'] = [
      '#type' => 'weight',
      '#title' => $this->t('Weight for @title', ['@title' => $item->label()]),
      '#title_display' => 'invisible',
      '#default_value' => $item->getWeight(),
      '#attributes' => ['class' => ['row-weight']],
    ];

    // Parent column.
    $options = ['_other' => $this->t('- None -')];
    foreach ($groups as $group_id => $group) {
      $options[$group_id] = $group->label();
    }

    $table[$item_id]['parent']['pid'] = [
      '#type' => 'select',
      '#title' => $this->t('Parent'),
      '#title_display' => 'invisible',
      '#options' => $options,
      '#default_value' => $item->getGroup() ?: '_other',
      '#attributes' => ['class' => ['row-pid']],
    ];

    // ID column (hidden).
    $table[$item_id]['parent']['id'] = [
      '#type' => 'hidden',
      '#default_value' => $item_id,
      '#attributes' => ['class' => ['row-id']],
    ];

    // Operations column.
    $table[$item_id]['operations'] = [
      '#type' => 'operations',
      '#links' => [
        'edit' => [
          'title' => $this->t('Edit'),
          'url' => Url::fromRoute('entity.merge_fields_item.edit_form', ['merge_fields_item' => $item_id]),
        ],
        'delete' => [
          'title' => $this->t('Delete'),
          'url' => Url::fromRoute('entity.merge_fields_item.delete_form', ['merge_fields_item' => $item_id]),
        ],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();
    $weights = [];
    $parents = [];

    // Extract weights and parents from the form values.
    foreach ($values['table-row'] as $id => $item) {
      $weights[$id] = (int) $item['weight'];
      if (isset($item['parent']['pid'])) {
        $parents[$id] = $item['parent']['pid'];
      }
    }

    if (!empty($weights)) {
      $group_storage = $this->entityTypeManager->getStorage('merge_fields_group');
      $item_storage = $this->entityTypeManager->getStorage('merge_fields_item');

      // Update group weights.
      $groups = $group_storage->loadMultiple(array_keys($weights));
      foreach ($groups as $group_id => $group) {
        if ($group_id === '_other') {
          // Do not save "Other" group
          continue;
        }
        if (isset($weights[$group_id])) {
          $group->setWeight($weights[$group_id]);
          $group->save();
        }
      }

      // Update item weights and parent groups.
      $items = $item_storage->loadMultiple(array_keys($weights));
      foreach ($items as $item_id => $item) {
        $updated = FALSE;

        // Update weight if changed.
        if (isset($weights[$item_id])) {
          $item->setWeight($weights[$item_id]);
          $updated = TRUE;
        }

        // Update parent group if changed.
        if (isset($parents[$item_id])) {
          $parent_id = $parents[$item_id];
          // Handle special case for ungrouped items.
          if ($parent_id === '_none') {
            $parent_id = '';
          }
          // Skip if parent is 0 (root) or the item itself
          elseif ($parent_id === '0' || $parent_id === $item_id) {
            $parent_id = '';
          }

          if ($item->getGroup() !== $parent_id) {
            // Add the item to the new group.
            $item->setGroup($parent_id);
            $updated = TRUE;
          }
        }

        // Save the item if it was updated.
        if ($updated) {
          $item->save();
        }
      }

      $this->messenger()->addStatus($this->t('The order has been updated.'));
    }

    // Redirect back to the form page.
    $form_state->setRedirect('ckeditor5_premium_features_merge_fields.overview');
  }

}

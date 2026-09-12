<?php

/*
 * Copyright (c) 2003-2026, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */

declare(strict_types=1);

namespace Drupal\ckeditor5_premium_features_merge_fields\Plugin\CKEditor5Plugin;

use Drupal\ckeditor5\Plugin\CKEditor5PluginConfigurableInterface;
use Drupal\ckeditor5\Plugin\CKEditor5PluginConfigurableTrait;
use Drupal\ckeditor5\Plugin\CKEditor5PluginDefault;
use Drupal\Component\Serialization\Json;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\editor\EditorInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * CKEditor 5 "Merge Fields" plugin.
 *
 * @internal
 *   Plugin classes are internal.
 */
class MergeFields extends CKEditor5PluginDefault implements ContainerFactoryPluginInterface, CKEditor5PluginConfigurableInterface {

  use CKEditor5PluginConfigurableTrait;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Creates the plugin instance.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param mixed ...$parent_arguments
   *   The parent plugin arguments.
   */
  public function __construct(
    protected ConfigFactoryInterface $configFactory,
    EntityTypeManagerInterface $entity_type_manager,
    ...$parent_arguments
  ) {
    parent::__construct(...$parent_arguments);
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $container->get('config.factory'),
      $container->get('entity_type.manager'),
      $configuration,
      $plugin_id,
      $plugin_definition
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getDynamicPluginConfig(array $static_plugin_config, EditorInterface $editor): array {
    $static_plugin_config = parent::getDynamicPluginConfig($static_plugin_config, $editor);
    $static_plugin_config['mergeFields'] = [];

    $enabled_items = $this->configuration['enabled_items'] ? Json::decode($this->configuration['enabled_items']) : [];

    $enableAll = FALSE;
    if (!empty($enabled_items)) {
      // Load only enabled merge fields groups and items.
      $groupIds = [];
      $wholeGroupIds = [];
      $itemIds = [];
      foreach ($enabled_items as $group_id => $group_data) {
        if (isset($group_data['enable']) && $group_data['enable']) {
          if (empty($group_data['items']) || !is_array($group_data['items'])) {
            continue;
          }
          if (!array_search(1, $group_data['items'])) {
            $wholeGroupIds[] = $group_id;
            $groupIds[] = $group_id;
          }
          else {
            $groupIds[] = $group_id;
            foreach ($group_data['items'] as $item_id => $enabled) {
              if ($enabled) {
                $itemIds[] = $item_id;
              }
            }
          }
        }
      }
      if (empty($wholeGroupIds) && empty($itemIds)) {
        $enableAll = TRUE;
      }
      else {
        $groups = $this->entityTypeManager->getStorage('merge_fields_group')->loadMultiple($groupIds);
        $query = $this->entityTypeManager->getStorage('merge_fields_item')->getQuery();
        $additionalItemIds = $query
          ->accessCheck(FALSE)
          ->condition('group', $wholeGroupIds, 'IN')
          ->execute();
        $itemIds = array_merge($itemIds, $additionalItemIds);
        $items = $this->entityTypeManager->getStorage('merge_fields_item')->loadMultiple($itemIds);
      }
    }
    if ($enableAll || empty($enabled_items)) {
      // Load all merge fields groups and items.
      $groups = $this->entityTypeManager->getStorage('merge_fields_group')->loadMultiple();
      $items = $this->entityTypeManager->getStorage('merge_fields_item')->loadMultiple();
    }

    // Sort groups by weight.
    uasort($groups, function ($a, $b) {
      return $a->getWeight() <=> $b->getWeight();
    });

    // Sort items by weight.
    uasort($items, function ($a, $b) {
      return $a->getWeight() <=> $b->getWeight();
    });

    // Group items by their group ID.
    $grouped_items = [];
    $ungrouped_items = [];

    foreach ($items as $item) {
      $group_id = $item->getGroup();
      if (!empty($group_id) && isset($groups[$group_id])) {
        if (!isset($grouped_items[$group_id])) {
          $grouped_items[$group_id] = [];
        }
        $grouped_items[$group_id][] = $item;
      } else {
        $ungrouped_items[] = $item;
      }
    }

    // Create the definitions structure with groups and their items.
    $definitions = [];

    // Add grouped items.
    foreach ($groups as $group_id => $group) {
      if (isset($grouped_items[$group_id]) && !empty($grouped_items[$group_id])) {
        $group_definitions = [];

        foreach ($grouped_items[$group_id] as $item) {
          $definition = [
            'id' => $item->id(),
            'label' => $item->label(),
            'defaultValue' => $item->getDefaultValue() ?: $item->getToken(),
            'token' => $item->getToken(),
            'type' => $item->getType(),
          ];

          // Add height for block and image types
          if (in_array($item->getType(), ['block', 'image']) && $item->getHeight()) {
            $definition['height'] = $item->getHeight();
          }

          // Add width for image type
          if ($item->getType() === 'image' && $item->getWidth()) {
            $definition['width'] = $item->getWidth();
          }

          $group_definitions[] = $definition;
        }

        $definitions[] = [
          'groupId' => $group_id,
          'groupLabel' => $group->label(),
          'definitions' => $group_definitions,
        ];
      }
    }

    // Add ungrouped items if any.
    if (!empty($ungrouped_items)) {
      $ungrouped_definitions = [];

      foreach ($ungrouped_items as $item) {
        $definition = [
          'id' => $item->id(),
          'label' => $item->label(),
          'defaultValue' => $item->getDefaultValue() ?: $item->getToken(),
          'token' => $item->getToken(),
          'type' => $item->getType(),
        ];

        // Add width for image type
        if ($item->getType() === 'image' && $item->getWidth()) {
          $definition['width'] = $item->getWidth();
        }

        // Add height for block and image types
        if (in_array($item->getType(), ['block', 'image']) && $item->getHeight()) {
          $definition['height'] = $item->getHeight();
        }

        $ungrouped_definitions[] = $definition;

      }
      $definitions[] = [
        'groupId' => '_other',
        'groupLabel' => $this->t('Other'),
        'definitions' => $ungrouped_definitions,
      ];
    }

    $static_plugin_config['mergeFields']['definitions'] = $definitions;

    return $static_plugin_config;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'enabled_items' => '',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form['description'] = [
      '#type' => 'item',
      '#markup' => $this->t('Top-level items are groups that will be available for this text format. Nested items are Merge Fields available for each group. You can specify which merge fields should be available within each group by selecting appropriate check boxes.')
      . '<br/>' . $this->t('By default all merge fields will be available in case nothing is selected. If group is selected without any child items, all items in that group will be available.'),
    ];

    $groups = $this->entityTypeManager->getStorage('merge_fields_group')->loadMultiple();
    $items = $this->entityTypeManager->getStorage('merge_fields_item')->loadMultiple();
    $enabled_items = $this->configuration['enabled_items'] ? Json::decode($this->configuration['enabled_items']) : [];

    // Create temporary empty group for ungrouped items.
    $groups[] = new \Drupal\ckeditor5_premium_features_merge_fields\Entity\MergeFieldsGroup([
      'id' => '_other',
      'label' => $this->t('Other'),
      'weight' => 1000,
    ], 'merge_fields_group');

    // Sort groups by weight.
    uasort($groups, function ($a, $b) {
      return $a->getWeight() <=> $b->getWeight();
    });

    // Sort items by weight.
    uasort($items, function ($a, $b) {
      return $a->getWeight() <=> $b->getWeight();
    });

    foreach ($groups as $group) {
      $form['enabled_items'][$group->id()] = [
        '#type' => 'container',
        'enable' => [
          '#type' => 'checkbox',
          '#title' => '<strong>' . $group->label() . '</strong>',
          '#default_value' => $enabled_items[$group->id()]['enable'] ?? FALSE,
        ],
        'items' => [
          '#type' => 'container',
          '#attributes' => [
            'style' => 'padding-left: 1em;',
          ],
          '#states' => [
            'visible' => [
              ':input[name="editor[settings][plugins][ckeditor5_premium_features_merge_fields__merge_fields][enabled_items][' . $group->id() . '][enable]"]' => ['checked' => TRUE],
            ],
          ],
        ],
      ];
    }
    foreach ($items as $item) {
      $group_id = $item->getGroup() ? $item->getGroup() : '_other';
      $form['enabled_items'][$group_id]['items'][$item->id()] = [
        '#type' => 'checkbox',
        '#title' => $item->label(),
        '#default_value' => $enabled_items[$group_id]['items'][$item->id()] ?? FALSE,
      ];
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {

  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();

    $this->configuration['enabled_items'] = Json::encode($values['enabled_items']);
  }
}

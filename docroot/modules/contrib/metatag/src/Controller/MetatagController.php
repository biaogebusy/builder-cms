<?php

namespace Drupal\metatag\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\metatag\MetatagGroupPluginManager;
use Drupal\metatag\MetatagTagPluginManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Returns responses for Metatag routes.
 */
class MetatagController extends ControllerBase {

  use StringTranslationTrait;

  /**
   * Metatag tag plugin manager.
   *
   * @var \Drupal\metatag\MetatagTagPluginManager
   */
  protected $tagManager;

  /**
   * Metatag group plugin manager.
   *
   * @var \Drupal\metatag\MetatagGroupPluginManager
   */
  protected $groupManager;

  /**
   * Constructs a new \Drupal\views_ui\Controller\ViewsUIController object.
   *
   * @param \Drupal\metatag\MetatagTagPluginManager $tag_manager
   *   The tag manager object.
   * @param \Drupal\metatag\MetatagGroupPluginManager $group_manager
   *   The group manager object.
   */
  public function __construct(MetatagTagPluginManager $tag_manager, MetatagGroupPluginManager $group_manager) {
    $this->tagManager = $tag_manager;
    $this->groupManager = $group_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('plugin.manager.metatag.tag'),
      $container->get('plugin.manager.metatag.group')
    );
  }

  /**
   * Lists all plugins.
   *
   * @return array
   *   The Metatag plugins report page.
   */
  public function reportPlugins(): array {
    // Get tags.
    $tag_definitions = $this->tagManager->getDefinitions();
    uasort($tag_definitions, [
      'Drupal\Component\Utility\SortArray',
      'sortByWeightElement',
    ]);
    $tags = [];
    foreach ($tag_definitions as $tag_name => $tag_definition) {
      $tags[$tag_definition['group']][$tag_name] = $tag_definition;
    }

    // Get groups.
    $group_definitions = $this->groupManager->getDefinitions();
    uasort($group_definitions, [
      'Drupal\Component\Utility\SortArray',
      'sortByWeightElement',
    ]);

    // Build plugin by group.
    $build = [];
    foreach ($group_definitions as $group_name => $group_definition) {
      $build[$group_name] = [];
      // Group title.
      $build[$group_name]['title'] = [
        '#markup' => $group_definition['label'] . ' (' . $group_name . ')',
        '#prefix' => '<h2>',
        '#suffix' => '</h2>',
      ];
      // Group description.
      $build[$group_name]['description'] = [
        '#markup' => $group_definition['description'] ?? '',
        '#prefix' => '<p>',
        '#suffix' => '</p>',
      ];

      $rows = [];
      foreach ($tags[$group_name] as $definition) {
        $row = [];
        $row['label'] = [
          'data' => [
            'label' => [
              '#markup' => $definition['label'],
              '#prefix' => '<h3>',
              '#suffix' => '</h3>',
            ],
          ],
        ];
        $row['name'] = [
          'data' => $definition['name'],
          'nowrap' => 'nowrap',
        ];
        $row['id'] = $definition['id'];
        $row['type'] = $definition['type'];
        $row['weight'] = $definition['weight'];
        $row['secure'] = $definition['secure'] ? $this->t('Yes') : $this->t('No');
        $row['multiple'] = $definition['multiple'] ? $this->t('Yes') : $this->t('No');
        $row['provider'] = $definition['provider'];
        $key = $definition['group'] . '.' . $definition['id'];
        $rows[$key] = $row;
        $row = [];
        $row['description'] = [
          'data' => [
            '#markup' => $definition['description'] ?? '',
          ],
          'colspan' => 8,
        ];
        $rows[$key . '_desc'] = $row;
      }
      ksort($rows);

      $build[$group_name]['tags'] = [
        '#type' => 'table',
        '#header' => [
          ['data' => $this->t('Label / Description')],
          ['data' => $this->t('Name')],
          ['data' => $this->t('ID'), 'class' => [RESPONSIVE_PRIORITY_LOW]],
          ['data' => $this->t('Type'), 'class' => [RESPONSIVE_PRIORITY_LOW]],
          ['data' => $this->t('Weight'), 'class' => [RESPONSIVE_PRIORITY_LOW]],
          ['data' => $this->t('Secure'), 'class' => [RESPONSIVE_PRIORITY_LOW]],
          ['data' => $this->t('Multiple'), 'class' => [RESPONSIVE_PRIORITY_LOW]],
          ['data' => $this->t('Provided by')],
        ],
        '#rows' => $rows,
        '#suffix' => '<br /><br />',
        '#caption' => $this->t('All meta tags in the "@group" group.', ['@group' => $group_definition['label']]),
        '#sticky' => TRUE,
      ];
    }

    // Build metatags info.
    $metatags_info = [];
    $metatags_group_rows = [];
    foreach ($group_definitions as $group_name => $group_definition) {
      $metatags_group_row = [];
      if (!empty($build[$group_name]['tags']['#rows'])) {
        $metatags_number = count($build[$group_name]['tags']['#rows']) / 2;
      }
      $metatags_group_row['group_name'] = [
        'data' => $group_definition['label'] . ' (' . $group_name . ')',
        'nowrap' => 'nowrap',
      ];
      $metatags_group_row['metatags_number'] = [
        'data' => $metatags_number ?? 0,
        'nowrap' => 'nowrap',
      ];
      $metatags_group_rows[] = $metatags_group_row;
    }
    // Add total number of metatags.
    $metatags_group_rows[] = [
      'group_name' => [
        'data' => [
          'label' => [
            '#markup' => $this->t('Total number of metatags'),
            '#prefix' => '<strong>',
            '#suffix' => '</strong>',
          ],
        ],
        'nowrap' => 'nowrap',
      ],
      'metatag_number' => [
        'data' => count($tag_definitions) ?? 0,
        'nowrap' => 'nowrap',
      ],
    ];
    // Add total number of groups.
    $metatags_group_rows[] = [
      'group_name' => [
        'data' => [
          'label' => [
            '#markup' => $this->t('Total number of groups'),
            '#prefix' => '<strong>',
            '#suffix' => '</strong>',
          ],
        ],
        'nowrap' => 'nowrap',
      ],
      'group_number' => [
        'data' => count($group_definitions) ?? 0,
        'nowrap' => 'nowrap',
      ],
    ];

    $metatags_info['info'] = [
      '#type' => 'table',
      '#header' => [
        ['data' => $this->t('Name of group')],
        ['data' => $this->t('Number of metatags')],
      ],
      '#rows' => $metatags_group_rows,
    ];
    // Add metatags_info to build.
    $build = array_merge($metatags_info, $build);
    return $build;
  }

}

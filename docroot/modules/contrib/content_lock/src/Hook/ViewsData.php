<?php

namespace Drupal\content_lock\Hook;

use Drupal\content_lock\ContentLock\ContentLockInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Views data hook for the Content Lock module.
 */
class ViewsData {
  use StringTranslationTrait;

  public function __construct(
    private ContentLockInterface $contentLock,
    private ConfigFactoryInterface $configFactory,
    private EntityTypeManagerInterface $entityTypeManager,
  ) {
  }

  /**
   * Implements hook_views_data().
   */
  #[Hook('views_data')]
  public function viewsData(): array {
    // Define the return array.
    $data = [];

    $data['content_lock']['table']['group'] = $this->t('Content lock');

    $data['content_lock']['table']['provider'] = 'content_lock';

    $data['content_lock']['table']['join'] = [
      'users_field_data' => [
        'left_field' => 'uid',
        'field' => 'uid',
      ],
    ];

    $types = (array) $this->configFactory->get('content_lock.settings')
      ->get("types");

    foreach (array_filter($types) as $type => $value) {
      $definition = $this->entityTypeManager->getDefinition($type);
      $data['content_lock']['table']['join'][$definition->getDataTable()] = [
        'left_field' => $definition->getKey('id'),
        'field' => 'entity_id',
        'extra' => [
          [
            'field' => 'entity_type',
            'value' => $type,
          ],
        ],
      ];
      if ($this->contentLock->isTranslationLockEnabled($type)) {
        $data['content_lock']['table']['join'][$definition->getDataTable()]['extra'][] = [
          'left_field' => $definition->getKey('langcode'),
          'field' => 'langcode',
        ];
      }

      $data['content_lock'][$definition->getKey('id')] = [
        'title' => $this->t('@type locked', ['@type' => $definition->getLabel()]),
        'help' => $this->t('The @type being locked.', ['@type' => $definition->getLabel()]),
        'relationship' => [
          'base' => $definition->getDataTable(),
          'base field' => $definition->getKey('id'),
          'id' => 'standard',
          'label' => $this->t('@type locked', ['@type' => $definition->getLabel()]),
        ],
      ];
    }

    $data['content_lock']['uid'] = [
      'title' => $this->t('Lock owner'),
      'help' => $this->t('The user locking the node.'),
      'relationship' => [
        'base' => 'users_field_data',
        'base field' => 'uid',
        'id' => 'standard',
        'label' => $this->t('Lock owner'),
      ],
    ];

    $data['content_lock']['timestamp'] = [
      'title' => $this->t('Lock Date/Time'),
      'help' => $this->t('Timestamp of the lock'),
      'field' => [
        'id' => 'date',
        'click sortable' => TRUE,
      ],
      'sort' => [
        'id' => 'date',
      ],
      'filter' => [
        'id' => 'date',
      ],
    ];

    $data['content_lock']['langcode'] = [
      'title' => $this->t('Lock Language'),
      'help' => $this->t('Language of the lock'),
      'field' => [
        'id' => 'language',
      ],
      'sort' => [
        'id' => 'standard',
      ],
      'filter' => [
        'id' => 'language',
      ],
      'argument' => [
        'id' => 'language',
      ],
      'entity field' => 'langcode',
    ];

    $data['content_lock']['form_op'] = [
      'title' => $this->t('Lock Form Operation'),
      'help' => $this->t('Form operation of the lock'),
      'field' => [
        'id' => 'standard',
      ],
      'sort' => [
        'id' => 'standard',
      ],
      'filter' => [
        'id' => 'string',
      ],
      'argument' => [
        'id' => 'string',
      ],
    ];

    $data['content_lock']['is_locked'] = [
      'real field' => 'timestamp',
      'title' => $this->t('Is Locked'),
      'help' => $this->t('Whether the node is currently locked'),
      'field' => [
        'id' => 'boolean',
        'click sortable' => TRUE,
      ],
      'sort' => [
        'id' => 'content_lock_sort',
      ],
      'filter' => [
        'id' => 'content_lock_filter',
      ],
    ];

    // Break link.
    $data['content_lock']['break'] = [
      'title' => $this->t('Break link'),
      'help' => $this->t('Link to break the content lock.'),
      'field' => [
        'id' => 'content_lock_break_link',
        'real field' => 'entity_id',
      ],
    ];

    return $data;
  }

}

<?php

declare(strict_types = 1);

namespace Drupal\entity_share_server\Form;

use Drupal\Component\Utility\SortArray;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Url;
use Drupal\entity_share_server\Entity\ChannelInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Entity form for the channel entity.
 *
 * @package Drupal\entity_share_server\Form
 */
class ChannelForm extends EntityForm implements ContainerInjectionInterface {

  /**
   * The bundle infos from the website.
   *
   * @var array
   */
  protected $bundleInfos;

  /**
   * The renderer service.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * Constructs a ChannelForm object.
   *
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $entity_type_bundle_info
   *   The entity type bundle info service.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer service.
   */
  public function __construct(
    EntityTypeBundleInfoInterface $entity_type_bundle_info,
    RendererInterface $renderer
  ) {
    $this->bundleInfos = $entity_type_bundle_info->getAllBundleInfo();
    $this->renderer = $renderer;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.bundle.info'),
      $container->get('renderer')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);

    /** @var \Drupal\entity_share_server\Entity\ChannelInterface $channel */
    $channel = $this->entity;
    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Label'),
      '#maxlength' => 255,
      '#default_value' => $channel->label(),
      '#description' => $this->t('Label for the channel.'),
      '#required' => TRUE,
    ];

    $form['id'] = [
      '#type' => 'machine_name',
      '#default_value' => $channel->id(),
      '#machine_name' => [
        'exists' => '\Drupal\entity_share_server\Entity\Channel::load',
      ],
      '#disabled' => !$channel->isNew(),
    ];

    $form['channel_entity_type'] = [
      '#type' => 'select',
      '#title' => $this->t('Entity type'),
      '#options' => $this->getEntityTypeOptions(),
      '#empty_value' => '',
      '#default_value' => $channel->get('channel_entity_type'),
      '#required' => TRUE,
      '#ajax' => [
        'callback' => [get_class($this), 'buildAjaxBundleSelect'],
        'effect' => 'fade',
        'method' => 'replace',
        'wrapper' => 'bundle-wrapper',
      ],
    ];

    // Container for the AJAX.
    $form['bundle_wrapper'] = [
      '#type' => 'container',
      // Force an id because otherwise default id is changed when using AJAX.
      '#attributes' => [
        'id' => 'bundle-wrapper',
      ],
    ];
    $this->buildBundleSelect($form, $form_state);

    $this->buildLanguageSelect($form, $form_state);

    $form['channel_maxsize'] = [
      '#type' => 'number',
      '#title' => $this->t('Max size'),
      '#description' => $this->t("The JSON:API's page limit option to limit the number of entities per page."),
      '#default_value' => $channel->get('channel_maxsize'),
      '#min' => 1,
      '#max' => 50,
      '#required' => TRUE,
    ];

    $this->buildGroupsTable($form, $form_state);

    $this->buildFiltersTable($form, $form_state);

    $this->buildSearchesTable($form, $form_state);

    $this->buildSortsTable($form, $form_state);

    $this->buildAccessSection($form, $form_state);

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    /** @var \Drupal\entity_share_server\Entity\ChannelInterface $channel */
    $channel = $this->entity;

    $channel->set('access_by_permission', $form_state->getValue('access_by_permission'));
    $authorized_roles = array_filter($form_state->getValue('authorized_roles'));
    $channel->set('authorized_roles', $authorized_roles);
    $authorized_users = array_filter($form_state->getValue('authorized_users'));
    $channel->set('authorized_users', $authorized_users);

    // Sorts order.
    $channel_sorts = $channel->get('channel_sorts');
    if (is_null($channel_sorts)) {
      $channel_sorts = [];
    }
    $sorts = $form_state->getValue('sort_table');
    if (!is_null($sorts) && is_array($sorts)) {
      foreach ($sorts as $sort_id => $sort) {
        $channel_sorts[$sort_id]['weight'] = $sort['weight'];
      }
    }
    $channel->set('channel_sorts', $channel_sorts);

    $status = $channel->save();

    switch ($status) {
      case SAVED_NEW:
        $this->messenger()->addStatus($this->t('Created the %label Channel.', [
          '%label' => $channel->label(),
        ]));
        break;

      default:
        $this->messenger()->addStatus($this->t('Saved the %label Channel.', [
          '%label' => $channel->label(),
        ]));
    }
    $form_state->setRedirectUrl($channel->toUrl('collection'));
  }

  /**
   * Ajax callback.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state object.
   *
   * @return array
   *   Subform.
   */
  public static function buildAjaxBundleSelect(array $form, FormStateInterface $form_state) {
    // We just need to return the relevant part of the form here.
    return $form['bundle_wrapper'];
  }

  /**
   * Ajax callback.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state object.
   *
   * @return array
   *   Subform.
   */
  public static function buildAjaxLanguageSelect(array $form, FormStateInterface $form_state) {
    // We just need to return the relevant part of the form here.
    return $form['bundle_wrapper']['language_wrapper'];
  }

  /**
   * Generate bundle select.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state object.
   */
  protected function buildBundleSelect(array &$form, FormStateInterface $form_state) {
    /** @var \Drupal\entity_share_server\Entity\ChannelInterface $channel */
    $channel = $this->entity;
    $channel_entity_type = $channel->get('channel_entity_type');
    $selected_entity_type = $form_state->getValue('channel_entity_type');

    // No entity type selected and the channel does not have any.
    if (empty($selected_entity_type) && $channel_entity_type == '') {
      return;
    }

    if (!empty($selected_entity_type)) {
      $entity_type = $selected_entity_type;
    }
    else {
      $entity_type = $channel_entity_type;
    }

    $form['bundle_wrapper']['channel_bundle'] = [
      '#type' => 'select',
      '#title' => $this->t('Bundle'),
      '#options' => $this->getBundleOptions($entity_type),
      '#empty_value' => '',
      '#default_value' => $channel->get('channel_bundle'),
      '#required' => TRUE,
      '#ajax' => [
        'callback' => [get_class($this), 'buildAjaxLanguageSelect'],
        'effect' => 'fade',
        'method' => 'replace',
        'wrapper' => 'language-wrapper',
      ],
    ];
    // Container for the AJAX.
    $form['bundle_wrapper']['language_wrapper'] = [
      '#type' => 'container',
      // Force an id because otherwise default id is changed when using AJAX.
      '#attributes' => [
        'id' => 'language-wrapper',
      ],
    ];
  }

  /**
   * Generate language select.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state object.
   */
  protected function buildLanguageSelect(array &$form, FormStateInterface $form_state) {
    /** @var \Drupal\entity_share_server\Entity\ChannelInterface $channel */
    $channel = $this->entity;
    $channel_entity_type = $channel->get('channel_entity_type');
    $channel_bundle = $channel->get('channel_bundle');
    $selected_entity_type = $form_state->getValue('channel_entity_type');
    $selected_bundle = $form_state->getValue('channel_bundle');

    // No bundle selected and the channel does not have any.
    if (empty($selected_bundle) && $channel_bundle == '') {
      return;
    }

    if (!empty($selected_entity_type) && !empty($selected_bundle)) {
      $entity_type = $selected_entity_type;
      $bundle = $selected_bundle;
    }
    else {
      $entity_type = $channel_entity_type;
      $bundle = $channel_bundle;
    }

    // Check if the bundle is translatable.
    if (isset($this->bundleInfos[$entity_type][$bundle]['translatable']) && $this->bundleInfos[$entity_type][$bundle]['translatable']) {
      $form['bundle_wrapper']['language_wrapper']['channel_langcode'] = [
        '#type' => 'language_select',
        '#title' => $this->t('Language'),
        '#languages' => LanguageInterface::STATE_ALL,
        '#default_value' => $channel->get('channel_langcode'),
      ];
    }
    else {
      $form['bundle_wrapper']['language_wrapper']['channel_langcode'] = [
        '#type' => 'value',
        '#value' => LanguageInterface::LANGCODE_NOT_SPECIFIED,
      ];
    }
  }

  /**
   * Generate group form elements.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state object.
   *
   * @throws \LogicException
   * @throws \Exception
   */
  protected function buildGroupsTable(array &$form, FormStateInterface $form_state) {
    /** @var \Drupal\entity_share_server\Entity\ChannelInterface $channel */
    $channel = $this->entity;
    $channel_groups = $channel->get('channel_groups');
    if (is_null($channel_groups)) {
      $channel_groups = [];
    }

    $form['channel_groups'] = [
      '#type' => 'details',
      '#title' => $this->t('Groups'),
      '#open' => TRUE,
    ];

    if ($channel->isNew()) {
      $form['channel_groups']['group_message'] = [
        '#markup' => $this->t("It will be possible to add groups after the channel's creation."),
      ];
    }
    else {
      $form['channel_groups']['group_actions'] = [
        '#type' => 'actions',
        '#weight' => -5,
      ];
      $form['channel_groups']['group_actions']['group_add'] = [
        '#type' => 'link',
        '#title' => $this->t('Add a new group'),
        '#url' => Url::fromRoute('entity_share_server.group_add_form', [
          'channel' => $channel->id(),
        ]),
        '#attributes' => [
          'class' => [
            'button',
            'button--primary',
          ],
        ],
      ];

      $header = [
        'id' => ['data' => $this->t('ID')],
        'conjunction' => ['data' => $this->t('Conjunction')],
        'memberof' => ['data' => $this->t('Parent group')],
        'operations' => ['data' => $this->t('Operations')],
      ];

      $rows = [];
      foreach ($channel_groups as $group_id => $group) {
        $operations = [
          '#type' => 'dropbutton',
          '#links' => [
            'edit' => [
              'title' => $this->t('Edit'),
              'url' => Url::fromRoute('entity_share_server.group_edit_form', [
                'channel' => $channel->id(),
                'group' => $group_id,
              ]),
            ],
            'delete' => [
              'title' => $this->t('Delete'),
              'url' => Url::fromRoute('entity_share_server.group_delete_form', [
                'channel' => $channel->id(),
                'group' => $group_id,
              ]),
            ],
          ],
        ];

        $row = [
          'id' => $group_id,
          'conjunction' => $group['conjunction'],
          'memberof' => isset($group['memberof']) ? $group['memberof'] : '',
          'operations' => $this->renderer->render($operations),
        ];

        $rows[] = $row;
      }

      $form['channel_groups']['group_table'] = [
        '#theme' => 'table',
        '#header' => $header,
        '#rows' => $rows,
        '#empty' => $this->t('There is currently no group for this channel.'),
      ];
    }
  }

  /**
   * Generate filter form elements.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state object.
   *
   * @throws \LogicException
   * @throws \Exception
   */
  protected function buildFiltersTable(array &$form, FormStateInterface $form_state) {
    /** @var \Drupal\entity_share_server\Entity\ChannelInterface $channel */
    $channel = $this->entity;
    $channel_filters = $channel->get('channel_filters');
    if (is_null($channel_filters)) {
      $channel_filters = [];
    }

    $form['channel_filters'] = [
      '#type' => 'details',
      '#title' => $this->t('Filters'),
      '#open' => TRUE,
    ];

    if ($channel->isNew()) {
      $form['channel_filters']['filter_message'] = [
        '#markup' => $this->t("It will be possible to add filters after the channel's creation."),
      ];
    }
    else {
      $form['channel_filters']['filter_actions'] = [
        '#type' => 'actions',
        '#weight' => -5,
      ];
      $form['channel_filters']['filter_actions']['filter_add'] = [
        '#type' => 'link',
        '#title' => $this->t('Add a new filter'),
        '#url' => Url::fromRoute('entity_share_server.filter_add_form', [
          'channel' => $channel->id(),
        ]),
        '#attributes' => [
          'class' => [
            'button',
            'button--primary',
          ],
        ],
      ];

      $header = [
        'id' => ['data' => $this->t('ID')],
        'path' => ['data' => $this->t('Path')],
        'operator' => ['data' => $this->t('Operator')],
        'value' => ['data' => $this->t('Value')],
        'group' => ['data' => $this->t('Group')],
        'operations' => ['data' => $this->t('Operations')],
      ];

      $rows = [];
      foreach ($channel_filters as $filter_id => $filter) {
        $operations = [
          '#type' => 'dropbutton',
          '#links' => [
            'edit' => [
              'title' => $this->t('Edit'),
              'url' => Url::fromRoute('entity_share_server.filter_edit_form', [
                'channel' => $channel->id(),
                'filter' => $filter_id,
              ]),
            ],
            'delete' => [
              'title' => $this->t('Delete'),
              'url' => Url::fromRoute('entity_share_server.filter_delete_form', [
                'channel' => $channel->id(),
                'filter' => $filter_id,
              ]),
            ],
          ],
        ];

        $row = [
          'id' => $filter_id,
          'path' => $filter['path'],
          'operator' => $filter['operator'],
          'value' => '',
          'filter' => isset($filter['memberof']) ? $filter['memberof'] : '',
          'operations' => $this->renderer->render($operations),
        ];

        if (isset($filter['value'])) {
          $value = [
            '#theme' => 'item_list',
            '#items' => $filter['value'],
          ];
          $row['value'] = $this->renderer->render($value);
        }

        $rows[] = $row;
      }

      $form['channel_filters']['filter_table'] = [
        '#theme' => 'table',
        '#header' => $header,
        '#rows' => $rows,
        '#empty' => $this->t('There is currently no filter for this channel.'),
      ];
    }
  }

  /**
   * Generate search form elements.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state object.
   *
   * @throws \LogicException
   * @throws \Exception
   */
  protected function buildSearchesTable(array &$form, FormStateInterface $form_state) {
    /** @var \Drupal\entity_share_server\Entity\ChannelInterface $channel */
    $channel = $this->entity;
    $channel_searches = $channel->get('channel_searches');
    if (is_null($channel_searches)) {
      $channel_searches = [];
    }

    $form['channel_searches'] = [
      '#type' => 'details',
      '#title' => $this->t('Searches'),
      '#open' => TRUE,
    ];

    // Add a warning message.
    $form['channel_searches']['warning_message'] = [
      '#theme' => 'status_messages',
      '#message_list' => [
        'warning' => [
          $this->t('The label of the entity if it exists is automatically searchable. Do not add a search for that.'),
        ],
      ],
    ];

    if ($channel->isNew()) {
      $form['channel_searches']['search_message'] = [
        '#markup' => $this->t("It will be possible to add searches after the channel's creation."),
      ];
    }
    else {
      $form['channel_searches']['search_actions'] = [
        '#type' => 'actions',
        '#weight' => -5,
      ];
      $form['channel_searches']['search_actions']['search_add'] = [
        '#type' => 'link',
        '#title' => $this->t('Add a new search'),
        '#url' => Url::fromRoute('entity_share_server.search_add_form', [
          'channel' => $channel->id(),
        ]),
        '#attributes' => [
          'class' => [
            'button',
            'button--primary',
          ],
        ],
      ];

      $header = [
        'id' => ['data' => $this->t('ID')],
        'path' => ['data' => $this->t('Path')],
        'label' => ['data' => $this->t('Label')],
        'operations' => ['data' => $this->t('Operations')],
      ];

      $rows = [];
      foreach ($channel_searches as $search_id => $search) {
        $operations = [
          '#type' => 'dropbutton',
          '#links' => [
            'edit' => [
              'title' => $this->t('Edit'),
              'url' => Url::fromRoute('entity_share_server.search_edit_form', [
                'channel' => $channel->id(),
                'search' => $search_id,
              ]),
            ],
            'delete' => [
              'title' => $this->t('Delete'),
              'url' => Url::fromRoute('entity_share_server.search_delete_form', [
                'channel' => $channel->id(),
                'search' => $search_id,
              ]),
            ],
          ],
        ];

        $row = [
          'id' => $search_id,
          'path' => $search['path'],
          'label' => $search['label'],
          'operations' => $this->renderer->render($operations),
        ];

        if (isset($search['value'])) {
          $value = [
            '#theme' => 'item_list',
            '#items' => $search['value'],
          ];
          $row['value'] = $this->renderer->render($value);
        }

        $rows[] = $row;
      }

      $form['channel_searches']['search_table'] = [
        '#theme' => 'table',
        '#header' => $header,
        '#rows' => $rows,
        '#empty' => $this->t('There is currently no search for this channel.'),
      ];
    }
  }

  /**
   * Generate sort form elements.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state object.
   */
  protected function buildSortsTable(array &$form, FormStateInterface $form_state) {
    /** @var \Drupal\entity_share_server\Entity\ChannelInterface $channel */
    $channel = $this->entity;
    $channel_sorts = $channel->get('channel_sorts');
    if (is_null($channel_sorts)) {
      $channel_sorts = [];
    }

    $form['channel_sorts'] = [
      '#type' => 'details',
      '#title' => $this->t('sorts'),
      '#open' => TRUE,
    ];

    if ($channel->isNew()) {
      $form['channel_sorts']['sort_message'] = [
        '#markup' => $this->t("It will be possible to add sorts after the channel's creation."),
      ];
    }
    else {
      $form['channel_sorts']['sort_actions'] = [
        '#type' => 'actions',
        '#weight' => -5,
      ];
      $form['channel_sorts']['sort_actions']['sort_add'] = [
        '#type' => 'link',
        '#title' => $this->t('Add a new sort'),
        '#url' => Url::fromRoute('entity_share_server.sort_add_form', [
          'channel' => $channel->id(),
        ]),
        '#attributes' => [
          'class' => [
            'button',
            'button--primary',
          ],
        ],
      ];

      $header = [
        'id' => ['data' => $this->t('ID')],
        'path' => ['data' => $this->t('Path')],
        'direction' => ['data' => $this->t('Direction')],
        'weight' => ['data' => $this->t('Weight')],
        'operations' => ['data' => $this->t('Operations')],
      ];

      $form['channel_sorts']['sort_table'] = [
        '#type' => 'table',
        '#header' => $header,
        '#empty' => $this->t('There is currently no sort for this channel.'),
        '#tabledrag' => [
          [
            'action' => 'order',
            'relationship' => 'sibling',
            'group' => 'weight',
          ],
        ],
      ];

      uasort($channel_sorts, [SortArray::class, 'sortByWeightElement']);
      foreach ($channel_sorts as $sort_id => $sort) {
        $row = [
          '#attributes' => [
            'class' => [
              'draggable',
            ],
          ],
          'id' => [
            '#markup' => $sort_id,
          ],
          'path' => [
            '#markup' => $sort['path'],
          ],
          'direction' => [
            '#markup' => $sort['direction'],
          ],
          'weight' => [
            '#type' => 'weight',
            '#title' => $this->t('Weight'),
            '#title_display' => 'invisible',
            '#default_value' => $sort['weight'],
            '#attributes' => ['class' => ['weight']],
          ],
          'operations' => [
            '#type' => 'dropbutton',
            '#links' => [
              'edit' => [
                'title' => $this->t('Edit'),
                'url' => Url::fromRoute('entity_share_server.sort_edit_form', [
                  'channel' => $channel->id(),
                  'sort' => $sort_id,
                ]),
              ],
              'delete' => [
                'title' => $this->t('Delete'),
                'url' => Url::fromRoute('entity_share_server.sort_delete_form', [
                  'channel' => $channel->id(),
                  'sort' => $sort_id,
                ]),
              ],
            ],
          ],
        ];

        $form['channel_sorts']['sort_table'][$sort_id] = $row;
      }
    }
  }

  /**
   * Generate access section.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state object.
   */
  protected function buildAccessSection(array &$form, FormStateInterface $form_state) {
    /** @var \Drupal\entity_share_server\Entity\ChannelInterface $channel */
    $channel = $this->entity;

    $form['access_section'] = [
      '#type' => 'details',
      '#title' => $this->t('Access'),
      '#open' => TRUE,
    ];

    $form['access_section']['access_by_permission'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('All users with the %entity_share_server_access_channels permission.', [
        '%entity_share_server_access_channels' => $this->t('Access channels list'),
      ]),
      '#default_value' => $channel->get('access_by_permission'),
    ];

    $authorized_roles = $channel->get('authorized_roles');
    $form['access_section']['authorized_roles'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Authorized roles'),
      '#description' => $this->t('Only roles with the %entity_share_server_access_channels permission are listed.', [
        '%entity_share_server_access_channels' => $this->t('Access channels list'),
      ]),
      '#options' => $this->getAuthorizedRolesOptions(),
      '#default_value' => !is_null($authorized_roles) ? $authorized_roles : [],
    ];

    $authorized_users = $channel->get('authorized_users');
    $form['access_section']['authorized_users'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Authorized users'),
      '#description' => $this->t('Only users with the %entity_share_server_access_channels permission are listed.', [
        '%entity_share_server_access_channels' => $this->t('Access channels list'),
      ]),
      '#options' => $this->getAuthorizedUsersOptions(),
      '#default_value' => !is_null($authorized_users) ? $authorized_users : [],
    ];
  }

  /**
   * Get the entity type options.
   *
   * @return array
   *   An array of options.
   */
  protected function getEntityTypeOptions() {
    $options = [];

    $definitions = $this->entityTypeManager->getDefinitions();
    foreach ($definitions as $entity_type_id => $definition) {
      // Keep only content entity type without user.
      if ($definition->getGroup() != 'content' || $entity_type_id == 'user') {
        continue;
      }
      // Keep only content entity type with UUID (required for JSON API).
      if (!$definition->hasKey('uuid')) {
        continue;
      }

      $options[$entity_type_id] = $definition->getLabel();
    }
    asort($options);

    return $options;
  }

  /**
   * Get the bundle options.
   *
   * @param string $selected_entity_type
   *   The entity type.
   *
   * @return array
   *   An array of options.
   */
  protected function getBundleOptions($selected_entity_type) {
    $options = [];
    foreach ($this->bundleInfos[$selected_entity_type] as $bundle_id => $bundle_info) {
      $options[$bundle_id] = $bundle_info['label'];
    }
    return $options;
  }

  /**
   * Get roles with the permission entity_share_server_access_channels.
   *
   * @return array
   *   An array of options.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   */
  protected function getAuthorizedRolesOptions() {
    $authorized_roles = [];

    // Filter on roles having access to the channel list.
    /** @var \Drupal\user\RoleInterface[] $roles */
    $roles = $this->entityTypeManager
      ->getStorage('user_role')
      ->loadMultiple();
    foreach ($roles as $role) {
      if ($role->hasPermission(ChannelInterface::CHANNELS_ACCESS_PERMISSION)) {
        $authorized_roles[$role->id()] = $role->label();
      }
    }

    return $authorized_roles;
  }

  /**
   * Get users with the permission entity_share_server_access_channels.
   *
   * @return array
   *   An array of options.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   */
  protected function getAuthorizedUsersOptions() {
    $authorized_users = [];
    $authorized_roles = $this->getAuthorizedRolesOptions();
    $users = [];

    if (!empty($authorized_roles)) {
      $authorized_roles = array_keys($authorized_roles);
      $users = $this->entityTypeManager
        ->getStorage('user')
        ->loadByProperties(['roles' => $authorized_roles]);
    }

    foreach ($users as $user) {
      $authorized_users[$user->uuid()] = $user->label();
    }

    return $authorized_users;
  }

}

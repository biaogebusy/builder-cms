<?php

declare(strict_types = 1);

namespace Drupal\entity_share_client\Form;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Component\Render\MarkupInterface;
use Drupal\Component\Serialization\Json;
use Drupal\Component\Utility\UrlHelper;
use Drupal\Component\Utility\Xss;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Link;
use Drupal\Core\Pager\PagerManagerInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\entity_share\EntityShareUtility;
use Drupal\entity_share_client\Exception\ResourceTypeNotFoundException;
use Drupal\entity_share_client\ImportContext;
use Drupal\entity_share_client\Service\FormHelperInterface;
use Drupal\entity_share_client\Service\ImportServiceInterface;
use Drupal\entity_share_client\Service\RemoteManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Form controller to pull entities.
 */
class PullForm extends FormBase {

  /**
   * The remote websites known from the website.
   *
   * @var \Drupal\entity_share_client\Entity\RemoteInterface[]
   */
  protected $remoteWebsites;

  /**
   * Channel infos as returned by entity_share_server entry point.
   *
   * @var array
   */
  protected $channelsInfos;

  /**
   * Field mappings as returned by entity_share_server entry point.
   *
   * @var array
   */
  protected $fieldMappings;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The remote manager.
   *
   * @var \Drupal\entity_share_client\Service\RemoteManagerInterface
   */
  protected $remoteManager;

  /**
   * The form helper.
   *
   * @var \Drupal\entity_share_client\Service\FormHelperInterface
   */
  protected $formHelper;

  /**
   * Query string parameters ($_GET).
   *
   * @var \Symfony\Component\HttpFoundation\ParameterBag
   */
  protected $query;

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * The renderer service.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * The module handler service.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The import service.
   *
   * @var \Drupal\entity_share_client\Service\ImportServiceInterface
   */
  protected $importService;

  /**
   * The pager manager service.
   *
   * @var \Drupal\Core\Pager\PagerManagerInterface
   */
  protected $pagerManager;

  /**
   * The max size.
   *
   * @var int
   */
  protected $maxSize = 50;

  /**
   * Constructs a ContentEntityForm object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\entity_share_client\Service\RemoteManagerInterface $remote_manager
   *   The remote manager service.
   * @param \Drupal\entity_share_client\Service\FormHelperInterface $form_helper
   *   The form helper service.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The request stack.
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   The language manager.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer service.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler service.
   * @param \Drupal\entity_share_client\Service\ImportServiceInterface $import_service
   *   The import service.
   * @param \Drupal\Core\Pager\PagerManagerInterface $pager_manager
   *   The pager manager service.
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    RemoteManagerInterface $remote_manager,
    FormHelperInterface $form_helper,
    RequestStack $request_stack,
    LanguageManagerInterface $language_manager,
    RendererInterface $renderer,
    ModuleHandlerInterface $module_handler,
    ImportServiceInterface $import_service,
    PagerManagerInterface $pager_manager
  ) {
    $this->entityTypeManager = $entity_type_manager;
    $this->remoteWebsites = $entity_type_manager
      ->getStorage('remote')
      ->loadMultiple();
    $this->remoteManager = $remote_manager;
    $this->formHelper = $form_helper;
    $this->query = $request_stack->getCurrentRequest()->query;
    $this->languageManager = $language_manager;
    $this->renderer = $renderer;
    $this->moduleHandler = $module_handler;
    $this->importService = $import_service;
    $this->pagerManager = $pager_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('entity_share_client.remote_manager'),
      $container->get('entity_share_client.form_helper'),
      $container->get('request_stack'),
      $container->get('language_manager'),
      $container->get('renderer'),
      $container->get('module_handler'),
      $container->get('entity_share_client.import_service'),
      $container->get('pager.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'entity_share_client_pull_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    // Build the Import configuration selector.
    $select_element = $this->buildSelectElement($form_state, 'import_config');
    if ($select_element) {
      $select_element['#title'] = $this->t('Import configuration');
      $select_element['#ajax'] = [
        'callback' => [get_class($this), 'buildAjaxChannelSelect'],
        'effect' => 'fade',
        'method' => 'replace',
        'wrapper' => 'channel-wrapper',
      ];
      $form['import_config'] = $select_element;
    }
    else {
      $url = Url::fromRoute('entity.import_config.collection');
      if ($url->renderAccess($url->toRenderArray())) {
        $this->messenger()
          ->addError($this->t('Please configure <a href=":url">Import configuration</a> before trying to import content.', [':url' => $url->toString()]));
      }
      else {
        $this->messenger()
          ->addError($this->t('There are no "Import configuration" available. Please contact the website administrator.'));
      }
      return;
    }

    // Build the Remote selector.
    $select_element = $this->buildSelectElement($form_state, 'remote');
    if ($select_element) {
      $select_element['#title'] = $this->t('Remote website');
      $select_element['#ajax'] = [
        'callback' => [get_class($this), 'buildAjaxChannelSelect'],
        'effect' => 'fade',
        'method' => 'replace',
        'wrapper' => 'channel-wrapper',
      ];
      $form['remote'] = $select_element;
    }

    // Container for the AJAX.
    $form['channel_wrapper'] = [
      '#type' => 'container',
      // Force an id because otherwise default id is changed when using AJAX.
      '#attributes' => [
        'id' => 'channel-wrapper',
      ],
    ];
    $this->buildChannelSelect($form, $form_state);

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $additional_query_parameters = [];
    $get_offset = $this->query->get('offset');
    if (is_numeric($get_offset)) {
      $additional_query_parameters['offset'] = $get_offset;
    }
    $get_page = $this->query->get('page');
    if (is_numeric($get_page)) {
      $additional_query_parameters['page'] = $get_page;
    }
    $get_sort = $this->query->get('sort', '');
    if (!empty($get_sort)) {
      $additional_query_parameters['sort'] = $get_sort;
    }
    $get_order = $this->query->get('order', '');
    if (!empty($get_order)) {
      $additional_query_parameters['order'] = $get_order;
    }
    $this->setFormRedirect($form_state, $additional_query_parameters);
  }

  /**
   * Ensure at least one entity is selected.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function validateSelectedEntities(array &$form, FormStateInterface $form_state) {
    $selected_entities = $form_state->getValue('entities');
    if (!is_null($selected_entities)) {
      $selected_entities = array_filter($selected_entities);
      if (empty($selected_entities)) {
        $form_state->setErrorByName('entities', $this->t('You must select at least one entity.'));
      }
    }
  }

  /**
   * Form submission handler for the 'synchronize' action.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function synchronizeSelectedEntities(array &$form, FormStateInterface $form_state) {
    $selected_entities = $form_state->getValue('entities');
    $selected_entities = array_filter($selected_entities);
    $remote_id = $form_state->getValue('remote');
    $channel_id = $form_state->getValue('channel');
    $import_config_id = $form_state->getValue('import_config');
    $import_context = new ImportContext($remote_id, $channel_id, $import_config_id);
    $this->importService->importEntities($import_context, array_values($selected_entities));
  }

  /**
   * Form submission handler for the 'Import the whole channel' action.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function importChannel(array &$form, FormStateInterface $form_state) {
    $storage = $form_state->getStorage();
    if (isset($storage['remote_channel_count'])) {
      $remote_id = $form_state->getValue('remote');
      $channel_id = $form_state->getValue('channel');
      $import_config_id = $form_state->getValue('import_config');
      $import_context = new ImportContext($remote_id, $channel_id, $import_config_id);
      $import_context->setRemoteChannelCount($storage['remote_channel_count']);
      $this->importService->importChannel($import_context);
    }
  }

  /**
   * Helper function.
   *
   * @param string $field
   *   The form field key.
   *
   * @return string[]
   *   An array of options for a given select list.
   */
  protected function getSelectOptions(string $field) {
    $options = [];
    switch ($field) {
      case 'remote':
        // An array of remote websites.
        foreach ($this->remoteWebsites as $id => $remote_website) {
          $options[$id] = $remote_website->label();
        }
        break;

      case 'channel':
        // An array of remote channels.
        foreach ($this->channelsInfos as $channel_id => $channel_infos) {
          $options[$channel_id] = $channel_infos['label'];
        }
        break;

      case 'import_config':
        // An array of import configs.
        $import_configs = $this->entityTypeManager->getStorage('import_config')
          ->loadMultiple();
        foreach ($import_configs as $import_config) {
          $options[$import_config->id()] = $import_config->label();
        }
        break;
    }
    return $options;
  }

  /**
   * Builds a required select element, disabled if only one option exists.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state object.
   * @param string $field
   *   The form field key.
   *
   * @return array
   *   The Drupal form element array, or an empty array if field is invalid.
   */
  protected function buildSelectElement(FormStateInterface $form_state, string $field) {
    // Get all available options for this field.
    $options = $this->getSelectOptions($field);
    // Sanity check for a valid $field parameter.
    if (!$options) {
      return [];
    }
    $disabled = FALSE;
    $default_value = $this->query->get($field);

    // If only one option, pre-select it and disable the select.
    if (count($options) == 1) {
      $disabled = TRUE;
      $default_value = key($options);
      $form_state->setValue($field, $default_value);
    }
    return [
      '#type' => 'select',
      '#options' => $options,
      '#default_value' => $default_value,
      '#empty_value' => '',
      '#required' => TRUE,
      '#disabled' => $disabled,
    ];
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
  public static function buildAjaxChannelSelect(array $form, FormStateInterface $form_state) {
    // We just need to return the relevant part of the form here.
    return $form['channel_wrapper'];
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
  public static function buildAjaxEntitiesSelectTable(array $form, FormStateInterface $form_state) {
    // We just need to return the relevant part of the form here.
    return $form['channel_wrapper']['entities_wrapper'];
  }

  /**
   * Helper function to generate channel select.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state object.
   */
  protected function buildChannelSelect(array &$form, FormStateInterface $form_state) {
    $selected_remote_id = $form_state->getValue('remote', $this->query->get('remote'));
    // No remote selected.
    if (empty($this->remoteWebsites[$selected_remote_id])) {
      return;
    }

    $selected_remote = $this->remoteWebsites[$selected_remote_id];

    try {
      $this->channelsInfos = $this->remoteManager->getChannelsInfos($selected_remote, [
        'rethrow' => TRUE,
      ]);
    }
    catch (\Throwable $exception) {
      $this->displayError($exception->getMessage());
      return;
    }

    $select_element = $this->buildSelectElement($form_state, 'channel');
    if ($select_element) {
      $select_element['#title'] = $this->t('Channel');
      $select_element['#ajax'] = [
        'callback' => [get_class($this), 'buildAjaxEntitiesSelectTable'],
        'effect' => 'fade',
        'method' => 'replace',
        'wrapper' => 'entities-wrapper',
      ];
      $form['channel_wrapper']['channel'] = $select_element;
    }

    if (empty($form['channel_wrapper']['channel']['#options'])) {
      if ($this->currentUser()->hasPermission('administer_remote_entity')) {
        $this->messenger()->addWarning($this->t('The selected website is returning no channels. Check that they are defined, and that this website has permission to access channels on the remote website.'));
      }
      else {
        $this->messenger()->addWarning($this->t('The selected website is returning no channels.'));
      }
    }

    // Container for the AJAX.
    $form['channel_wrapper']['entities_wrapper'] = [
      '#type' => 'container',
      // Force an id because otherwise default id is changed when using AJAX.
      '#attributes' => [
        'id' => 'entities-wrapper',
      ],
    ];
    $this->buildEntitiesSelectTable($form, $form_state);
  }

  /**
   * Helper function to generate entities table.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state object.
   */
  protected function buildEntitiesSelectTable(array &$form, FormStateInterface $form_state) {
    $triggering_element = $form_state->getTriggeringElement();
    // Form state by default, else from query.
    $selected_import_config = $form_state->getValue('import_config', $this->query->get('import_config'));
    $selected_remote_id = $form_state->getValue('remote', $this->query->get('remote'));
    $selected_channel = $form_state->getValue('channel', $this->query->get('channel'));

    if (
      empty($this->remoteWebsites[$selected_remote_id]) ||
      empty($this->channelsInfos[$selected_channel]) ||
      $selected_import_config == NULL
    ) {
      return;
    }

    // At this step we have the channels infos, the selected channel and the
    // selected import config, so we can "compute" the max size.
    /** @var \Drupal\entity_share_client\Entity\ImportConfigInterface $import_config */
    $import_config = $this->entityTypeManager->getStorage('import_config')->load($selected_import_config);
    if ($import_config == NULL) {
      $this->messenger()->addError($this->t('The selected import config @selected_import_config is not available.', [
        '@selected_import_config' => $selected_import_config,
      ]));
      return;
    }

    $this->maxSize = EntityShareUtility::getMaxSize($import_config, $selected_channel, $this->channelsInfos);

    // If Ajax was triggered set offset to default value: 0.
    $offset = !is_array($triggering_element) ? $this->query->get('offset', 0) : 0;
    if (!is_array($triggering_element) && is_numeric($this->query->get('page'))) {
      $offset = $this->query->get('page') * $this->maxSize;
    }

    $channel_entity_type = $this->channelsInfos[$selected_channel]['channel_entity_type'];
    $channel_bundle = $this->channelsInfos[$selected_channel]['channel_bundle'];
    $selected_remote = $this->remoteWebsites[$selected_remote_id];
    $this->fieldMappings = $this->remoteManager->getfieldMappings($selected_remote);
    $entity_storage = $this->entityTypeManager->getStorage($channel_entity_type);
    $entity_keys = $entity_storage->getEntityType()->getKeys();

    $parsed_url = UrlHelper::parse($this->channelsInfos[$selected_channel]['url']);
    // Add offset to the selected channel.
    $parsed_url['query']['page']['offset'] = $offset;
    // Handle search.
    $searched_text = $form_state->getValue('search', '');
    if (empty($searched_text)) {
      $get_searched_text = $this->query->get('search', '');
      // If it is not an ajax trigger, check if it is in the GET parameters.
      if (!is_array($triggering_element) && !empty($get_searched_text)) {
        $searched_text = $get_searched_text;
      }
    }
    if (!empty($searched_text)) {
      $search_filter_and_group = [
        'channel_searched_text_group' => [
          'group' => [
            'conjunction' => 'OR',
          ],
        ],
      ];
      foreach ($this->channelsInfos[$selected_channel]['search_configuration'] as $search_key => $search_info) {
        $search_filter_and_group['search_filter_' . $search_key] = [
          'condition' => [
            'path' => $search_info['path'],
            'operator' => 'CONTAINS',
            'value' => $searched_text,
            'memberOf' => 'channel_searched_text_group',
          ],
        ];
      }
      $parsed_url['query']['filter'] = isset($parsed_url['query']['filter']) ? array_merge_recursive($parsed_url['query']['filter'], $search_filter_and_group) : $search_filter_and_group;
    }
    // Change the sort if a sort had been selected.
    $sort_field = $this->query->get('order', '');
    $sort_direction = $this->query->get('sort', '');
    $sort_context = [
      'name' => $sort_field,
      'sort' => $sort_direction,
      'query' => [
        'remote' => $selected_remote_id,
        'channel' => $selected_channel,
        'import_config' => $selected_import_config,
        'search' => $searched_text,
      ],
    ];

    if (!empty($sort_field) && !empty($sort_direction) && isset($this->fieldMappings[$channel_entity_type][$channel_bundle][$sort_field])) {
      $parsed_url['query']['sort'] = [
        $sort_field => [
          'path' => $this->fieldMappings[$channel_entity_type][$channel_bundle][$sort_field],
          'direction' => strtoupper($sort_direction),
        ],
      ];
    }
    $query = UrlHelper::buildQuery($parsed_url['query']);
    $prepared_url = $parsed_url['path'] . '?' . $query;

    try {
      $response = $this->remoteManager->jsonApiRequest($selected_remote, 'GET', $prepared_url, [
        'rethrow' => TRUE,
      ]);
    }
    catch (\Throwable $exception) {
      $this->displayError($exception->getMessage());
      return;
    }
    $json = Json::decode((string) $response->getBody());

    // Apply max size to response data.
    $json['data'] = array_slice($json['data'], 0, $this->maxSize);

    // Search.
    $form['channel_wrapper']['entities_wrapper']['search'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Search'),
      '#default_value' => $searched_text,
      '#weight' => -20,
      '#ajax' => [
        'callback' => [get_class($this), 'buildAjaxEntitiesSelectTable'],
        'disable-refocus' => TRUE,
        'effect' => 'fade',
        'keypress' => TRUE,
        'method' => 'replace',
        'wrapper' => 'entities-wrapper',
      ],
    ];
    if (isset($this->channelsInfos[$selected_channel]['search_configuration']) && !empty($this->channelsInfos[$selected_channel]['search_configuration'])) {
      $search_list = [
        '#theme' => 'item_list',
        '#items' => [],
      ];
      foreach ($this->channelsInfos[$selected_channel]['search_configuration'] as $search_info) {
        $search_list['#items'][] = $search_info['label'];
      }
      $search_list = $this->renderer->render($search_list);
      $search_description = $this->t('The search (CONTAINS operator) will occur on the following fields:') . $search_list;
    }
    else {
      $search_description = $this->t('There is no field on the server site to search on this channel.');
    }
    $form['channel_wrapper']['entities_wrapper']['search']['#description'] = $search_description;

    // Full pager.
    if (isset($json['meta']['count'])) {
      $this->pagerManager->createPager((int) $json['meta']['count'], $this->maxSize);
      $form['channel_wrapper']['entities_wrapper']['pager'] = [
        '#type' => 'pager',
        '#route_name' => 'entity_share_client.admin_content_pull_form',
        '#parameters' => [
          'remote' => $selected_remote_id,
          'channel' => $selected_channel,
          'import_config' => $selected_import_config,
          'search' => $searched_text,
          'order' => $sort_field,
          'sort' => $sort_direction,
        ],
        '#attached' => [
          'library' => [
            'entity_share_client/full-pager',
          ],
        ],
      ];
    }
    // Basic pager.
    else {
      // Store the JSON:API links to use it in the pager submit handlers.
      $storage = $form_state->getStorage();
      $storage['links'] = $json['links'];
      $form_state->setStorage($storage);

      // Pager.
      $form['channel_wrapper']['entities_wrapper']['pager'] = [
        '#type' => 'actions',
        '#weight' => -10,
      ];
      $pager_links = $this->getBasicPagerLinks();
      foreach ($pager_links as $key => $label) {
        if (isset($json['links'][$key]['href'])) {
          $form['channel_wrapper']['entities_wrapper']['pager'][$key] = [
            '#type' => 'submit',
            '#value' => $label,
            '#submit' => ['::goToPage'],
          ];
        }
      }
    }

    if (!empty($sort_field) || !empty($sort_direction)) {
      $form['channel_wrapper']['entities_wrapper']['reset_sort'] = [
        '#type' => 'actions',
        '#weight' => -15,
        'reset_sort' => [
          '#type' => 'submit',
          '#value' => $this->t('Reset sort'),
          '#submit' => ['::resetSort'],
        ],
      ];
    }

    $label_header_machine_name = isset($entity_keys['label']) ? $entity_keys['label'] : 'label';

    // Table to select entities.
    $header = [
      'label' => $this->getHeader($this->t('Label'), $label_header_machine_name, $sort_context),
      'type' => $this->t('Type'),
      'bundle' => $this->t('Bundle'),
      'language' => $this->t('Language'),
      'changed' => $this->getHeader($this->t('Remote entity changed date'), 'changed', $sort_context),
      'status' => $this->t('Status'),
      'policy' => $this->t('Policy'),
    ];

    $entities_options = [];
    try {
      $entities_options = $this->formHelper->buildEntitiesOptions($json['data'], $selected_remote, $selected_channel);
    }
    catch (ResourceTypeNotFoundException $exception) {
      $this->displayError($exception->getMessage());
    }

    $form['channel_wrapper']['entities_wrapper']['entities'] = [
      '#type' => 'tableselect',
      '#header' => $header,
      '#options' => $entities_options,
      '#empty' => $this->t('No entities to be pulled have been found.'),
      '#attached' => [
        'library' => [
          'entity_share_client/admin',
        ],
      ],
    ];
    if ($this->moduleHandler->moduleExists('entity_share_diff')) {
      $form['channel_wrapper']['entities_wrapper']['entities']['#attached']['library'][] = 'core/drupal.dialog.ajax';
    }

    // Actions bottom.
    $form['channel_wrapper']['entities_wrapper']['actions_bottom']['#type'] = 'actions';
    // The button for importing selected entities always appears.
    $synchronize_button = [
      '#type' => 'submit',
      '#value' => $this->t('Synchronize entities'),
      '#button_type' => 'primary',
      '#validate' => ['::validateSelectedEntities'],
      '#submit' => [
        '::submitForm',
        '::synchronizeSelectedEntities',
      ],
    ];
    $form['channel_wrapper']['entities_wrapper']['actions_bottom']['synchronize'] = $synchronize_button;
    // The button for importing the whole channel appears only if JSON:API
    // option "Include count in collection queries" is activated on remote.
    if (isset($json['meta']['count'])) {
      $import_channel_button = [
        '#type' => 'submit',
        '#value' => $this->t('Import the whole channel'),
        '#button_type' => 'primary',
        '#submit' => [
          '::submitForm',
          '::importChannel',
        ],
      ];
      $form['channel_wrapper']['entities_wrapper']['actions_bottom']['import_channel'] = $import_channel_button;
      // Remember the remote channel count.
      $storage = $form_state->getStorage();
      $storage['remote_channel_count'] = (int) $json['meta']['count'];
      $form_state->setStorage($storage);
    }

    // Actions on top are the same as the actions at the bottom.
    $form['channel_wrapper']['entities_wrapper']['actions_top'] = $form['channel_wrapper']['entities_wrapper']['actions_bottom'];
    $form['channel_wrapper']['entities_wrapper']['actions_top']['#weight'] = -1;
  }

  /**
   * Helper function.
   *
   * Prepare a header sortable link.
   *
   * Inspired from \Drupal\Core\Utility\TableSort::header().
   *
   * @param \Drupal\Component\Render\MarkupInterface $header
   *   The header label.
   * @param string $header_machine_name
   *   The header machine name.
   * @param array $context
   *   The context of sort.
   *
   * @return array
   *   A sort link to be put in a table header.
   */
  protected function getHeader(MarkupInterface $header, $header_machine_name, array $context) {
    $cell = [];

    $title = new TranslatableMarkup('sort by @s', ['@s' => $header]);
    if ($header_machine_name == $context['name']) {
      // aria-sort is a WAI-ARIA property that indicates if items in a table
      // or grid are sorted in ascending or descending order. See
      // http://www.w3.org/TR/wai-aria/states_and_properties#aria-sort
      $cell['aria-sort'] = ($context['sort'] == 'asc') ? 'ascending' : 'descending';
      $context['sort'] = (($context['sort'] == 'asc') ? 'desc' : 'asc');
      $cell['class'][] = 'is-active';
      $tablesort_indicator = [
        '#theme' => 'tablesort_indicator',
        '#style' => $context['sort'],
      ];
      $image = $this->renderer->render($tablesort_indicator);
    }
    else {
      // If the user clicks a different header, we want to sort ascending
      // initially.
      $context['sort'] = 'asc';
      $image = '';
    }
    $cell['data'] = Link::createFromRoute(new FormattableMarkup('@cell_content@image', [
      '@cell_content' => $header,
      '@image' => $image,
    ]), '<current>', [], [
      'attributes' => ['title' => $title],
      'query' => array_merge($context['query'], [
        'sort' => $context['sort'],
        'order' => $header_machine_name,
      ]),
    ]);

    return $cell;
  }

  /**
   * Helper function to get simple pager's link ID's and labels.
   *
   * @return array
   *   Array keyed by link ID's with labels as values.
   */
  protected function getBasicPagerLinks() {
    return [
      'first' => $this->t('First'),
      'prev' => $this->t('Previous'),
      'next' => $this->t('Next'),
      'last' => $this->t('Last'),
    ];
  }

  /**
   * Form submission handler to go to pager link: first, previous, next, last.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function goToPage(array &$form, FormStateInterface $form_state) {
    $triggering_element = $form_state->getTriggeringElement();
    $link_name = current($triggering_element['#parents']);
    if ($link_name !== FALSE && array_key_exists($link_name, $this->getBasicPagerLinks())) {
      $this->pagerRedirect($form_state, $link_name);
    }
  }

  /**
   * Helper function to redirect with the form to right page to handle pager.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   * @param string $link_name
   *   The link name. Possibles values: first, prev, next, last.
   */
  protected function pagerRedirect(FormStateInterface $form_state, $link_name) {
    $storage = $form_state->getStorage();
    if (isset($storage['links'][$link_name]['href'])) {
      $parsed_url = UrlHelper::parse($storage['links'][$link_name]['href']);
      if (isset($parsed_url['query']['page']) && isset($parsed_url['query']['page']['offset'])) {
        $offset = $parsed_url['query']['page']['offset'];
        // In the case of the basic pager, previous and next links' offset needs
        // to manually be set.
        if ($link_name == 'prev') {
          $get_offset = $this->query->get('offset', 0);
          $offset = $get_offset - $this->maxSize;
        }
        elseif ($link_name == 'next') {
          $get_offset = $this->query->get('offset', 0);
          $offset = $get_offset + $this->maxSize;
        }

        $additional_query_parameters = [
          'offset' => $offset,
          'order' => $this->query->get('order', ''),
          'sort' => $this->query->get('sort', ''),
        ];
        $this->setFormRedirect($form_state, $additional_query_parameters);
      }
    }
  }

  /**
   * Helper function to redirect the form.
   *
   * By default it provides some parameters coming from the form state:
   *   - Remote
   *   - Channel
   *   - Import config
   *   - Search keyword.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   * @param array $additional_query_parameters
   *   Array of additional query parameters, if needed.
   */
  protected function setFormRedirect(FormStateInterface $form_state, array $additional_query_parameters = []) {
    $query_parameters = [
      'remote' => $form_state->getValue('remote'),
      'channel' => $form_state->getValue('channel'),
      'import_config' => $form_state->getValue('import_config'),
      'search' => $form_state->getValue('search', ''),
    ];
    if ($additional_query_parameters) {
      $query_parameters = array_merge($query_parameters, $additional_query_parameters);
    }
    $form_state->setRedirect('entity_share_client.admin_content_pull_form', [], ['query' => $query_parameters]);
  }

  /**
   * Form submission handler to reset the sort.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function resetSort(array &$form, FormStateInterface $form_state) {
    $this->setFormRedirect($form_state);
  }

  /**
   * Display the exception message.
   *
   * @param string $message
   *   The exception message.
   */
  protected function displayError(string $message) {
    if ($this->currentUser()->hasPermission('entity_share_client_display_errors')) {
      $message = Xss::filterAdmin($message);
      $message = $this->prepareErrorMessage($message);
      $this->messenger()->addError($message);
    }
    else {
      $this->messenger()->addError($this->t('An error occurred when requesting the remote website. Please contact the site administrator.'));
    }
  }

  /**
   * Cut part of response from exception message.
   *
   * @param string $message
   *   The exception message.
   *
   * @return string
   *   Prepared message.
   */
  protected function prepareErrorMessage(string $message) {
    return preg_replace('/\sresponse:.+$/s', '', $message);
  }

}

<?php

namespace Drupal\flood_control\Form;

use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Url;
use Drupal\flood_control\FloodUnblockManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Admin form of Flood Unblock.
 */
class FloodUnblockAdminForm extends FormBase {

  /**
   * The FloodUnblockManager service.
   *
   * @var \Drupal\flood_control\FloodUnblockManagerInterface
   */
  protected $floodUnblockManager;

  /**
   * The date formatter service.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected $dateFormatter;

  /**
   * User flood config object.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $userFloodConfig;

  /**
   * Current user object.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * The request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * FloodUnblockAdminForm constructor.
   *
   * @param \Drupal\flood_control\FloodUnblockManagerInterface $floodUnblockManager
   *   The FloodUnblockManager service.
   * @param \Drupal\Core\Datetime\DateFormatterInterface $date_formatter
   *   The date formatter service.
   * @param \Drupal\Core\Session\AccountProxyInterface $currentUser
   *   The current user service.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The request stack.
   */
  public function __construct(FloodUnblockManagerInterface $floodUnblockManager, DateFormatterInterface $date_formatter, AccountProxyInterface $currentUser, RequestStack $request_stack) {
    $this->floodUnblockManager = $floodUnblockManager;
    $this->dateFormatter = $date_formatter;
    $this->userFloodConfig = $this->configFactory()->get('user.flood');
    $this->currentUser = $currentUser;
    $this->requestStack = $request_stack;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('flood_control.flood_unblock_manager'),
      $container->get('date.formatter'),
      $container->get('current_user'),
      $container->get('request_stack')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'flood_unblock_admin_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $request = $this->requestStack->getCurrentRequest();
    // Fetches the limit from the query string of the request.
    $limit = $request->query->get('limit') ?? 33;

    // Fetches the identifier from the query string of the request.
    $identifier = $request->query->get('identifier') ?? '';

    // Fetches the blocked status from the query string of the request.
    $blocked = $request->query->get('blocked') ?? FALSE;

    // Set default markup.
    $top_markup = $this->t("List of IP addresses and user ID's that are recorded in Drupal's flood after multiple failed login attempts. You can remove separate entries.");

    // Add link to control settings page if current user has
    // permission to access it.
    if ($this->currentUser->hasPermission('administer flood unblock')) {
      $top_markup .= $this->t('You can configure the login attempt limits and time windows on the <a href=":url">Flood Control settings page</a>.', [':url' => Url::fromRoute('flood_control.settings')->toString()]);
    }

    // Provides introduction to the table.
    $form['top_markup'] = [
      '#markup' => "<p> {$top_markup} </p>",
    ];

    // Provides table filters.
    if ($this->floodUnblockManager->canFilter()) {
      $form['filter'] = [
        '#type' => 'details',
        '#title' => $this->t('Filter'),
        '#open' => FALSE,
        'limit' => [
          '#type' => 'number',
          '#title' => $this->t('Amount'),
          '#description' => $this->t("Number of lines shown in table."),
          '#size' => 5,
          '#min' => 1,
          '#steps' => 10,
          '#default_value' => $limit,
        ],
        'identifier' => [
          '#type' => 'textfield',
          '#title' => $this->t('Identifier'),
          '#default_value' => $identifier,
          '#size' => 20,
          '#description' => $this->t('(Part of) identifier: IP address or UID'),
          '#maxlength' => 256,
        ],
        'blocked' => [
          '#type' => 'checkbox',
          '#title' => $this->t('Only blocked'),
          '#description' => $this->t('Show only the blocked requests'),
          '#default_value' => $blocked,
        ],
        'submit' => [
          '#type' => 'submit',
          '#value' => $this->t('Filter'),
          '#submit' => ['::applyFilters'],
        ],
      ];
    }

    // Provides header for tableselect element.
    $header = [
      'identifier' => [
        'data' => $this->t('Identifier'),
        'field' => 'identifier',
        'sort' => 'asc',
      ],
      'blocked' => $this->t('Status'),
      'event' => [
        'data' => $this->t('Event'),
        'field' => 'event',
        'sort' => 'asc',
      ],
      'timestamp' => [
        'data' => $this->t('Timestamp'),
        'field' => 'timestamp',
        'sort' => 'asc',
      ],
      'expiration' => [
        'data' => $this->t('Expiration'),
        'field' => 'expiration',
        'sort' => 'asc',
      ],
    ];

    $options = [];

    // Fetches data for the table.
    $entries = $this->floodUnblockManager->getEntries($limit, $identifier, $header);

    // Fetches user names or location string for identifiers.
    $identifiers = $this->floodUnblockManager->fetchIdentifiers(array_unique($entries['result_identifiers']));

    foreach ($entries['results'] as $result) {

      // Gets status of identifier.
      $is_blocked = $this->floodUnblockManager->isBlocked($result->identifier, $result->event);

      // Defines list of options for tableselect element.
      if ($blocked && $is_blocked) {
        $options[$result->fid] = [
          'title' => ['data' => ['#title' => $this->t('Flood id @id', ['@id' => $result->fid])]],
          'identifier' => $identifiers[$result->identifier],
          'blocked' => $is_blocked ? $this->t('Blocked') : $this->t('Not blocked'),
          'event' => $this->floodUnblockManager->getEventLabel($result->event),
          'timestamp' => $this->dateFormatter->format($result->timestamp, 'short'),
          'expiration' => $this->dateFormatter->format($result->expiration, 'short'),
        ];
      }
      elseif (!$blocked) {
        $options[$result->fid] = [
          'title' => ['data' => ['#title' => $this->t('Flood id @id', ['@id' => $result->fid])]],
          'identifier' => $identifiers[$result->identifier],
          'blocked' => $is_blocked ? $this->t('Blocked') : $this->t('Not blocked'),
          'event' => $this->floodUnblockManager->getEventLabel($result->event),
          'timestamp' => $this->dateFormatter->format($result->timestamp, 'short'),
          'expiration' => $this->dateFormatter->format($result->expiration, 'short'),
        ];
      }
    }

    // Provides the tableselect element.
    $form['table'] = [
      '#type' => 'tableselect',
      '#header' => $header,
      '#options' => $options,
      '#empty' => $this->t('There are no failed logins at this time.'),
    ];

    $form['actions'] = ['#type' => 'actions'];

    // Provides the remove submit button.
    $form['actions']['remove'] = [
      '#type' => 'submit',
      '#value' => $this->t('Remove selected items from the flood table'),
      '#validate' => ['::validateRemoveItems'],
    ];
    if (count($options) == 0) {
      $form['actions']['remove']['#disabled'] = TRUE;
    }

    // Provides the pager element.
    $form['pager'] = [
      '#type' => 'pager',
    ];

    $form['#cache'] = [
      'tags' => $this->userFloodConfig->getCacheTags(),
    ];
    return $form;
  }

  /**
   * Validates that items have been selected for removal.
   */
  public function validateRemoveItems(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);
    $entries = $form_state->getValue('table');
    $selected_entries = array_filter($entries, function ($selected) {
      return $selected !== 0;
    });
    if (empty($selected_entries)) {
      $form_state->setErrorByName('table', $this->t('Please make a selection.'));
    }
  }

  /**
   * Applies the filter parameters to the url.
   *
   * @param array $form
   *   The current form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public function applyFilters(array &$form, FormStateInterface $form_state) {
    $field = $form_state->getValues();
    $url = Url::fromRoute('flood_control.unblock_form')
      ->setRouteParameters(
        [
          'limit' => $field["limit"],
          'identifier' => $field["identifier"],
          'blocked' => $field['blocked'],
        ]
      );
    $form_state->setRedirectUrl($url);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    foreach ($form_state->getValue('table') as $fid) {
      if ($fid !== 0) {
        $this->floodUnblockManager->floodUnblockClearEvent($fid);
      }
    }
    $form_state->setRebuild();
  }

}

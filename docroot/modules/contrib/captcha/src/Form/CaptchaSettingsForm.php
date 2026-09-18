<?php

namespace Drupal\captcha\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\captcha\Constants\CaptchaConstants;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Displays the captcha settings form.
 */
class CaptchaSettingsForm extends ConfigFormBase {

  /**
   * The cache backend.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface
   */
  protected $cacheBackend;

  /**
   * The CAPTCHA helper service.
   *
   * @var \Drupal\captcha\Service\CaptchaService
   */
  protected $captchaService;

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The request object.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = parent::create($container);
    $instance->cacheBackend = $container->get('cache.default');
    $instance->moduleHandler = $container->get('module_handler');
    $instance->captchaService = $container->get('captcha.helper');
    $instance->requestStack = $container->get('request_stack');

    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['captcha.settings'];
  }

  /**
   * Implements \Drupal\Core\Form\FormInterface::getFormID().
   */
  public function getFormId() {
    return 'captcha_settings';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('captcha.settings');
    $this->moduleHandler->loadInclude('captcha', 'inc');

    $form['default_challenge'] = [
      '#type' => 'select',
      '#title' => $this->t('Default challenge type'),
      '#description' => $this->t('Select the default <em>CAPTCHA Point</em> challenge type. This can be overridden for each <em>CAPTCHA Point</em> individually.'),
      '#options' => $this->captchaService->getAvailableChallengeTypes(FALSE),
      '#default_value' => $config->get('default_challenge'),
    ];

    // Option for enabling CAPTCHA for all forms.
    $form['enable_globally'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Add CAPTCHA challenges on all forms'),
      '#description' => $this->t('Adds CAPTCHA to all Drupal forms, regardless of the Captcha Points list. Note, that the captcha point <em>default challenge</em> will be used as the challenge type for the created CAPTCHA challenges.'),
      '#default_value' => $config->get('enable_globally'),
    ];
    $form['enable_globally_on_admin_routes'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Additionally add CAPTCHA challenges on admin forms'),
      '#default_value' => $config->get('enable_globally_on_admin_routes'),
      '#states' => [
        'invisible' => [
          ':input[name="enable_globally"]' => ['checked' => FALSE],
        ],
      ],
    ];
    // Field for the CAPTCHA administration mode.
    $form['administration_mode'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Add CAPTCHA administration information to forms'),
      '#default_value' => $config->get('administration_mode'),
      '#description' => $this->t('This option makes it easy to manage CAPTCHA settings on forms. When enabled, users with the <em>administer CAPTCHA settings</em> permission will see a fieldset with CAPTCHA administration links and informations on all forms, except on administrative pages.'),
    ];
    // Field for the CAPTCHAs on admin pages.
    $form['administration_mode_on_admin_routes'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Additionally add administration informations on admin pages'),
      '#description' => $this->t("Typically this isn't needed. In some situations, e.g. in the case of demo sites, it can be useful to allow CAPTCHAs on administrative pages."),
      '#default_value' => $config->get('administration_mode_on_admin_routes'),
      '#states' => [
        'invisible' => [
          ':input[name="administration_mode"]' => ['checked' => FALSE],
        ],
      ],
    ];

    // Adding configuration for ip protection.
    $form['whitelist_ips_settings'] = [
      '#type' => 'details',
      '#title' => $this->t('Whitelisted IP Addresses'),
      '#description' => $this->t('Enter the IP addresses or IP address ranges you wish to whitelist. All CAPTCHA challenges will be skipped for these IP addresses.'),
      '#open' => !empty($config->get('whitelist_ips')),
    ];

    $ip_address = $this->requestStack->getCurrentRequest()->getClientIp();
    $form['whitelist_ips_settings']['whitelist_ips'] = [
      '#title' => $this->t('IP addresses list'),
      '#type' => 'textarea',
      '#required' => FALSE,
      '#default_value' => $config->get('whitelist_ips'),
      '#description' => $this->t('Enter one IP-address per row in the format XXX.XXX.XXX.XXX. Alternatively you can also define IP-address ranges per row in the format XXX.XXX.XXX.YYY-XXX.XXX.XXX.ZZZ. No spaces allowed. Your current IP address is %ip_address.', ['%ip_address' => $ip_address]),
    ];

    // Button for clearing the CAPTCHA placement cache.
    // Based on Drupal core's "Clear all caches" (performance settings page).
    $form['placement_caching'] = [
      '#type' => 'item',
      '#title' => $this->t('CAPTCHA placement caching'),
      '#description' => $this->t('For efficiency, the positions of the CAPTCHA elements in each of the configured forms are cached. Most of the time, the structure of a form does not change and it would be a waste to recalculate the positions every time. Occasionally however, the form structure can change (e.g. during site building) and clearing the CAPTCHA placement cache can be required to fix the CAPTCHA placement.'),
    ];
    $form['placement_caching']['placement_cache_clear'] = [
      '#type' => 'submit',
      '#value' => $this->t('Clear the CAPTCHA placement cache'),
      '#submit' => ['::clearCaptchaPlacementCacheSubmit'],
    ];

    $form['title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Challenge title'),
      '#description' => $this->t('Configure the title for the CAPTCHA form. Leave empty to show no title. Default: "@title_default"', ['@title_default' => $this->t('CAPTCHA')]),
      '#default_value' => _captcha_get_title(),
      '#maxlength' => 256,
    ];

    $form['description'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Challenge description'),
      '#description' => $this->t('Configurable description of the CAPTCHA. Leave empty to show no description. Default: "@description_default"', ['@description_default' => $this->t('This question is for testing whether or not you are a human visitor and to prevent automated spam submissions.')]),
      '#default_value' => _captcha_get_description(),
      '#maxlength' => 256,
    ];

    // Field for the wrong captcha response error message.
    $form['wrong_captcha_response_message'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Wrong CAPTCHA response error message'),
      '#description' => $this->t('Configurable error message that the user gets when it enters an incorrect CAPTCHA answer.'),
      '#default_value' => _captcha_get_error_message(),
      '#maxlength' => 256,
      '#required' => TRUE,
    ];

    // Option for case sensitive/insensitive validation of the responses.
    $form['default_validation'] = [
      '#type' => 'radios',
      '#title' => $this->t('Default CAPTCHA validation'),
      '#description' => $this->t('Define how the response should be processed by default. Note that the modules that provide the actual challenges can override or ignore this.'),
      '#options' => [
        CaptchaConstants::CAPTCHA_DEFAULT_VALIDATION_CASE_SENSITIVE => $this->t('Case sensitive validation: the response has to exactly match the solution.'),
        CaptchaConstants::CAPTCHA_DEFAULT_VALIDATION_CASE_INSENSITIVE => $this->t('Case insensitive validation: lowercase/uppercase errors are ignored.'),
      ],
      '#default_value' => $config->get('default_validation'),
    ];

    // Field for CAPTCHA persistence.
    // @todo for D7: Rethink/simplify the explanation and UI strings.
    $form['persistence'] = [
      '#type' => 'radios',
      '#title' => $this->t('Persistence'),
      '#default_value' => $config->get('persistence'),
      '#options' => [
        CaptchaConstants::CAPTCHA_PERSISTENCE_SHOW_ALWAYS => $this->t('Always add a challenge.'),
        CaptchaConstants::CAPTCHA_PERSISTENCE_SKIP_ONCE_SUCCESSFUL_PER_FORM_INSTANCE => $this->t('Omit challenges in a multi-step/preview workflow once the user successfully responds to a challenge.'),
        CaptchaConstants::CAPTCHA_PERSISTENCE_SKIP_ONCE_SUCCESSFUL_PER_FORM_TYPE => $this->t('Omit challenges on a form type once the user successfully responds to a challenge on a form of that type.'),
        CaptchaConstants::CAPTCHA_PERSISTENCE_SKIP_ONCE_SUCCESSFUL => $this->t('Omit challenges on all forms once the user successfully responds to any challenge on the site.'),
      ],
      '#description' => $this->t('Define if challenges should be omitted during the rest of a session once the user successfully responds to a challenge.'),
    ];

    // Enable wrong response counter.
    $form['enable_stats'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable statistics'),
      '#description' => $this->t('Keep CAPTCHA related counters in the <a href=":statusreport">status report</a>. Note that this comes with a performance penalty as updating the counters results in clearing the variable cache.', [
        ':statusreport' => Url::fromRoute('system.status')->toString(),
      ]),
      '#default_value' => $config->get('enable_stats'),
    ];

    // Option for logging wrong responses.
    $form['log_wrong_responses'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Log wrong responses'),
      '#description' => $this->t('Report information about wrong responses to the log.'),
      '#default_value' => $config->get('log_wrong_responses'),
    ];

    // Replace the description with a link if dblog.module is enabled.
    if ($this->moduleHandler->moduleExists('dblog')) {
      $form['log_wrong_responses']['#description'] = $this->t('Report information about wrong responses to the <a href=":dblog">log</a>.', [
        ':dblog' => Url::fromRoute('dblog.overview')->toString(),
      ]);
    }

    // Submit button.
    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save configuration'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    // Validating whitelisted ip addresses.
    $whitelist_ips_value = trim($form_state->getValue('whitelist_ips', ''));
    if (!empty($whitelist_ips_value)) {
      $whitelist_ips = captcha_whitelist_ips_parse_values($whitelist_ips_value);

      // Checking single ip addresses.
      foreach ($whitelist_ips[CaptchaConstants::CAPTCHA_WHITELIST_IP_ADDRESS] as $ip_address) {
        if (filter_var($ip_address, FILTER_VALIDATE_IP) == FALSE) {
          $form_state->setErrorByName('whitelist_ips', $this->t('IP address %ip_address is not valid.', ['%ip_address' => $ip_address]));
        }
      }

      // Checking ip ranges.
      foreach ($whitelist_ips[CaptchaConstants::CAPTCHA_WHITELIST_IP_RANGE] as $ip_range) {
        [$ip_lower, $ip_upper] = explode('-', $ip_range, 2);

        if (filter_var($ip_lower, FILTER_VALIDATE_IP) == FALSE) {
          $form_state->setErrorByName('whitelist_ips', $this->t('Lower IP address %ip_address in range %ip_range is not valid.', [
            '%ip_address' => $ip_lower,
            '%ip_range' => $ip_range,
          ]));
        }

        if (filter_var($ip_upper, FILTER_VALIDATE_IP) == FALSE) {
          $form_state->setErrorByName('whitelist_ips', $this->t('Upper IP address %ip_address in range %ip_range is not valid.', [
            '%ip_address' => $ip_upper,
            '%ip_range' => $ip_range,
          ]));
        }

        $ip_lower_dec = (float) sprintf("%u", ip2long($ip_lower));
        $ip_upper_dec = (float) sprintf("%u", ip2long($ip_upper));

        if ($ip_lower_dec == $ip_upper_dec) {
          $form_state->setErrorByName('whitelist_ips', $this->t('Lower and upper IP addresses should be different. Please correct range %ip_range.', ['%ip_range' => $ip_range]));
        }
        elseif ($ip_lower_dec > $ip_upper_dec) {
          $form_state->setErrorByName('whitelist_ips', $this->t("Lower IP can't be greater than upper IP addresses in range. Please correct range %ip_range.", ['%ip_range' => $ip_range]));
        }
      }
    }

    parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('captcha.settings');
    $config->set('administration_mode', $form_state->getValue('administration_mode'));
    $config->set('administration_mode_on_admin_routes', ($form_state->getValue('administration_mode') && $form_state->getValue('administration_mode_on_admin_routes')));
    $config->set('enable_globally', $form_state->getValue('enable_globally'));
    $config->set('enable_globally_on_admin_routes', ($form_state->getValue('enable_globally') && $form_state->getValue('enable_globally_on_admin_routes')));
    $config->set('default_challenge', $form_state->getValue('default_challenge'));

    // Whitelisted ip addresses and ranges.
    $config->set('whitelist_ips', $form_state->getValue('whitelist_ips'));

    // Save the CAPTCHA title:
    $config->set('title', $form_state->getValue('title'));
    // Save the CAPTCHA description:
    $config->set('description', $form_state->getValue('description'));

    $config->set('wrong_captcha_response_message', $form_state->getValue('wrong_captcha_response_message'));
    $config->set('default_validation', $form_state->getValue('default_validation'));
    $config->set('persistence', $form_state->getValue('persistence'));
    $config->set('enable_stats', $form_state->getValue('enable_stats'));
    $config->set('log_wrong_responses', $form_state->getValue('log_wrong_responses'));
    $config->save();

    parent::submitForm($form, $form_state);
  }

  /**
   * Submit callback; clear CAPTCHA placement cache.
   *
   * @param array $form
   *   Form structured array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Form state structured array.
   */
  public function clearCaptchaPlacementCacheSubmit(array $form, FormStateInterface $form_state) {
    $this->cacheBackend->delete('captcha_placement_map_cache');
    $this->messenger()->addMessage($this->t('Cleared the CAPTCHA placement cache.'));
  }

}

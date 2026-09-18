<?php

namespace Drupal\captcha\Element;

use Drupal\Component\Utility\Crypt;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Render\Element\FormElement;
use Drupal\captcha\Constants\CaptchaConstants;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines the CAPTCHA form element with default properties.
 *
 * @FormElement("captcha")
 */
class Captcha extends FormElement implements ContainerFactoryPluginInterface {

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Captcha constructor.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin ID for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The configuration factory.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, ConfigFactoryInterface $configFactory) {
    $this->configFactory = $configFactory;
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('config.factory')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getInfo() {
    $captcha_element = [
      '#input' => TRUE,
      '#process' => [[static::class, 'processCaptchaElement']],
      // The type of challenge: e.g. 'default', 'captcha/Math', etc.
      '#captcha_type' => CaptchaConstants::CAPTCHA_TYPE_DEFAULT,
      '#default_value' => '',
      // CAPTCHA in admin mode: presolve the CAPTCHA and always show
      // it (despite previous successful responses).
      '#captcha_admin_mode' => FALSE,
      // The default CAPTCHA validation function.
      // @todo should this be a single string or an array of strings?
      '#captcha_validate' => 'captcha_validate_strict_equality',
    ];
    // Override the default CAPTCHA validation function for case
    // insensitive validation.
    // @todo shouldn't this be done somewhere else, e.g. in form_alter?
    if (CaptchaConstants::CAPTCHA_DEFAULT_VALIDATION_CASE_INSENSITIVE == $this->configFactory->get('captcha.settings')
      ->get('default_validation')
    ) {
      $captcha_element['#captcha_validate'] = 'captcha_validate_case_insensitive_equality';
    }
    return $captcha_element;
  }

  /**
   * Process callback for CAPTCHA form element.
   */
  public static function processCaptchaElement(&$element, FormStateInterface $form_state, &$complete_form) {
    // Add captcha.inc file.
    \Drupal::moduleHandler()->loadInclude('captcha', 'inc');

    // Add JavaScript for general CAPTCHA functionality.
    $element['#attached']['library'][] = 'captcha/base';

    if ($form_state->getTriggeringElement() && isset($form_state->getTriggeringElement()['#limit_validation_errors']) && is_array($form_state->getTriggeringElement()['#limit_validation_errors'])) {
      // This is a partial (ajax) submission with limited validation. Do not
      // change anything about the captcha element, assume that it will not
      // update the captcha element, do not generate anything, which keeps the
      // current token intact for the real submission.
      return $element;
    }

    // Get the form ID of the form we are currently processing (which is not
    // necessary the same form that is submitted (if any).
    $this_form_id = isset($complete_form['form_id']['#value']) ?
      preg_replace("/[^a-z0-9_-]/", "", (string) $complete_form['form_id']['#value'])
      : NULL;

    // Get the CAPTCHA session ID.
    // If there is a submitted form: try to retrieve and reuse the
    // CAPTCHA session ID from the posted data.
    [$posted_form_id, $posted_captcha_sid] = _captcha_get_posted_captcha_info($element, $form_state, $this_form_id);
    if ($this_form_id == $posted_form_id && isset($posted_captcha_sid)) {
      $captcha_sid = $posted_captcha_sid;
    }
    else {
      // Generate a new CAPTCHA session if we could
      // not reuse one from a posted form.
      $captcha_sid = _captcha_generate_captcha_session($this_form_id, CaptchaConstants::CAPTCHA_STATUS_UNSOLVED);
      $captcha_token = Crypt::randomBytesBase64();
      \Drupal::database()->update('captcha_sessions')
        ->fields(['token' => $captcha_token])
        ->condition('csid', $captcha_sid)
        ->execute();
    }

    // Store CAPTCHA session ID as hidden field.
    // Strictly speaking, it is not necessary to send the CAPTCHA session id
    // with the form, as the one time CAPTCHA token (see lower) is enough.
    // However, we still send it along, because it can help debugging
    // problems on live sites with only access to the markup.
    $element['captcha_sid'] = [
      '#type' => 'hidden',
      '#value' => $captcha_sid,
    ];

    // Store CAPTCHA token as hidden field.
    $captcha_token = \Drupal::database()
      ->select('captcha_sessions', 'cs')
      ->fields('cs', ['token'])
      ->condition('csid', $captcha_sid)
      ->execute()
      ->fetchField();

    $element['captcha_token'] = [
      '#type' => 'hidden',
      '#value' => $captcha_token,
    ];

    // Get implementing module and challenge for CAPTCHA.
    [$captcha_type_module, $captcha_type_challenge] = _captcha_parse_captcha_type($element['#captcha_type']);

    // Store CAPTCHA information for further processing in
    // - $form_state->get('captcha_info'), which survives
    //   a form rebuild (e.g. node preview),
    //   useful in _captcha_get_posted_captcha_info().
    // - $element['#captcha_info'], for post processing functions that do not
    //   receive a $form_state argument (e.g. the pre_render callback).
    // Added a new access attribute,
    // by default it will be true if access attribute
    // not defined in a custom form.
    $info = [
      'this_form_id' => $this_form_id,
      'posted_form_id' => $posted_form_id,
      'captcha_sid' => $captcha_sid,
      'module' => $captcha_type_module,
      'captcha_type' => $captcha_type_challenge,
      'access' => $element['#access'] ?? CaptchaConstants::CAPTCHA_FIELD_DEFAULT_ACCESS,
    ];
    $form_state->set('captcha_info', $info);
    $element['#captcha_info'] = [
      'form_id' => $this_form_id,
      'captcha_sid' => $captcha_sid,
    ];

    if (_captcha_required_for_user($captcha_sid, $this_form_id) || $element['#captcha_admin_mode']) {
      // Generate a CAPTCHA and its solution
      // (note that the CAPTCHA session ID is given as third argument).
      $captcha = \Drupal::moduleHandler()
        ->invoke($captcha_type_module, 'captcha', [
          'generate',
          $captcha_type_challenge,
          $captcha_sid,
        ]);

      // Allow other modules to alter the generated captcha:
      \Drupal::moduleHandler()->alter('captcha', $captcha, $info);

      // @todo Isn't this moment a bit late to figure out
      // that we don't need CAPTCHA?
      if (!isset($captcha)) {
        return $element;
      }

      if (!isset($captcha['form']) || !isset($captcha['solution'])) {
        // The selected module did not return what we expected:
        // log about it and quit.
        \Drupal::logger('CAPTCHA')->error(
          'CAPTCHA problem: unexpected result from hook_captcha() of module %module when trying to retrieve challenge type %type for form %form_id.',
          [
            '%type' => $captcha_type_challenge,
            '%module' => $captcha_type_module,
            '%form_id' => $this_form_id,
          ]
        );

        return $element;
      }
      // Add form elements from challenge as children to CAPTCHA form element.
      $element['captcha_widgets'] = $captcha['form'];

      // Add a validation callback for the CAPTCHA form element
      // (when not in admin mode).
      if (!$element['#captcha_admin_mode']) {
        $element['#element_validate'] = ['captcha_validate'];
      }

      // Set a custom CAPTCHA validate function if requested.
      if (isset($captcha['captcha_validate'])) {
        $element['#captcha_validate'] = $captcha['captcha_validate'];
      }

      // Set the theme function.
      $element['#theme'] = 'captcha';

      // Provide the captcha type:
      $element['#captcha_type_challenge'] = $captcha_type_challenge;

      // Add pre_render callback for additional CAPTCHA processing.
      if (!isset($element['#pre_render'])) {
        $element['#pre_render'] = [];
      }
      $element['#pre_render'][] = [Captcha::class, 'preRenderProcess'];

      // Store the solution in the #captcha_info array.
      $element['#captcha_info']['solution'] = $captcha['solution'];

      // Store if this captcha type is cacheable:
      // A cacheable captcha must not depend on the sid or solution, but be
      // independent - like for example recaptcha.
      $element['#captcha_info']['cacheable'] = !empty($captcha['cacheable']);

      if (!empty($element['#captcha_info']['cacheable'])) {
        // This is only used to avoid the re-use message.
        $element['captcha_cacheable'] = [
          '#type' => 'hidden',
          '#value' => 1,
        ];
      }

      // Make sure we can use a top level form value
      // $form_state->getValue('captcha_response'),
      // even if the form has #tree=true.
      $element['#tree'] = FALSE;
    }

    return $element;
  }

  /**
   * Pre-render callback for additional processing of a CAPTCHA form element.
   *
   * This encompasses tasks that should happen after the general FAPI processing
   * (building, submission and validation) but before rendering
   * (e.g. storing the solution).
   *
   * @param array $element
   *   The CAPTCHA form element.
   *
   * @return array
   *   The manipulated element.
   */
  public static function preRenderProcess(array $element) {
    \Drupal::moduleHandler()->loadInclude('captcha', 'inc');

    // Get form and CAPTCHA information.
    $captcha_info = $element['#captcha_info'];
    $form_id = $captcha_info['form_id'];
    $captcha_sid = (int) ($captcha_info['captcha_sid']);
    // Check if CAPTCHA is still required.
    // This check is done in a first phase during the element processing
    // (@see captcha_process), but it is also done here for better support
    // of multi-page forms. Take previewing a node submission for example:
    // when the challenge is solved correctly on preview, the form is still
    // not completely submitted, but the CAPTCHA can be skipped.
    if (_captcha_required_for_user($captcha_sid, $form_id) || $element['#captcha_admin_mode']) {
      // Update captcha_sessions table: store the solution
      // of the generated CAPTCHA.
      _captcha_update_captcha_session($captcha_sid, $captcha_info['solution']);

      // For "show always" persistence, regenerate the token after validation
      // since it may have been invalidated to prevent replay attacks. This
      // ensures the rendered form has a valid token for the next submission.
      // @see https://www.drupal.org/project/captcha/issues/3558256
      $captcha_persistence = \Drupal::config('captcha.settings')
        ->get('persistence');
      if ($captcha_persistence == CaptchaConstants::CAPTCHA_PERSISTENCE_SHOW_ALWAYS) {
        $new_token = Crypt::randomBytesBase64();
        \Drupal::database()->update('captcha_sessions')
          ->fields(['token' => $new_token])
          ->condition('csid', $captcha_sid)
          ->execute();
        $element['captcha_token']['#value'] = $new_token;
      }

      // Handle the response field if it is available and if it is a textfield.
      if (isset($element['captcha_widgets']['captcha_response']['#type']) && $element['captcha_widgets']['captcha_response']['#type'] == 'textfield') {
        // Before rendering: presolve an admin mode challenge or
        // empty the value of the captcha_response form item.
        $value = $element['#captcha_admin_mode'] ? $captcha_info['solution'] : '';
        $element['captcha_widgets']['captcha_response']['#value'] = $value;
      }
    }
    else {
      // Remove CAPTCHA widgets from form.
      unset($element['captcha_widgets']);
    }

    return $element;
  }

}

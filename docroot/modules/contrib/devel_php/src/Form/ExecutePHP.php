<?php

declare(strict_types=1);

namespace Drupal\devel_php\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines a form that allows privileged users to execute arbitrary PHP code.
 */
class ExecutePHP extends FormBase {

  /**
   * Number of rows for the textarea.
   */
  public const ROWS_NUMBER = 20;

  /**
   * The devel dumper manager service.
   *
   * @var \Drupal\devel\DevelDumperManagerInterface
   */
  protected $develDumper;

  /**
   * The session service.
   *
   * @var \Symfony\Component\HttpFoundation\Session\SessionInterface
   */
  protected $session;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    $instance = parent::create($container);
    $instance->develDumper = $container->get('devel.dumper');
    $instance->session = $container->get('session');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'devel_execute_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $details_open = TRUE;
    $build_info = $form_state->getBuildInfo();
    if (isset($build_info['args'][0])) {
      $details_open = $build_info['args'][0];
    }

    $form['#redirect'] = FALSE;
    $code = $this->session->get('devel_execute_code', '');

    $form['execute'] = [
      '#type' => 'details',
      '#title' => $this->t('PHP code to execute'),
      '#open' => (!empty($code) || $details_open),
    ];

    $form['execute']['code'] = [
      '#type' => 'textarea',
      '#title' => $this->t('PHP code to execute'),
      '#title_display' => 'invisible',
      '#description' => $this->t('Enter some code. Do not use <code>&lt;?php ?&gt;</code> tags.'),
      '#default_value' => $code,
      '#rows' => self::ROWS_NUMBER,
      '#attributes' => [
        'style' => 'font-family: monospace; font-size: 1.25em;',
      ],
    ];
    $form['execute']['op'] = [
      '#type' => 'submit',
      '#value' => $this->t('Execute'),
    ];

    if ($this->session->has('devel_execute_code')) {
      $this->session->remove('devel_execute_code');
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   *
   * @SuppressWarnings(PHPMD.EvalExpression)
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $code = $form_state->getValue('code');

    try {
      \ob_start();
      // phpcs:ignore
      print eval($code);
      // phpcs:enable
      $this->develDumper->message(\ob_get_clean());
    }
    catch (\Throwable $error) {
      $this->messenger()->addError($error->getMessage());
    }

    $this->session->set('devel_execute_code', $code);
  }

}

<?php

namespace Drupal\autologout\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\autologout\AutologoutManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a settings for autologout module.
 */
class AutologoutBlockForm extends FormBase {

  /**
   * The autologout manager service.
   *
   * @var \Drupal\autologout\AutologoutManagerInterface
   */
  protected $autoLogoutManager;

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'autologout_reset_block';
  }

  /**
   * Constructs an AutologoutBlockForm object.
   *
   * @param \Drupal\autologout\AutologoutManagerInterface $autologout
   *   The autologout manager service.
   */
  public function __construct(AutologoutManagerInterface $autologout) {
    $this->autoLogoutManager = $autologout;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('autologout.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    // Generate CSRF token for the AJAX URL.
    $url = Url::fromRoute('autologout.ajax_set_last');
    $token = \Drupal::csrfToken()->get($url->getInternalPath());

    $form['reset'] = [
      '#type' => 'button',
      '#value' => $this->t('Reset Timeout'),
      '#weight' => 1,
      '#limit_validation_errors' => FALSE,
      '#executes_submit_callback' => FALSE,
      '#ajax' => [
        'url' => $url,
        'options' => [
          'query' => [
            'token' => $token,
          ],
        ],
      ],
    ];

    $form['timer'] = [
      '#markup' => $this->autoLogoutManager->createTimer(),
    ];

    // CSRF tokens are session-specific, so vary cache by session.
    $form['#cache']['contexts'][] = 'session';

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Submits on block form.
  }

}

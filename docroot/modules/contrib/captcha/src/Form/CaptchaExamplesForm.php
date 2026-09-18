<?php

namespace Drupal\captcha\Form;

use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Displays the captcha settings form.
 */
class CaptchaExamplesForm extends FormBase {

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * CaptchaExamplesForm constructor.
   *
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $moduleHandler
   *   Constructor.
   */
  public function __construct(ModuleHandlerInterface $moduleHandler) {
    $this->moduleHandler = $moduleHandler;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('module_handler')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'captcha_examples';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $module = NULL, $challenge = NULL) {
    $form = [];
    if ($module && $challenge) {
      // Generate 10 example challenges.
      for ($i = 0; $i < 10; $i++) {
        $form["challenge_{$i}"] = $this->buildChallenge($module, $challenge);
      }
    }
    else {
      // Generate a list with examples of the available CAPTCHA types.
      $form['info'] = [
        '#markup' => $this->t('This page gives an overview of all available challenge types, generated with their current settings.'),
      ];
      $challenges = [];
      $this->moduleHandler->invokeAllWith('captcha', function (callable $hook, string $module) use (&$challenges) {
        if ($challenge = $hook('list')) {
          $challenges[$module] = $challenge;
        }
      });

      if ($challenges) {
        foreach ($challenges as $module => $challenge_list) {
          foreach ($challenge_list as $ckey => $challenge) {
            $form["captcha_{$module}_{$ckey}"] = [
              '#type' => 'details',
              '#title' => $this->t('Challenge %challenge by module %module', [
                '%challenge' => $challenge,
                '%module' => $module,
              ]),
              'challenge' => $this->buildChallenge($module, $challenge),
              'more_examples' => [
                '#markup' => Link::fromTextAndUrl($this->t('10 more examples of this challenge.'), Url::fromRoute('captcha_examples', [
                  'module' => $module,
                  'challenge' => $challenge,
                ]))->toString(),
              ],
            ];
          }
        }
      }
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {}

  /**
   * Returns a renderable array for a given CAPTCHA challenge.
   */
  protected function buildChallenge($module, $challenge) {
    return [
      '#type' => 'captcha',
      '#captcha_type' => $module . '/' . $challenge,
      '#captcha_admin_mode' => TRUE,
    ];
  }

}

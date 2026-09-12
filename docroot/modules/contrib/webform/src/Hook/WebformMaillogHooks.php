<?php

namespace Drupal\webform\Hook;

use Drupal\Core\EventSubscriber\MainContentViewSubscriber;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\webform\Entity\Webform;
use Drupal\webform\Plugin\WebformHandler\EmailWebformHandler;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Hook\Attribute\Hook;

/**
 * Hook implementations for webform.
 */
class WebformMaillogHooks {
  use StringTranslationTrait;

  /**
   * Implements hook_webform_submission_form_alter().
   */
  #[Hook('webform_submission_form_alter', module: 'maillog')]
  public function maillogWebformSubmissionFormAlter(array &$form, FormStateInterface $form_state, $form_id) {
    // Never display Maillog message via CLI.
    if (PHP_SAPI === 'cli') {
      return;
    }
    // Never display Maillog message via dialogs or Ajax requests.
    $wrapper_format = \Drupal::request()->get(MainContentViewSubscriber::WRAPPER_FORMAT);
    if ($wrapper_format) {
      return;
    }
    $config = \Drupal::config('maillog.settings');
    // Only display warning if emails are not being sent.
    if ($config->get('send')) {
      return;
    }
    // If this webform does not send any email do not display any warning.
    $webform = Webform::load($form['#webform_id']);
    $sends_email = FALSE;
    $handlers = $webform->getHandlers();
    foreach ($handlers as $handler) {
      if ($handler instanceof EmailWebformHandler) {
        $sends_email = TRUE;
      }
    }
    if (!$sends_email) {
      return;
    }
    // Build warning message base on maillog settings and permissions.
    $build = [];
    // Display warning that all emails will be logged to admins only.
    // Display warning that all emails will be logged to admins only.
    if (\Drupal::currentUser()->hasPermission('administer maillog')) {
      $t_args = [':href' => Url::fromRoute('maillog.settings')->toString()];
      if ($config->get('log')) {
        $build[] = [
          '#markup' => $this->t('The <a href=":href">Maillog</a> module is logging all emails.', $t_args),
        ];
      }
      else {
        $build[] = ['#markup' => $this->t('The <a href=":href">Maillog</a> module is installed.', $t_args)];
      }
    }
    // Display warning if the user can view email on page.
    if (\Drupal::currentUser()->hasPermission('view maillog') && $config->get('verbose')) {
      $build[] = ['#prefix' => ' ', '#markup' => $this->t('Emails will displayed on this page.')];
    }
    // Display warning if no emails are being sent.
    $build[] = [
      '#markup' => $this->t('No emails will be sent.'),
      '#prefix' => ' <b>',
      '#suffix' => '</b>',
    ];
    if ($build) {
      \Drupal::messenger()->addWarning(\Drupal::service('renderer')->renderInIsolation($build));
    }
  }

}

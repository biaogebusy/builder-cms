<?php

namespace Drupal\acquia_connector\EventSubscriber\KernelView;

use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\KernelEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Custom Code Studio Message Event Subscriber.
 *
 * This class checks for Code Studio environment variables and displays a
 * message indicating the environment you're in if those variables are found.
 *
 * @package Drupal\acquia_connector\EventSubscriber
 */
class CodeStudioMessage implements EventSubscriberInterface {

  use StringTranslationTrait;

  /**
   * Drupal Messenger Service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * InitSubscriber constructor.
   *
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   Drupal Messenger Service.
   */
  public function __construct(MessengerInterface $messenger) {
    $this->messenger = $messenger;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[KernelEvents::VIEW][] = ['onViewRenderArray', 100];
    return $events;
  }

  /**
   * {@inheritdoc}
   */
  public function onViewRenderArray(KernelEvent $event) {
    // Only load script in CD Environment (nÃ©e ODE).
    $ah_env = getenv('AH_SITE_ENVIRONMENT');
    if (!$this->isOdeEnvironment($ah_env)) {
      return;
    }
    $required_variables = [
      'CODE_STUDIO_CI_PROJECT_ID',
      'CODE_STUDIO_CI_MERGE_REQUEST_IID',
      'CODE_STUDIO_CI_PROJECT_ID',
      'CODE_STUDIO_CI_PROJECT_PATH',
    ];
    foreach ($required_variables as $required_variable) {
      if (!getenv($required_variable)) {
        // Exit early if a required variable is missing.
        return;
      }
    }

    $this->messenger->addStatus($this->t('This Acquia Continuous Delivery Environment (CDE) was automatically created by <a href=":code_studio_url" target="_blank">Acquia Code Studio</a> for merge request <a href=":merge_request_url" target="_blank">!@merge_request_iid</a> for <a href=":project_url" target"_blank">@project_path</a>. It will be destroyed when the merge request is closed or merged.', [
      ':code_studio_url' => getenv('CODE_STUDIO_CI_SERVER_URL'),
      '@merge_request_iid' => getenv('CODE_STUDIO_CI_MERGE_REQUEST_IID'),
      ':merge_request_url' => getenv('CODE_STUDIO_CI_SERVER_URL') . '/' . getenv('CODE_STUDIO_CI_PROJECT_PATH') . '/-/merge_requests/' . getenv('CODE_STUDIO_CI_MERGE_REQUEST_IID'),
      '@project_path' => getenv('CODE_STUDIO_CI_PROJECT_PATH'),
      ':project_url' => getenv('CODE_STUDIO_CI_SERVER_URL') . '/' . getenv('CODE_STUDIO_CI_PROJECT_PATH'),
    ]));
  }

  /**
   * Is AH ODE.
   *
   * @param string $ah_env
   *   Environment machine name.
   *
   * @return false|int
   *   TRUE if ODE, FALSE otherwise.
   */
  protected function isOdeEnvironment($ah_env) {
    // CDEs (formerly 'ODEs') can be 'ode1', 'ode2', ...
    return (preg_match('/^ode\d*$/', $ah_env));
  }

}

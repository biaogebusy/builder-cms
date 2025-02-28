<?php

namespace Drupal\autologout\Controller;

use Drupal\autologout\AutologoutManagerInterface;
use Drupal\Component\Datetime\TimeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Ajax\SettingsCommand;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Returns responses for autologout module routes.
 */
class AutologoutController extends ControllerBase {

  /**
   * The autologout manager service.
   *
   * @var \Drupal\autologout\AutologoutManagerInterface
   */
  protected $autoLogoutManager;


  /**
   * The Time Service.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected $time;

  /**
   * Constructs an AutologoutSubscriber object.
   *
   * @param \Drupal\autologout\AutologoutManagerInterface $autologout
   *   The autologout manager service.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   */
  public function __construct(
    AutologoutManagerInterface $autologout,
    TimeInterface $time
  ) {
    $this->autoLogoutManager = $autologout;
    $this->time = $time;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('autologout.manager'),
      $container->get('datetime.time')
    );
  }

  /**
   * Alternative logout.
   */
  public function altLogout() {
    $this->autoLogoutManager->logout();
    $redirect_url = $this->config('autologout.settings')->get('redirect_url');
    $url = Url::fromUserInput(
      $redirect_url,
      [
        'absolute' => TRUE,
        'query' => [
          'autologout_timeout' => 1,
        ],
      ]
    );

    return new RedirectResponse($url->toString());
  }

  /**
   * AJAX logout.
   */
  public function ajaxLogout() {
    $this->autoLogoutManager->logout();
    $response = new AjaxResponse();
    $response->setStatusCode(200);

    return $response;
  }

  /**
   * Ajax callback to reset the last access session variable.
   */
  public function ajaxSetLast() {
    $_SESSION['autologout_last'] = $this->time->getRequestTime();

    // Reset the timer.
    $response = new AjaxResponse();
    $markup = $this->autoLogoutManager->createTimer();
    $response->addCommand(new ReplaceCommand('#timer', $markup));

    return $response;
  }

  /**
   * AJAX callback that returns the time remaining for this user is logged out.
   */
  public function ajaxGetRemainingTime() {

    $req = \Drupal::requestStack()->getCurrentRequest();
    $active = $req->get('uactive');
    $response = new AjaxResponse();

    if (isset($active) && $active === "false") {
      $response->addCommand(new ReplaceCommand('#timer', 0));
      $response->addCommand(new SettingsCommand(['time' => 0]));

      return $response;
    }

    $time_remaining_ms = $this->autoLogoutManager->getRemainingTime() * 1000;

    // Reset the timer.
    $markup = $this->autoLogoutManager->createTimer();

    $response->addCommand(new ReplaceCommand('#timer', $markup));
    $response->addCommand(new SettingsCommand(['time' => $time_remaining_ms]));

    return $response;
  }

}

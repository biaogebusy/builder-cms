<?php

declare(strict_types=1);

namespace Drupal\tourauto\Hook;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\tourauto\TourautoManager;
use Drupal\user\Entity\User;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Hook implementations for tourauto module.
 */
class TourautoHooks {

  public function __construct(
    #[Autowire(service: 'tourauto.manager')]
    protected TourautoManager $tourautoManager,
    protected AccountProxyInterface $currentUser,
    protected RendererInterface $renderer,
    protected RouteMatchInterface $routeMatch,
  ) {}

  /**
   * Implements hook_form_FORM_ID_alter() for user_form.
   */
  #[Hook('form_user_form_alter')]
  public function formUserFormAlter(array &$form, FormStateInterface $form_state, string $form_id): void {
    $account = $form_state->getBuildInfo()['callback_object']->getEntity();
    $manager = $this->tourautoManager->getManagerForAccount($account);

    $form['tourauto'] = [
      '#type' => 'details',
      '#title' => $manager->translate('Tours'),
      '#open' => TRUE,
    ];
    $form['tourauto']['tourauto_enable'] = [
      '#type' => 'checkbox',
      '#title' => 'Open tours automatically',
      '#default_value' => $manager->tourautoEnabled(),
    ];
    $form['tourauto']['tourauto_clear'] = [
      '#type' => 'checkbox',
      '#title' => 'Clear status (mark all tours as unseen)',
      '#default_value' => FALSE,
    ];
    array_unshift($form['actions']['submit']['#submit'], static::class . ':userFormSubmit');
  }

  /**
   * Submit handler that saves user's tourauto data.
   */
  public function userFormSubmit(array &$form, FormStateInterface $form_state): void {
    $account = $form_state->getBuildInfo()['callback_object']->getEntity();
    $manager = $this->tourautoManager->getManagerForAccount($account);

    $manager->setTourautoPreference((bool) $form_state->getValue('tourauto_enable'));

    if ($form_state->getValue('tourauto_clear')) {
      $manager->clearState();
    }
  }

  /**
   * Implements hook_page_bottom().
   */
  #[Hook('page_bottom')]
  public function pageBottom(array &$page_bottom): void {
    $build = [];

    if ($this->tourautoManager->tourautoEnabled()) {
      $tours = $this->tourautoManager->getCurrentTours();

      // Determine if there are any tours on this page that the user hasn't
      // seen.
      $available = array_keys($tours);
      $seen = $this->tourautoManager->getSeenTours();
      $new_tours = array_diff($available, $seen);
      $should_open = (count($new_tours) > 0);

      // Update the user's profile to show they've seen the tours on this page.
      $this->tourautoManager->markToursSeen($new_tours);

      // Let the client know whether it should pop open the tour window.
      $build['#attached']['drupalSettings']['tourauto_open'] = $should_open;
      $build['#attached']['library'][] = 'tourauto/tourauto';

      // Add debugging information.
      if ($this->currentUser->hasPermission('administer site configuration')) {
        $build['#attached']['drupalSettings']['tourauto_debug'] = [
          'enabled' => $this->tourautoManager->tourautoEnabled(),
          'available_tours' => $available,
          'seen_tours' => $seen,
          'new_tours' => $new_tours,
          'should_open' => $should_open,
          'route' => $this->routeMatch->getRouteName(),
          'tour_count' => count($tours),
          'available_count' => count($available),
          'seen_count' => count($seen),
          'new_count' => count($new_tours),
        ];
      }
    }

    // The results of all this logic depend on the current page's tours...
    $build['#cache'] = $page_bottom['tour']['#cache'] ?? [];
    // And also on the current user.
    $build['#cache']['contexts'][] = 'user';
    $this->renderer->addCacheableDependency($build, User::load($this->currentUser->id()));

    $page_bottom['tourauto'] = $build;
  }

}

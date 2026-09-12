<?php

namespace Drupal\tourauto;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\tour\Entity\Tour;
use Drupal\tour\TourHelper;
use Drupal\user\UserDataInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Manages tourauto-related data for a given user account.
 *
 * The tourauto.manager service manages data for the current user. To manage
 * data for a different user, you can create a new instance of the class, using
 * the helper method getManagerForAccount() on the default Drupal
 * tourauto.manager service.
 */
class TourautoManager {

  use StringTranslationTrait;

  /**
   * The tour query interface.
   *
   * @var \Drupal\Core\Entity\Query\QueryInterface
   */
  protected QueryInterface $tourQuery;

  public function __construct(
    protected AccountInterface $account,
    protected UserDataInterface $userData,
    protected RouteMatchInterface $currentRouteMatch,
    protected EntityTypeManagerInterface $entityTypeManager,
    #[Autowire(service: 'tour.helper')]
    protected TourHelper $tourHelper,
    TranslationInterface $stringTranslation,
  ) {
    $this->tourQuery = $this->entityTypeManager->getStorage('tour')->getQuery()->accessCheck(FALSE);
    $this->setStringTranslation($stringTranslation);
  }

  /**
   * Returns an instance of TourautoManager for a different user account.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The account to use (for example, the user being edited on user/%/edit).
   *
   * @return \Drupal\tourauto\TourautoManager
   *   A new TourautoManager instance for the specified account.
   */
  public function getManagerForAccount(AccountInterface $account) {
    return new static($account, $this->userData, $this->currentRouteMatch, $this->entityTypeManager, $this->tourHelper, $this->stringTranslation);
  }

  /**
   * Whether the current user has tourauto enabled.
   *
   * @return bool
   *   TRUE if tourauto is enabled for the user, FALSE otherwise.
   *   Defaults to TRUE for users who haven't saved a preference.
   */
  public function tourautoEnabled(): bool {
    return $this->account->id() && ($this->userData->get('tourauto', $this->account->id(), 'tourauto_enable') ?? TRUE);
  }

  /**
   * Saves the tourauto_enable preference for a given user.
   *
   * @param bool $preference
   *   Whether the user wants tourauto enabled or not.
   */
  public function setTourautoPreference(bool $preference): void {
    $this->account->id() && $this->userData->set('tourauto', $this->account->id(), 'tourauto_enable', (bool) $preference);
  }

  /**
   * Returns a list of tours to be shown for the current route.
   *
   * This should ideally come from tour module.
   * (see https://www.drupal.org/project/drupal/issues/3214593)
   *
   * @return array
   *   Array of tour configuration data, keyed by tour id.
   */
  public function getCurrentTours(): array {
    $items = [];

    if (!$this->account->hasPermission('access tour')) {
      return $items;
    }

    // Use the injected tour helper service to get tours for the current route.
    $results = $this->tourHelper->loadTourEntities();

    if (!empty($results)) {
      $tours = Tour::loadMultiple($results);
      foreach ($tours as $tour_id => $tour) {
        foreach ($tour->getTips() as $tip) {
          $tip_id = $tip->id();
          $items[$tour_id][$tip_id] = $tip->getConfiguration();
        }
      }
    }

    return $items;
  }

  /**
   * Gets the current user's tourauto state.
   *
   * @return array
   *   The user's tourauto state array.
   */
  protected function getState(): array {
    // Load the current user's already visited tours.
    $json = $this->userData->get('tourauto', $this->account->id(), 'tourauto_state') ?? '{}';
    try {
      $state = json_decode($json, JSON_OBJECT_AS_ARRAY);
      $state['seen'] = $state['seen'] ?? [];
    }
    catch (\Exception $e) {
      $state = ['seen' => []];
    }
    return $state;
  }

  /**
   * Resets the user's tourauto state, so all their tours show up as unseen.
   */
  public function clearState(): void {
    $this->userData->delete('tourauto', $this->account->id(), 'tourauto_state');
  }

  /**
   * Returns a list of tours that have been seen by the current user.
   *
   * @return string[]
   *   Array of tour IDs that have been seen.
   */
  public function getSeenTours(): array {
    $state = $this->getState();
    return array_keys($state['seen'] ?? []);
  }

  /**
   * Marks a list of tour IDs as seen for the current user.
   *
   * @param string[] $tours
   *   Array of tour IDs to mark as seen.
   */
  public function markToursSeen(array $tours): void {
    $state = $this->getState();
    $old_json = json_encode($state);
    $state['seen'] = $state['seen'] + array_fill_keys($tours, TRUE);
    $new_json = json_encode($state);
    if ($old_json !== $new_json) {
      $this->userData->set('tourauto', $this->account->id(), 'tourauto_state', $new_json);
    }
  }

  /**
   * Translates a string using the injected translation service.
   *
   * @param string $string
   *   The string to translate.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   The translated string.
   */
  public function translate(string $string) {
    // phpcs:ignore Drupal.Semantics.FunctionT.NotLiteralString
    return $this->t($string);
  }

}

<?php

declare(strict_types=1);

namespace Drupal\acquia_connector\Commands;

use Drupal\acquia_connector\Subscription;
use Drupal\Core\State\StateInterface;
use Drush\Commands\DrushCommands;

/**
 * Refresh Subscription Data for Acquia Connector.
 */
final class RefreshSubscription extends DrushCommands {

  /**
   * The Acquia subscription.
   *
   * @var \Drupal\acquia_connector\Subscription
   */
  private Subscription $subscription;

  /**
   * The state service.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  private StateInterface $state;

  /**
   * Constructs a new RefreshSubscription command object.
   *
   * @param \Drupal\acquia_connector\Subscription $subscription
   *   Acquia Connector Subscription service.
   * @param \Drupal\Core\State\StateInterface $state
   *   The state service.
   */
  public function __construct(Subscription $subscription, StateInterface $state) {
    parent::__construct();
    $this->subscription = $subscription;
    $this->state = $state;
  }

  /**
   * Refreshes Acquia Connector Subscription Data.
   *
   * @option reset
   *   If set, local subscription data will be reset before refreshing.
   * @option debug
   *   Shows the subscription data returned.
   *
   * @command acquia:refresh-subscription
   * @aliases ac-r
   */
  public function refreshSubscription(array $options = ['reset' => FALSE]): void {

    if ($this->input->getOption('reset')) {
      // Also removes any legacy state key data.
      $this->state->deleteMultiple([
        'acquia_subscription_data',
        'acquia_connector.subscription_data',
        'acquia_connector.key',
        'acquia_connector.identifier',
        'acquia_connector.application_uuid',
      ]);
    }

    $subscription = $this->subscription->getSubscription(TRUE);
    if ($this->input->getOption('verbose')) {
      $this->output()->writeln("Subscription ID: " . $this->subscription->getSettings()->getIdentifier());
      $this->output()->writeln("Subscription Key: " . $this->subscription->getSettings()->getSecretKey());
      $this->output()->writeln("Subscription App UUID: " . $this->subscription->getSettings()->getApplicationUuid());
      $this->output()->writeln("Subscription Metadata:");
      $this->output()->writeln(print_r($this->subscription->getSettings()->getMetadata(), TRUE));
      $this->output()->writeln("Subscription Data:");
      $this->output()->writeln(print_r($subscription, TRUE));
    }
    if ($subscription) {
      $this->logger()->success(dt('Successfully refreshed Subscription.'));
      drupal_flush_all_caches();
    }
    else {
      throw new \Exception(dt('Error trying to connect to Acquia. You may need to manually authenicate to retrieve keys.'));
    }
  }

}

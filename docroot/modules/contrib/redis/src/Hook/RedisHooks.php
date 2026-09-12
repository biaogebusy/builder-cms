<?php

namespace Drupal\redis\Hook;

use Drupal\Core\Extension\Requirement\RequirementSeverity;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\redis\ClientFactory;

class RedisHooks {

  use StringTranslationTrait;

  public function __construct(protected ClientFactory $clientFactory) {
  }

  /**
   * Implements hook_help().
   */
  #[Hook('help')]
  function help($route_name, RouteMatchInterface $route_match): ?string {

    switch ($route_name) {
      case 'help.page.redis':

        if ($this->clientFactory->hasClient()) {
          $messages = '<p><strong>' . $this->t("Current connected client uses the <em>@name</em> library.", ['@name' => $this->clientFactory->getClientName()]) . '</strong></p>';
        }
        else {
          $messages = '<p><strong>' . $this->t('No redis connection configured.') . '</strong></p>';
        }
        return $messages;
    }

    return NULL;
  }

  /**
   * Implements hook_runtime_requirements().
   */
  #[Hook('runtime_requirements')]
  function runtimeRequirements() {
    if ($this->clientFactory->hasClient()) {
      $requirements['redis'] = [
        'title'       => "Redis",
        'value'       => $this->t("Connected, using the <em>@name</em> client.", ['@name' => $this->clientFactory->getClientName()]),
        'severity'    => RequirementSeverity::OK,
      ];
    }
    else {
      $requirements['redis'] = [
        'title'       => "Redis",
        'value'       => $this->t("Not connected."),
        'severity'    => RequirementSeverity::Warning,
        'description' => $this->t("No Redis client connected, this module is therefore not used. Ensure that Redis is configured correctly, or disable this module."),
      ];
    }

    return $requirements;
  }

}

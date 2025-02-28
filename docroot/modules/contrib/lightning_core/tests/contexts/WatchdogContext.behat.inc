<?php

use Behat\Behat\Hook\Scope\ScenarioScope;
use Drupal\DrupalExtension\Context\DrupalSubContextBase;

/**
 * Tracks errors logged to the database during the scenario.
 *
 * @internal
 *   This is an internal part of Lightning Core's testing system and may be
 *   changed or removed at any time without warning. It should not be extended,
 *   instantiated, or used in any way by external code! If you need to use this
 *   functionality, you should copy the relevant code into your own project.
 */
final class WatchdogContext extends DrupalSubContextBase {

  /**
   * Start time for the scenario.
   *
   * @var int
   */
  private $startTime;

  /**
   * Indicates if dblog was installed during the scenario.
   *
   * @var bool
   */
  private $uninstall = FALSE;

  /**
   * Checks if errors should be checked after the scenario.
   *
   * @param \Behat\Behat\Hook\Scope\ScenarioScope $scope
   *   The scenario scope.
   *
   * @return bool
   *   TRUE if errors should be checked, FALSE otherwise.
   */
  private function isDiabled(ScenarioScope $scope) {
    return in_array('errors', $scope->getScenario()->getTags());
  }

  /**
   * Install dblog and store the time the scenario began.
   *
   * @param \Behat\Behat\Hook\Scope\ScenarioScope $scope
   *   The scenario scope.
   *
   * @BeforeScenario ~@errors
   */
  public function setUp(ScenarioScope $scope) {
    if ($this->isDiabled($scope)) {
      return;
    }

    if (!\Drupal::moduleHandler()->moduleExists('dblog')) {
      $this->uninstall = \Drupal::service('module_installer')->install(['dblog']);
    }
    $this->startTime = time();
  }

  /**
   * Check for errors since the scenario started.
   *
   * @param \Behat\Behat\Hook\Scope\ScenarioScope $scope
   *   The scenario scope.
   *
   * @AfterScenario ~@errors
   */
  public function checkWatchdog(ScenarioScope $scope) {
    if ($this->isDiabled($scope)) {
      return;
    }

    $db = \Drupal::database();

    if ($db->schema()->tableExists('watchdog')) {
      $log = $db->select('watchdog', 'w')
        ->fields('w')
        ->condition('w.type', 'php', '=')
        ->condition('w.timestamp', $this->startTime, '>=')
        ->execute()
        ->fetchAll();

      if ($log) {
        foreach ($log as $error) {
          // Make the substitutions easier to read in the log.
          $error->variables = unserialize($error->variables);
          print_r($error);
        }
        throw new \Exception('PHP errors logged to watchdog in this scenario.');
      }
    }

    if ($this->uninstall) {
      \Drupal::service('module_installer')->uninstall(['dblog']);
    }
  }

}

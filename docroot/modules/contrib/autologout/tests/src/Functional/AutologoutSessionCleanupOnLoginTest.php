<?php

namespace Drupal\Tests\autologout\Functional;

use Behat\Mink\Driver\BrowserKitDriver;
use Behat\Mink\Session;
use Drupal\Tests\DrupalTestBrowser;
use Drupal\Tests\BrowserTestBase;
use Drupal\user\Entity\User;

/**
 * Tests session cleanup on login.
 *
 * @description Ensure that the autologout module cleans up stale sessions at login
 *
 * @group Autologout
 */
class AutologoutSessionCleanupOnLoginTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = ['autologout', 'node'];

  /**
   * A store references to different sessions.
   *
   * @var array
   */
  protected $loggedInUsers = [];

  /**
   * User with admin rights.
   *
   * @var \Drupal\user\Entity\User|false
   */
  protected $privilegedUser;

  /**
   * Database service.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * Config factory service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Performs any pre-requisite tasks that need to happen.
   */
  public function setUp(): void {
    parent::setUp();
    // Create and log in our privileged user.
    $this->privilegedUser = $this->drupalCreateUser([
      'access content overview',
      'administer site configuration',
      'access site reports',
      'access administration pages',
      'bypass node access',
      'administer content types',
      'administer nodes',
      'administer autologout',
      'change own logout threshold',
    ]);
    $this->configFactory = $this->container->get('config.factory');
    $this->database = $this->container->get('database');
  }

  /**
   * Tests that stale sessions are cleaned up at login.
   */
  public function testSessionCleanupAtLogin() {
    $config = $this->container->get('config.factory')
      ->getEditable('autologout.settings');
    // For the purposes of the test, set the timeout periods to 5 seconds.
    $config->set('timeout', 5)
      ->set('padding', 0)
      ->save();

    // Login in session 1.
    $this->drupalLogin($this->privilegedUser);
    $this->mink->registerSession(
      $this->privilegedUser->sessionId,
      $this->getSession()
    );

    // Check one active session.
    $sessions = $this->getSessions($this->privilegedUser);
    self::assertEquals(
      1,
      count($sessions),
      'After initial login there is one active session'
    );

    // Switch sessions.
    $session1 = $this->stashSession();

    // Login to session 2.
    $this->drupalLogin($this->privilegedUser);

    // Check two active sessions.
    $sessions = $this->getSessions($this->privilegedUser);
    self::assertEquals(
      2,
      count($sessions),
      'After second login there is now two active session'
    );

    $this->stashSession();
    // Switch sessions.
    // Wait for sessions to expire.
    sleep(6);

    // Login to session 3.
    $this->drupalLogin($this->privilegedUser);

    // Check one active session.
    $sessions = $this->getSessions($this->privilegedUser);
    self::assertEquals(
      1,
      count($sessions),
      'After third login, there is 1 active session, two stale sessions were cleaned up.'
    );

    // Switch back to session 1 and check no longer logged in.
    $this->restoreSession($session1);
    $this->drupalGet('node');
    self::assertFalse($this->drupalUserIsLoggedIn($this->privilegedUser));

    $this->closeAllSessions();
  }

  /**
   * Gets active sessions for given user.
   *
   * @param \Drupal\user\Entity\User $account
   *   User account object.
   *
   * @return array
   *   Array of sessions of the user.
   */
  public function getSessions(User $account) {
    // Check there is one session in the sessions table.
    $result = $this->database->select('sessions', 's')
      ->fields('s')
      ->condition('uid', $account->id())
      ->orderBy('timestamp', 'DESC')
      ->execute();
    $sessions = [];
    foreach ($result as $session) {
      $sessions[] = $session;
    }

    return $sessions;
  }

  /**
   * Initialises a new unique session.
   *
   * @return string
   *   Unique identifier for the session just stored.
   */
  public function stashSession() {
    if (empty($this->getSessionName())) {
      return 0;
    }

    $session_id = $this->privilegedUser->sessionId;

    do {
      $this->generateSessionName($this->randomMachineName());
    } while (isset($this->loggedInUsers[$this->getSessionName()]));

    $this->loggedInUsers[$session_id] = clone $this->privilegedUser;
    $client = new DrupalTestBrowser();
    $driver = new BrowserKitDriver($client);
    $session = new Session($driver);
    $this->mink->registerSession(
      $this->getSessionName(),
      $session
    );
    $this->mink->setDefaultSessionName($this->getSessionName());
    $this->loggedInUser = FALSE;

    return $session_id;
  }

  /**
   * Restores a previously stashed session.
   *
   * @param string $session_id
   *   The session to restore as returned by stashSession();
   *   This is also the path to the cookie file.
   *
   * @return string
   *   The old session id that was replaced.
   */
  public function restoreSession($session_id) {
    $old_session_id = NULL;

    if (isset($this->loggedInUsers[$session_id])) {
      $old_session_id = $this->stashSession();
    }

    $this->mink->setDefaultSessionName($session_id);

    $this->loggedInUser = $this->loggedInUsers[$session_id];
    $this->privilegedUser = $this->loggedInUsers[$session_id];
    $this->loggedInUser->sessionId = $session_id;
    $this->privilegedUser->sessionId = $session_id;

    return $old_session_id;
  }

  /**
   * Closes all stashed sessions and the current session.
   */
  public function closeAllSessions() {
    $this->database->truncate('sessions')->execute();
    $this->loggedInUsers = [];
    $this->sessionName = NULL;
    $this->loggedInUser = FALSE;
    $this->mink->resetSessions();
  }

}

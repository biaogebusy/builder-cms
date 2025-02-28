<?php

namespace Drupal\acquia_connector\Controller;

use Drupal\acquia_connector\Client;
use Drupal\Component\Serialization\Json;
use Drupal\Component\Utility\Html;
use Drupal\Core\Config\BootstrapConfigStorageFactory;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Database;
use Drupal\Core\DrupalKernel;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\Logger\RfcLogLevel;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Site\Settings;
use Drupal\node\NodeInterface;
use Drupal\path_alias\AliasManagerInterface;
use Drupal\user\Entity\Role;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * SPI Controller class.
 */
class SpiController extends ControllerBase {

  /**
   * The Acquia client.
   *
   * @var \Drupal\acquia_connector\Client
   */
  protected $client;

  /**
   * Path alias manager.
   *
   * @var mixed
   */
  protected $pathAliasManager;

  /**
   * Constructs a \Drupal\system\ConfigFormBase object.
   *
   * @param \Drupal\acquia_connector\Client $client
   *   Acquia Client.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   Config factory service.
   * @param \Drupal\path_alias\AliasManagerInterface $path_alias
   *   Path alias service.
   */
  public function __construct(Client $client, ConfigFactoryInterface $config_factory, AliasManagerInterface $path_alias) {
    $this->client = $client;
    $this->configFactory = $config_factory;
    $this->pathAliasManager = $path_alias;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('acquia_connector.client'),
      $container->get('config.factory'),
      $container->get('path_alias.manager')
    );
  }

  /**
   * Gather site profile information about this site.
   *
   * @param string $method
   *   Optional identifier for the method initiating request.
   *   Values could be 'cron' or 'menu callback' or 'drush'.
   *
   * @return array
   *   An associative array keyed by types of information.
   */
  public function get($method = '') {
    $config = $this->configFactory->getEditable('acquia_connector.settings');

    // Get the Drupal version.
    $drupal_version = $this->getVersionInfo();

    $stored = $this->dataStoreGet(['platform']);
    if (!empty($stored['platform'])) {
      $platform = $stored['platform'];
    }
    else {
      $platform = $this->getPlatform();
    }

    $acquia_hosted = $this->checkAcquiaHosted();
    $environment = $this->config('acquia_connector.settings')->get('spi.site_environment');
    $env_detection_enabled = $this->config('acquia_connector.settings')->get('spi.env_detection_enabled');
    if ($acquia_hosted) {
      if ($environment != $_SERVER['AH_SITE_ENVIRONMENT']) {
        $config->set('spi.site_environment', $_SERVER['AH_SITE_ENVIRONMENT']);
        $environment = $_SERVER['AH_SITE_ENVIRONMENT'];
        if ($env_detection_enabled) {
          $this->state()->set('spi.site_machine_name', $this->getAcquiaHostedMachineName());
        }
      }
    }
    else {
      if ($environment) {
        $config->set('spi.site_environment', NULL);
      }
      $environment = NULL;
    }

    if ($env_detection_enabled === NULL) {
      $config->set('spi.env_detection_enabled', TRUE);
    }

    $config->save();

    $spi = [
      // Used in HMAC validation.
      'rpc_version'        => ACQUIA_CONNECTOR_ACQUIA_SPI_DATA_VERSION,
      // Used in Fix it now feature.
      'spi_data_version'   => ACQUIA_CONNECTOR_ACQUIA_SPI_DATA_VERSION,
      'site_key'           => sha1(\Drupal::service('private_key')->get()),
      'site_uuid'          => $this->config('acquia_connector.settings')->get('spi.site_uuid'),
      'env_changed_action' => $this->config('acquia_connector.settings')->get('spi.environment_changed_action'),
      'acquia_hosted'      => $acquia_hosted,
      'name'               => $this->state()->get('spi.site_name'),
      'machine_name'       => $this->state()->get('spi.site_machine_name'),
      'environment'        => $environment,
      'modules'            => $this->getModules(),
      'platform'           => $platform,
      'quantum'            => $this->getQuantum(),
      'system_status'      => $this->getSystemStatus(),
      'failed_logins'      => $this->config('acquia_connector.settings')->get('spi.send_watchdog') ? $this->getFailedLogins() : [],
      '404s'               => $this->config('acquia_connector.settings')->get('spi.send_watchdog') ? $this->get404s() : [],
      'watchdog_size'      => $this->getWatchdogSize(),
      'watchdog_data'      => $this->config('acquia_connector.settings')->get('spi.send_watchdog') ? $this->getWatchdogData() : [],
      'last_nodes'         => $this->config('acquia_connector.settings')->get('spi.send_node_user') ? $this->getLastNodes() : [],
      'last_users'         => $this->config('acquia_connector.settings')->get('spi.send_node_user') ? $this->getLastUsers() : [],
      'extra_files'        => $this->checkFilesPresent(),
      'ssl_login'          => $this->checkLogin(),
      'distribution'       => $drupal_version['distribution'] ?? '',
      'base_version'       => $drupal_version['base_version'],
      'build_data'         => $drupal_version,
      'roles'              => Json::encode(user_roles()),
      'uid_0_present'      => $this->getUidZeroIsPresent(),
    ];

    $scheme = parse_url($this->config('acquia_connector.settings')->get('spi.server'), PHP_URL_SCHEME);
    $via_ssl = (in_array('ssl', stream_get_transports(), TRUE) && $scheme == 'https') ? TRUE : FALSE;
    if ($this->config('acquia_connector.settings')->get('spi.ssl_override')) {
      $via_ssl = TRUE;
    }

    $additional_data = [];

    $security_review = new SecurityReviewController();
    $security_review_results = $security_review->runSecurityReview();

    // It's worth sending along node access control information even if there
    // are no modules implementing it - some alerts are simpler if we know we
    // don't have to worry about node access.
    // Check for node grants modules.
    // Compatibility for Drupal 9.2.
    if (method_exists(\Drupal::moduleHandler(), 'invokeAllWith')) {
      $additional_data['node_grants_modules'] = [];
      \Drupal::moduleHandler()->invokeAllWith('node_grants', function (callable $hook, string $module) use (&$modules) {
        // There is minimal overhead since the hook is not invoked.
        $additional_data['node_grants_modules'][] = $module;
      });
    }
    else {
      // @phpstan-ignore-next-line
      $additional_data['node_grants_modules'] = \Drupal::moduleHandler()->getImplementations('node_grants');
    }

    // Check for node access modules.
    // Compatibility for Drupal 9.2.
    if (method_exists(\Drupal::moduleHandler(), 'invokeAllWith')) {
      $additional_data['node_grants_modules'] = [];
      \Drupal::moduleHandler()->invokeAllWith('node_access', function (callable $hook, string $module) use (&$modules) {
        // There is minimal overhead since the hook is not invoked.
        $additional_data['node_access_modules'][] = $module;
      });
    }
    else {
      // @phpstan-ignore-next-line
      $additional_data['node_access_modules'] = \Drupal::moduleHandler()->getImplementations('node_access');
    }

    if (!empty($security_review_results)) {
      $additional_data['security_review'] = $security_review_results['security_review'];
    }

    // Collect all user-contributed custom tests that pass validation.
    $custom_tests_results = $this->testCollect();
    if (!empty($custom_tests_results)) {
      $additional_data['custom_tests'] = $custom_tests_results;
    }

    $spi_data = $this->moduleHandler()->invokeAll('acquia_connector_spi_get');
    if (!empty($spi_data)) {
      foreach ($spi_data as $name => $data) {
        if (is_string($name) && is_array($data)) {
          $additional_data[$name] = $data;
        }
      }
    }

    include_once "core/includes/update.inc";
    $additional_data['pending_updates'] = (bool) update_get_update_list();

    if (!empty($additional_data)) {
      // JSON encode this additional data.
      $spi['additional_data'] = json_encode($additional_data);
    }

    if (!empty($method)) {
      $spi['send_method'] = $method;
    }

    if (!$via_ssl) {
      return $spi;
    }
    else {
      $variablesController = new VariablesController();
      // Values returned only over SSL.
      $spi_ssl = [
        'system_vars' => $variablesController->getVariablesData(),
        'settings_ra' => $this->getSettingsPermissions(),
        'admin_count' => $this->config('acquia_connector.settings')->get('spi.admin_priv') ? $this->getAdminCount() : '',
        'admin_name' => $this->config('acquia_connector.settings')->get('spi.admin_priv') ? $this->getSuperName() : '',
      ];

      return array_merge($spi, $spi_ssl);
    }
  }

  /**
   * Collects all user-contributed test results that pass validation.
   *
   * @return array
   *   An associative array containing properly formatted user-contributed
   *   tests.
   */
  private function testCollect() {
    $custom_data = [];

    // Collect all custom data provided by hook_insight_custom_data().
    $collections = $this->moduleHandler()->invokeAll('acquia_connector_spi_test');

    foreach ($collections as $test_name => $test_params) {
      $status = new TestStatusController();
      $result = $status->testValidate([$test_name => $test_params]);

      if ($result['result']) {
        $custom_data[$test_name] = $test_params;
      }
    }

    return $custom_data;
  }

  /**
   * Checks to see if SSL login is required.
   *
   * @return int
   *   1 if SSL login is required.
   */
  private function checkLogin() {
    $login_safe = 0;

    if ($this->moduleHandler()->moduleExists('securelogin')) {
      $secureLoginConfig = $this->config('securelogin.settings')->get();
      if ($secureLoginConfig['all_forms']) {
        $forms_safe = TRUE;
      }
      else {
        // All the required forms should be enabled.
        $required_forms = [
          'form_user_login_form',
          'form_user_form',
          'form_user_register_form',
          'form_user_pass_reset',
          'form_user_pass',
        ];
        $forms_safe = TRUE;
        foreach ($required_forms as $form_variable) {
          if (!$secureLoginConfig[$form_variable]) {
            $forms_safe = FALSE;
            break;
          }
        }
      }
      // \Drupal::request()->isSecure() ($conf['https'] in D7) should be false
      // for expected behavior.
      if ($forms_safe && !\Drupal::request()->isSecure()) {
        $login_safe = 1;
      }
    }

    return $login_safe;
  }

  /**
   * Check to see if the unneeded release files with Drupal are removed.
   *
   * @return int
   *   1 if they are removed, 0 if they aren't.
   */
  private function checkFilesPresent() {

    $files_exist = FALSE;
    $files_to_remove = [
      'CHANGELOG.txt',
      'COPYRIGHT.txt',
      'INSTALL.mysql.txt',
      'INSTALL.pgsql.txt',
      'INSTALL.txt',
      'LICENSE.txt',
      'MAINTAINERS.txt',
      'README.txt',
      'UPGRADE.txt',
      'PRESSFLOW.txt',
      'install.php',
    ];

    foreach ($files_to_remove as $file) {

      $path = DRUPAL_ROOT . DIRECTORY_SEPARATOR . $file;
      if (file_exists($path)) {
        $files_exist = TRUE;
      }
    }

    return $files_exist ? 1 : 0;
  }

  /**
   * Attempt to determine if this site is hosted with Acquia.
   *
   * @return bool
   *   TRUE if site is hosted with Acquia, otherwise FALSE.
   */
  public function checkAcquiaHosted() {
    return isset($_SERVER['AH_SITE_ENVIRONMENT'], $_SERVER['AH_SITE_NAME']);
  }

  /**
   * Generate the name for acquia hosted sites.
   *
   * @return string
   *   The Acquia Hosted name.
   */
  public function getAcquiaHostedName() {
    $subscription_name = $this->config('acquia_connector.settings')->get('subscription_name');

    if ($this->checkAcquiaHosted() && $subscription_name) {
      return $this->config('acquia_connector.settings')->get('subscription_name') . ': ' . $_SERVER['AH_SITE_ENVIRONMENT'];
    }
  }

  /**
   * Generate the machine name for acquia hosted sites.
   *
   * @return string
   *   The suggested Acquia Hosted machine name.
   */
  public function getAcquiaHostedMachineName() {
    $sub_data = $this->state()->get('acquia_subscription_data');

    if ($this->checkAcquiaHosted() && is_array($sub_data)) {
      $uuid = new StatusController();
      $sub_uuid = str_replace('-', '_', $uuid->getIdFromSub($sub_data));

      return $sub_uuid . '__' . $_SERVER['AH_SITE_NAME'] . '__' . uniqid();
    }
  }

  /**
   * Check if a site environment change has been detected.
   *
   * @return bool
   *   TRUE if change detected that needs to be addressed, otherwise FALSE.
   */
  public function checkEnvironmentChange() {
    $changes = $this->config('acquia_connector.settings')->get('spi.environment_changes');
    $change_action = $this->config('acquia_connector.settings')->get('spi.environment_changed_action');

    return !empty($changes) && empty($change_action);
  }

  /**
   * Get last 15 users created.
   *
   * Useful for determining if your site is compromised.
   *
   * @return array
   *   The details of last 15 users created.
   */
  private function getLastUsers() {
    $last_five_users = [];
    $result = Database::getConnection()->select('users_field_data', 'u')
      ->fields('u', ['uid', 'name', 'mail', 'created'])
      ->condition('u.created', \Drupal::time()->getRequestTime() - 3600, '>')
      ->orderBy('created', 'DESC')
      ->range(0, 15)
      ->execute();

    $count = 0;
    foreach ($result as $record) {
      $last_five_users[$count]['uid'] = $record->uid;
      $last_five_users[$count]['name'] = $record->name;
      $last_five_users[$count]['email'] = $record->mail;
      $last_five_users[$count]['created'] = $record->created;
      $count++;
    }

    return $last_five_users;
  }

  /**
   * Get last 15 nodes created.
   *
   * This can be useful to determine if you have some sort of spam on your site.
   *
   * @return array
   *   Array of the details of last 15 nodes created.
   */
  private function getLastNodes() {
    $last_five_nodes = [];
    if ($this->moduleHandler()->moduleExists('node')) {
      $result = Database::getConnection()->select('node_field_data', 'n')
        ->fields('n', ['title', 'type', 'nid', 'created', 'langcode'])
        ->condition('n.created', \Drupal::time()->getRequestTime() - 3600, '>')
        ->orderBy('n.created', 'DESC')
        ->range(0, 15)
        ->execute();

      $count = 0;
      foreach ($result as $record) {
        $last_five_nodes[$count]['url'] = $this->pathAliasManager
          ->getAliasByPath('/node/' . $record->nid, $record->langcode);
        $last_five_nodes[$count]['title'] = $record->title;
        $last_five_nodes[$count]['type'] = $record->type;
        $last_five_nodes[$count]['created'] = $record->created;
        $count++;
      }
    }

    return $last_five_nodes;
  }

  /**
   * Get the latest (last hour) critical and emergency warnings from watchdog.
   *
   * These errors are 'severity' 0 and 2.
   *
   * @return array
   *   EMERGENCY and CRITICAL watchdog records for last hour.
   */
  private function getWatchdogData() {
    $wd = [];
    if ($this->moduleHandler()->moduleExists('dblog')) {
      // phpcs:disable
      $result = Database::getConnection()->select('watchdog', 'w')
        ->fields('w', ['wid', 'severity', 'type', 'message', 'timestamp'])
        ->condition('w.severity', [RfcLogLevel::EMERGENCY, RfcLogLevel::CRITICAL], 'IN')
        ->condition('w.timestamp', \Drupal::time()->getRequestTime() - 3600, '>')
        ->execute();
      // phpcs:enable

      while ($record = $result->fetchAssoc()) {
        $wd[$record['severity']] = $record;
      }
    }

    return $wd;
  }

  /**
   * Get the number of rows in watchdog.
   *
   * @return int
   *   Number of watchdog records.
   */
  private function getWatchdogSize() {
    if ($this->moduleHandler()->moduleExists('dblog')) {
      return Database::getConnection()->select('watchdog', 'w')->fields('w', ['wid'])->countQuery()->execute()->fetchField();
    }
  }

  /**
   * Grabs the last 404 errors in logs.
   *
   * Grabs the last 404 errors in logs, excluding the checks we run for drupal
   * files like README.
   *
   * @return array
   *   An array of the pages not found and some associated data.
   */
  private function get404s() {
    $data = [];
    $row = 0;

    if ($this->moduleHandler()->moduleExists('dblog')) {
      $result = Database::getConnection()->select('watchdog', 'w')
        ->fields('w', ['message', 'hostname', 'referer', 'timestamp'])
        ->condition('w.type', 'page not found', '=')
        ->condition('w.timestamp', \Drupal::time()->getRequestTime() - 3600, '>')
        ->condition('w.message', [
          "UPGRADE.txt",
          "MAINTAINERS.txt",
          "README.txt",
          "INSTALL.pgsql.txt",
          "INSTALL.txt",
          "LICENSE.txt",
          "INSTALL.mysql.txt",
          "COPYRIGHT.txt",
          "CHANGELOG.txt",
        ], 'NOT IN')
        ->orderBy('w.timestamp', 'DESC')
        ->range(0, 10)
        ->execute();

      foreach ($result as $record) {
        $data[$row]['message'] = $record->message;
        $data[$row]['hostname'] = $record->hostname;
        $data[$row]['referer'] = $record->referer;
        $data[$row]['timestamp'] = $record->timestamp;
        $row++;
      }
    }

    return $data;
  }

  /**
   * Get the information on failed logins in the last cron interval.
   *
   * @return array
   *   Array of last 10 failed logins.
   */
  private function getFailedLogins() {
    $last_logins = [];
    $cron_interval = $this->config('acquia_connector.settings')->get('spi.cron_interval');

    if ($this->moduleHandler()->moduleExists('dblog')) {
      $result = Database::getConnection()->select('watchdog', 'w')
        ->fields('w', ['message', 'variables', 'timestamp'])
        ->condition('w.message', 'login attempt failed%', 'LIKE')
        ->condition('w.timestamp', \Drupal::time()->getRequestTime() - $cron_interval, '>')
        ->condition('w.message', [
          "UPGRADE.txt",
          "MAINTAINERS.txt",
          "README.txt",
          "INSTALL.pgsql.txt",
          "INSTALL.txt",
          "LICENSE.txt",
          "INSTALL.mysql.txt",
          "COPYRIGHT.txt",
          "CHANGELOG.txt",
        ], 'NOT IN')
        ->orderBy('w.timestamp', 'DESC')
        ->range(0, 10)
        ->execute();

      foreach ($result as $record) {
        $variables = unserialize($record->variables);
        if (!empty($variables['%user'])) {
          $last_logins['failed'][$record->timestamp] = Html::escape($variables['%user']);
        }
      }
    }
    return $last_logins;
  }

  /**
   * This function is a trimmed version of Drupal's system_status function.
   *
   * @return array
   *   System status array.
   */
  private function getSystemStatus() {
    $data = [];

    if (\Drupal::hasContainer()) {
      $profile = \Drupal::installProfile();
    }
    else {
      $profile = BootstrapConfigStorageFactory::getDatabaseStorage()->read('core.extension')['profile'];
    }
    if ($profile != 'standard') {
      $extension_list = \Drupal::service('extension.list.module');
      $info = $extension_list->getExtensionInfo($profile);
      $data['install_profile'] = [
        'title' => 'Install profile',
        'value' => sprintf('%s (%s-%s)', $info['name'], $profile, $info['version']),
      ];
    }
    $data['php'] = [
      'title' => 'PHP',
      'value' => phpversion(),
    ];
    $conf_dir = TRUE;
    $settings = TRUE;
    $dir = DrupalKernel::findSitePath(\Drupal::request(), TRUE);
    if (is_writable($dir) || is_writable($dir . '/settings.php')) {
      $value = 'Not protected';
      if (is_writable($dir)) {
        $conf_dir = FALSE;
      }
      elseif (is_writable($dir . '/settings.php')) {
        $settings = FALSE;
      }
    }
    else {
      $value = 'Protected';
    }
    $data['settings.php'] = [
      'title' => 'Configuration file',
      'value' => $value,
      'conf_dir' => $conf_dir,
      'settings' => $settings,
    ];
    $cron_last = \Drupal::state()->get('system.cron_last');
    if (!is_numeric($cron_last)) {
      $cron_last = \Drupal::state()->get('install_time', 0);
    }
    $data['cron'] = [
      'title' => 'Cron maintenance tasks',
      'value' => sprintf('Last run %s ago', \Drupal::service('date.formatter')->formatInterval(\Drupal::time()->getRequestTime() - $cron_last)),
      'cron_last' => $cron_last,
    ];
    if (!empty(Settings::get('update_free_access'))) {
      $data['update access'] = [
        'value' => 'Not protected',
        'protected' => FALSE,
      ];
    }
    else {
      $data['update access'] = [
        'value' => 'Protected',
        'protected' => TRUE,
      ];
    }
    $data['update access']['title'] = 'Access to update.php';
    if (!$this->moduleHandler()->moduleExists('update')) {
      $data['update status'] = [
        'value' => 'Not enabled',
      ];
    }
    else {
      $data['update status'] = [
        'value' => 'Enabled',
      ];
    }
    $data['update status']['title'] = 'Update notifications';
    return $data;
  }

  /**
   * Check the presence of UID 0 in the users table.
   *
   * @return bool
   *   Whether UID 0 is present.
   */
  private function getUidZeroIsPresent() {
    $count = Database::getConnection()->query('SELECT uid FROM {users} WHERE uid = 0')->fetchAll();
    return (boolean) $count;
  }

  /**
   * The number of users who have admin-level user roles.
   *
   * @return int
   *   Count of admin users.
   */
  private function getAdminCount() {
    $roles_name = [];
    $get_roles = Role::loadMultiple();
    unset($get_roles[AccountInterface::ANONYMOUS_ROLE]);
    $permission = ['administer permissions', 'administer users'];
    foreach ($permission as $value) {
      $filtered_roles = array_filter($get_roles, function ($role) use ($value) {
        return $role->hasPermission($value);
      });
      foreach ($filtered_roles as $role_name => $data) {
        $roles_name[] = $role_name;
      }
    }

    if (!empty($roles_name)) {
      $roles_name_unique = array_unique($roles_name);
      $query = Database::getConnection()->select('user__roles', 'ur');
      $query->fields('ur', ['entity_id']);
      $query->condition('ur.bundle', 'user', '=');
      $query->condition('ur.deleted', '0', '=');
      $query->condition('ur.roles_target_id', $roles_name_unique, 'IN');
      $count = $query->countQuery()->execute()->fetchField();
    }

    return (isset($count) && is_numeric($count)) ? $count : NULL;
  }

  /**
   * Determine if the super user has a weak name.
   *
   * @return int
   *   1 if the super user has a weak name, 0 otherwise.
   */
  private function getSuperName() {
    $result = Database::getConnection()->query("SELECT name FROM {users_field_data} WHERE uid = 1 AND (name LIKE '%admin%' OR name LIKE '%root%') AND LENGTH(name) < 15")->fetchAll();
    return (int) $result;
  }

  /**
   * Determines if settings.php is read-only.
   *
   * @return bool
   *   TRUE if settings.php is read-only, FALSE otherwise.
   */
  private function getSettingsPermissions() {
    $settings_permissions_read_only = TRUE;
    // http://en.wikipedia.org/wiki/File_system_permissions.
    $writes = ['2', '3', '6', '7'];
    $settings_file = './' . DrupalKernel::findSitePath(\Drupal::request(), TRUE) . '/settings.php';
    $permissions = mb_substr(sprintf('%o', fileperms($settings_file)), -4);

    foreach ($writes as $bit) {
      if (strpos($permissions, $bit)) {
        $settings_permissions_read_only = FALSE;
        break;
      }
    }

    return $settings_permissions_read_only;
  }

  /**
   * Determine if a path is a file type we care about for modifications.
   */
  private function isManifestType($path) {
    $extensions = [
      'yml' => 1,
      'php' => 1,
      'php4' => 1,
      'php5' => 1,
      'module' => 1,
      'inc' => 1,
      'install' => 1,
      'test' => 1,
      'theme' => 1,
      'engine' => 1,
      'profile' => 1,
      'css' => 1,
      'js' => 1,
      'info' => 1,
      'sh' => 1,
      // SSL certificates.
      'pem' => 1,
      'pl' => 1,
      'pm' => 1,
    ];
    $pathinfo = pathinfo($path);
    return isset($pathinfo['extension']) && isset($extensions[$pathinfo['extension']]);
  }

  /**
   * Calculate the sha1 hash for a path.
   *
   * @param string $path
   *   The name of the file or a directory.
   *
   * @return string
   *   base64 encoded sha1 hash. 'hash' is an empty string for directories.
   */
  private function hashPath($path = '') {
    $hash = '';
    if (file_exists($path)) {
      if (!is_dir($path)) {
        $string = file_get_contents($path);
        // Remove trailing whitespace.
        $string = rtrim($string);
        // Replace all line endings and CVS/svn Id tags.
        $string = preg_replace('/\$Id[^;<>{}\(\)\$]*\$/', 'x$' . 'Id$', $string);
        $string = preg_replace('/\r\n|\n|\r/', ' ', $string);
        $hash = base64_encode(pack("H*", sha1($string)));
      }
    }
    return $hash;
  }

  /**
   * Attempt to determine the version of Drupal being used.
   *
   * Note, there is better information on this in the common.inc file.
   *
   * @return array
   *   An array containing some detail about the version
   */
  private function getVersionInfo() {
    $server = \Drupal::request()->server->all();
    $ver = [];

    $ver['base_version'] = \Drupal::VERSION;
    $install_root = DRUPAL_ROOT;
    $ver['distribution'] = '';

    // Determine if this puppy is Acquia Drupal.
    acquia_connector_load_versions();

    if (IS_ACQUIA_DRUPAL) {
      $ver['distribution']   = 'Acquia Drupal';
      $ver['ad']['version']  = ACQUIA_DRUPAL_VERSION;
      $ver['ad']['series']   = ACQUIA_DRUPAL_SERIES;
      $ver['ad']['branch']   = ACQUIA_DRUPAL_BRANCH;
      $ver['ad']['revision'] = ACQUIA_DRUPAL_REVISION;
    }

    // Determine if we are looking at Pressflow.
    if (defined('CACHE_EXTERNAL')) {
      $ver['distribution'] = 'Pressflow';
      $press_version_file = $install_root . './PRESSFLOW.txt';
      if (is_file($press_version_file)) {
        $ver['pr']['version'] = trim(file_get_contents($press_version_file));
      }
    }
    // Determine if this is Open Atrium.
    elseif (is_dir($install_root . '/profiles/openatrium')) {
      $ver['distribution'] = 'Open Atrium';
      $version_file = $install_root . 'profiles/openatrium/VERSION.txt';
      if (is_file($version_file)) {
        $ver['oa']['version'] = trim(file_get_contents($version_file));
      }
    }
    // Determine if this is Commons.
    elseif (is_dir($install_root . '/profiles/commons')) {
      $ver['distribution'] = 'Commons';
    }
    // Determine if this is COD.
    elseif (is_dir($install_root . '/profiles/cod')) {
      $ver['distribution'] = 'COD';
    }

    return $ver;
  }

  /**
   * Put SPI data in local storage.
   *
   * @param array $data
   *   Keyed array of data to store.
   * @param int $expire
   *   Expire time or null to use default of 1 day.
   */
  public function dataStoreSet(array $data, $expire = NULL) {
    if (is_null($expire)) {
      $expire = \Drupal::time()->getRequestTime() + (60 * 60 * 24);
    }
    foreach ($data as $key => $value) {
      \Drupal::cache()->set('acquia.spi.' . $key, $value, $expire);
    }
  }

  /**
   * Get SPI data out of local storage.
   *
   * @param array $keys
   *   Array of keys to extract data for.
   *
   * @return array
   *   Stored data or false if no data is retrievable from storage.
   */
  public function dataStoreGet(array $keys) {
    $store = [];
    foreach ($keys as $key) {
      if ($cache = \Drupal::cache()->get('acquia.spi.' . $key)) {
        if (!empty($cache->data)) {
          $store[$key] = $cache->data;
        }
      }
    }
    return $store;
  }

  /**
   * Gather platform specific information.
   *
   * @return array
   *   An associative array keyed by a platform information type.
   */
  public static function getPlatform() {
    $server = \Drupal::request()->server;
    // Database detection depends on the structure starting with the database.
    $db_class = '\Drupal\Core\Database\Driver\\' . Database::getConnection()->driver() . '\Install\Tasks';
    $db_tasks = new $db_class();
    // Webserver detection is based on name being before the slash, and
    // version being after the slash.
    preg_match('!^([^/]+)(/.+)?$!', (string) $server->get('SERVER_SOFTWARE'), $webserver);

    if (isset($webserver[1]) && stristr($webserver[1], 'Apache') && function_exists('apache_get_version')) {
      $webserver[2] = apache_get_version();
    }

    // Get some basic PHP vars.
    $php_quantum = [
      'memory_limit' => ini_get('memory_limit'),
      'register_globals' => 'Off',
      'post_max_size' => ini_get('post_max_size'),
      'max_execution_time' => ini_get('max_execution_time'),
      'upload_max_filesize' => ini_get('upload_max_filesize'),
      'error_log' => ini_get('error_log'),
      'error_reporting' => ini_get('error_reporting'),
      'display_errors' => ini_get('display_errors'),
      'log_errors' => ini_get('log_errors'),
      'session.cookie_domain' => ini_get('session.cookie_domain'),
      'session.cookie_lifetime' => ini_get('session.cookie_lifetime'),
      'newrelic.appname' => ini_get('newrelic.appname'),
      'sapi' => php_sapi_name(),
    ];

    $platform = [
      'php'               => PHP_VERSION,
      'webserver_type'    => $webserver[1] ?? '',
      'webserver_version' => $webserver[2] ?? '',
      'php_extensions'    => get_loaded_extensions(),
      'php_quantum'       => $php_quantum,
      'database_type'     => (string) $db_tasks->name(),
      'database_version'  => Database::getConnection()->version(),
      'system_type'       => php_uname('s'),
      // php_uname() only accepts one character, so we need to concatenate
      // ourselves.
      'system_version'    => php_uname('r') . ' ' . php_uname('v') . ' ' . php_uname('m') . ' ' . php_uname('n'),
    ];

    return $platform;
  }

  /**
   * Gather information about modules on the site.
   *
   * @return array
   *   An associative array keyed by filename of associative arrays with
   *   information on the modules.
   */
  private function getModules() {
    $modules = \Drupal::service('extension.list.module')->reset()->getList();
    if (method_exists(ModuleExtensionList::class, 'sortByName')) {
      uasort($modules, [ModuleExtensionList::class, 'sortByName']);
    }
    else {
      // @phpstan-ignore-next-line
      uasort($modules, 'system_sort_modules_by_info_name');
    }

    $result = [];
    $keys_to_send = ['name', 'version', 'package', 'core', 'project'];
    foreach ($modules as $module) {
      $info = [];
      $info['status'] = $module->status;
      foreach ($keys_to_send as $key) {
        $info[$key] = $module->info[$key] ?? '';
      }
      $info['filename'] = $module->getPathname();
      if (empty($info['project']) && $module->origin == 'core') {
        $info['project'] = 'drupal';
      }

      $result[] = $info;
    }
    return $result;
  }

  /**
   * Gather information about nodes, users and comments.
   *
   * @return array
   *   An associative array.
   */
  private function getQuantum() {
    $quantum = [];

    if ($this->moduleHandler()->moduleExists('node')) {
      // Get only published nodes.
      $quantum['nodes'] = Database::getConnection()->select('node_field_data', 'n')
        ->fields('n', ['nid'])
        ->condition('n.status', NodeInterface::PUBLISHED)
        ->countQuery()
        ->execute()
        ->fetchField();
    }

    // Get only active users.
    $quantum['users'] = Database::getConnection()->select('users_field_data', 'u')
      ->fields('u', ['uid'])
      ->condition('u.status', 1)
      ->countQuery()
      ->execute()
      ->fetchField();

    if ($this->moduleHandler()->moduleExists('comment')) {
      // Get only active comments.
      $quantum['comments'] = Database::getConnection()->select('comment_field_data', 'c')
        ->fields('c', ['cid'])
        ->condition('c.status', 1)
        ->countQuery()
        ->execute()
        ->fetchField();
    }

    return $quantum;
  }

  /**
   * Gather full SPI data and send to Acquia.
   *
   * @param string $method
   *   Optional identifier for the method initiating request.
   *   Values could be 'cron' or 'menu callback' or 'drush'.
   *
   * @return mixed
   *   FALSE if data is not sent or environment change detected,
   *   otherwise return NSPI response array.
   */
  public function sendFullSpi($method = '') {
    $this->getLogger('acquia spi')->error('Acquia Insight is EOL. Please remove this method call from your module/scripts.');
    return FALSE;
  }

  /**
   * Parses and displays messages from the NSPI response.
   *
   * @param array $response
   *   Response array from NSPI.
   *
   * @deprecated in acquia_connector:3.0.5 and is removed from
   *   acquia_connector:4.0.0. You can safely remove any calls to this method,
   *   as Acquia Insight is now EOL.
   *
   * @see https://www.drupal.org/node/3300034
   */
  public function spiProcessMessages(array $response) {
    $this->messenger()
      ->addError($this->t('The spiProcessMessages method is no longer supported, you can remove any custom calls to Acquia Insight.'));
    return [];
  }

}

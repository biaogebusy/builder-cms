<?php

namespace Drupal\acquia_connector\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\DrupalKernel;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Site\Settings;
use Drupal\field\Entity\FieldConfig;
use Drupal\user\UserInterface;
use Drupal\views\Views;

/**
 * Acquia Security Review page.
 *
 * @package Drupal\acquia_connector\Controller
 */
class SecurityReviewController extends ControllerBase {

  /**
   * Run some checks from the Security Review module.
   */
  public function runSecurityReview() {
    // Collect the checklist.
    $checklist = $this->securityReviewGetChecks();
    // Run only specific checks.
    $to_check = [
      'views_access',
      'temporary_files',
      'executable_php',
      'input_formats',
      'admin_permissions',
      'untrusted_php',
      'private_files',
      'upload_extensions',
    ];
    foreach ($checklist as $module => $checks) {
      foreach ($checks as $check_name => $args) {
        if (!in_array($check_name, $to_check)) {
          unset($checklist[$module][$check_name]);
        }
      }
      if (empty($checklist[$module])) {
        unset($checklist[$module]);
      }
    }
    $checklist_results = $this->securityReviewRun($checklist);
    foreach ($checklist_results as $module => $checks) {
      foreach ($checks as $check_name => $check) {
        // Unset data that does not need to be sent.
        if (is_null($check['result'])) {
          unset($checklist_results[$module][$check_name]);
        }
        else {
          unset($check['success']);
          unset($check['failure']);
          $checklist_results[$module][$check_name] = $check;
        }
      }
      if (empty($checklist_results[$module])) {
        unset($checklist_results[$module]);
      }
    }
    return $checklist_results;
  }

  /**
   * Function for running Security Review checklist and returning results.
   *
   * @param array|null $checklist
   *   Array of checks to run, indexed by module namespace.
   * @param bool $log
   *   Whether to log check processing using security_review_log.
   * @param bool $help
   *   Whether to load the help file and include in results.
   *
   * @return array
   *   Results from running checklist, indexed by module namespace.
   */
  private function securityReviewRun(array $checklist = NULL, $log = FALSE, $help = FALSE) {
    return $this->getSecurityReviewResults($checklist, $log);
  }

  /**
   * Private function the review and returns the full results.
   *
   * @param array $checklist
   *   Array of checks.
   * @param bool $log
   *   If TRUE logs result.
   *
   * @return array
   *   Result.
   */
  private function getSecurityReviewResults(array $checklist, $log = FALSE) {
    $results = [];
    foreach ($checklist as $module => $checks) {
      foreach ($checks as $check_name => $arguments) {
        $check_result = $this->getSecurityReviewRunCheck($module, $check_name, $arguments, $log);
        if (!empty($check_result)) {
          $results[$module][$check_name] = $check_result;
        }
      }
    }
    return $results;
  }

  /**
   * Run a single Security Review check.
   */
  private function getSecurityReviewRunCheck($module, $check_name, $check, $log, $store = FALSE) {
    $return = ['result' => NULL];
    if (isset($check['file'])) {
      // Handle Security Review defining checks for other modules.
      if (isset($check['module'])) {
        $module = $check['module'];
      }
      \Drupal::moduleHandler()->loadInclude($module, 'inc', $check['file']);
    }
    $function = $check['callback'];
    if (method_exists($this, $function)) {

      $return = call_user_func([
        __NAMESPACE__ . '\SecurityReviewController',
        $function,
      ]);

    }
    $check_result = array_merge($check, $return);
    $check_result['lastrun'] = \Drupal::time()->getRequestTime();

    // Do not log if result is NULL.
    if ($log && !is_null($return['result'])) {
      $variables = ['@name' => $check_result['title']];
      if ($check_result['result']) {
        $this->getSecurityReviewLog($module, $check_name, '@name check passed', $variables, WATCHDOG_INFO);
      }
      else {
        $this->getSecurityReviewLog($module, $check_name, '@name check failed', $variables, WATCHDOG_ERROR);
      }
    }
    return $check_result;
  }

  /**
   * Log results.
   *
   * @param string $module
   *   Module.
   * @param string $check_name
   *   Check name.
   * @param string $message
   *   Message.
   * @param array $variables
   *   Variables.
   * @param string $type
   *   Event type.
   */
  private function getSecurityReviewLog($module, $check_name, $message, array $variables, $type) {
    $this->moduleHandler()
      ->invokeAll('acquia_spi_security_review_log', [
        $module,
        $check_name,
        $message,
        $variables,
        $type,
      ]);
  }

  /**
   * Helper function allows for collection of this file's security checks.
   */
  private function securityReviewGetChecks() {
    // Use Security Review's checks if available.
    if ($this->moduleHandler()->moduleExists('security_review') && function_exists('security_review_security_checks')) {
      return $this->moduleHandler()->invokeAll('security_checks');
    }
    else {
      return $this->securityReviewSecurityChecks();
    }
  }

  /**
   * Checks for acquia_spi_security_review_get_checks().
   *
   * @return array
   *   Result.
   */
  private function securityReviewSecurityChecks() {

    $checks['input_formats'] = [
      'title' => $this->t('Text formats'),
      'callback' => 'checkInputFormats',
      'success' => $this->t('Untrusted users are not allowed to input dangerous HTML tags.'),
      'failure' => $this->t('Untrusted users are allowed to input dangerous HTML tags.'),
    ];
    $checks['upload_extensions'] = [
      'title' => $this->t('Allowed upload extensions'),
      'callback' => 'checkUploadExtensions',
      'success' => $this->t('Only safe extensions are allowed for uploaded files and images.'),
      'failure' => $this->t('Unsafe file extensions are allowed in uploads.'),
    ];
    $checks['admin_permissions'] = [
      'title' => $this->t('Drupal permissions'),
      'callback' => 'checkAdminPermissions',
      'success' => $this->t('Untrusted roles do not have administrative or trusted Drupal permissions.'),
      'failure' => $this->t('Untrusted roles have been granted administrative or trusted Drupal permissions.'),
    ];
    // Check dependent on PHP filter being enabled.
    if ($this->moduleHandler()->moduleExists('php')) {
      $checks['untrusted_php'] = [
        'title' => $this->t('PHP access'),
        'callback' => 'checkPhpFilter',
        'success' => $this->t('Untrusted users do not have access to use the PHP input format.'),
        'failure' => $this->t('Untrusted users have access to use the PHP input format.'),
      ];
    }
    $checks['executable_php'] = [
      'title' => $this->t('Executable PHP'),
      'callback' => 'checkExecutablePhp',
      'success' => $this->t('PHP files in the Drupal files directory cannot be executed.'),
      'failure' => $this->t('PHP files in the Drupal files directory can be executed.'),
    ];
    $checks['temporary_files'] = [
      'title' => $this->t('Temporary files'),
      'callback' => 'checkTemporaryFiles',
      'success' => $this->t('No sensitive temporary files were found.'),
      'failure' => $this->t('Sensitive temporary files were found on your files system.'),
    ];
    if ($this->moduleHandler()->moduleExists('views')) {
      $checks['views_access'] = [
        'title' => $this->t('Views access'),
        'callback' => 'checkViewsAccess',
        'success' => $this->t('Views are access controlled.'),
        'failure' => $this->t('There are Views that do not provide any access checks.'),
      ];
    }

    return ['security_review' => $checks];
  }

  /**
   * Check for sensitive temporary files like settings.php~.
   *
   * @param int|null $last_check
   *   Timestamp.
   *
   * @return array
   *   Result.
   */
  private function checkTemporaryFiles($last_check = NULL) {
    $result = TRUE;
    $check_result_value = [];
    $files = [];
    $site_path = \Drupal::getContainer()->getParameter('site.path');

    $dir = scandir(DRUPAL_ROOT . '/' . $site_path . '/');
    foreach ($dir as $file) {
      // Set full path to only files.
      if (!is_dir($file)) {
        $files[] = DRUPAL_ROOT . '/' . $site_path . '/' . $file;
      }
    }
    $this->moduleHandler()->alter('security_review_temporary_files', $files);
    foreach ($files as $path) {
      $matches = [];
      if (file_exists($path) && preg_match('/.*(~|\.sw[op]|\.bak|\.orig|\.save)$/', (string) $path, $matches) !== FALSE && !empty($matches)) {
        $result = FALSE;
        $check_result_value[] = $path;
      }
    }
    return ['result' => $result, 'value' => $check_result_value];
  }

  /**
   * Check views access.
   *
   * @param int|null $last_check
   *   Timestamp.
   *
   * @return array
   *   Result.
   */
  private function checkViewsAccess($last_check = NULL) {
    $result = TRUE;
    $check_result_value = [];
    // Need review.
    $views = Views::getEnabledViews();
    foreach ($views as $view) {
      $view_name = $view->get('originalId');
      $view_display = $view->get('display');
      // Access is set in display options of a display.
      foreach ($view_display as $display_name => $display) {
        if (isset($display['display_options']['access']) && $display['display_options']['access']['type'] == 'none') {
          $check_result_value[$view_name][] = $display_name;
        }
      }
    }
    if (!empty($check_result_value)) {
      $result = FALSE;
    }
    return ['result' => $result, 'value' => $check_result_value];
  }

  /**
   * Check if PHP files written to the files directory can be executed.
   */
  private function checkExecutablePhp($last_check = NULL) {
    global $base_url;
    $result = TRUE;
    $check_result_value = [];

    $message = 'Security review test ' . date('Ymdhis');
    $content = "<?php\necho '" . $message . "';";
    $directory = Settings::get('file_public_path');
    if (empty($directory)) {
      $directory = DrupalKernel::findSitePath(\Drupal::request()) . DIRECTORY_SEPARATOR . 'files';
    }
    if (empty($directory)) {
      $directory = 'sites/default/files';
    }
    $file = '/security_review_test.php';
    if ($file_create = @fopen('./' . $directory . $file, 'w')) {
      fwrite($file_create, $content);
      fclose($file_create);
    }

    try {
      $response = \Drupal::httpClient()
        ->post($base_url . '/' . $directory . $file);
      if ($response->getStatusCode() == 200 && $response->getBody()->read(100) === $message) {
        $result = FALSE;
        $check_result_value[] = 'executable_php';
      }

    }
    catch (\Exception $e) {
      // Do nothing.
    }

    if (file_exists('./' . $directory . $file)) {
      @unlink('./' . $directory . $file);
    }
    // Check for presence of the .htaccess file and if the contents are correct.
    if (!file_exists($directory . '/.htaccess')) {
      $result = FALSE;
      $check_result_value[] = 'missing_htaccess';
    }
    else {
      $contents = file_get_contents($directory . '/.htaccess');
      // Text from includes/file.inc.
      $expected = '';
      if ($contents !== $expected) {
        $result = FALSE;
        $check_result_value[] = 'incorrect_htaccess';
      }
      if (is_writable($directory . '/.htaccess')) {
        // Don't modify $result.
        $check_result_value[] = 'writable_htaccess';
      }
    }

    return ['result' => $result, 'value' => $check_result_value];
  }

  /**
   * Check upload extensions.
   *
   * @param int|null $last_check
   *   Last check.
   *
   * @return array
   *   Result.
   */
  private function checkUploadExtensions($last_check = NULL) {
    $check_result = TRUE;
    $check_result_value = [];
    $unsafe_extensions = $this->unsafeExtensions();
    $fields = FieldConfig::loadMultiple();
    foreach ($fields as $field) {
      $dependencies = $field->get('dependencies');
      if (isset($dependencies) && !empty($dependencies['module'])) {
        foreach ($dependencies['module'] as $module) {
          if ($module == 'image' || $module == 'file') {
            foreach ($unsafe_extensions as $unsafe_extension) {
              // Check instance file_extensions.
              if (strpos($field->getSetting('file_extensions'), $unsafe_extension) !== FALSE) {
                // Found an unsafe extension.
                $check_result_value[$field->getName()][$field->getTargetBundle()] = $unsafe_extension;
                $check_result = FALSE;
              }
            }
          }
        }
      }
    }
    return ['result' => $check_result, 'value' => $check_result_value];
  }

  /**
   * Check input formats of unsafe tags.
   *
   * Check for formats that either do not have HTML filter that can be used by
   * untrusted users, or if they do check if unsafe tags are allowed.
   *
   * @return array
   *   Result.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  private function checkInputFormats() {
    $result = TRUE;

    /** @var \Drupal\filter\FilterFormatInterface[] $formats */
    $formats = $this->entityTypeManager()
      ->getStorage('filter_format')
      ->loadByProperties(['status' => TRUE]);
    $check_result_value = [];

    // Check formats that are accessible by untrusted users.
    // $untrusted_roles = acquia_spi_security_review_untrusted_roles();
    $untrusted_roles = $this->untrustedRoles();
    $untrusted_roles = array_keys($untrusted_roles);
    foreach ($formats as $id => $format) {
      $format_roles = filter_get_roles_by_format($format);
      $intersect = array_intersect(array_keys($format_roles), $untrusted_roles);
      if (!empty($intersect)) {
        $filters = $formats[$id]->get('filters');
        // Check format for enabled HTML filter.
        if (in_array('filter_html', array_keys($filters)) && $filters['filter_html']['status'] == 1) {
          $filter = $filters['filter_html'];
          // Check for unsafe tags in allowed tags.
          $allowed_tags = $filter['settings']['allowed_html'];
          $unsafe_tags = $this->unsafeTags();
          foreach ($unsafe_tags as $tag) {
            if (strpos($allowed_tags, '<' . $tag . '>') !== FALSE) {
              // Found an unsafe tag.
              $check_result_value['tags'][$id] = $tag;
            }
          }
        }
        elseif (!in_array('filter_html_escape', array_keys($filters)) || !$filters['filter_html_escape']['status'] == 1) {
          // Format is usable by untrusted users but does not contain
          // the HTML Filter or the HTML escape.
          $check_result_value['formats'][$id] = $format;
        }
      }
    }

    if (!empty($check_result_value)) {
      $result = FALSE;
    }
    return ['result' => $result, 'value' => $check_result_value];
  }

  /**
   * Look for admin permissions granted to untrusted roles.
   */
  private function checkAdminPermissions() {
    $result = TRUE;
    $check_result_value = [];
    $mapping_role = ['anonymous' => 1, 'authenticated' => 2];
    $untrusted_roles = $this->untrustedRoles();

    // Collect permissions marked as for trusted users only.
    $all_permissions = \Drupal::service('user.permissions')->getPermissions();
    $all_keys = array_keys($all_permissions);

    // Get permissions for untrusted roles.
    $untrusted_permissions = user_role_permissions(array_keys($untrusted_roles));
    foreach ($untrusted_permissions as $rid => $permissions) {
      $intersect = array_intersect($all_keys, $permissions);
      foreach ($intersect as $permission) {
        if (!empty($all_permissions[$permission]['restrict access'])) {
          $check_result_value[$mapping_role[$rid]][] = $permission;
        }
      }
    }

    if (!empty($check_result_value)) {
      $result = FALSE;
    }
    return ['result' => $result, 'value' => $check_result_value];
  }

  /**
   * Check if untrusted users can use PHP Filter format.
   *
   * @return array
   *   Result.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function checkPhpFilter() {
    $result = TRUE;
    $check_result_value = [];
    /** @var \Drupal\filter\FilterFormatInterface[] $formats */
    $formats = $this->entityTypeManager()
      ->getStorage('filter_format')
      ->loadByProperties(['status' => TRUE]);
    // Check formats that are accessible by untrusted users.
    $untrusted_roles = $this->untrustedRoles();
    $untrusted_roles = array_keys($untrusted_roles);
    foreach ($formats as $id => $format) {
      $format_roles = filter_get_roles_by_format($format);
      $intersect = array_intersect(array_keys($format_roles), $untrusted_roles);
      if (!empty($intersect)) {
        // Untrusted users can use this format.
        $filters = $formats[$id]->get('filters');
        // Check format for enabled PHP filter.
        if (in_array('php_code', array_keys($filters)) && $filters['php_code']['status'] == 1) {
          $result = FALSE;
          $check_result_value['formats'][$id] = $format;
        }
      }
    }

    return ['result' => $result, 'value' => $check_result_value];
  }

  /**
   * Helper function defines file extensions considered unsafe.
   */
  public function unsafeExtensions() {
    return [
      'swf',
      'exe',
      'html',
      'htm',
      'php',
      'phtml',
      'py',
      'js',
      'vb',
      'vbe',
      'vbs',
    ];
  }

  /**
   * Helper function defines HTML tags that are considered unsafe.
   *
   * Based on wysiwyg_filter_get_elements_blacklist().
   */
  public function unsafeTags() {
    return [
      'applet',
      'area',
      'audio',
      'base',
      'basefont',
      'body',
      'button',
      'comment',
      'embed',
      'eval',
      'form',
      'frame',
      'frameset',
      'head',
      'html',
      'iframe',
      'image',
      'img',
      'input',
      'isindex',
      'label',
      'link',
      'map',
      'math',
      'meta',
      'noframes',
      'noscript',
      'object',
      'optgroup',
      'option',
      'param',
      'script',
      'select',
      'style',
      'svg',
      'table',
      'td',
      'textarea',
      'title',
      'video',
      'vmlframe',
    ];
  }

  /**
   * Helper function for user-defined or default untrusted Drupal roles.
   *
   * @return array
   *   An associative array with the role id as the key and the role name as
   *   value.
   */
  public function untrustedRoles() {
    $defaults = $this->defaultUntrustedRoles();
    $roles = $defaults;
    return array_filter($roles);
  }

  /**
   * Helper function defines the default untrusted Drupal roles.
   */
  public function defaultUntrustedRoles() {
    $roles = [AccountInterface::ANONYMOUS_ROLE => 'anonymous user'];
    // Need set default value.
    $user_register = \Drupal::config('user.settings')->get('register');
    // If visitors are allowed to create accounts they are considered untrusted.
    if ($user_register != UserInterface::REGISTER_ADMINISTRATORS_ONLY) {
      $roles[AccountInterface::AUTHENTICATED_ROLE] = 'authenticated user';
    }
    return $roles;
  }

}

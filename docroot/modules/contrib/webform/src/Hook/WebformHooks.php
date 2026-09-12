<?php

namespace Drupal\webform\Hook;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Cache\RefinableCacheableDependencyInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Render\Markup;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\file\FileInterface;
use Drupal\webform\Entity\Webform;
use Drupal\webform\Entity\WebformSubmission;
use Drupal\webform\Plugin\WebformElement\ManagedFile;
use Drupal\webform\Plugin\WebformElementFileDownloadAccessInterface;
use Drupal\webform\Plugin\WebformVariant\OverrideWebformVariant;
use Drupal\webform\Utility\WebformMailHelper;
use Drupal\webform\WebformInterface;

/**
 * Hook implementations for webform.
 */
class WebformHooks {
  use StringTranslationTrait;

  /**
   * Implements hook_help().
   */
  #[Hook('help')]
  public function help($route_name, RouteMatchInterface $route_match) {
    if (!$route_match->getRouteObject()) {
      return NULL;
    }
    // Get path from route match.
    $path = preg_replace('/^' . preg_quote(base_path(), '/') . '/', '/', Url::fromRouteMatch($route_match)->setAbsolute(FALSE)->toString());
    if (!in_array($route_name, ['system.modules_list', 'update.status']) && !str_contains($route_name, 'webform') && !str_contains($path, '/webform')) {
      return NULL;
    }
    /** @var \Drupal\webform\WebformHelpManagerInterface $help_manager */
    $help_manager = \Drupal::service('webform.help_manager');
    if ($route_name === 'help.page.webform') {
      $build = $help_manager->buildIndex();
    }
    else {
      $build = $help_manager->buildHelp($route_name, $route_match);
    }
    if ($build) {
      $renderer = \Drupal::service('renderer');
      $config = \Drupal::config('webform.settings');
      $renderer->addCacheableDependency($build, $config);
      return $build;
    }
    else {
      return NULL;
    }
  }

  /**
   * Implements hook_webform_message_custom().
   */
  #[Hook('webform_message_custom')]
  public function webformMessageCustom($operation, $id) {
    if (str_starts_with($id, 'webform_help_notification__') && $operation === 'close') {
      $id = str_replace('webform_help_notification__', '', $id);
      /** @var \Drupal\webform\WebformHelpManagerInterface $help_manager */
      $help_manager = \Drupal::service('webform.help_manager');
      $help_manager->deleteNotification($id);
    }
  }

  /**
   * Implements hook_modules_installed().
   */
  #[Hook('modules_installed')]
  public function modulesInstalled($modules) {
    // Add webform paths when the path.module is being installed.
    if (in_array('path', $modules)) {
      /** @var \Drupal\webform\WebformInterface[] $webforms */
      $webforms = Webform::loadMultiple();
      foreach ($webforms as $webform) {
        $webform->updatePaths();
      }
    }
    // Check HTML email provider support as modules are installed.
    /** @var \Drupal\webform\WebformEmailProviderInterface $email_provider */
    $email_provider = \Drupal::service('webform.email_provider');
    $email_provider->check();
    // Update Webform HTML editor.
    if (in_array('ckeditor5', $modules)) {
      \Drupal::moduleHandler()->loadInclude('webform', 'inc', 'includes/webform.install') . _webform_update_html_editor();
    }
  }

  /**
   * Implements hook_modules_uninstalled().
   */
  #[Hook('modules_uninstalled')]
  public function modulesUninstalled($modules) {
    // Remove uninstalled module's third party settings from admin settings.
    $config = \Drupal::configFactory()->getEditable('webform.settings');
    $third_party_settings = $config->get('third_party_settings');
    $has_third_party_settings = FALSE;
    foreach ($modules as $module) {
      if (isset($third_party_settings[$module])) {
        $has_third_party_settings = TRUE;
        unset($third_party_settings[$module]);
      }
    }
    if ($has_third_party_settings) {
      $config->set('third_party_settings', $third_party_settings);
      $config->save();
    }
    // Check HTML email provider support as modules are uninstalled.
    /** @var \Drupal\webform\WebformEmailProviderInterface $email_provider */
    $email_provider = \Drupal::service('webform.email_provider');
    $email_provider->check();
    // Update Webform HTML editor.
    if (in_array('ckeditor5', $modules)) {
      \Drupal::moduleHandler()->loadInclude('webform', 'inc', 'includes/webform.install') . _webform_update_html_editor();
    }
  }

  /**
   * Implements hook_config_schema_info_alter().
   */
  #[Hook('config_schema_info_alter')]
  public function configSchemaInfoAlter(&$definitions) {
    if (empty($definitions['webform.webform.*']['mapping'])) {
      return;
    }

    $mapping = $definitions['webform.webform.*']['mapping'];

    // Copy properties, settings, elements, and handlers to variant override schema.
    if (isset($definitions['webform.variant.override'])) {
      $definitions['webform.variant.override']['mapping'] += [
        'properties' => [
          'type' => 'mapping',
          'label' => 'Properties',
          'mapping' => array_intersect_key($mapping, array_flip(OverrideWebformVariant::OVERRIDE_PROPERTIES)),
        ],
        'settings' => $mapping['settings'],
        'elements' => $mapping['elements'],
        'handlers' => $mapping['handlers'],
      ];
    }

    // Append settings handler settings schema.
    if (isset($definitions['webform.handler.settings'])) {
      $definitions['webform.handler.settings']['mapping'] += _webform_config_schema_info_alter_settings_recursive($mapping['settings']['mapping']);
    }
  }

  /**
   * Implements hook_user_login().
   */
  #[Hook('user_login')]
  public function userLogin($account) {
    // Notify the storage of this log in.
    \Drupal::entityTypeManager()->getStorage('webform_submission')->userLogin($account);
  }

  /**
   * Implements hook_cron().
   */
  #[Hook('cron')]
  public function cron() {
    $config = \Drupal::config('webform.settings');
    \Drupal::entityTypeManager()->getStorage('webform_submission')->purge($config->get('purge.cron_size'));
  }

  /**
   * Implements hook_rebuild().
   */
  #[Hook('rebuild')]
  public function rebuild() {
    /** @var \Drupal\webform\WebformEmailProviderInterface $email_provider */
    $email_provider = \Drupal::service('webform.email_provider');
    $email_provider->check();
  }

  /**
   * Implements hook_local_tasks_alter().
   */
  #[Hook('local_tasks_alter')]
  public function localTasksAlter(&$local_tasks) {
    // Change config translation local task hierarchy.
    if (isset($local_tasks['config_translation.local_tasks:entity.webform.config_translation_overview'])) {
      $local_tasks['config_translation.local_tasks:entity.webform.config_translation_overview']['base_route'] = 'entity.webform.canonical';
    }
    if (isset($local_tasks['config_translation.local_tasks:config_translation.item.overview.webform.config'])) {
      // Set weight to 110 so that the 'Translate' tab comes after
      // the 'Advanced' tab.
      // @see webform.links.task.yml
      $local_tasks['config_translation.local_tasks:config_translation.item.overview.webform.config']['weight'] = 110;
      $local_tasks['config_translation.local_tasks:config_translation.item.overview.webform.config']['parent_id'] = 'webform.config';
    }
    // Disable 'Contribute' tab if explicitly disabled or the Contribute module
    // is installed.
    if (\Drupal::config('webform.settings')->get('ui.contribute_disabled') || \Drupal::moduleHandler()->moduleExists('contribute')) {
      unset($local_tasks['webform.contribute']);
    }
  }

  /**
   * Implements hook_menu_local_tasks_alter().
   */
  #[Hook('menu_local_tasks_alter')]
  public function menuLocalTasksAlter(&$data, $route_name, RefinableCacheableDependencyInterface $cacheability) {
    // Change config entities 'Translate *' tab to be just label 'Translate'.
    $webform_entities = ['webform', 'webform_options'];
    foreach ($webform_entities as $webform_entity) {
      if (isset($data['tabs'][0]["config_translation.local_tasks:entity.{$webform_entity}.config_translation_overview"]['#link']['title'])) {
        $data['tabs'][0]["config_translation.local_tasks:entity.{$webform_entity}.config_translation_overview"]['#link']['title'] = $this->t('Translate');
      }
    }
    // Change simple config 'Translate *' tab to be just label 'Translate'.
    if (isset($data['tabs'][1]['config_translation.local_tasks:config_translation.item.overview.webform.config'])) {
      $data['tabs'][1]['config_translation.local_tasks:config_translation.item.overview.webform.config']['#link']['title'] = $this->t('Translate');
    }
    // ISSUE:
    // Devel routes do not use 'webform' parameter which throws the below error.
    // Some mandatory parameters are missing ("webform") to generate a URL for
    // route "entity.webform_submission.canonical"
    //
    // WORKAROUND:
    // Make sure webform parameter is set for all routes.
    if (str_starts_with($route_name, 'entity.webform_submission.devel_') || $route_name === 'entity.webform_submission.token_devel') {
      foreach ($data['tabs'] as $tab_level) {
        foreach ($tab_level as $tab) {
          /** @var \Drupal\Core\Url $url */
          $url = $tab['#link']['url'];
          $tab_route_name = $url->getRouteName();
          $tab_route_parameters = $url->getRouteParameters();
          if (!str_starts_with($tab_route_name, 'entity.webform_submission.devel_')) {
            $webform_submission = WebformSubmission::load($tab_route_parameters['webform_submission']);
            $url->setRouteParameter('webform', $webform_submission->getWebform()->id());
          }
        }
      }
    }
    // Allow webform query string parameters to be transferred
    // from a canonical URL to a test URL.
    //
    // Please note: This behavior is only applicable when a user can
    // test a webform.
    $route_names = [
      'entity.webform.test_form' => 'entity.webform.canonical',
      'entity.node.webform.test_form' => 'entity.node.canonical',
    ];
    if (in_array($route_name, $route_names) || array_key_exists($route_name, $route_names)) {
      $query = \Drupal::request()->query->all();
      $has_test_tab = FALSE;
      foreach ($route_names as $test_route_name => $view_route_name) {
        if (isset($data['tabs'][0][$test_route_name])) {
          $has_test_tab = TRUE;
          if ($query) {
            $data['tabs'][0][$test_route_name]['#link']['url']->setOption('query', $query);
            $data['tabs'][0][$view_route_name]['#link']['url']->setOption('query', $query);
          }
        }
      }
      // Query string to cache context webform canonical and test routes.
      if ($has_test_tab) {
        $cacheability->addCacheContexts(['url.query_args']);
      }
    }
  }

  /**
   * Implements hook_token_info_alter().
   */
  #[Hook('token_info_alter')]
  public function tokenInfoAlter(&$data) {
    \Drupal::moduleHandler()->loadInclude('webform', 'tokens.inc', 'includes/webform');
    // Append learn more about token suffixes to all webform token descriptions.
    // @see \Drupal\webform\WebformTokenManager::replace
    // @see webform_page_attachments()
    $token_suffixes = $this->t('Append the below suffixes to alter the returned value.') . '<ul>' .
      '<li>' . $this->t('<code>:base64encode</code> base64 encodes returned value') . '</li>' .
      '<li>' . $this->t('<code>:clear</code> removes the token when it is not replaced.') . '</li>' .
      '<li>' . $this->t('<code>:urlencode</code> URL encodes returned value.') . '</li>' .
      '<li>' . $this->t('<code>:rawurlencode</code> Raw URL encodes returned value with only hex digits.') . '</li>' .
      '<li>' . $this->t('<code>:xmlencode</code> XML encodes returned value.') . '</li>' .
      '<li>' . $this->t('<code>:htmldecode</code> decodes HTML entities in returned value.') . '<br/><b>' . $this->t('This suffix has security implications.') . '</b><br/>' . $this->t('Use <code>:htmldecode</code> with <code>:striptags</code>.') . '</li>' .
      '<li>' . $this->t('<code>:striptags</code> removes all HTML tags from returned value.') . '</li>' .
    '</ul>';
    $more = \Drupal::service(WebformTokensHooks::class)->renderMore(t('Learn about token suffixes'), $token_suffixes);
    foreach ($data['types'] as $type => &$info) {
      if (str_starts_with($type, 'webform')) {
        if (isset($info['description']) && !empty($info['description'])) {
          $description = $info['description'] . $more;
        }
        else {
          $description = $more;
        }
        $info['description'] = Markup::create($description);
      }
    }
  }

  /**
   * Implements hook_entity_update().
   */
  #[Hook('entity_update')]
  public function entityUpdate(EntityInterface $entity) {
    _webform_clear_webform_submission_list_cache_tag($entity);
  }

  /**
   * Implements hook_entity_delete().
   */
  #[Hook('entity_delete')]
  public function entityDelete(EntityInterface $entity) {
    _webform_clear_webform_submission_list_cache_tag($entity);
    /** @var \Drupal\webform\WebformEntityReferenceManagerInterface $entity_reference_manager */
    $entity_reference_manager = \Drupal::service('webform.entity_reference_manager');
    // Delete saved export settings for a webform or source entity with the
    // webform field.
    if ($entity instanceof WebformInterface || $entity_reference_manager->hasField($entity)) {
      $name = 'webform.export.' . $entity->getEntityTypeId() . '.' . $entity->id();
      \Drupal::state()->delete($name);
    }
  }

  /**
   * Implements hook_mail().
   */
  #[Hook('mail')]
  public function mail($key, &$message, $params) {
    // Never send emails when using devel generate to create
    // 1000's of submissions.
    if (\Drupal::moduleHandler()->moduleExists('devel_generate')) {
      /** @var \Drupal\devel_generate\DevelGeneratePluginManager $devel_generate */
      $devel_generate = \Drupal::service('plugin.manager.develgenerate');
      $definition = $devel_generate->getDefinition('webform_submission', FALSE);
      if ($definition) {
        $class = $definition['class'];
        if ($class::isGeneratingSubmissions()) {
          $message['send'] = FALSE;
        }
      }
    }
    // Set default parameters.
    $params += [
      'from_mail' => '',
      'from_name' => '',
      'cc_mail' => '',
      'bcc_mail' => '',
      'reply_to' => '',
      'return_path' => '',
      'sender_mail' => '',
      'sender_name' => '',
    ];
    $message['subject'] = $params['subject'];
    $message['body'][] = $params['body'];
    // Set the header 'From'.
    // Using the 'from_mail' so that the webform's email from value is used
    // instead of site's email address.
    // @see: \Drupal\Core\Mail\MailManager::mail.
    if (!empty($params['from_mail'])) {
      // 'From name' is only used when the 'From mail' contains a single
      // email address.
      $from = !empty($params['from_name']) && !str_contains($params['from_mail'], ',') ? WebformMailHelper::formatAddress($params['from_mail'], $params['from_name']) : $params['from_mail'];
      $message['from'] = $message['headers']['From'] = $from;
    }
    // Set header 'Cc'.
    if (!empty($params['cc_mail'])) {
      $message['headers']['Cc'] = $params['cc_mail'];
    }
    // Set header 'Bcc'.
    if (!empty($params['bcc_mail'])) {
      $message['headers']['Bcc'] = $params['bcc_mail'];
    }
    // Set header 'Reply-to'.
    $reply_to = $params['reply_to'] ?: '';
    if (empty($reply_to) && !empty($params['from_mail'])) {
      $reply_to = $message['from'];
    }
    if ($reply_to) {
      $message['reply-to'] = $message['headers']['Reply-to'] = $reply_to;
    }
    // Set header 'Return-Path' which only supports a single email address and the
    // 'from_mail' may contain multiple comma delimited email addresses.
    $return_path = ($params['return_path'] ?: $params['from_mail']) ?: '';
    if ($return_path) {
      $return_path = explode(',', $return_path);
      $message['headers']['Sender'] = $message['headers']['Return-Path'] = $return_path[0];
    }
    // Set header 'Sender'.
    $sender_mail = $params['sender_mail'] ?: '';
    $sender_name = ($params['sender_name'] ?: $params['from_name']) ?: '';
    if ($sender_mail) {
      $message['headers']['Sender'] = WebformMailHelper::formatAddress($sender_mail, $sender_name);
    }
  }

  /**
   * Implements hook_mail_alter().
   */
  #[Hook('mail_alter')]
  public function mailAlter(&$message) {
    // Drupal hardcodes all mail header as 'text/plain' so we need to set the
    // header's 'Content-type' to HTML if the EmailWebformHandler's
    // 'html' flag has been set.
    // @see \Drupal\Core\Mail\MailManager::mail()
    // @see \Drupal\webform\Plugin\WebformHandler\EmailWebformHandler::getMessage().
    if (str_starts_with($message['id'], 'webform')) {
      if (isset($message['params']['html']) && $message['params']['html']) {
        $message['headers']['Content-Type'] = 'text/html; charset=UTF-8; format=flowed';
      }
    }
  }

  /**
   * Implements hook_toolbar_alter().
   */
  #[Hook('toolbar_alter')]
  public function toolbarAlter(&$items) {
    if (\Drupal::config('webform.settings')->get('ui.toolbar_item')) {
      $items['administration']['#attached']['library'][] = 'webform/webform.admin.toolbar';
    }
  }

  /**
   * Implements hook_menu_links_discovered_alter().
   */
  #[Hook('menu_links_discovered_alter')]
  public function menuLinksDiscoveredAlter(&$links) {
    // Display Webforms as a top-level administration menu item in the toolbar.
    if (\Drupal::config('webform.settings')->get('ui.toolbar_item')) {
      $links['entity.webform.collection']['parent'] = 'system.admin';
      $links['entity.webform.collection']['weight'] = -9;
    }
    // Add webform local tasks as admin menu toolbar menu items.
    if (\Drupal::moduleHandler()->moduleExists('admin_toolbar_tools')) {
      // Get local task definitions.
      /** @var \Drupal\Core\Menu\LocalTaskManager $local_task_manager */
      $local_task_manager = \Drupal::service('plugin.manager.menu.local_task');
      $definitions = $local_task_manager->getDefinitions();
      // Set default definition.
      $default_definition = ['provider' => 'webform', 'menu_name' => 'admin'];
      // Get keys to be copied.
      $keys = ['title', 'route_name', 'weight'];
      $keys_to_copy = array_combine($keys, $keys);
      $menu_links = [];
      foreach ($definitions as $task_name => $definition) {
        if (isset($definition['base_route']) && $definition['base_route'] === 'entity.webform.collection') {
          $menu_links[$task_name . '.item'] = $default_definition + array_intersect_key($definition, $keys_to_copy) + ['parent' => 'entity.webform.collection'];
        }
      }
      foreach ($menu_links as $sub_link_task_name => $sub_link) {
        foreach ($definitions as $task_name => $definition) {
          if (isset($definition['parent_id']) && $definition['parent_id'] === preg_replace('/\.item$/', '', $sub_link_task_name)) {
            $menu_links[$task_name . '.item'] = $default_definition + array_intersect_key($definition, $keys_to_copy) + ['parent' => $sub_link_task_name];
          }
        }
      }
      // Make sure weight are integers and not floats which throw fatal errors
      // for PostgreSQL.
      // @see https://www.drupal.org/project/webform/issues/3247861
      // @see https://www.drupal.org/project/drupal/issues/3248199
      foreach ($menu_links as &$menu_link) {
        $menu_link['weight'] = (int) $menu_link['weight'];
      }
      $links += $menu_links;
    }
  }

  /**
   * Implements hook_page_attachments().
   */
  #[Hook('page_attachments')]
  public function pageAttachments(array &$attachments) {
    $route_name = \Drupal::routeMatch()->getRouteName();
    // Attach global libraries only to webform specific pages and module list.
    if (preg_match('/^(webform\.|^entity\.([^.]+\.)?webform)/', $route_name) || $route_name === 'system.modules_list') {
      _webform_page_attachments($attachments);
    }
    // Attach codemirror and select2 library to block admin to ensure that the
    // library is loaded by the webform block is placed using Ajax.
    if (str_starts_with($route_name, 'block.admin_display')) {
      $attachments['#attached']['library'][] = 'webform/webform.block';
    }
    // Attach webform dialog library and options to every page.
    if (\Drupal::config('webform.settings')->get('settings.dialog')) {
      $attachments['#attached']['library'][] = 'webform/webform.dialog';
      $attachments['#attached']['drupalSettings']['webform']['dialog']['options'] = \Drupal::config('webform.settings')->get('settings.dialog_options');
      /** @var \Drupal\webform\WebformRequestInterface $request_handler */
      $request_handler = \Drupal::service('webform.request');
      if ($source_entity = $request_handler->getCurrentSourceEntity()) {
        $attachments['#attached']['drupalSettings']['webform']['dialog']['entity_type'] = $source_entity->getEntityTypeId();
        $attachments['#attached']['drupalSettings']['webform']['dialog']['entity_id'] = $source_entity->id();
      }
    }
    // Attach webform more element to token token help.
    // @see webform_token_info_alter()
    if ($route_name === 'help.page' && \Drupal::routeMatch()->getRawParameter('name') === 'token') {
      $attachments['#attached']['library'][] = 'webform/webform.token';
    }
    // Attach meta tag robots noindex directive to all webform confirmation pages
    // , if the metatag module is not installed.
    // @see webform_metatags_alter()
    if (!\Drupal::moduleHandler()->moduleExists('metatag') && !empty(\Drupal::config('webform.settings')->get('settings.default_confirmation_noindex')) && preg_match('/^(entity\.webform\.confirmation|entity\.[a-z-_]+\.webform\.confirmation)$/', $route_name)) {
      $attachments['#attached']['html_head'][] = [
        [
          '#tag' => 'meta',
          '#attributes' => [
            'name' => 'robots',
            'content' => 'noindex',
          ],
        ],
        'webform_confirmation_noindex',
      ];
    }
  }

  /**
   * Implements hook_metatags_alter().
   */
  #[Hook('metatags_alter')]
  public function metatagsAlter(array &$metatags, array &$context) {
    $route_name = \Drupal::routeMatch()->getRouteName();
    if (!empty(\Drupal::config('webform.settings')->get('settings.default_confirmation_noindex')) && $route_name && preg_match('/^(entity\.webform\.confirmation|entity\.[a-z-_]+\.webform\.confirmation)$/', $route_name)) {
      $robots = !empty($metatags['robots']) ? preg_split('/\s*,\s*/', $metatags['robots']) : [];
      $robots[] = 'noindex';
      $metatags['robots'] = implode(', ', array_unique($robots));
    }
  }

  /**
   * Implements hook_file_access().
   *
   * @see file_file_download()
   * @see webform_preprocess_file_link()
   */
  #[Hook('file_access')]
  public function fileAccess(FileInterface $file, $operation, AccountInterface $account) {
    $is_webform_download = $operation === 'download' && str_starts_with($file->getFileUri(), 'private://webform/');
    // Block access to temporary anonymous private file uploads
    // only when an anonymous user is attempting to download the file.
    // Links to anonymous file uploads are automatically suppressed.
    // @see webform_preprocess_file_link()
    // @see webform_file_download()
    if ($is_webform_download && $file->isTemporary() && $file->getOwner() && $file->getOwner()->isAnonymous() && \Drupal::routeMatch()->getRouteName() === 'system.files') {
      return AccessResult::forbidden();
    }
    // Allow access to files associated with a webform submission.
    // This prevent uploaded webform files from being lost when another user
    // edits a submission with multiple file uploads.
    // @see \Drupal\file\Element\ManagedFile::valueCallback
    if ($is_webform_download && ManagedFile::accessFile($file, $account)) {
      return AccessResult::allowed();
    }
    return AccessResult::neutral();
  }

  /**
   * Implements hook_file_download().
   */
  #[Hook('file_download')]
  public function fileDownload($uri) {
    /** @var \Drupal\webform\Plugin\WebformElementManagerInterface $webform_element_manager */
    $webform_element_manager = \Drupal::service('plugin.manager.webform.element');
    $webform_elements = $webform_element_manager->getInstances();
    foreach ($webform_elements as $webform_element) {
      if ($webform_element->isEnabled() && $webform_element instanceof WebformElementFileDownloadAccessInterface) {
        $result = $webform_element::accessFileDownload($uri);
        if ($result !== NULL) {
          return $result;
        }
      }
    }
    return NULL;
  }

  /**
   * Implements hook_contextual_links_view_alter().
   *
   * Add .webform-contextual class to all webform context links.
   *
   * @see webform.links.contextual.yml
   * @see js/webform.contextual.js
   */
  #[Hook('contextual_links_view_alter')]
  public function contextualLinksViewAlter(&$element, $items) {
    $links = [
      'entitywebformtest-form',
      'entitywebformresults-submissions',
      'entitywebformedit-form',
      'entitywebformsettings',
    ];
    foreach ($links as $link) {
      if (isset($element['#links'][$link])) {
        $element['#links'][$link]['attributes']['class'][] = 'webform-contextual';
      }
    }
  }

  /**
   * Implements hook_webform_access_rules().
   */
  #[Hook('webform_access_rules')]
  public function webformAccessRules() {
    return [
      'create' => [
        'title' => $this->t('Create submissions'),
        'roles' => [
          'anonymous',
          'authenticated',
        ],
      ],
      'view_any' => [
        'title' => $this->t('View any submissions'),
      ],
      'update_any' => [
        'title' => $this->t('Update any submissions'),
      ],
      'delete_any' => [
        'title' => $this->t('Delete any submissions'),
      ],
      'purge_any' => [
        'title' => $this->t('Purge any submissions'),
      ],
      'view_own' => [
        'title' => $this->t('View own submissions'),
      ],
      'update_own' => [
        'title' => $this->t('Update own submissions'),
      ],
      'delete_own' => [
        'title' => $this->t('Delete own submissions'),
      ],
      'administer' => [
        'title' => $this->t('Administer webform & submissions'),
        'description' => [
          '#type' => 'webform_message',
          '#message_type' => 'warning',
          '#message_message' => $this->t('<strong>Warning</strong>: The below settings give users, permissions, and roles full access to this webform and its submissions.'),
        ],
      ],
      'test' => [
        'title' => $this->t('Test webform'),
      ],
      'configuration' => [
        'title' => $this->t('Access webform configuration'),
        'description' => [
          '#type' => 'webform_message',
          '#message_type' => 'warning',
          '#message_message' => $this->t("<strong>Warning</strong>: The below settings give users, permissions, and roles full access to this webform's configuration via API requests."),
        ],
      ],
    ];
  }

  /**
   * Implements hook_element_info_alter().
   */
  #[Hook('element_info_alter')]
  public function elementInfoAlter(array &$info) {
    $info['checkboxes']['#process'][] = 'webform_process_options';
    $info['radios']['#process'][] = 'webform_process_options';
    $info['webform_entity_checkboxes']['#process'][] = 'webform_process_options';
    $info['webform_entity_radios']['#process'][] = 'webform_process_options';
    if (isset($info['text_format'])) {
      $editorProcessTextFormat = [
        [
          '\Drupal\webform\Element\WebformHtmlEditor',
          'processTextFormat',
        ],
      ];
      $info['text_format']['#process'] = array_merge(
        $info['text_format']['#process'] ?? [],
        $editorProcessTextFormat
      );
      $editorPreRenderTextFormat = [
        [
          '\Drupal\webform\Element\WebformHtmlEditor',
          'preRenderTextFormat',
        ],
      ];
      $info['text_format']['#pre_render'] = array_merge(
        $editorPreRenderTextFormat,
        $info['text_format']['#pre_render'] ?? []
      );
    }
    if (isset($info['processed_text'])) {
      $info['processed_text']['#pre_render'][] = [
        '\Drupal\webform\Element\WebformHtmlEditor',
        'preRenderProcessedText',
      ];
    }
  }

}

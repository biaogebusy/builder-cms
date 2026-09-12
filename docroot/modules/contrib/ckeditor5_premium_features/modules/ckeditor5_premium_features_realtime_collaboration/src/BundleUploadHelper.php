<?php

/*
 * Copyright (c) 2003-2026, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */

namespace Drupal\ckeditor5_premium_features_realtime_collaboration;

use Drupal\ckeditor5_premium_features\Utility\ApiAdapter;
use Drupal\Component\Plugin\PluginManagerInterface;
use Drupal\Core\Asset\LibraryDependencyResolverInterface;
use Drupal\Core\Asset\LibraryDiscoveryInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use GuzzleHttp\Client;

/**
 * Helper class for uploading editor bundle to the cloud server.
 */
class BundleUploadHelper {

  use StringTranslationTrait;

  const COLLABORATION_TOOLBAR_ITEMS = [
    'trackChanges',
    'comment',
    'revisionHistory',
  ];

  /**
   * BundleUploadHelper constructor.
   *
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $loggerFactory
   *   The logger factory.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory.
   * @param \Drupal\Component\Plugin\PluginManagerInterface $pluginManager
   *   The plugin manager.
   * @param \Drupal\Core\Asset\LibraryDependencyResolverInterface $libraryDependencyResolver
   *   The library dependency resolver.
   * @param \Drupal\Core\Asset\LibraryDiscoveryInterface $libraryDiscovery
   *   The library discovery.
   * @param \GuzzleHttp\Client $httpClient
   *   The Guzzle Http client.
   * @param \Drupal\ckeditor5_premium_features\Utility\ApiAdapter $apiAdapter
   *   The CKSource cloud services API adapter.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $moduleHandler
   *   The module handler.
   */
  public function __construct(
    private MessengerInterface $messenger,
    private LoggerChannelFactoryInterface $loggerFactory,
    private ConfigFactoryInterface $configFactory,
    private PluginManagerInterface $pluginManager,
    private LibraryDependencyResolverInterface $libraryDependencyResolver,
    private LibraryDiscoveryInterface $libraryDiscovery,
    private Client $httpClient,
    private ApiAdapter $apiAdapter,
    private ModuleHandlerInterface $moduleHandler
  ) {
  }

  /**
   * Upload editor bundle to the cloud server.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The editor entity.
   *
   * @return void
   */
  public function uploadBundle(EntityInterface $entity): void {
    $format = $entity->id();
    $formatBundle = $format . '_' . time();
    $config = $this->configFactory->get('ckeditor5_premium_features.settings');
    $realtimeConfig = $this->configFactory->getEditable('ckeditor5_premium_features_realtime_collaboration.settings');
    $plugin = $this->pluginManager->createInstance('ckeditor5');

    $libraries = $plugin->getLibraries($entity);
    $libraries = $this->libraryDependencyResolver->getLibrariesWithDependencies($libraries);
    $libraries = array_filter($libraries);

    $files = [];
    foreach ($libraries as $library) {
      $expl = explode('/', $library);
      $info = $this->libraryDiscovery->getLibraryByName($expl[0], $expl[1]);
      if ($info && $info['js']) {
        foreach ($info['js'] as $item) {
          $files[] = $item['data'];
        }
      }
    }

    $content = [];

    foreach ($files as $file) {
      // Skip some scripts which causes errors on cloud server.
      if (str_contains($file, 'jquery.ui') || str_contains($file, 'ckeditor5.dialog.fix.js') || str_contains($file, 'jquery-ui') || str_contains($file, 'dialog.js')) {
        continue;
      }

      if (str_starts_with($file, 'https://')) {
        $response = $this->httpClient->request('GET', $file, []);
        if ($response->getStatusCode() == 200) {
          $fileContent = '/*' . $file . "*/\n" . $response->getBody()
              ->getContents();
          $content[$file] = $fileContent;
        }
        else {
          $this->messenger->addError("Failed to download external CKEditor 5 library. Editor bundle upload was aborted, please try saving text format again.");
        }
      }
      elseif (file_exists($file)) {
        $fileContent = '/*' . $file . "*/\n" . file_get_contents($file);
        $content[$file] = $fileContent;
      }
    }

    $code = implode("", $content);
    $code .= "window.CKEditorCS=window.CKEditor5.editorClassic.ClassicEditor;";

    $conf = $plugin->getJSSettings($entity);

    // Some plugins cause errors on bundle upload or document export. Those plugins
    // can be excluded here.
    $excludePlugins = [
      'DocumentOutlineAdapter',
      'DocumentOutline',
      'RealtimeAdapter',
      'CommentsAdapter',
      'RemoveIncorrectCollaborationMarkers',
      'RealtimeRevisionHistoryAdapter',
      'RealtimeCommentNotifications',
      'WordCountAdapter',
      'ToolbarAdapter'
    ];

    $excludeCustomPlugins = $this->moduleHandler->invokeAll('ckeditor5_premium_features_exclude_bundle_plugins');
    $excludePlugins = array_filter(array_merge($excludePlugins, $excludeCustomPlugins));

    $plugins = array_map(function ($a) {
      return 'CKEditor5.' . $a;
    }, array_filter($conf['plugins']));

    $code .= "\nwindow.CKEditorCS.CKEditorPlugins=[" . implode(',', $plugins) . "]";

    $bundleConfig = $conf['config'];
    $bundleConfig['cloudServices']['bundleVersion'] = $formatBundle;
    $alreadyExcluded = $bundleConfig['removePlugins'] ?? [];
    $bundleConfig['removePlugins'] = array_merge($alreadyExcluded, $excludePlugins);
    $bundleConfig["htmlSupport"]["allow"] = [["name" => "/.*/"]];

    $response = $this->apiAdapter->postEditor($bundleConfig, $code);

    $is409Error = isset($response['code']) && $response['code'] == 409;

    $logger = $this->loggerFactory->get('ckeditor5_premium_features_realtime_collaboration');

    if ($is409Error) {
      $this->messenger->addWarning($this->t("CKEditor 5 bundle %bundle didn't upload successfully to the cloud. This will cause validation errors on form submit that uses realtime collaboration. Please check recent log messages for details and contact support if you need help solving the issue.", ['%bundle' => $formatBundle]));
      $logger->warning('Bundle upload failed. Active plugins: %plugins', ['%plugins' => print_r($plugins, TRUE)]);
      $logger->warning('Bundle upload failed. Server response: %response', ['%response' => $response['message']]);
    }
    elseif ($response) {
      $bundles = $realtimeConfig->get('editor_bundles') ?? [];
      $bundles[$format] = $formatBundle;
      $this->messenger->addStatus($this->t('Uploaded CKEditor5 bundle %bundle to the cloud server.', ['%bundle' => $formatBundle]));
      $realtimeConfig->set('editor_bundles', $bundles);
      $realtimeConfig->save();
    }
  }

}

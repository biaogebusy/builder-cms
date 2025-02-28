<?php

namespace Drupal\json_editor;

use Drupal\Core\Asset\LibraryDiscoveryInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;

class JsonEditorLibrariesManager implements JsonEditorLibrariesManagerInterface {

  use StringTranslationTrait;

  /**
   * The library discovery service.
   *
   * @var \Drupal\Core\Asset\LibraryDiscoveryInterface
   */
  protected $libraryDiscovery;

  /**
   * The configuration object factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The renderer.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * Libraries that provides additional functionality to the Json Editor module.
   *
   * @var array
   */
  protected $libraries;

  /**
   * Constructs a JsonEditorLibrariesManager object.
   *
   * @param \Drupal\Core\Asset\LibraryDiscoveryInterface $library_discovery
   *   The library discovery service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The configuration object factory.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer.
   */
  public function __construct(LibraryDiscoveryInterface $library_discovery, ConfigFactoryInterface $config_factory, ModuleHandlerInterface $module_handler, RendererInterface $renderer) {
    $this->libraryDiscovery = $library_discovery;
    $this->configFactory = $config_factory;
    $this->moduleHandler = $module_handler;
    $this->renderer = $renderer;
  }


  /**
   * {@inheritdoc}
   */
  public function requirements() {
    $libraries = $this->getLibraries();

    // Track stats.
    $severity = REQUIREMENT_OK;
    $stats = [
      '@total' => count($libraries),
      '@installed' => 0,
      '@missing' => 0,
    ];

    // Build library info array.
    $info = [
      '#prefix' => '<p><hr/></p><dl>',
      '#suffix' => '</dl>',
    ];

    foreach ($libraries as $library_name => $library) {

      $library_path = '/libraries/' . $library_name;
      $library_exists = (file_exists(DRUPAL_ROOT . $library_path)) ? TRUE : FALSE;

      $t_args = [
        '@title' => $library['title'],
        '@version' => $library['version'],
        '@path' => $library_path,
        ':download_href' => $library['download_url']->toString(),
        ':homepage_href' => $library['homepage_url']->toString(),
      ];

      if (!empty($library['module'])) {
        // Installed by module.
        $t_args['@module'] = $library['module'];
        $t_args[':module_href'] = 'https://www.drupal.org/project/' . $library['module'];
        $stats['@installed']++;
        $title = $this->t('<strong>@title</strong> (Installed)', $t_args);
        $description =  $this->t('The <a href=":homepage_href">@title</a> library is installed by the <b><a href=":module_href">@module</a></b> module.', $t_args);
      }
      elseif ($library_exists) {
        // Installed.
        $stats['@installed']++;
        $title =  $this->t('<strong>@title @version</strong> (Installed)', $t_args);
        $description =  $this->t('The <a href=":homepage_href">@title</a> library is installed in <b>@path</b>.', $t_args);
      }

      $info[$library_name] = [];
      $info[$library_name]['title'] = [
        '#markup' => $title,
        '#prefix' => '<dt>',
        '#suffix' => '</dt>',
      ];
      $info[$library_name]['description'] = [
        'content' => [
          '#markup' => $description,
        ],
        'status' => (!empty($library['deprecated'])) ? [
          '#markup' => $library['deprecated'],
          '#prefix' => '<div class="color-warning"><strong>',
          '#suffix' => '</strong></div>',
        ] : [],
        '#prefix' => '<dd>',
        '#suffix' => '</dd>',
      ];
    }
    // Description.
    $description = [
      'info' => $info,
    ];

    return [
      'json_editor_libraries' => [
        'title' => $this->t('JsonEditor: External libraries'),
        'value' => $this->t('@total libraries (@installed installed; @missing)', $stats),
        'description' => $this->renderer->renderPlain($description),
        'severity' => $severity,
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getLibrary($name) {
    $libraries = $this->getLibraries();
    return $libraries[$name];
  }

  /**
   * {@inheritdoc}
   */
  public function getLibraries($included = NULL) {
    // Initialize libraries.
    if (!isset($this->libraries)) {
      $this->libraries = $this->initLibraries();
    }

    $libraries = $this->libraries;

    return $libraries;
  }

  /**
   * Initialize libraries.
   *
   * @return array
   *   An associative array containing libraries.
   */
  protected function initLibraries() {
    $libraries = [];

    $libraries['jsoneditor'] = [
      'title' => $this->t('Json Editor'),
      'description' => $this->t("JSON Editor is a web-based tool to view, edit, format, and validate JSON. It has various modes such as a tree editor, a code editor, and a plain text editor."),
      'notes' => NULL,
      'homepage_url' => Url::fromUri('https://github.com/josdejong/jsoneditor'),
      'download_url' => Url::fromUri('https://github.com/josdejong/jsoneditor/archive/master.zip'),
      'version' => '5.32.0',
    ];
    $libraries['filesaver'] = [
      'title' => $this->t('File Saver'),
      'description' => $this->t("apps that generates files on the client."),
      'notes' => NULL,
      'homepage_url' => Url::fromUri('https://github.com/eligrey/FileSaver.js/'),
      'download_url' => Url::fromUri('https://github.com/eligrey/FileSaver.js/archive/master.zip'),
      'version' => '2.0.1',
    ];

    return $libraries;
  }
}
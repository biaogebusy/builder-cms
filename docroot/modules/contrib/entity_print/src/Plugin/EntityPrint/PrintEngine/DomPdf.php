<?php

namespace Drupal\entity_print\Plugin\EntityPrint\PrintEngine;

use Dompdf\Dompdf as DompdfLib;
use Dompdf\Exception as DompdfLibException;
use Dompdf\Options as DompdfLibOptions;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\entity_print\Plugin\ExportTypeInterface;
use Drupal\entity_print\PrintEngineException;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Dompdf\Adapter\CPDF;

/**
 * A Entity Print plugin for the DomPdf library.
 *
 * @PrintEngine(
 *   id = "dompdf",
 *   label = @Translation("Dompdf"),
 *   export_type = "pdf"
 * )
 */
class DomPdf extends PdfEngineBase implements ContainerFactoryPluginInterface {

  /**
   * Name of DomPdf log file.
   *
   * @var string
   */
  const LOG_FILE_NAME = 'log.html';

  /**
   * The Dompdf instance.
   *
   * @var \Dompdf\Dompdf
   */
  protected $dompdf;

  /**
   * The Dompdf instance.
   *
   * @var \Dompdf\Options
   */
  protected $dompdfOptions;

  /**
   * Keep track of HTML pages as they're added.
   *
   * @var string
   */
  protected $html = '';

  /**
   * Keep track of whether we've rendered or not.
   *
   * @var bool
   */
  protected $hasRendered;

  /**
   * The current request.
   *
   * @var \Symfony\Component\HttpFoundation\Request
   */
  protected $request;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, ExportTypeInterface $export_type, Request $request) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $export_type);

    $this->request = $request;

    $this->dompdfOptions = new DompdfLibOptions($this->configuration);

    $this->dompdfOptions->setTempDir(\Drupal::service('file_system')->getTempDirectory());
    $this->dompdfOptions->setFontCache(\Drupal::service('file_system')->getTempDirectory());
    $this->dompdfOptions->setFontDir(\Drupal::service('file_system')->getTempDirectory());
    $this->dompdfOptions->setLogOutputFile(\Drupal::service('file_system')->getTempDirectory() . DIRECTORY_SEPARATOR . self::LOG_FILE_NAME);
    $this->dompdfOptions->setIsRemoteEnabled($this->configuration['enable_remote']);
    $this->dompdfOptions->setIsFontSubsettingEnabled($this->configuration['font_subsetting']);
    $this->dompdfOptions->setIsPhpEnabled($this->configuration['embedded_php']);

    $this->dompdf = new DompdfLib($this->dompdfOptions);
    if ($this->configuration['disable_log']) {
      $this->dompdfOptions->setLogOutputFile('');
    }

    $this->dompdf
      ->setBaseHost($request->getHttpHost())
      ->setProtocol($request->getScheme() . '://');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('plugin.manager.entity_print.export_type')->createInstance($plugin_definition['export_type']),
      $container->get('request_stack')->getCurrentRequest()
    );
  }

  /**
   * {@inheritdoc}
   */
  public static function getInstallationInstructions() {
    return t('Please install with: @command', ['@command' => 'composer require "dompdf/dompdf ^2.0.1"']);
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return parent::defaultConfiguration() + [
      'enable_html5_parser' => TRUE,
      'disable_log' => FALSE,
      'enable_remote' => TRUE,
      'font_subsetting' => TRUE,
      'embedded_php' => FALSE,
      'cafile' => '',
      'verify_peer' => TRUE,
      'verify_peer_name' => TRUE,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);
    $form['enable_html5_parser'] = [
      '#title' => $this->t('Enable HTML5 Parser'),
      '#type' => 'checkbox',
      '#default_value' => $this->configuration['enable_html5_parser'],
      '#description' => $this->t("Note, this library doesn't work without this option enabled."),
    ];
    $form['disable_log'] = [
      '#title' => $this->t('Disable Log'),
      '#type' => 'checkbox',
      '#default_value' => $this->configuration['disable_log'],
      '#description' => $this->t("Check to disable DomPdf logging to <code>@log_file_name</code> in Drupal's temporary directory.", [
        '@log_file_name' => self::LOG_FILE_NAME,
      ]),
    ];
    $form['enable_remote'] = [
      '#title' => $this->t('Enable Remote URLs'),
      '#type' => 'checkbox',
      '#default_value' => $this->configuration['enable_remote'],
      '#description' => $this->t('This settings must be enabled for CSS and Images to work unless you manipulate the source manually.'),
    ];
    $form['font_subsetting'] = [
      '#title' => $this->t('Enable font subsetting'),
      '#type' => 'checkbox',
      '#default_value' => $this->configuration['font_subsetting'],
      '#description' => $this->t('The bundled, PHP-based php-font-lib provides support for loading and sub-setting fonts.'),
    ];
    $form['embedded_php'] = [
      '#title' => $this->t('Enable embedded PHP'),
      '#type' => 'checkbox',
      '#default_value' => $this->configuration['embedded_php'],
      '#description' => $this->t('If this setting is set to true then DomPdf will automatically evaluate embedded PHP. See <a href=":wiki">https://github.com/dompdf/dompdf/wiki/Usage#embedded-php-support</a>', [
        ':wiki' => 'https://github.com/dompdf/dompdf/wiki/Usage#embedded-php-support',
      ]),
    ];
    $form['ssl_configuration'] = [
      '#type' => 'details',
      '#title' => $this->t('SSL Configuration'),
      '#open' => !empty($this->configuration['cafile']) || empty($this->configuration['verify_peer']) || empty($this->configuration['verify_peer_name']),
    ];
    $form['ssl_configuration']['cafile'] = [
      '#title' => $this->t('CA File'),
      '#type' => 'textfield',
      '#default_value' => $this->configuration['cafile'],
      '#description' => $this->t('Path to the CA file. This may be needed for development boxes that use SSL. You can leave this empty in production.'),
    ];
    $form['ssl_configuration']['verify_peer'] = [
      '#title' => $this->t('Verify Peer'),
      '#type' => 'checkbox',
      '#default_value' => $this->configuration['verify_peer'],
      '#description' => $this->t("Verify an SSL Peer's certificate. For development only, do not disable this in production. See https://curl.haxx.se/libcurl/c/CURLOPT_SSL_VERIFYPEER.html"),
    ];
    $form['ssl_configuration']['verify_peer_name'] = [
      '#title' => $this->t('Verify Peer Name'),
      '#type' => 'checkbox',
      '#default_value' => $this->configuration['verify_peer_name'],
      '#description' => $this->t("Verify an SSL Peer's certificate. For development only, do not disable this in production. See https://curl.haxx.se/libcurl/c/CURLOPT_SSL_VERIFYPEER.html"),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function addPage($content) {
    // We must keep adding to previously added HTML as loadHtml() replaces the
    // entire document.
    $this->html .= (string) $content;
    $this->dompdf->loadHtml($this->html);
  }

  /**
   * {@inheritdoc}
   */
  public function send($filename, $force_download = TRUE) {
    $this->doRender();

    // The Dompdf library internally adds the .pdf extension so we remove it
    // from our filename here.
    $filename = preg_replace('/\.pdf$/i', '', $filename);

    // If the filename received here is NULL, force open in the browser
    // otherwise attempt to have it downloaded.
    $this->dompdf->stream($filename, ['Attachment' => $force_download]);
  }

  /**
   * {@inheritdoc}
   */
  public function getBlob() {
    $this->doRender();
    return $this->dompdf->output();
  }

  /**
   * Tell Dompdf to render the HTML into a PDF.
   *
   * @throws \Drupal\entity_print\PrintEngineException
   */
  protected function doRender() {
    $this->setupHttpContext();

    if (!$this->hasRendered) {
      try {
        $this->dompdf->render();
        $this->hasRendered = TRUE;
      }
      catch (DompdfLibException $e) {
        throw new PrintEngineException(sprintf('Failed to generate PDF: %s', $e));
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function dependenciesAvailable() {
    return class_exists('Dompdf\Dompdf') && !drupal_valid_test_ua();
  }

  /**
   * Setup the HTTP Context used by Dompdf for requesting resources.
   */
  protected function setupHttpContext() {
    $context_options = [
      'ssl' => [
        'verify_peer' => $this->configuration['verify_peer'],
        'verify_peer_name' => $this->configuration['verify_peer_name'],
      ],
    ];
    if ($this->configuration['cafile']) {
      $context_options['ssl']['cafile'] = $this->configuration['cafile'];
    }

    // If we have authentication then add it to the request context.
    if (!empty($this->configuration['username'])) {
      $auth = base64_encode(sprintf('%s:%s', $this->configuration['username'], $this->configuration['password']));
      $context_options['http']['header'] = [
        'Authorization: Basic ' . $auth,
      ];
    }

    // When embedding images from Drupal's private file system,
    // the DomPdf library uses file_get_contents to retrieve the image.
    // Without the cookie header, the request will be redirect to
    // the site's login page.
    // See \DomPdf\Image\Cache::resolve_url for details.
    $session = $this->request->getSession();
    if ($session) {
      $cookie = 'Cookie: ' . $session->getName() . '=' . $session->getId();
      $context_options['http']['header'][] = $cookie;
    }

    $http_context = stream_context_create($context_options);
    $this->dompdf->setHttpContext($http_context);
  }

  /**
   * {@inheritdoc}
   */
  protected function getPaperSizes() {
    return array_combine(array_keys(CPDF::$PAPER_SIZES), array_map('ucfirst', array_keys(CPDF::$PAPER_SIZES)));
  }

  /**
   * {@inheritdoc}
   */
  public function getPrintObject() {
    return $this->dompdf;
  }

}

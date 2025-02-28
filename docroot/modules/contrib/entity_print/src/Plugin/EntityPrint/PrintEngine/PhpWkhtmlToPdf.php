<?php

namespace Drupal\entity_print\Plugin\EntityPrint\PrintEngine;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\OptGroup;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Site\Settings;
use Drupal\entity_print\Plugin\ExportTypeInterface;
use Drupal\entity_print\PrintEngineException;
use mikehaertl\wkhtmlto\Pdf;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * PHP wkhtmltopdf plugin.
 *
 * @PrintEngine(
 *   id = "phpwkhtmltopdf",
 *   label = @Translation("Php Wkhtmltopdf"),
 *   export_type = "pdf"
 * )
 *
 * To use this implementation you will need the Php Wkhtmltopdf library, simply
 * run:
 *
 * @code
 *     composer require "mikehaertl/phpwkhtmltopdf ~2.1"
 * @endcode
 */
class PhpWkhtmlToPdf extends PdfEngineBase implements AlignableHeaderFooterInterface, ContainerFactoryPluginInterface {

  /**
   * Popular viewport sizes.
   *
   * @var array
   * @constant
   */
  public static $viewportSizeOptions
    = [
      '_none' => 'Default',
      '1920x1080' => '1920x1080',
      '1366x768' => '1366x768',
      '1280x1024' => '1280x1024',
      '1280x800' => '1280x800',
      '1024x768' => '1024x768',
      '768x1024' => '768x1024',
      '720x1280' => '720x1280',
      '375x667' => '375x667',
      '360x640' => '360x640',
    ];

  /**
   * The library instance.
   *
   * @var \mikehaertl\wkhtmlto\Pdf
   */
  protected $pdf;

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
    $this->request = $request;

    parent::__construct($configuration, $plugin_id, $plugin_definition, $export_type);
    $this->pdf = new Pdf([
      'binary' => $this->configuration['binary_location'],
      'orientation' => $this->configuration['default_paper_orientation'],
      'username' => $this->configuration['username'],
      'password' => $this->configuration['password'],
      'page-size' => $this->configuration['default_paper_size'],
      'zoom' => $this->configuration['zoom'],
      'viewport-size' => $this->configuration['viewport_size'],
    ]);

    if ($this->configuration['remove_pdf_margins']) {
      $this->pdf->setOptions([
        'margin-top' => 0,
        'margin-bottom' => 0,
        'margin-left' => 0,
        'margin-right' => 0,
      ]);
    }

    // Table of contents handling.
    if ($this->configuration['toc_generate']) {
      if ($this->configuration['toc_enable_back_links']) {
        // This option is actually a page option.
        $this->getPrintObject()->setOptions(['enable-toc-back-links']);
      }

      $options = [];
      if ($this->configuration['toc_disable_dotted_lines']) {
        $options[] = 'disable-dotted-lines';
      }
      if ($this->configuration['toc_disable_links']) {
        $options[] = 'disable-toc-links';
      }
      $this->getPrintObject()->addToc($options);
    }

    // Proxy configuration.
    $config = Settings::get('http_client_config');
    if (!empty($config['proxy']['https'])) {
      $this->pdf->setOptions([
        'proxy' => $config['proxy']['https'],
      ]);
    }
    elseif (!empty($config['proxy']['http'])) {
      $this->pdf->setOptions([
        'proxy' => $config['proxy']['http'],
      ]);
    }

    // When embedding images from Drupal's private file system, the library
    // fails because it's seen as an anonymous user. See DomPDF for details.
    $session = $this->request->getSession();
    if ($session) {
      $options = [
        'cookie' => [
          $session->getName() => $session->getId(),
        ],
      ];
      $this->getPrintObject()->setOptions($options);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getPrintObject() {
    return $this->pdf;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('plugin.manager.entity_print.export_type')
        ->createInstance($plugin_definition['export_type']),
      $container->get('request_stack')->getCurrentRequest()
    );
  }

  /**
   * {@inheritdoc}
   */
  public static function getInstallationInstructions() {
    return t('Please install with: @command', ['@command' => 'composer require "mikehaertl/phpwkhtmltopdf ~2.1"']);
  }

  /**
   * {@inheritdoc}
   */
  public static function dependenciesAvailable() {
    return class_exists('mikehaertl\wkhtmlto\Pdf') && !drupal_valid_test_ua();
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return parent::defaultConfiguration() + [
      'binary_location' => '/usr/local/bin/wkhtmltopdf',
      'zoom' => 1,
      'toc_generate' => FALSE,
      'toc_enable_back_links' => FALSE,
      'toc_disable_dotted_lines' => FALSE,
      'toc_disable_links' => FALSE,
      'viewport_size' => '_none',
      'remove_pdf_margins' => FALSE,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);
    $form['zoom'] = [
      '#type' => 'number',
      '#title' => $this->t('Zoom'),
      '#description' => $this->t('Set this to zoom the pages - needed to produce hairlines.'),
      '#default_value' => $this->configuration['zoom'],
      '#weight' => -8,
      '#step' => 0.01,
    ];

    $form['binary_location'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Binary Location'),
      '#description' => $this->t('Set this to the system path where the PDF engine binary is located.'),
      '#default_value' => $this->configuration['binary_location'],
      '#weight' => -7,
    ];

    $form['toc'] = [
      '#type' => 'details',
      '#title' => $this->t('Table of contents'),
      '#tree' => TRUE,
      '#open' => $this->configuration['toc_generate'],
    ];

    $form['toc']['toc_generate'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Generate table of contents?'),
      '#default_value' => $this->configuration['toc_generate'],
    ];

    $form['toc']['toc_enable_back_links'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Link from section header to table of contents?'),
      '#default_value' => $this->configuration['toc_enable_back_links'],
    ];

    $form['toc']['toc_disable_dotted_lines'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Do not use dotted lines in the table of contents?'),
      '#default_value' => $this->configuration['toc_disable_dotted_lines'],
    ];

    $form['toc']['toc_disable_links'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Do not link from table of contents to sections'),
      '#default_value' => $this->configuration['toc_disable_links'],
    ];

    $form['viewport_size'] = [
      '#type' => 'select',
      '#title' => $this->t('Viewport Size'),
      '#options' => self::$viewportSizeOptions,
      '#description' => $this->t('Set viewport size if you have custom scrollbars or css attribute overflow to emulate window size.'),
      '#default_value' => $this->configuration['viewport_size'],
      '#weight' => -6,
    ];

    $form['remove_pdf_margins'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Remove PDF margins'),
      '#description' => $this->t('Remove the page margins on the PDF'),
      '#default_value' => $this->configuration['remove_pdf_margins'],
      '#weight' => -5,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::validateConfigurationForm($form, $form_state);
    $values = OptGroup::flattenOptions($form_state->getValues());
    $binary_location = $values['binary_location'] ?? NULL;
    if ($binary_location && !file_exists($binary_location)) {
      $form_state->setErrorByName('binary_location', sprintf('The wkhtmltopdf binary does not exist at %s', $binary_location));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function send($filename, $force_download = TRUE) {
    // If the filename received here is NULL, force open in the browser
    // otherwise attempt to have it downloaded.
    if (!$this->pdf->send($filename, !$force_download)) {
      throw new PrintEngineException(sprintf('Failed to generate PDF: %s', $this->pdf->getError()));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getBlob() {
    return $this->pdf->toString();
  }

  /**
   * {@inheritdoc}
   */
  public function addPage($content) {
    $this->pdf->addPage($content);
  }

  /**
   * {@inheritdoc}
   */
  public function setHeaderText($text, $alignment) {
    $this->pdf->setOptions(['header-' . $alignment => $text]);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function setFooterText($text, $alignment) {
    $this->pdf->setOptions(['footer-' . $alignment => $text]);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  protected function getPaperSizes() {
    return [
      'a0' => 'A0',
      'a1' => 'A1',
      'a2' => 'A2',
      'a3' => 'A3',
      'a4' => 'A4',
      'a5' => 'A5',
      'a6' => 'A6',
      'a7' => 'A7',
      'a8' => 'A8',
      'a9' => 'A9',
      'b0' => 'B0',
      'b1' => 'B1',
      'b10' => 'B10',
      'b2' => 'B2',
      'b3' => 'B3',
      'b4' => 'B4',
      'b5' => 'B5',
      'b6' => 'B6',
      'b7' => 'B7',
      'b8' => 'B8',
      'b9' => 'B9',
      'ce5' => 'CE5',
      'comm10e' => 'Comm10E',
      'dle' => 'DLE',
      'executive' => 'Executive',
      'folio' => 'Folio',
      'ledger' => 'Ledger',
      'legal' => 'Legal',
      'letter' => 'Letter',
      'tabloid' => 'Tabloid',
    ];
  }

}

<?php

namespace Drupal\views_data_export\Plugin\views\style;

use Drupal\Component\Utility\Html;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Routing\RedirectDestinationTrait;
use Drupal\Core\Url;
use Drupal\rest\Plugin\views\style\Serializer;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Serializer\Encoder\XmlEncoder;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * A style plugin for data export views.
 *
 * @ingroup views_style_plugins
 *
 * @ViewsStyle(
 *   id = "data_export",
 *   title = @Translation("Data export"),
 *   help = @Translation("Configurable row output for data exports."),
 *   display_types = {"data"}
 * )
 */
class DataExport extends Serializer {

  use RedirectDestinationTrait;

  /**
   * Field labels should be enabled by default for this Style.
   *
   * @var bool
   */
  protected $defaultFieldLabels = TRUE;

  /**
   * The module handler service.
   *
   * @var \Drupal\Core\Extension\ModuleHandler
   */
  protected $moduleHandler;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('serializer'),
      $container->getParameter('serializer.formats'),
      $container->getParameter('serializer.format_providers'),
      $container->get('module_handler')
    );
  }

  /**
   * Constructs a Plugin object.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, SerializerInterface $serializer, array $serializer_formats, array $serializer_format_providers, $module_handler) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $serializer, $serializer_formats, $serializer_format_providers);

    $this->moduleHandler = $module_handler;
  }

  /**
   * {@inheritdoc}
   */
  public function defineOptions() {
    $options = parent::defineOptions();

    // CSV options.
    // @todo Can these somehow be moved to a plugin?
    $options['csv_settings']['contains'] = [
      'delimiter' => ['default' => ','],
      'enclosure' => ['default' => '"'],
      'escape_char' => ['default' => '\\'],
      'strip_tags' => ['default' => TRUE],
      'trim' => ['default' => TRUE],
      'encoding' => ['default' => 'utf8'],
      'utf8_bom' => ['default' => FALSE],
      'use_serializer_encode_only' => ['default' => FALSE],
      'output_header' => ['default' => TRUE],
    ];

    $options['xml_settings']['contains'] = [
      'encoding' => ['default' => 'UTF-8'],
      'root_node_name' => ['default' => 'response'],
      'item_node_name' => ['default' => 'item'],
      'format_output' => ['default' => FALSE],
    ];

    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);

    switch ($form_state->get('section')) {
      case 'style_options':

        // Change format to radios instead, since multiple formats here do not
        // make sense as they do for REST exports.
        $form['formats']['#type'] = 'radios';
        $form['formats']['#default_value'] = reset($this->options['formats']);

        $format_options = $this->getFormatOptions();

        if (in_array('csv', $format_options)) {
          // CSV options.
          // @todo Can these be moved to a plugin?
          $csv_options = $this->options['csv_settings'];
          $form['csv_settings'] = [
            '#type' => 'details',
            '#open' => FALSE,
            '#title' => $this->t('CSV settings'),
            '#tree' => TRUE,
            '#states' => [
              'visible' => [':input[name="style_options[formats]"]' => ['value' => 'csv']],
            ],
            'delimiter' => [
              '#type' => 'textfield',
              '#title' => $this->t('Delimiter'),
              '#description' => $this->t('Indicates the character used to delimit fields. Defaults to a comma (<code>,</code>). For tab-separation use <code>\t</code> characters.'),
              '#default_value' => $csv_options['delimiter'],
            ],
            'enclosure' => [
              '#type' => 'textfield',
              '#title' => $this->t('Enclosure'),
              '#description' => $this->t('Indicates the character used for field enclosure. Defaults to a double quote (<code>"</code>).'),
              '#default_value' => $csv_options['enclosure'],
            ],
            'escape_char' => [
              '#type' => 'textfield',
              '#title' => $this->t('Escape character'),
              '#description' => $this->t('Indicates the character used for escaping. Defaults to a backslash (<code>\</code>).'),
              '#default_value' => $csv_options['escape_char'],
            ],
            'strip_tags' => [
              '#type' => 'checkbox',
              '#title' => $this->t('Strip HTML'),
              '#description' => $this->t('Strips HTML tags from CSV cell values.'),
              '#default_value' => $csv_options['strip_tags'],
            ],
            'trim' => [
              '#type' => 'checkbox',
              '#title' => $this->t('Trim whitespace'),
              '#description' => $this->t('Trims whitespace from beginning and end of CSV cell values.'),
              '#default_value' => $csv_options['trim'],
            ],
            'encoding' => [
              '#type' => 'radios',
              '#title' => $this->t('Encoding'),
              '#description' => $this->t('Determines the encoding used for CSV cell values.'),
              '#options' => [
                'utf8' => $this->t('UTF-8'),
              ],
              '#default_value' => $csv_options['encoding'],
            ],
            'utf8_bom' => [
              '#type' => 'checkbox',
              '#title' => $this->t(
                  'Include unicode signature (<a href="@bom" target="_blank">BOM</a>).', [
                    '@bom' => 'https://www.w3.org/International/questions/qa-byte-order-mark',
                  ]
              ),
              '#default_value' => $csv_options['utf8_bom'],
            ],
            'use_serializer_encode_only' => [
              '#type' => 'checkbox',
              '#title' => $this->t('Only use Symfony serializer->encode method'),
              '#description' => $this->t('Skips the symfony data normalize method when rendering data export to increase performance on large datasets. <strong>(Only use when not exporting nested data)</strong>'),
              '#default_value' => $csv_options['use_serializer_encode_only'],
            ],
            'output_header' => [
              '#type' => 'checkbox',
              '#title' => $this->t("Show the header row"),
              '#default_value' => $csv_options['output_header'],
            ],
          ];
        }

        if (in_array('xml', $format_options)) {
          $form['xml_settings'] = [
            '#type' => 'details',
            '#open' => TRUE,
            '#title' => $this->t('XML settings'),
            '#tree' => TRUE,
            '#states' => [
              'visible' => [
                ':input[name="style_options[formats]"]' => [
                  ['value' => 'xml'],
                ],
              ],
            ],
          ];

          $xml_options = $this->options['xml_settings'];
          // Add our default options for backwards compatibility.
          $xml_options += [
            'encoding' => 'UTF-8',
            'root_node_name' => 'response',
            'item_node_name' => 'item',
            'format_output' => FALSE,
          ];
          $form['xml_settings']['encoding'] = [
            '#type' => 'select',
            '#title' => $this->t('Encoding'),
            '#default_value' => $xml_options['encoding'],
            '#options' => [
              '' => $this->t('None specified'),
              'UTF-8' => $this->t('UTF-8'),
            ],
          ];
          // @todo widen this regex to support all possible XML tags, while making sure it can still work in the browser.
          $xml_tag_regex = '[A-Za-z_][A-Za-z0-9_.:\\-]*';
          $form['xml_settings']['root_node_name'] = [
            '#type' => 'textfield',
            '#title' => $this->t('Root node name'),
            '#description' => $this->t('This should be a valid XML node name.'),
            '#default_value' => $xml_options['root_node_name'],
            '#pattern' => $xml_tag_regex,
            '#states' => [
              'required' => [
                ':input[name="style_options[formats]"]' => [
                  ['value' => 'xml'],
                ],
              ],
            ],
          ];
          $form['xml_settings']['item_node_name'] = [
            '#type' => 'textfield',
            '#title' => $this->t('Item node name'),
            '#description' => $this->t('This should be a valid XML node name.'),
            '#default_value' => $xml_options['item_node_name'],
            '#pattern' => $xml_tag_regex,
            '#states' => [
              'required' => [
                ':input[name="style_options[formats]"]' => [
                  ['value' => 'xml'],
                ],
              ],
            ],
          ];
          $form['xml_settings']['format_output'] = [
            '#type' => 'checkbox',
            '#title' => $this->t('Pretty-print output'),
            '#description' => $this->t('If the XML file is going to be consumed by humans, you might want to have it formatted with line breaks and indents.'),
            '#default_value' => $xml_options['format_output'],
          ];
        }
        break;
    }

  }

  /**
   * {@inheritdoc}
   */
  public function submitOptionsForm(&$form, FormStateInterface $form_state) {
    // Transform the formats back into an array.
    $format = $form_state->getValue(['style_options', 'formats']);
    $form_state->setValue(['style_options', 'formats'], [$format => $format]);
    parent::submitOptionsForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   *
   * @todo This should implement AttachableStyleInterface once
   * https://www.drupal.org/node/2779205 lands.
   */
  public function attachTo(array &$build, $display_id, Url $url, $title) {
    // @todo This mostly hard-codes CSV handling. Figure out how to abstract.
    $url_options = [];
    $input = $this->view->getExposedInput();
    if ($input) {
      $url_options['query'] = $input;
    }
    if ($pager = $this->view->getPager()) {
      $url_options['query']['page'] = $pager->getCurrentPage();
    }
    if (!empty($this->options['formats'])) {
      $url_options['query']['_format'] = reset($this->options['formats']);
    }

    $url = $url->setOptions($url_options)->toString();

    // Add the icon to the view.
    $format = $this->displayHandler->getContentType();
    $this->view->feedIcons[] = [
      '#theme' => 'export_icon',
      '#url' => $url,
      '#format' => mb_strtoupper($format),
      '#title' => $title,
      '#theme_wrappers' => [
        'container' => [
          '#attributes' => [
            'class' => [
              Html::cleanCssIdentifier($format) . '-feed',
              'views-data-export-feed',
              Html::cleanCssIdentifier('views-data-export-feed--' . $this->displayHandler->display['id']),
            ],
          ],
        ],
      ],
      '#attached' => [
        'library' => [
          'views_data_export/views_data_export',
        ],
      ],
    ];

    // Attach a link to the CSV feed, which is an alternate representation.
    $build['#attached']['html_head_link'][][] = [
      'rel' => 'alternate',
      'type' => $this->displayHandler->getMimeType(),
      'title' => $title,
      'href' => $url,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildSortPost() {
    $query = $this->view->getRequest()->query;
    $sort_field = $query->get('order');

    if (empty($sort_field) || empty($this->view->field[$sort_field])) {
      return;
    }

    // Ensure sort order is valid.
    $sort_order = strtolower($query->get('sort'));
    if (empty($sort_order) || ($sort_order != 'asc' && $sort_order != 'desc')) {
      $sort_order = 'asc';
    }

    // Tell the field to click sort.
    $this->view->field[$sort_field]->clickSort($sort_order);
  }

  /**
   * {@inheritdoc}
   */
  public function render() {
    // This is pretty close to the parent implementation.
    // Difference (noted below) stems from not being able to get anything other
    // than json rendered even when the display was set to export csv or xml.
    $rows = [];
    foreach ($this->view->result as $row_index => $row) {
      $this->view->row_index = $row_index;
      $output = $this->view->rowPlugin->render($row);
      $this->moduleHandler->alter('views_data_export_row', $output, $row, $this->view);
      $rows[] = $output;
    }

    unset($this->view->row_index);

    // Get the format configured in the display or fallback to json.
    // We intentionally implement this different from the parent method because
    // $this->displayHandler->getContentType() will always return json due to
    // the request's header (i.e. "accept:application/json") and
    // we want to be able to render csv or xml data as well in accordance with
    // the data export format configured in the display.
    $format = !empty($this->options['formats']) ? reset($this->options['formats']) : 'json';

    // If data is being exported as a CSV we give the option to not use the
    // Symfony normalize method which increases performance on large data sets.
    // This option can be configured in the CSV Settings section of the data
    // export.
    if ($format === 'csv' && $this->options['csv_settings']['use_serializer_encode_only'] == 1) {
      return $this->serializer->encode($rows, $format, ['views_style_plugin' => $this]);
    }

    if ($format === 'xml') {
      return $this->renderXmlStyle($rows);
    }
    else {
      return $this->serializer->serialize($rows, $format, ['views_style_plugin' => $this]);
    }

  }

  /**
   * Renders the style using the XML format.
   *
   * @param array $rows
   *   The rows to render.
   *
   * @return string
   *   The rendered style.
   */
  protected function renderXmlStyle(array $rows) {
    $options = $this->options['xml_settings'] ?? [];
    // Add our default options for backwards compatibility.
    $options += [
      'encoding' => 'UTF-8',
      'root_node_name' => 'response',
      'item_node_name' => 'item',
      'format_output' => FALSE,
    ];
    $context = [
      'views_style_plugin' => $this,
    ];

    if ($options['root_node_name']) {
      // @todo We can rely on the constant being defined once we drop support for Drupal 9.
      $context[defined('XmlEncoder::ROOT_NODE_NAME') ? XmlEncoder::ROOT_NODE_NAME : 'xml_root_node_name'] = $options['root_node_name'];
    }
    if ($options['encoding']) {
      // @todo We can rely on the constant being defined once we drop support for Drupal 9.
      $context[defined('XmlEncoder::ENCODING') ? XmlEncoder::ENCODING : 'xml_encoding'] = $options['encoding'];
    }
    if ($options['format_output']) {
      // @todo We can rely on the constant being defined once we drop support for Drupal 9.
      $context[defined('XmlEncoder::FORMAT_OUTPUT') ? XmlEncoder::FORMAT_OUTPUT : 'xml_format_output'] = $options['format_output'];
    }

    $item_node_name = $options['item_node_name'] ?: 'item';
    if ($item_node_name != 'item') {
      $new_rows = [];
      foreach ($rows as $k => $row) {
        $new_rows[$item_node_name][] = [
          '@key' => $k,
          '#' => $row,
        ];
      }
      $rows = $new_rows;
    }

    return $this->serializer->serialize($rows, 'xml', $context);
  }

}

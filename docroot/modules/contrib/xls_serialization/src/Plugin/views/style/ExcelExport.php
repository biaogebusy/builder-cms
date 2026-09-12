<?php

namespace Drupal\xls_serialization\Plugin\views\style;

use Drupal\Component\Utility\Html;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\rest\Plugin\views\style\Serializer;

/**
 * A style plugin for Excel export views.
 *
 * @ingroup views_style_plugins
 *
 * @ViewsStyle(
 *   id = "excel_export",
 *   title = @Translation("Excel export"),
 *   help = @Translation("Configurable row output for Excel exports."),
 *   display_types = {"data"}
 * )
 */
class ExcelExport extends Serializer {

  use ExcelExportStyleTrait;

  /**
   * Constructs a Plugin object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param mixed $serializer
   *   The serializer for the plugin instance.
   * @param array $serializer_formats
   *   The serializer formats for the plugin instance.
   * @param array $serializer_format_providers
   *   The serializer format providers for the plugin instance.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, $serializer, array $serializer_formats, array $serializer_format_providers) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $serializer, $serializer_formats, $serializer_format_providers);

    $this->initializeSerializerFormats();
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
    $url_options['absolute'] = TRUE;
    if (!empty($this->options['formats'])) {
      $url_options['query']['_format'] = reset($this->options['formats']);
    }

    $url = $url->setOptions($url_options)->toString();

    // Add the CSV icon to the view.
    $type = $this->displayHandler->getContentType();
    $this->view->feedIcons[] = [
      '#theme' => 'export_icon',
      '#url' => $url,
      '#format' => mb_strtoupper($type),
      '#title' => $title,
      '#theme_wrappers' => [
        'container' => [
          '#attributes' => [
            'class' => [
              Html::cleanCssIdentifier($type) . '-feed',
              'views-data-export-feed',
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
  public function submitOptionsForm(&$form, FormStateInterface $form_state) {
    // Transform the formats back into an array.
    $format = $form_state->getValue(['style_options', 'formats']);
    $form_state->setValue(['style_options', 'formats'], [$format => $format]);

    parent::submitOptionsForm($form, $form_state);
  }

}

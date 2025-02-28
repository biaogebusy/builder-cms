<?php

namespace Drupal\views_data_export\Plugin\views\display;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Cache\CacheableResponse;
use Drupal\Core\Config\StorageException;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\rest\Plugin\views\display\RestExport;
use Drupal\views\Views;
use Drupal\views\ViewExecutable;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;
use Drupal\search_api\Plugin\views\query\SearchApiQuery;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Drupal\Core\File\FileSystemInterface;

/**
 * Provides a data export display plugin.
 *
 * This overrides the REST Export display to make labeling clearer on the admin
 * UI, and to allow attaching of these to other displays.
 *
 * @ingroup views_display_plugins
 *
 * @ViewsDisplay(
 *   id = "data_export",
 *   title = @Translation("Data export"),
 *   help = @Translation("Export the view results to a file. Can handle very large result sets."),
 *   uses_route = TRUE,
 *   admin = @Translation("Data export"),
 *   returns_response = TRUE
 * )
 */
class DataExport extends RestExport {

  /**
   * {@inheritdoc}
   */
  public static function buildResponse($view_id, $display_id, array $args = [], &$view = []) {
    // Load the View we're working with and set its display ID so we can get
    // the exposed input.
    $view = Views::getView($view_id);
    $view->setDisplay($display_id);
    $view->setArguments($args);

    // Build different responses whether batch or standard method is used.
    if ($view->display_handler->getOption('export_method') == 'batch') {
      return static::buildBatch($view, $args);
    }

    return static::buildStandard($view);
  }

  /**
   * Builds batch export response.
   *
   * @param \Drupal\views\ViewExecutable $view
   *   The view to export.
   * @param array $args
   *   Arguments for the $view.
   *
   * @return null|\Symfony\Component\HttpFoundation\RedirectResponse
   *   Redirect to the batching page.
   */
  protected static function buildBatch(ViewExecutable &$view, array $args) {
    // Get total number of items.
    $view->get_total_rows = TRUE;
    $export_limit = $view->getDisplay()->getOption('export_limit');
    $view->preExecute($args);
    $view->build();

    if ($view->getQuery() instanceof SearchApiQuery) {
      $total_rows = $view->query->getSearchApiQuery()->range(NULL, 1)->execute()->getResultCount();
    }
    else {
      $count_query_results = $view->query->query()->countQuery()->execute();
      $total_rows = (int) $count_query_results->fetchField();
    }

    // If export limit is set and the number of rows is greater than the
    // limit, then set the total to limit.
    if ($export_limit && $export_limit < $total_rows) {
      $total_rows = $export_limit;
    }

    // Get view exposed input which is the query string parameters from url.
    $query_parameters = $view->getExposedInput();
    // Remove the file format parameter from the query string.
    if (array_key_exists('_format', $query_parameters)) {
      unset($query_parameters['_format']);
    }

    // Check where to redirect the user after the batch finishes.
    // Defaults to the <front> route.
    $redirect_url = Url::fromRoute('<front>');

    // Get options set in views display configuration.
    $custom_redirect = $view->getDisplay()->getOption('custom_redirect_path');
    $redirect_to_display = $view->getDisplay()->getOption('redirect_to_display');

    // Check if the url query string should be added to the redirect URL.
    $include_query_params = $view->display_handler->getOption('include_query_params');

    if ($custom_redirect) {
      $redirect_path = $view->display_handler->getOption('redirect_path');
      if (isset($redirect_path)) {
        // Replace tokens in the redirect_path.
        $token_service = \Drupal::token();
        $redirect_path = $token_service->replace($redirect_path, ['view' => $view]);

        if ($include_query_params) {
          $redirect_url = Url::fromUserInput(trim($redirect_path), ['query' => $query_parameters]);
        }
        else {
          $redirect_url = Url::fromUserInput(trim($redirect_path));
        }
      }
    }
    elseif (isset($redirect_to_display) && $redirect_to_display !== 'none') {
      // Get views display URL.
      $display_route = $view->getUrl([], $redirect_to_display)->getRouteName();
      if ($include_query_params) {
        $redirect_url = Url::fromRoute($display_route, [], ['query' => $query_parameters]);
      }
      else {
        $redirect_url = Url::fromRoute($display_route);
      }
    }

    $batch_definition = [
      'operations' => [
        [
          [static::class, 'processBatch'],
          [
            $view->id(),
            $view->current_display,
            $view->args,
            $view->getExposedInput(),
            $total_rows,
            $query_parameters,
            $redirect_url->toString(),
          ],
        ],
      ],
      'title' => t('Exporting data...'),
      'progressive' => TRUE,
      'progress_message' => t('@percentage% complete. Time elapsed: @elapsed'),
      'finished' => [static::class, 'finishBatch'],
    ];
    batch_set($batch_definition);

    return batch_process();
  }

  /**
   * Builds standard export response.
   *
   * @param \Drupal\views\ViewExecutable $view
   *   The view to export.
   *
   * @return \Drupal\Core\Cache\CacheableResponse
   *   Redirect to the batching page.
   */
  protected static function buildStandard(ViewExecutable $view) {
    $build = $view->buildRenderable();

    // Setup an empty response so headers can be added as needed during views
    // rendering and processing.
    $response = new CacheableResponse('', 200);
    $build['#response'] = $response;

    /** @var \Drupal\Core\Render\RendererInterface $renderer */
    $renderer = \Drupal::service('renderer');
    $output = (string) $renderer->renderRoot($build);

    $response->setContent($output);
    $cache_metadata = CacheableMetadata::createFromRenderArray($build);
    $response->addCacheableDependency($cache_metadata);

    // Set filename if such exists.
    if ($filename = $view->getDisplay()->getOption('filename')) {
      $bubbleable_metadata = BubbleableMetadata::createFromObject($cache_metadata);
      $response->headers->set('Content-Disposition', 'attachment; filename="' . \Drupal::token()->replace($filename, ['view' => $view], [], $bubbleable_metadata) . '"');
    }
    $response->headers->set('Content-type', $build['#content_type']);

    return $response;
  }

  /**
   * {@inheritdoc}
   */
  protected function defineOptions() {
    $options = parent::defineOptions();

    $options['displays'] = ['default' => []];

    // Set the default style plugin, and default to fields.
    $options['style']['contains']['type']['default'] = 'data_export';
    $options['row']['contains']['type']['default'] = 'data_field';

    // We don't want to use pager as it doesn't make any sense. But it cannot
    // just be removed from a view as it is core functionality. These values
    // will be controlled by custom configuration.
    $options['pager']['contains'] = [
      'type' => ['default' => 'none'],
      'options' => ['default' => ['offset' => 0]],
    ];

    $options['export_method']['default'] = 'standard';
    $options['export_batch_size']['default'] = '1000';
    $options['export_limit']['default'] = '0';

    // Set facet source default.
    if (\Drupal::service('module_handler')->moduleExists('facets')) {
      $options['facet_settings']['default'] = 'none';
    }

    // Set download, file storage and redirect defaults.
    $options['automatic_download']['default'] = FALSE;
    $options['store_in_public_file_directory']['default'] = FALSE;
    $options['custom_redirect_path']['default'] = FALSE;

    // Redirect to views display option.
    $options['redirect_to_display']['default'] = 'none';

    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function optionsSummary(&$categories, &$options) {
    parent::optionsSummary($categories, $options);

    // Doesn't make sense to have a pager for data export so remove it.
    unset($categories["pager"]);

    // Add a view configuration category for data export settings in the
    // second column.
    $categories['export_settings'] = [
      'title' => $this->t('Export settings'),
      'column' => 'second',
      'build' => [
        '#weight' => 50,
      ],
    ];

    $options['export_method'] = [
      'category' => 'export_settings',
      'title' => $this->t('Method'),
      'desc' => $this->t('Change the way rows are processed.'),
    ];

    switch ($this->getOption('export_method')) {
      case 'standard':
        $options['export_method']['value'] = $this->t('Standard');
        break;

      case 'batch':
        $options['export_method']['value'] =
          $this->t('Batch (size: @size)', ['@size' => $this->getOption('export_batch_size')]);
        break;
    }

    $options['export_limit'] = [
      'category' => 'export_settings',
      'title' => $this->t('Limit'),
      'desc' => $this->t('The maximum amount of rows to export.'),
    ];

    $limit = $this->getOption('export_limit');
    if ($limit) {
      $options['export_limit']['value'] = $this->t('@nr rows', ['@nr' => $limit]);
    }
    else {
      $options['export_limit']['value'] = $this->t('no limit');
    }

    $displays = array_filter($this->getOption('displays'));
    if (count($displays) > 1) {
      $attach_to = $this->t('Multiple displays');
    }
    elseif (count($displays) == 1) {
      $display = array_shift($displays);
      $displays = $this->view->storage->get('display');
      if (!empty($displays[$display])) {
        $attach_to = $displays[$display]['display_title'];
      }
    }

    if (!isset($attach_to)) {
      $attach_to = $this->t('None');
    }

    $options['displays'] = [
      'category' => 'path',
      'title' => $this->t('Attach to'),
      'value' => $attach_to,
    ];

    if (\Drupal::service('module_handler')->moduleExists('facets')) {
      // Add a view configuration category for data facet settings in the
      // second column.
      $categories['facet_settings'] = [
        'title' => $this->t('Facet settings'),
        'column' => 'second',
        'build' => [
          '#weight' => 40,
        ],
      ];

      $facet_source = $this->getOption('facet_settings');
      $options['facet_settings'] = [
        'category' => 'facet_settings',
        'title' => $this->t('Facet source'),
        'value' => $facet_source,
      ];
    }

    // Add filename to the summary if set.
    if ($this->getOption('filename')) {
      $options['path']['value'] .= $this->t('(@filename)', ['@filename' => $this->getOption('filename')]);
    }

    // Display the selected format from the style plugin if available.
    $style_options = $this->getOption('style')['options'];
    if (!empty($style_options['formats'])) {
      $options['style']['value'] .= $this->t('(@export_format)', ['@export_format' => reset($style_options['formats'])]);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);

    // Remove the 'serializer' option to avoid confusion.
    switch ($form_state->get('section')) {
      case 'style':
        unset($form['style']['type']['#options']['serializer']);
        break;

      case 'export_method':
        $form['export_method'] = [
          '#type' => 'radios',
          '#title' => $this->t('Export method'),
          '#default_value' => $this->options['export_method'],
          '#options' => [
            'standard' => $this->t('Standard'),
            'batch' => $this->t('Batch'),
          ],
          '#required' => TRUE,
        ];

        $form['export_method']['standard']['#description'] = $this->t('Exports under one request. Best fit for small exports.');
        $form['export_method']['batch']['#description'] = $this->t('Exports data in sequences. Should be used when large amount of data is exported (> 2000 rows).');

        $form['export_batch_size'] = [
          '#type' => 'number',
          '#title' => $this->t('Batch size'),
          '#description' => $this->t("The number of rows to process under a request."),
          '#default_value' => $this->options['export_batch_size'],
          '#required' => TRUE,
          '#states' => [
            'visible' => [':input[name=export_method]' => ['value' => 'batch']],
          ],
        ];
        break;

      case 'export_limit':
        $form['export_limit'] = [
          '#type' => 'number',
          '#title' => $this->t('Limit'),
          '#description' => $this->t("The maximum amount of rows to export. 0 means unlimited."),
          '#default_value' => $this->options['export_limit'],
          '#min' => 0,
          '#required' => TRUE,
        ];
        break;

      case 'path':
        $form['file_fieldset'] = [
          '#type' => 'fieldset',
          '#title' => $this->t('File Storage/Download Settings'),
        ];
        $form['filename'] = [
          '#type' => 'textfield',
          '#title' => $this->t('Filename'),
          '#default_value' => $this->getOption('filename'),
          '#description' => $this->t('The filename that will be suggested to the browser for downloading purposes. You may include replacement patterns from the list below.'),
          '#fieldset' => 'file_fieldset',
        ];

        $streamWrapperManager = \Drupal::service('stream_wrapper_manager');
        // Check if the private file system is ready to use.
        if ($streamWrapperManager->isValidScheme('private')) {
          $form['store_in_public_file_directory'] = [
            '#type' => 'checkbox',
            '#title' => $this->t("Store file in public files directory"),
            '#description' => $this->t("Check this if you want to store the export files in the public:// files directory instead of the private:// files directory."),
            '#default_value' => $this->options['store_in_public_file_directory'],
            '#fieldset' => 'file_fieldset',
          ];
        }
        else {
          $form['store_in_public_file_directory'] = [
            '#type' => 'markup',
            '#markup' => $this->t('<strong>The private:// file system is not configured so the exported files will be stored in the public:// files directory. Click <a href="@link" target="_blank">here</a> for instructions on configuring the private files in the settings.php file.</strong>', ['@link' => 'https://www.drupal.org/docs/8/modules/skilling/installation/set-up-a-private-file-path']),
            '#fieldset' => 'file_fieldset',
          ];
        }

        $form['automatic_download'] = [
          '#type' => 'checkbox',
          '#title' => $this->t("Download immediately"),
          '#description' => $this->t("Check this if you want to download the file immediately after it is created. Does <strong>NOT</strong> work for JSON data exports."),
          '#default_value' => $this->options['automatic_download'],
          '#fieldset' => 'file_fieldset',
        ];

        $form['redirect_fieldset'] = [
          '#type' => 'fieldset',
          '#title' => 'Redirect Settings',
        ];

        $form['custom_redirect_path'] = [
          '#type' => 'checkbox',
          '#title' => $this->t("Custom redirect path"),
          '#description' => $this->t("Check this if you want to configure a custom redirect path."),
          '#default_value' => $this->options['custom_redirect_path'],
          '#fieldset' => 'redirect_fieldset',
        ];

        $displays = ['none' => 'None'];
        foreach ($this->view->storage->get('display') as $display_id => $display) {
          // Get displays that accept attachments and have a path.
          if ($this->view->displayHandlers->has($display_id) && $this->view->displayHandlers->get($display_id)->acceptAttachments() && isset($display['display_options']['path'])) {
            $displays[$display_id] = $display['display_title'];
          }
        }

        $form['redirect_to_display'] = [
          '#type' => 'select',
          '#title' => $this->t("Redirect to this display"),
          '#description' => $this->t("Select the display to redirect to after batch finishes. If None is selected the user will be redirected to the front page."),
          '#options' => array_map('\Drupal\Component\Utility\Html::escape', $displays),
          '#default_value' => $this->getOption('redirect_to_display'),
          '#fieldset' => 'redirect_fieldset',
          '#states' => [
            'invisible' => [
              ':input[name="custom_redirect_path"]' => ['checked' => TRUE],
            ],
          ],
        ];

        $form['redirect_path'] = [
          '#type' => 'textfield',
          '#title' => $this->t('Custom redirect path'),
          '#default_value' => $this->getOption('redirect_path'),
          '#description' => $this->t('Enter custom path to redirect user after batch finishes.'),
          '#fieldset' => 'redirect_fieldset',
          '#states' => [
            'visible' => [
              ':input[name="custom_redirect_path"]' => ['checked' => TRUE],
            ],
          ],
        ];

        $form['include_query_params'] = [
          '#type' => 'checkbox',
          '#title' => $this->t("Include query string parameters on redirect"),
          '#description' => $this->t("Check this if you want to include query string parameters on redirect."),
          '#default_value' => $this->getOption('include_query_params'),
          '#fieldset' => 'redirect_fieldset',
        ];

        // Support tokens.
        $this->globalTokenForm($form, $form_state);
        break;

      case 'displays':
        $form['#title'] .= $this->t('Attach to');
        $displays = [];
        foreach ($this->view->storage->get('display') as $display_id => $display) {
          if ($this->view->displayHandlers->has($display_id) && $this->view->displayHandlers->get($display_id)->acceptAttachments()) {
            $displays[$display_id] = $display['display_title'];
          }
        }
        $form['displays'] = [
          '#title' => $this->t('Displays'),
          '#type' => 'checkboxes',
          '#description' => $this->t('The data export icon will be available only to the selected displays.'),
          '#options' => array_map('\Drupal\Component\Utility\Html::escape', $displays),
          '#default_value' => $this->getOption('displays'),
        ];
        break;

      case 'facet_settings':
        // Determine if the view is a Search API data source view and load facet
        // sources if facets module exists.
        $view = $form_state->getStorage()['view'];
        $dependencies = $view->get('storage')->getDependencies();
        if (isset($dependencies['module'])) {
          $view_module_dependencies = $dependencies['module'];
          if (in_array('search_api', $view_module_dependencies)) {
            // Check if the facets module is enabled.
            if (\Drupal::service('module_handler')->moduleExists('facets')) {
              $facet_source_plugin_manager = \Drupal::service('plugin.manager.facets.facet_source');
              $facet_sources = $facet_source_plugin_manager->getDefinitions();
              $facet_source_list = ['none' => 'None'];
              foreach ($facet_sources as $source_id => $source) {
                $facet_source_list[$source_id] = $source['label'];
              }

              $form['#title'] .= $this->t('Facet source');
              $form['facet_settings'] = [
                '#title' => $this->t('Facet source'),
                '#type' => 'select',
                '#description' => $this->t('Choose the facet source used to alter data export. This should be the display that this data export is attached to.'),
                '#options' => $facet_source_list,
                '#default_value' => $this->options['facet_settings'],
              ];
            }
          }
        }
        break;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function attachTo(ViewExecutable $clone, $display_id, array &$build) {
    $displays = $this->getOption('displays');
    if (empty($displays[$display_id])) {
      return;
    }

    if (!$this->access()) {
      return;
    }

    // Defer to the feed style; it may put in meta information, and/or
    // attach a feed icon.
    $clone->setArguments($this->view->args);
    $clone->setDisplay($this->display['id']);
    $clone->buildTitle();
    $displays = $clone->storage->get('display');
    $title = $clone->getTitle();

    if (!empty($displays[$this->display['id']])) {
      $title = $displays[$this->display['id']]['display_title'];
    }

    if ($plugin = $clone->display_handler->getPlugin('style')) {
      $plugin->attachTo($build, $display_id, $clone->getUrl(), $title);
      foreach ($clone->feedIcons as $feed_icon) {
        $this->view->feedIcons[] = $feed_icon;
      }
    }

    // Clean up.
    $clone->destroy();
    unset($clone);
  }

  /**
   * {@inheritdoc}
   */
  public function submitOptionsForm(&$form, FormStateInterface $form_state) {
    parent::submitOptionsForm($form, $form_state);
    $section = $form_state->get('section');
    switch ($section) {
      case 'displays':
        $this->setOption($section, $form_state->getValue($section));
        break;

      case 'export_method':
        $this->setOption('export_method', $form_state->getValue('export_method'));
        $batch_size = $form_state->getValue('export_batch_size');
        $this->setOption('export_batch_size', $batch_size > 1 ? $batch_size : 1);
        break;

      case 'export_limit':
        $limit = $form_state->getValue('export_limit');
        $this->setOption('export_limit', $limit > 0 ? $limit : 0);

        // Set the limit option on the pager as-well. This is used for the
        // standard rendering.
        $this->setOption(
          'pager', [
            'type' => 'some',
            'options' => [
              'items_per_page' => $limit,
              'offset' => 0,
            ],
          ]
        );
        break;

      case 'path':
        $this->setOption('filename', $form_state->getValue('filename'));
        $this->setOption('automatic_download', $form_state->getValue('automatic_download'));
        $this->setOption('store_in_public_file_directory', $form_state->getValue('store_in_public_file_directory'));

        // Adds slash if not in the redirect path if custom path is chosen.
        if ($form_state->getValue('custom_redirect_path')) {
          $redirect_path = $form_state->getValue('redirect_path');
          if ($redirect_path !== '' && $redirect_path[0] !== '/') {
            $redirect_path = '/' . $form_state->getValue('redirect_path');
          }
          $this->setOption('redirect_path', $redirect_path);
        }

        $this->setOption('redirect_to_display', $form_state->getValue('redirect_to_display'));
        $this->setOption('custom_redirect_path', $form_state->getValue('custom_redirect_path'));
        $this->setOption('include_query_params', $form_state->getValue('include_query_params'));
        break;

      case 'facet_settings':
        $this->setOption('facet_settings', $form_state->getValue('facet_settings'));
        break;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getAvailableGlobalTokens($prepared = FALSE, array $types = []) {
    $types += ['date'];
    return parent::getAvailableGlobalTokens($prepared, $types);
  }

  /**
   * Implements callback_batch_operation() - perform processing on each batch.
   *
   * Writes rendered data export View rows to an output file that will be
   * returned by callback_batch_finished() (i.e. finishBatch) when we're done.
   *
   * @param string $view_id
   *   ID of the view.
   * @param string $display_id
   *   ID of the view display.
   * @param array $args
   *   Views arguments.
   * @param array $exposed_input
   *   Exposed input.
   * @param int $total_rows
   *   Total rows.
   * @param array $query_parameters
   *   Query string parameters.
   * @param string $redirect_url
   *   Redirect URL.
   * @param mixed $context
   *   Batch context information.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \PhpOffice\PhpSpreadsheet\Exception
   * @throws \PhpOffice\PhpSpreadsheet\Reader\Exception
   * @throws \PhpOffice\PhpSpreadsheet\Writer\Exception
   */
  public static function processBatch($view_id, $display_id, array $args, array $exposed_input, $total_rows, array $query_parameters, $redirect_url, &$context) {
    // Add query string back to the URL for processing.
    if ($query_parameters) {
      \Drupal::request()->query->add($query_parameters);
    }

    // Load the View we're working with and set its display ID so we get the
    // content we expect.
    $view = Views::getView($view_id);
    $view->setDisplay($display_id);
    $view->setArguments($args);
    $view->setExposedInput($exposed_input);

    if (isset($context['sandbox']['progress'])) {
      $view->setOffset($context['sandbox']['progress']);
    }

    $export_limit = $view->display_handler->getOption('export_limit');

    $view->preExecute($args);
    // Build the View so the query parameters and offset get applied. so our
    // This is necessary for the total to be calculated accurately and the call
    // to $view->render() to return the items we expect to process in the
    // current batch (i.e. not the same set of N, where N is the number of
    // items per page, over and over).
    $view->build();

    // First time through - create an output file to write to, set our
    // current item to zero and our total number of items we'll be processing.
    if (empty($context['sandbox'])) {
      // Set the redirect URL and the automatic download configuration in the
      // results array so they can be accessed when the batch is finished.
      $context['results'] = [
        'automatic_download' => $view->display_handler->options['automatic_download'],
        'redirect_url' => $redirect_url,
      ];

      // Initialize progress counter, which will keep track of how many items
      // we've processed.
      $context['sandbox']['progress'] = 0;

      // Initialize file we'll write our output results to.
      // This file will be written to with each batch iteration until all
      // batches have been processed.
      // This is a private file because some use cases will want to restrict
      // access to the file. The View display's permissions will govern access
      // to the file.
      $current_user = \Drupal::currentUser();
      $user_ID = $current_user->isAuthenticated() ? $current_user->id() : NULL;
      $timestamp = \Drupal::time()->getRequestTime();
      $filename = \Drupal::token()->replace($view->getDisplay()->options['filename'], ['view' => $view]);
      $extension = reset($view->getDisplay()->options['style']['options']['formats']);

      // Checks if extension is already included in the filename.
      if (!preg_match("/^.*\.($extension)$/i", $filename)) {
        $filename = $filename . "." . $extension;
      }

      $user_dir = $user_ID ? "$user_ID-$timestamp" : $timestamp;
      $view_dir = $view_id . '_' . $display_id;

      // Determine if the export file should be stored in the public or private
      // file system.
      $store_in_public_file_directory = TRUE;
      $streamWrapperManager = \Drupal::service('stream_wrapper_manager');
      // Check if the private file system is ready to use.
      if ($streamWrapperManager->isValidScheme('private')) {
        $store_in_public_file_directory = $view->getDisplay()->getOption('store_in_public_file_directory');
      }

      if ($store_in_public_file_directory === TRUE) {
        $directory = "public://views_data_export/$view_dir/$user_dir/";
      }
      else {
        $directory = "private://views_data_export/$view_dir/$user_dir/";
      }

      try {
        $fileSystem = \Drupal::service('file_system');
        $fileSystem->prepareDirectory($directory, FileSystemInterface::CREATE_DIRECTORY);
        $destination = $directory . $filename;
        $file = \Drupal::service('file.repository')->writeData('', $destination, FileSystemInterface::EXISTS_REPLACE);
        if (!$file) {
          // Failed to create the file, abort the batch.
          unset($context['sandbox']);
          $context['success'] = FALSE;
          throw new StorageException('Could not create a temporary file.');
        }

        $file->setTemporary();
        $file->save();
        // Create sandbox variable from filename that can be referenced
        // throughout the batch processing.
        $context['sandbox']['vde_file'] = $file->getFileUri();

        // Store URI of export file in results array because it can be accessed
        // in our callback_batch_finished (finishBatch) callback. Better to do
        // this than use a SESSION variable. Also, we're not returning any
        // results so the $context['results'] array is unused.
        $context['results']['vde_file'] = $context['sandbox']['vde_file'];
      }
      catch (StorageException $e) {
        $message = t('Could not write to temporary output file for result export (@file). Check permissions.', ['@file' => $context['sandbox']['vde_file']]);
        \Drupal::logger('views_data_export')->error($message);
      }
    }

    // Render the current batch of rows - these will then be appended to the
    // output file we write to each batch iteration.
    // Make sure that if limit is set the last batch will output the remaining
    // amount of rows and not more.
    $items_this_batch = $view->display_handler->getOption('export_batch_size');
    if ($export_limit && $context['sandbox']['progress'] + $items_this_batch > $export_limit) {
      $items_this_batch = $export_limit - $context['sandbox']['progress'];
    }

    // Set the limit directly on the query.
    $view->query->setLimit((int) $items_this_batch);
    $view->execute($display_id);

    // Check to see if the build failed.
    if (!empty($view->build_info['fail'])) {
      return;
    }
    if (!empty($view->build_info['denied'])) {
      return;
    }

    // We have to render the whole view to get all hooks executes.
    // Only rendering the display handler would result in many empty fields.
    $rendered_rows = $view->render();
    $string = (string) $rendered_rows['#markup'];

    // Workaround for CSV headers, remove the first line.
    if ($context['sandbox']['progress'] != 0 && reset($view->getStyle()->options['formats']) == 'csv') {
      $string = preg_replace('/^[^\n]+/', '', $string);
    }

    // Workaround for XML.
    $output_format = reset($view->getStyle()->options['formats']);
    if ($output_format == 'xml') {
      $maximum = $export_limit ? $export_limit : $total_rows;
      // Remove xml declaration and response opening tag.
      if ($context['sandbox']['progress'] != 0) {
        $string = str_replace('<?xml version="1.0"?>', '', $string);
        $string = str_replace('<response>', '', $string);
      }
      // Remove response closing tag.
      if ($context['sandbox']['progress'] + $items_this_batch < $maximum) {
        $string = str_replace('</response>', '', $string);
      }
    }

    // Workaround for XLS/XLSX.
    if ($context['sandbox']['progress'] != 0 && ($output_format == 'xls' || $output_format == 'xlsx')) {
      $vdeFileRealPath = \Drupal::service('file_system')->realpath($context['sandbox']['vde_file']);
      $previousExcel = IOFactory::load($vdeFileRealPath);
      file_put_contents($vdeFileRealPath, $string);
      $currentExcel = IOFactory::load($vdeFileRealPath);

      // Append all rows to previous created excel.
      $rowIndex = $previousExcel->getActiveSheet()->getHighestRow();
      foreach ($currentExcel->getActiveSheet()->getRowIterator() as $row) {
        if ($row->getRowIndex() == 1) {
          // Skip header.
          continue;
        }
        $rowIndex++;
        $colIndex = 0;
        foreach ($row->getCellIterator() as $cell) {
          $previousExcel->getActiveSheet()->setCellValueByColumnAndRow(++$colIndex, $rowIndex, $cell->getValue());
        }
      }

      $objWriter = new Xlsx($previousExcel);
      $objWriter->save($vdeFileRealPath);
    }
    // Write rendered rows to output file.
    elseif (file_put_contents($context['sandbox']['vde_file'], $string, FILE_APPEND) === FALSE) {
      // Write to output file failed - log in logger and in ResponseText on
      // batch execution page user will end up on if write to file fails.
      $message = t('Could not write to temporary output file for result export (@file). Check permissions.', ['@file' => $context['sandbox']['vde_file']]);
      \Drupal::logger('views_data_export')->error($message);
      throw new ServiceUnavailableHttpException(NULL, $message);
    }

    // Update the progress of our batch export operation (i.e. number of
    // items we've processed). Note can exceed the number of total rows we're
    // processing, but that's considered in the if/else to determine when we're
    // finished below.
    $context['sandbox']['progress'] += $items_this_batch;

    // If our progress is less than the total number of items we expect to
    // process, we updated the "finished" variable to show the user how much
    // progress we've made via the progress bar.
    if ($context['sandbox']['progress'] < $total_rows) {
      $context['finished'] = $context['sandbox']['progress'] / $total_rows;
    }
    else {
      // We're finished processing, set progress bar to 100%.
      $context['finished'] = 1;
    }
  }

  /**
   * Implements callback for batch finish.
   *
   * @param bool $success
   *   Indicates whether we hit a fatal PHP error.
   * @param array $results
   *   Contains batch results.
   * @param array $operations
   *   If $success is FALSE, contains the operations that remained unprocessed.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   Where to redirect when batching ended.
   */
  public static function finishBatch($success, array $results, array $operations) {
    // Set Drupal status message to let the user know the results of the export.
    // The 'success' parameter means no fatal PHP errors were detected.
    // All other error management should be handled using 'results'.
    $response = new RedirectResponse($results['redirect_url']);
    if ($success && isset($results['vde_file']) && file_exists($results['vde_file'])) {
      // Check the permissions of the file to grant access and allow
      // modules to hook into permissions via hook_file_download().
      $headers = \Drupal::moduleHandler()->invokeAll('file_download', [$results['vde_file']]);

      // Require at least one module granting access and none denying access.
      if (!empty($headers) && !in_array(-1, $headers)) {
        // Create a web server accessible URL for the private file.
        // Permissions for accessing this URL will be inherited from the View
        // display's configuration.
        $url = \Drupal::service('file_url_generator')->generateAbsoluteString($results['vde_file']);
        $message = t('Export complete. Download the file <a download href=":download_url"  data-download-enabled="false" id="vde-automatic-download">here</a>.', [':download_url' => $url]);
        // If the user specified instant download than redirect to the file.
        if ($results['automatic_download']) {
          // Prevents browser from displaying JSON data if automatic download
          // is selected.
          if (!preg_match("/^.*\.(json)$/i", $results['vde_file'])) {
            $message = t('Export complete. Download the file <a download href=":download_url" data-download-enabled="true" id="vde-automatic-download">here</a> if file is not automatically downloaded.', [':download_url' => $url]);
          }
        }

        \Drupal::messenger()->addMessage($message);
      }
      return $response;
    }
    else {
      $message = t('Export failed. Make sure the private file system is configured and check the error log.');
      \Drupal::messenger()->addError($message);
      return $response;
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function getRoute($view_id, $display_id) {
    $route = parent::getRoute($view_id, $display_id);
    $view = Views::getView($view_id);
    $view->setDisplay($display_id);

    // If this display is going to perform a redirect to the batch url
    // make sure thr redirect response is never cached.
    if ($view->display_handler->getOption('export_method') == 'batch') {
      $route->setOption('no_cache', TRUE);
    }
    return $route;
  }

}

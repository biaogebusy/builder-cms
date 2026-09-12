<?php

namespace Drupal\xls_serialization\Plugin\views\display;

use Drupal\Core\Form\FormStateInterface;
use Drupal\rest\Plugin\views\display\RestExport;

/**
 * Provides an Excel export display plugin.
 *
 * This overrides the REST Export display to make labeling clearer on the admin
 * UI, and add specific Excel-related functionality.
 *
 * @ingroup views_display_plugins
 *
 * @ViewsDisplay(
 *   id = "excel_export",
 *   title = @Translation("Excel export"),
 *   help = @Translation("Export the view results to an Excel file."),
 *   uses_route = TRUE,
 *   admin = @Translation("Excel export"),
 *   returns_response = TRUE
 * )
 */
class ExcelExport extends RestExport {

  use ExcelExportDisplayTrait;

  /**
   * Overrides the content type of the data response, if needed.
   *
   * @var string
   */
  protected $contentType = 'xlsx';

  /**
   * {@inheritdoc}
   */
  public function render() {
    // Add the content disposition header if a custom filename has been used.
    if (($response = $this->view->getResponse()) && $this->getOption('filename')) {
      $response->headers->set('Content-Disposition', 'attachment; filename="' . $this->generateFilename($this->getOption('filename')) . '"');
    }

    return parent::render();
  }

  /**
   * Given a filename and a view, generate a filename.
   *
   * @param string $filename_pattern
   *   The filename, which may contain replacement tokens.
   *
   * @return string
   *   The filename with any tokens replaced.
   */
  protected function generateFilename($filename_pattern) {
    return $this->globalTokenReplace($filename_pattern);
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);

    switch ($form_state->get('section')) {
      case 'style':
        $this->buildStyleSectionForm($form, $form_state);
        break;

      case 'path':
        $this->buildPathSectionForm($form, $form_state);
        break;

      case 'format_header':
        $this->buildFormatHeaderSectionForm($form, $form_state);
        break;
    }

    $this->buildConditionalFormattingRulesForm($form, $form_state);
  }

}

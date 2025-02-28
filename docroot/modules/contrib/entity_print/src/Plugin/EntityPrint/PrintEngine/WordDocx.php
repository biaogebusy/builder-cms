<?php

namespace Drupal\entity_print\Plugin\EntityPrint\PrintEngine;

use Drupal\entity_print\EntityPrintPHPWord;
use Drupal\entity_print\Plugin\ExportTypeInterface;
use Drupal\entity_print\Plugin\PrintEngineBase;
use Drupal\entity_print\PrintEngineException;
use PhpOffice\PhpWord\Shared\Html;

/**
 * @PrintEngine(
 *   id = "word_docx",
 *   label = @Translation("Word Docx"),
 *   export_type = "word_docx"
 * )
 *
 * To use this implementation you will need the PHPWord library, simply run:
 *
 * @code
 *     composer require "phpoffice/phpword v0.12.*"
 * @endcode
 */
class WordDocx extends PrintEngineBase
{

  /**
   * @var \PhpOffice\PhpWord\PhpWord
   */
  protected $print;

  /**
   * @var \PhpOffice\PhpWord\Element\Section;
   */
  protected $section;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, ExportTypeInterface $export_type)
  {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $export_type);
    $this->print = new EntityPrintPHPWord();
    $this->section = $this->print->addSection();
  }

  /**
   * {@inheritdoc}
   */
  public static function getInstallationInstructions()
  {
    return t('Please install with: @command', ['@command' => 'composer require "phpoffice/phpword v0.12.*"']);
  }

  /**
   * {@inheritdoc}
   */
  public function addPage($content)
  {
    // @TODO, this only supports adding one page?
    // PHPWord library is exporting only
    preg_match('/<body>(.*?)<\/body>/s', $content, $matches);
    Html::addHtml($this->section, $matches[1], FALSE);
  }

  /**
   * {@inheritdoc}
   */
  public function send($filename, $force_download = TRUE)
  {
    try {
      $this->print->save($filename ?: 'tmp-file', 'Word2007', (bool)$filename);
    } catch (\Exception $e) {
      throw new PrintEngineException($e->getMessage());
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function dependenciesAvailable()
  {
    return class_exists('PhpOffice\PhpWord\PhpWord') && !drupal_valid_test_ua();
  }

  /**
   *
   * {@inheritdoc}
   */
  public function getPrintObject(){
    $this->print;
  }

  /**
   * {@inheritdoc}
   * @throws
   */
  public function getBlob() {
    try {
      return $this->print->getBlob();
    } catch (\Exception $e) {
      throw new PrintEngineException($e->getMessage());
    }
  }

}

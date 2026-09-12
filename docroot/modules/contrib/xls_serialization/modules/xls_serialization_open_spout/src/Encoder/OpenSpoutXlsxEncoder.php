<?php

namespace Drupal\xls_serialization_open_spout\Encoder;

use Drupal\Component\Serialization\Exception\InvalidDataTypeException;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\xls_serialization\Encoder\Xlsx;
use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Cell\StringCell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;

/**
 * Basic encoder using the OpenSpout library.
 */
class OpenSpoutXlsxEncoder extends Xlsx {

  /**
   * Construct a new OpenSpoutXlsxEncoder.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The configuration factory service.
   * @param \Drupal\Core\File\FileSystemInterface $fileSystem
   *   Drupal file system service.
   */
  public function __construct(ConfigFactoryInterface $config_factory, protected FileSystemInterface $fileSystem) {
    parent::__construct($config_factory);
  }

  /**
   * {@inheritdoc}
   */
  public function encode($data, $format, array $context = []): string {
    switch (gettype($data)) {
      case 'array':
        // Nothing to do.
        break;

      case 'object':
        $data = (array) $data;
        break;

      default:
        $data = [$data];
        break;
    }

    try {
      $writer = new Writer();

      // Use a temp file to write the output to.
      $tempName = $this->fileSystem->tempnam('temporary://', 'xls_serialization');
      $tempFilepath = $this->fileSystem->realpath($tempName);
      $writer->openToFile($tempFilepath);

      // No settings are currently used by this encoder, but extracting the
      // settings from the context here will future-proof for #3108301 which
      // will apply for this encoder.
      if (isset($context['views_style_plugin']->options['xls_settings'])) {
        $this->setSettings($context['views_style_plugin']->options['xls_settings']);
      }

      // Set headers.
      $headers = $this->extractHeaders($data, $context);
      $headers = array_map([$this, 'formatValue'], $headers);
      $writer->addRow(Row::fromValues($headers));

      // Set data.
      foreach ($data as $row) {
        $cells = array_map(function ($value) {
          $formattedValue = $this->formatValue($value);

          // @see https://www.drupal.org/project/xls_serialization/issues/3268139
          if (is_string($formattedValue) && strlen($formattedValue) > 1 && $formattedValue[0] === '=') {
            return StringCell::fromValue($formattedValue);
          }

          return Cell::fromValue($formattedValue);
        }, (array) $row);

        $writer->addRow(new Row($cells));
      }

      $writer->close();
      $content = file_get_contents($tempFilepath);
      $this->fileSystem->unlink($tempFilepath);

      return $content;
    }
    catch (\Exception $e) {
      throw new InvalidDataTypeException($e->getMessage(), $e->getCode(), $e);
    }
  }

}

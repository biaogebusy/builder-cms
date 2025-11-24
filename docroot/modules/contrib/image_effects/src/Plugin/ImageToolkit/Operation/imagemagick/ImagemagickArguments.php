<?php

declare(strict_types=1);

namespace Drupal\image_effects\Plugin\ImageToolkit\Operation\imagemagick;

use Drupal\Core\ImageToolkit\Attribute\ImageToolkitOperation;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\image_effects\Plugin\ImageToolkit\Operation\ImagemagickArgumentsTrait;
use Drupal\imagemagick\Plugin\ImageToolkit\Operation\imagemagick\ImagemagickImageToolkitOperationBase;

/**
 * Defines ImageMagick 'Imagemagick arguments' operation.
 */
#[ImageToolkitOperation(
  id: 'image_effects_imagemagick_imagemagick_arguments',
  toolkit: 'imagemagick',
  operation: 'imagemagick_arguments',
  label: new TranslatableMarkup('ImageMagick arguments'),
  description: new TranslatableMarkup('Directly execute ImageMagick command line arguments.'),
)]
class ImagemagickArguments extends ImagemagickImageToolkitOperationBase {

  use ImagemagickArgumentsTrait;

  /**
   * {@inheritdoc}
   */
  protected function execute(array $arguments) {
    // Split the string in multiple space-separated tokens. Quotes, both " and
    // ', can delimit tokens with spaces inside. Such tokens can contain
    // escaped quotes too.
    // @see https://stackoverflow.com/questions/366202/regex-for-splitting-a-string-using-space-when-not-surrounded-by-single-or-double
    // @see https://stackoverflow.com/questions/6525556/regular-expression-to-match-escaped-characters-quotes
    $re = '/[^\s"\']+|"([^"\\\\]*(?:\\\\.[^"\\\\]*)*)"|\'([^\'\\\\]*(?:\\\\.[^\'\\\\]*)*)\'/m';
    preg_match_all($re, $arguments['command_line'], $tokens, PREG_SET_ORDER);
    $args = [];
    foreach ($tokens as $token) {
      // The escape character needs to be removed, Symfony Process will
      // escape the quote character again.
      $args[] = str_replace("\\", "", end($token));
    }
    $this->addArguments($args);

    // Set dimensions.
    $this->getToolkit()
      ->setWidth($arguments['width'])
      ->setHeight($arguments['height']);

    return TRUE;
  }

}

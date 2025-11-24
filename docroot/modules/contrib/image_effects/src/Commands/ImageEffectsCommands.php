<?php

declare(strict_types=1);

namespace Drupal\image_effects\Commands;

use Consolidation\AnnotatedCommand\AnnotationData;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\image_effects\ImageEffectsConverter;
use Drush\Commands\DrushCommands;
use Drush\Utils\StringUtils;
use Symfony\Component\Console\Input\InputInterface;

/**
 * A Drush command class for image effects conversions.
 */
class ImageEffectsCommands extends DrushCommands {

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly ImageEffectsConverter $imageEffectsConverter,
  ) {
  }

// phpcs:disable
  /**
   * Converts image styles having core rotate effects to image effects' rotate.
   *
   * @command image-effects:convert-rotate
   * @usage drush image-effects:convert-rotate
   *   Pick an image style and convert the 'rotate' image effects to the one provided by 'image_effects' module.
   * @usage drush image-effects:convert-rotate --revert
   *   Viceversa, convert the image_effects module provided effects back to Drupal core effects.
   * @usage drush image-effects:convert-rotate foo,bar
   *   Convert 'foo' and 'bar' image styles only.
   *
   * @param $style_names A comma delimited list of image style machine names. If not provided, user may choose from a list of names.
   *
   * @option name-contains Only image styles with a machine name containing this value will be selected.
   * @option label-contains Only image styles with a name containing this value will be selected.
   * @option revert Revert conversion, image_effects rotate -> core image rotate
   *
   * @validate-module-enabled image
   * @validate-module-enabled image_effects
   */
  public function convertRotate(string $style_names, array $options = ['name-contains' => NULL, 'label-contains' => NULL, 'revert' => FALSE]): void {
// phpcs:enable
    $image_style_names = $this->getImageStylesContainingRotateEffect($options['name-contains'], $options['label-contains'], $options['revert']);

    foreach ($this->entityTypeManager->getStorage('image_style')->loadMultiple(StringUtils::csvToArray($style_names)) as $style_name => $style) {
      if (!in_array($style_name, $image_style_names)) {
        continue;
      }
      if (!$options['revert']) {
        $success = $this->imageEffectsConverter->coreRotate2ie($style);
      }
      else {
        $success = $this->imageEffectsConverter->ieRotate2core($style);
      }
      if ($success) {
        $this->logger()->success(dt('Image style !style_name converted.', ['!style_name' => $style_name]));
      }
      else {
        $this->logger()->error(dt('Image style !style_name conversion failed.', ['!style_name' => $style_name]));
      }
    }
  }

  /**
   * Manage interaction for a convert-rotate command.
   *
   * @hook interact image-effects:convert-rotate
   */
  public function interactConvertRotate(InputInterface $input): void {
    $image_style_names = [];
    $choices = ['all' => 'all'];
    foreach (StringUtils::csvToArray($input->getArgument('style_names')) as $style_name) {
      $style = $this->entityTypeManager->getStorage('image_style')->load($style_name);
      $choices[$style_name] = $style->label() . ' (' . $style_name . ')';
      $image_style_names[] = $style_name;
    }

    $style_names = $this->io()->choice(dt("Choose an image style to convert"), $choices, 'all');
    if ($style_names == 'all') {
      $style_names = implode(',', $image_style_names);
    }

    $input->setArgument('style_names', $style_names);
  }

  /**
   * Initializes a convert-rotate command.
   *
   * @hook init image-effects:convert-rotate
   */
  public function initConvertRotate(InputInterface $input, AnnotationData $annotationData): void {
    // Needed for non-interactive calls.
    if (!$input->getArgument('style_names')) {
      $image_style_names = $this->getImageStylesContainingRotateEffect($input->getOption('name-contains'), $input->getOption('label-contains'), $input->getOption('revert'));
      $input->setArgument('style_names', implode(",", $image_style_names));
    }
  }

  /**
   * Returns a list of ImageStyle entity names containing a rotate effect.
   *
   * @param string|null $name_contains
   *   When not null, the query will be limited to ImageStyle entities whose
   *   machine name contain the value indicated.
   * @param string|null $label_contains
   *   When not null, the query will be limited to ImageStyle entities whose
   *   label contain the value indicated.
   * @param bool $revert
   *   (Optional) When TRUE, select image styles containing rotate effects
   *   provided by Image Effects, otherwise those provided by Drupal core.
   *   Defaults to FALSE.
   *
   * @return string[]
   *   The list of machine names of ImageStyles containing the rotate effects.
   */
  private function getImageStylesContainingRotateEffect(?string $name_contains, ?string $label_contains, bool $revert = FALSE): array {
    $query = $this->getImageStyleQuery($name_contains, $label_contains);
    $query->condition('effects.*.id', $revert ? 'image_effects_rotate' : 'image_rotate');
    return array_keys($query->execute());
  }

  /**
   * Returns an entity query for ImageStyle entities.
   *
   * @param string|null $name_contains
   *   When not null, the query will be limited to ImageStyle entities whose
   *   machine name contain the value indicated.
   * @param string|null $label_contains
   *   When not null, the query will be limited to ImageStyle entities whose
   *   label contain the value indicated.
   *
   * @return \Drupal\Core\Entity\Query\QueryInterface
   *   The entity query.
   */
  private function getImageStyleQuery(?string $name_contains, ?string $label_contains): QueryInterface {
    $query = $this->entityTypeManager->getStorage('image_style')->getQuery();
    if ($name_contains !== NULL) {
      $query->condition('name', $name_contains, 'CONTAINS');
    }
    if ($label_contains !== NULL) {
      $query->condition('label', $label_contains, 'CONTAINS');
    }
    return $query;
  }

}

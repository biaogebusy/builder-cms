<?php

declare(strict_types=1);

namespace Drupal\tour\Plugin\ConfigAction;

use Drupal\Core\Config\Action\Attribute\ConfigAction;
use Drupal\Core\Config\Action\ConfigActionException;
use Drupal\Core\Config\Action\ConfigActionPluginInterface;
use Drupal\Core\Config\ConfigManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\tour\Entity\Tour;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Adds a tour tip to an existing tour.
 *
 * An example of using this in a recipe's config actions would be:
 *
 * @code
 *   tour.tour.tip_edit:
 *     addTipToTour:
 *       -
 *         id: tip_recipe_example
 *         plugin: text
 *         label: 'My new Tip!'
 *         weight: 1
 *         position: top
 *         selector: '#main-content'
 *         body: 'Hello World!!'
 *         fail_if_exists: TRUE
 *       -
 *         id: another_tip_recipe_example
 *         plugin: text
 *         label: 'My second Tip!'
 *         weight: 2
 *         position: top
 *         selector: '#main-content'
 *         body: 'Second tip!'
 *         fail_if_exists: FALSE
 * @endcode
 *
 * This will add a Tour Tip to an existing Tour.
 * The 'tip_values' defines the actual Tip we are adding.
 *
 * @internal
 *   This API is experimental.
 */
#[ConfigAction(
  id: 'addTipToTour',
  admin_label: new TranslatableMarkup('Add Tip to Tour'),
)]
final class AddTipToTour implements ConfigActionPluginInterface, ContainerFactoryPluginInterface {

  public function __construct(
    private readonly ConfigManagerInterface $configManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $container->get(ConfigManagerInterface::class),
    );

  }

  /**
   * {@inheritdoc}
   */
  public function apply(string $configName, mixed $value): void {
    assert(is_array($value));
    $tour = $this->configManager->loadConfigEntityByName($configName);
    if (!$tour instanceof Tour) {
      throw new ConfigActionException("No tour found to apply tip to.");
    }

    $new_tips = [];
    $tips = $tour->getTips();
    foreach ($value as $new_tip_values) {
      assert(is_array($new_tip_values));
      $new_tip_id = $new_tip_values['id'];
      $fail_if_exists = $new_tip_values['fail_if_exists'] ?? FALSE;

      if (isset($tips[$new_tip_id]) && $fail_if_exists === TRUE) {
        throw new ConfigActionException("Tour {$tour->id()} already has a tip with the ID $new_tip_id.");
      }
      unset($new_tip_values['fail_if_exists']);
      // Add our tip and save.
      $new_tips[$new_tip_values['id']] = $new_tip_values;
    }

    foreach ($tips as $tip) {
      $new_tips[$tip->id()] = $tip->getConfiguration();
    }

    $tour->set('tips', $new_tips)->save();
  }

}

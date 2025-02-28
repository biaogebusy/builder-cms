<?php

namespace Drupal\migrate_plus\Plugin\migrate\process;

use Drupal\Component\Transliteration\TransliterationInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Transliterates text from Unicode to US-ASCII.
 *
 * The transliteration process plugin takes the source value and runs it through
 * the transliteration service. Letters will have language decorations and
 * accents removed.
 *
 * Example:
 *
 * @code
 * process:
 *   bar:
 *     plugin: transliteration
 *     source: foo
 * @endcode
 *
 * If the value of foo in the source is 'áéí!' then the destination value of
 * bar will be 'aei!'.
 *
 * @see \Drupal\migrate\Plugin\MigrateProcessInterface
 *
 * @MigrateProcessPlugin(
 *   id = "transliteration"
 * )
 *
 * @deprecated in migrate_plus:8.x-5.3 and is removed from migrate_plus:6.0.0.
 * Use 'service' process plugin instead. See https://www.drupal.org/node/3255994
 *
 * @see https://www.drupal.org/node/3255994
 */
class Transliteration extends ProcessPluginBase implements ContainerFactoryPluginInterface {

  /**
   * The transliteration service.
   *
   * @var \Drupal\Component\Transliteration\TransliterationInterface
   */
  protected $transliteration;

  /**
   * Constructs a Transliteration plugin.
   *
   * @param array $configuration
   *   The plugin configuration.
   * @param string $plugin_id
   *   The plugin ID.
   * @param mixed $plugin_definition
   *   The plugin definition.
   * @param \Drupal\Component\Transliteration\TransliterationInterface $transliteration
   *   The transliteration service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, TransliterationInterface $transliteration) {
    @trigger_error(__CLASS__ . ' is deprecated in migrate_plus:8.x-5.3 and is removed from migrate_plus:6.0.0. Use Drupal\migrate_plus\Plugin\migrate\process\Service process plugin instead. See https://www.drupal.org/node/3255994', E_USER_DEPRECATED);
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->transliteration = $transliteration;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('transliteration')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    return $this->transliteration->transliterate($value, LanguageInterface::LANGCODE_DEFAULT, '_');
  }

}

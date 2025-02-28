<?php

namespace Drupal\bootstrap_styles\Style;

use Drupal\Component\Plugin\PluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Render\Markup;

/**
 * A base class to help developers implement their own Styles Group plugins.
 */
abstract class StylePluginBase extends PluginBase implements StylePluginInterface {
  use StringTranslationTrait;

  /**
   * Config settings.
   *
   * @var string
   */
  const CONFIG = 'bootstrap_styles.settings';

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Constructs a StylePluginBase object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin ID for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The configuration factory.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, ConfigFactoryInterface $config_factory) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->configFactory = $config_factory;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('config.factory')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getTitle() {
    return $this->pluginDefinition['title'];
  }

  /**
   * {@inheritdoc}
   */
  public function config() {
    return $this->configFactory->getEditable(static::CONFIG);
  }

  /**
   * Helper function to get the options of given style name.
   *
   * @param string $name
   *   A config style name like background_color.
   *
   * @return array
   *   Array of key => value of style name options.
   */
  public function getStyleOptions(string $name) {
    $config = $this->config();
    $options = [];
    $config_options = $config->get($name);

    $options = ['_none' => $this->t('N/A')];
    $lines = explode(PHP_EOL, $config_options ?? '');
    foreach ($lines as $line) {

      $line = explode('|', $line ?? '');
      if ($line && isset($line[0]) && isset($line[1])) {
        $options[$line[0]] = $line[1] . '<div class="bs_tooltip" data-placement="top" role="tooltip">' . $line[1] . '</div>';
      }
    }

    return $options;
  }

  /**
   * Helper function to get SVG Markup.
   *
   * @param string $path
   *   The absolute path to the SVG icon.
   *
   * @return string
   *   The icon markup.
   */
  public function getSvgIconMarkup(string $path) {
    $svg = file_get_contents(DRUPAL_ROOT . '/' . $path);
    $svg = preg_replace(['/<\?xml.*\?>/i', '/<!DOCTYPE((.|\n|\r)*?)">/i'], '', $svg);
    $svg = trim($svg);
    return Markup::create($svg);
  }

  /**
   * Helper function to get the class from the options list.
   *
   * @param string $name
   *   A config style name like background_color.
   * @param int $index
   *   The index of the class at the option list.
   *
   * @return string
   *   The class name or null.
   */
  public function getStyleOptionClassByIndex(string $name, int $index) {
    $class = '';
    $options = $this->getStyleOptions($name);
    $count = 0;
    foreach ($options as $key => $value) {
      if ($count == $index) {
        $class = $key;
        break;
      }
      $count++;
    }

    return $class;
  }

  /**
   * Helper function to get the index of the class at options list.
   *
   * @param string $name
   *   A config style name like background_color.
   * @param string $class
   *   The class name.
   *
   * @return int
   *   The index.
   */
  public function getStyleOptionIndexByClass(string $name, string $class) {
    $index = 0;
    $options = $this->getStyleOptions($name);
    $count = 0;
    foreach ($options as $key => $value) {
      if ($key == $class) {
        $index = $count;
        break;
      }
      $count++;
    }

    return $index;
  }

  /**
   * Helper function to get the options of given style name.
   *
   * @param string $name
   *   A config style name like background_color.
   *
   * @return array
   *   Array of key => value of style name options.
   */
  public function getStyleOptionsCount(string $name) {
    // -1 to drop the _none option from the count.
    $count = count($this->getStyleOptions($name)) - 1;
    return $count;
  }

  /**
   * Helper function to add the classes to the build.
   *
   * @param array $build
   *   The build array.
   * @param array $classes
   *   Array of the classes that we need to assign to build.
   * @param string $theme_wrapper
   *   The theme wrapper if exists.
   *
   * @return array
   *   The build array.
   */
  public function addClassesToBuild(array $build, array $classes, $theme_wrapper = NULL) {
    // Assign the style to element or its theme wrapper if exist.
    if ($theme_wrapper && isset($build['#theme_wrappers'][$theme_wrapper])) {
      $build['#theme_wrappers'][$theme_wrapper]['#attributes']['class'] = array_merge($build['#theme_wrappers'][$theme_wrapper]['#attributes']['class'], $classes);
    }
    elseif (isset($build['#attributes']['class'])) {
      $build['#attributes']['class'] = array_merge($build['#attributes']['class'], $classes);
    }
    else {
      $build['#attributes']['class'] = $classes;
    }
    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
  }

  /**
   * {@inheritdoc}
   */
  public function buildStyleFormElements(array &$form, FormStateInterface $form_state, $storage) {
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitStyleFormElements(array $group_elements) {
  }

  /**
   * {@inheritdoc}
   */
  public function build(array $build, array $storage, $theme_wrapper = NULL) {
    return $build;
  }

}

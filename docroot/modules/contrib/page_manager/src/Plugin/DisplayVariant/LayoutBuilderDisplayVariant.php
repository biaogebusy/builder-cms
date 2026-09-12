<?php

namespace Drupal\page_manager\Plugin\DisplayVariant;

use Drupal\Core\Display\ContextAwareVariantInterface;
use Drupal\Core\Display\VariantBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Layout\LayoutPluginManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\ctools\Plugin\PluginWizardInterface;
use Drupal\layout_builder\LayoutEntityHelperTrait;
use Drupal\layout_builder\SectionListTrait;
use Drupal\page_manager\Form\LayoutBuilderForm;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a Layout Builder variant.
 *
 * @DisplayVariant(
 *   id = "layout_builder",
 *   admin_label = @Translation("Layout Builder")
 * )
 */
class LayoutBuilderDisplayVariant extends VariantBase implements PluginWizardInterface, ContextAwareVariantInterface, ContainerFactoryPluginInterface {

  use SectionListTrait;
  use LayoutEntityHelperTrait;

  /**
   * An array of collected contexts.
   *
   * This is only used on runtime, and is not stored.
   *
   * @var \Drupal\Component\Plugin\Context\ContextInterface[]
   */
  protected $contexts = [];

  /**
   * The layout plugin instance, lazily created by ::getLayout().
   *
   * @var \Drupal\Core\Layout\LayoutInterface|null
   */
  protected $layout;

  /**
   * The layout plugin manager.
   *
   * @var \Drupal\Core\Layout\LayoutPluginManagerInterface|null
   */
  protected $layoutManager;

  /**
   * Constructs a new LayoutBuilderDisplayVariant.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin ID for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Layout\LayoutPluginManagerInterface|null $layout_manager
   *   (optional) The layout plugin manager. Defaults to the manager from the
   *   service container when not provided.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, ?LayoutPluginManagerInterface $layout_manager = NULL) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->layoutManager = $layout_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('plugin.manager.core.layout')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $build = [];
    $contexts = $this->getContexts();
    foreach ($this->getSections() as $delta => $section) {
      $build[$delta] = $section->toRenderArray($contexts);
    }
    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return parent::defaultConfiguration() + [
      'sections' => [],
    ];
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
  public function getWizardOperations($cached_values) {
    $operations = [];
    $operations['layout_builder'] = [
      'title' => $this->t('Layout'),
      'form' => LayoutBuilderForm::class,
    ];

    return $operations;
  }

  /**
   * {@inheritdoc}
   */
  protected function setSections(array $sections) {
    $this->configuration['sections'] = array_values($sections);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getSections() {
    if (!isset($this->configuration['sections'])) {
      $this->configuration['sections'] = [];
    }
    return $this->configuration['sections'];
  }

  /**
   * Returns instance of the layout plugin used by this page variant.
   *
   * @return \Drupal\Core\Layout\LayoutInterface
   *   A layout plugin instance.
   */
  public function getLayout() {
    if (!isset($this->layout)) {
      $this->layout = $this->getLayoutManager()->createInstance($this->configuration['layout'], $this->configuration['layout_settings']);
    }
    return $this->layout;
  }

  /**
   * Returns the layout plugin manager.
   *
   * @return \Drupal\Core\Layout\LayoutPluginManagerInterface
   *   The layout plugin manager.
   */
  protected function getLayoutManager() {
    if (!isset($this->layoutManager)) {
      // @phpstan-ignore-next-line
      $this->layoutManager = \Drupal::service('plugin.manager.core.layout');
    }
    return $this->layoutManager;
  }

  /**
   * {@inheritdoc}
   */
  public function getContexts() {
    return $this->contexts;
  }

  /**
   * {@inheritdoc}
   */
  public function setContexts(array $contexts) {
    $this->contexts = $contexts;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies() {
    $configuration = $this->getConfiguration();
    foreach ($configuration['sections'] as $section) {
      $this->calculatePluginDependencies($section->getLayout());
      foreach ($section->getComponents() as $component) {
        $this->calculatePluginDependencies($component->getPlugin());
      }
    }

    return parent::calculateDependencies();
  }

}

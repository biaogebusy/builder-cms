<?php

namespace Drupal\page_manager\Plugin\DisplayVariant;

use Drupal\Core\Display\ContextAwareVariantInterface;
use Drupal\Core\Display\VariantBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\ctools\Plugin\PluginWizardInterface;
use Drupal\layout_builder\LayoutEntityHelperTrait;
use Drupal\layout_builder\SectionListTrait;
use Drupal\page_manager\Form\LayoutBuilderForm;

/**
 * Provides a Layout Builder variant.
 *
 * @DisplayVariant(
 *   id = "layout_builder",
 *   admin_label = @Translation("Layout Builder")
 * )
 */
class LayoutBuilderDisplayVariant extends VariantBase implements PluginWizardInterface, ContextAwareVariantInterface {

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
      $this->layout = $this->layoutManager->createInstance($this->configuration['layout'], $this->configuration['layout_settings']);
    }
    return $this->layout;
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

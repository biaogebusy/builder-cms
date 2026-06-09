<?php

declare(strict_types=1);

namespace Drupal\ai_api_explorer\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\ai_api_explorer\AiApiExplorerPluginManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base form for all AI API Explorer forms.
 */
class AiApiExplorerForm extends FormBase implements AiApiExplorerFormInterface {

  /**
   * The AI Explorer Plugin Manager.
   *
   * @var \Drupal\ai_api_explorer\AiApiExplorerPluginManager|null
   */
  protected ?AiApiExplorerPluginManager $pluginManager = NULL;

  /**
   * The current plugin being built.
   *
   * @var string|null
   */
  protected ?string $pluginId = NULL;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    $instance = parent::create($container);
    $instance->pluginManager = $container->get('plugin.manager.ai_api_explorer');

    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'ai_api_explorer_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?string $plugin_id = NULL): array {
    $definition = $this->pluginManager->getDefinition($plugin_id);

    /** @var \Drupal\ai_api_explorer\AiApiExplorerInterface $plugin */
    $plugin = $this->pluginManager->createInstance($plugin_id, $definition);
    $this->pluginId = $plugin_id;

    $form['#attached']['library'][] = 'ai_api_explorer/explorer';

    return $plugin->buildForm($form, $form_state);
  }

  /**
   * {@inheritDoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $definition = $this->pluginManager->getDefinition($this->pluginId);

    /** @var \Drupal\ai_api_explorer\AiApiExplorerInterface $plugin */
    $plugin = $this->pluginManager->createInstance($this->pluginId, $definition);
    $plugin->submitForm($form, $form_state);
  }

  /**
   * {@inheritDoc}
   */
  public function ajaxResponse(array &$form, FormStateInterface $form_state): array {
    $definition = $this->pluginManager->getDefinition($this->pluginId);

    /** @var \Drupal\ai_api_explorer\AiApiExplorerInterface $plugin */
    $plugin = $this->pluginManager->createInstance($this->pluginId, $definition);
    return $plugin->getResponse($form, $form_state);
  }

}

<?php

declare(strict_types=1);

namespace Drupal\private_message\Plugin\PrivateMessageConfigForm;

use Drupal\Component\Render\MarkupInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\PluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base class for PrivateMessageConfigForm plugins.
 */
abstract class PrivateMessageConfigFormBase extends PluginBase implements PrivateMessageConfigFormPluginInterface {

  public function __construct(
    array $configuration,
    string $plugin_id,
    mixed $plugin_definition,
    protected ConfigFactoryInterface $configFactory,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): self {
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
  public function getName(): MarkupInterface|string {
    @trigger_error(__METHOD__ . '() is deprecated in private_message:4.0.0 and is removed from private_message:5.0.0. Instead, you should just use the plugin definition "name" value $plugin->getPluginDefinition()["name"]. See https://www.drupal.org/node/3501696', E_USER_DEPRECATED);
    return $this->pluginDefinition['name'];
  }

  /**
   * {@inheritdoc}
   */
  public function getId(): string {
    @trigger_error(__METHOD__ . '() is deprecated in private_message:4.0.0 and is removed from private_message:5.0.0. Instead, you should just use the plugin ::getPluginId() method. See https://www.drupal.org/node/3501696', E_USER_DEPRECATED);
    return $this->getPluginId();
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $formState): void {
  }

}

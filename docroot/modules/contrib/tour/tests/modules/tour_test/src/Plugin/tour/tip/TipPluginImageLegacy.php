<?php

namespace Drupal\tour_test\Plugin\tour\tip;

use Drupal\Component\Utility\Html;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Utility\Token;
use Drupal\tour\Attribute\Tip;
use Drupal\tour\TipPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Displays an image as a tip.
 */
#[Tip(
  id: 'image_legacy',
  title: new TranslatableMarkup('Image Legacy'),
)]
class TipPluginImageLegacy extends TipPluginBase implements ContainerFactoryPluginInterface {

  /**
   * The URL which is used for the image in this Tip.
   *
   * @var string
   *   A URL used for the image.
   */
  protected string $url;

  /**
   * The alt text which is used for the image in this Tip.
   *
   * @var string
   *   An alt text used for the image.
   */
  protected string $alt;

  /**
   * Constructs a TipPluginImageLegacy object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Utility\Token $token
   *   The token service.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, protected Token $token, protected RendererInterface $renderer) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('token'),
      $container->get('renderer'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getConfigurationOrNot(): array {
    $image = [
      '#theme' => 'image',
      '#uri' => $this->get('url'),
      '#alt' => $this->get('alt'),
    ];

    return [
      'title' => Html::escape($this->get('label')),
      'body' => $this->token->replace($this->renderer->renderInIsolation($image)),
    ];
  }

}

<?php

namespace Drupal\tour\Plugin\tour\tip;

use Drupal\Component\Utility\Xss;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Utility\Token;
use Drupal\tour\Attribute\Tip;
use Drupal\tour\TipPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Displays some text as a tip.
 */
#[Tip(
  id: 'text',
  title: new TranslatableMarkup('Text'),
)]
class TipPluginText extends TipPluginBase implements ContainerFactoryPluginInterface {

  /**
   * Constructs a \Drupal\tour\Plugin\tour\tip\TipPluginText object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Utility\Token $token
   *   The token service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, protected Token $token) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static($configuration, $plugin_id, $plugin_definition, $container->get('token'));
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return parent::defaultConfiguration() + [
      'body' => '',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getBody(): array {
    return [
      '#type' => 'html_tag',
      '#tag' => 'p',
      '#value' => $this->token->replace($this->get('body')),
      '#attributes' => [
        'class' => ['tour-tip-body'],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form = parent::buildConfigurationForm($form, $form_state);
    $tags = Xss::getAdminTagList();
    $form['body'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Body'),
      '#required' => TRUE,
      '#default_value' => $this->get('body'),
      '#description' => $this->t('You could use the following tags: %s', ['%s' => implode(', ', $tags)]),
    ];

    return $form;
  }

}

<?php

namespace Drupal\commerce_cart\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Render\PlaceholderGeneratorInterface;
use Drupal\Core\Security\TrustedCallbackInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a cart block.
 */
#[Block(
  id: "commerce_cart",
  admin_label: new TranslatableMarkup('Cart'),
  category: new TranslatableMarkup('Commerce'),
)]
class CartBlock extends BlockBase implements ContainerFactoryPluginInterface, TrustedCallbackInterface {

  /**
   * The cart provider.
   *
   * @var \Drupal\commerce_cart\CartProviderInterface
   */
  protected $cartProvider;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The module extension list.
   *
   * @var \Drupal\Core\Extension\ModuleExtensionList
   */
  protected $moduleExtensionList;

  /**
   * The render placeholder generator.
   *
   * @var \Drupal\Core\Render\PlaceholderGeneratorInterface
   */
  protected PlaceholderGeneratorInterface $renderPlaceholderGenerator;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = new static($configuration, $plugin_id, $plugin_definition);
    $instance->cartProvider = $container->get('commerce_cart.cart_provider');
    $instance->entityTypeManager = $container->get('entity_type.manager');
    $instance->moduleExtensionList = $container->get('extension.list.module');
    $instance->renderPlaceholderGenerator = $container->get('render_placeholder_generator');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'dropdown' => TRUE,
      'show_if_empty' => TRUE,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {
    $form['commerce_cart_dropdown'] = [
      '#type' => 'radios',
      '#title' => $this->t('Display cart contents in a dropdown'),
      '#default_value' => (int) $this->configuration['dropdown'],
      '#options' => [
        $this->t('No'),
        $this->t('Yes'),
      ],
    ];
    $form['commerce_show_if_empty'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Display block if the cart is empty'),
      '#default_value' => (int) $this->configuration['show_if_empty'],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    $this->configuration['dropdown'] = $form_state->getValue('commerce_cart_dropdown');
    $this->configuration['show_if_empty'] = $form_state->getValue('commerce_show_if_empty');
  }

  /**
   * Builds the cart block.
   *
   * @return array
   *   A render array.
   */
  public function build(): array {
    return [
      '#pre_render' => [
        [$this, 'generatePlaceholder'],
      ],
      '#include_fallback' => FALSE,
    ];
  }

  /**
   * A #pre_render callback to generate a placeholder.
   *
   * @param array $element
   *   A render array.
   *
   * @return array
   *   The updated render array containing the placeholder.
   */
  public function generatePlaceholder(array $element): array {
    $build = [
      '#lazy_builder' => ['commerce_cart.lazy_builders:cartBlock', [$this->configuration['dropdown'], $this->configuration['show_if_empty']]],
      '#create_placeholder' => TRUE,
    ];

    // Directly create a placeholder as we need this to be placeholdered
    // regardless if this is a POST or GET request.
    // @todo remove this when https://www.drupal.org/node/2367555 lands.
    $build = $this->renderPlaceholderGenerator->createPlaceholder($build);

    if ($element['#include_fallback']) {
      return [
        'fallback' => [
          '#markup' => '<div data-drupal-cart-block-fallback class="hidden"></div>',
        ],
        'message' => $build,
      ];
    }
    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public static function trustedCallbacks(): array {
    return ['generatePlaceholder'];
  }

}

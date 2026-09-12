<?php

namespace Drupal\page_manager_ui\Form;

use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\TempStore\SharedTempStoreFactory;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a form for deleting an access condition.
 */
class VariantPluginDeleteBlockForm extends ConfirmFormBase {

  /**
   * The block variant.
   *
   * @var \Drupal\ctools\Plugin\BlockVariantInterface
   */
  protected $plugin;

  /**
   * The plugin being configured.
   *
   * @var \Drupal\Core\Block\BlockPluginInterface
   */
  protected $block;

  /**
   * The shared tempstore factory.
   *
   * @var \Drupal\Core\TempStore\SharedTempStoreFactory|null
   */
  protected $tempstoreFactory;

  /**
   * Constructs a new VariantPluginDeleteBlockForm.
   *
   * @param \Drupal\Core\TempStore\SharedTempStoreFactory|null $tempstore_factory
   *   (optional) The shared tempstore factory. Defaults to the factory from
   *   the service container when not provided.
   */
  public function __construct(?SharedTempStoreFactory $tempstore_factory = NULL) {
    $this->tempstoreFactory = $tempstore_factory;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static($container->get('tempstore.shared'));
  }

  /**
   * Get the tempstore id.
   *
   * @return string
   *   The temp store id.
   */
  protected function getTempstoreId() {
    return 'page_manager.block_display';
  }

  /**
   * Get the tempstore.
   *
   * @return \Drupal\Core\TempStore\SharedTempStore
   *   The shared temp store.
   */
  protected function getTempstore() {
    if (!isset($this->tempstoreFactory)) {
      // Fallback for subclasses that override ::create() without passing the
      // factory along.
      // @phpstan-ignore-next-line
      $this->tempstoreFactory = \Drupal::service('tempstore.shared');
    }
    return $this->tempstoreFactory->get($this->getTempstoreId());
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'page_manager_variant_delete_block_form';
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Are you sure you want to delete the block %label?', ['%label' => $this->block->label()]);
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    return $this->getRequest()->attributes->get('destination');
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText() {
    return $this->t('Delete');
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $block_display = NULL, $block_id = NULL) {
    $this->plugin = $this->getTempstore()->get($block_display)['plugin'];
    $this->block = $this->plugin->getBlock($block_id);
    $form['block_display'] = [
      '#type' => 'value',
      '#value' => $block_display,
    ];
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->plugin->removeBlock($this->block->getConfiguration()['uuid']);
    $cached_values = $this->getTempstore()->get($form_state->getValue('block_display'));
    $cached_values['plugin'] = $this->plugin;
    $this->getTempstore()->set($form_state->getValue('block_display'), $cached_values);
    $this->messenger()->addMessage($this->t('The block %label has been removed.', ['%label' => $this->block->label()]));
  }

}

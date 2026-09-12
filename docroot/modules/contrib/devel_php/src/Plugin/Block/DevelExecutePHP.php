<?php

declare(strict_types=1);

namespace Drupal\devel_php\Plugin\Block;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\devel_php\Form\ExecutePHP;

/**
 * Provides a block for executing PHP code.
 */
#[Block(
  id: 'devel_execute_php',
  admin_label: new TranslatableMarkup('Execute PHP Code')
)]
class DevelExecutePHP extends BlockBase implements ContainerFactoryPluginInterface {

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected FormBuilderInterface $formBuilder,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  protected function blockAccess(AccountInterface $account) {
    return AccessResult::allowedIfHasPermission($account, 'execute php code');
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    // @phpstan-ignore-next-line
    return $this->formBuilder->getForm(ExecutePHP::class, FALSE);
  }

}

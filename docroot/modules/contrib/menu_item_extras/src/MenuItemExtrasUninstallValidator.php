<?php

namespace Drupal\menu_item_extras;

use Drupal\Core\Database\Connection;
use Drupal\Core\Extension\ModuleUninstallValidatorInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Core\Url;

/**
 * Prevents uninstall menu item extras module if there are extra data.
 */
class MenuItemExtrasUninstallValidator implements ModuleUninstallValidatorInterface {

  use StringTranslationTrait;

  /**
   * The current database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $connection;

  /**
   * Constructs a new MenuItemExtrasUninstallValidator.
   *
   * @param \Drupal\Core\Database\Connection $connection
   *   The current database connection.
   * @param \Drupal\Core\StringTranslation\TranslationInterface $string_translation
   *   The string translation service.
   */
  public function __construct(Connection $connection, TranslationInterface $string_translation) {
    $this->connection = $connection;
    $this->stringTranslation = $string_translation;
  }

  /**
   * {@inheritdoc}
   */
  public function validate($module) {
    $reasons = [];
    if ($module === 'menu_item_extras' && $this->hasExtraData()) {
      $reasons[] = $this->t('There are extra data for menus. <a href=":url">Remove extra data</a>.', [
        ':url' => Url::fromRoute('menu_item_extras.clear_all_extra_data')->toString(),
      ]);
    }
    return $reasons;
  }

  /**
   * Determines if there is any extra data for menu or not.
   *
   * @return bool
   *   TRUE if there are extra data for menus, FALSE otherwise.
   */
  protected function hasExtraData() {
    $results = $this->connection->select('menu_link_content_data', 'mlcd')
      ->fields('mlcd', ['view_mode'])
      ->isNotNull('view_mode')
      ->execute()
      ->fetchAll();
    return !empty($results);
  }

}

<?php

namespace Drupal\commerce_order\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\commerce_order\AddressBookInterface;

/**
 * Checks address book access.
 *
 * Intended to be combined with more specific (overview / CRUD) access checks.
 *
 * Requirements key: '_address_book_access'.
 */
class AddressBookAccessCheck implements AccessInterface {

  /**
   * Constructs a new AddressBookAccessCheck object.
   *
   * @param \Drupal\commerce_order\AddressBookInterface $addressBook
   *   The address book.
   */
  public function __construct(protected AddressBookInterface $addressBook) {}

  /**
   * Checks address book access.
   *
   * Ensures that the user is logged in, and the address book UI is exposed.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The currently logged in account.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function access(AccountInterface $account) {
    if ($account->isAnonymous()) {
      // Anonymous users can't have an address book.
      return AccessResult::forbidden()->addCacheContexts(['user.roles:authenticated']);
    }
    if (!$this->addressBook->hasUi()) {
      // No UI available.
      return AccessResult::forbidden()->addCacheTags(['config:profile_type_list']);
    }

    return AccessResult::allowed();
  }

}

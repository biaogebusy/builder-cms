<?php

/**
 * @file
 * Post update functions for Tax.
 */

use Drupal\commerce_tax\Entity\TaxType;
use Drupal\commerce_tax\Plugin\Commerce\TaxType\LocalTaxTypeInterface;
use Drupal\profile\Entity\ProfileType;

/**
 * Add the tax_number field to customer profiles.
 */
function commerce_tax_post_update_1() {
  if (!\Drupal::moduleHandler()->moduleExists('commerce_order')) {
    return '';
  }
  if (!ProfileType::load('customer')) {
    // Commerce expects the "customer" profile type to always be present,
    // but some sites have still succeeded in removing it.
    return '';
  }

  /** @var \Drupal\commerce\Config\ConfigUpdaterInterface $config_updater */
  $config_updater = \Drupal::service('commerce.config_updater');
  $result = $config_updater->import([
    'field.storage.profile.tax_number',
    'field.field.profile.customer.tax_number',
  ]);
  $message = implode('<br>', $result->getFailed());

  return $message;
}

/**
 * Add the tax_number field to customer profile view displays.
 */
function commerce_tax_post_update_2() {
  // Expose the tax_number field on customer profile view displays.
  $storage = \Drupal::entityTypeManager()->getStorage('entity_view_display');
  /** @var \Drupal\Core\Entity\Display\EntityViewDisplayInterface $default_display */
  $default_display = $storage->load('profile.customer.default');
  if ($default_display) {
    $default_display->setComponent('tax_number', [
      'type' => 'commerce_tax_number_default',
      'settings' => [
        'show_verification' => FALSE,
      ],
    ]);
    $default_display->save();
  }

  /** @var \Drupal\Core\Entity\Display\EntityViewDisplayInterface $admin_display */
  $admin_display = $storage->load('profile.customer.admin');
  if ($admin_display) {
    $admin_display->setComponent('tax_number', [
      'type' => 'commerce_tax_number_default',
      'settings' => [
        'show_verification' => TRUE,
      ],
    ]);
    $admin_display->save();
  }
}

/**
 * Back-fill the "tax_registrations" for existing stores.
 */
function commerce_tax_post_update_3(&$sandbox = NULL) {
  $entity_type_manager = \Drupal::entityTypeManager();
  $store_storage = $entity_type_manager->getStorage('commerce_store');

  if (!isset($sandbox['current_count'])) {
    $tax_type_storage = $entity_type_manager->getStorage('commerce_tax_type');
    $local_tax_types = array_filter($tax_type_storage->loadMultiple(), function (TaxType $tax_type) {
      return $tax_type->getPlugin() instanceof LocalTaxTypeInterface;
    });

    // If there is no local tax type configured, no need to do anything.
    if (!$local_tax_types) {
      $sandbox['#finished'] = 1;
      return;
    }

    $query = $store_storage
      ->getQuery()
      ->accessCheck(FALSE)
      ->exists('address.country_code')
      // Update only stores which have no tax registration set.
      ->notExists('tax_registrations');
    $sandbox['total_count'] = $query->count()->execute();
    $sandbox['current_count'] = 0;

    if (empty($sandbox['total_count'])) {
      $sandbox['#finished'] = 1;
    }
  }

  $query = $store_storage->getQuery();
  $query
    ->accessCheck(FALSE)
    ->exists('address.country_code')
    ->notExists('tax_registrations')
    ->range($sandbox['current_count'], 25);
  $result = $query->execute();

  if (empty($result)) {
    $sandbox['#finished'] = 1;
    return;
  }

  /** @var \Drupal\commerce_store\Entity\StoreInterface[] $stores */
  $stores = $store_storage->loadMultiple($result);
  foreach ($stores as $store) {
    // With the previous code, if the tax registration field was empty, the
    // assumption was that the store is registered in its country.
    // See https://www.drupal.org/project/commerce/issues/3246388.
    $store->set('tax_registrations', [$store->getAddress()->getCountryCode()]);
    $store->save();
  }

  $sandbox['current_count'] += 25;
  if ($sandbox['current_count'] >= $sandbox['total_count']) {
    $sandbox['#finished'] = 1;
  }
  else {
    $sandbox['#finished'] = ($sandbox['total_count'] - $sandbox['current_count']) / $sandbox['total_count'];
  }
}

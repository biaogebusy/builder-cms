<?php

declare(strict_types = 1);

// @codingStandardsIgnoreFile
/**
 * @file
 * A database dump for testing purposes.
 *
 * Common database queries for all SMS Framework for Drupal 7 migration tests.
 */

use Drupal\Core\Database\Database;

$connection = Database::getConnection();
$connection->insert('system')
  ->fields(array(
    'filename',
    'name',
    'type',
    'owner',
    'status',
    'bootstrap',
    'schema_version',
    'weight',
  ))
  ->values(array(
    'filename' => 'modules/sms/sms.module',
    'name' => 'sms',
    'type' => 'module',
    'owner' => '',
    'status' => '1',
    'bootstrap' => '0',
    'schema_version' => '7000',
    'weight' => '0',
  ))
  ->execute();

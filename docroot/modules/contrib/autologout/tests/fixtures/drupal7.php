<?php

/**
 * @file
 * A database agnostic dump for testing purposes.
 */

use Drupal\Core\Database\Database;

$connection = Database::getConnection();

$connection->insert('variable')
  ->fields([
    'name',
    'value',
  ])
  ->values([
    'name' => 'autologout_enforce_admin',
    'value' => 'i:0;',
  ])
  ->values([
    'name' => 'autologout_inactivity_message',
    'value' => 's:43:"You have been logged out due to inactivity.";',
  ])
  ->values([
    'name' => 'autologout_max_timeout',
    'value' => 's:6:"172800";',
  ])
  ->values([
    'name' => 'autologout_message',
    'value' => 's:108:"We are about to log you out for inactivity. If we do, you will lose any unsaved work. Do you need more time?";',
  ])
  ->values([
    'name' => 'autologout_no_dialog',
    'value' => 'i:0;',
  ])
  ->values([
    'name' => 'autologout_padding',
    'value' => 's:2:"20";',
  ])
  ->values([
    'name' => 'autologout_redirect_url',
    'value' => 's:10:"user/login";',
  ])
  ->values([
    'name' => 'autologout_role_logout',
    'value' => 'i:0;',
  ])
  ->values([
    'name' => 'autologout_timeout',
    'value' => 's:4:"1800";',
  ])
  ->values([
    'name' => 'autologout_use_alt_logout_method',
    'value' => 'i:0;',
  ])
  ->values([
    'name' => 'autologout_use_watchdog',
    'value' => 'i:0;',
  ])
  ->values([
    'name' => 'autologout_whitelisted_ip_addresses',
    'value' => 'i:0;',
  ])
  ->execute();

$connection->insert('system')
  ->fields([
    'filename',
    'name',
    'type',
    'owner',
    'status',
    'bootstrap',
    'schema_version',
    'weight',
    'info',
  ])
  ->values([
    'filename' => 'sites/all/modules/autologout/autologout.module',
    'name' => 'autologout',
    'type' => 'module',
    'owner' => '',
    'status' => '1',
    'bootstrap' => '0',
    'schema_version' => '7401',
    'weight' => '0',
    'info' => 'a:13:{s:4:"name";s:16:"Automated Logout";s:11:"description";s:27:"Adds automated timed logout";s:4:"core";s:3:"7.x";s:5:"files";a:1:{i:0;s:21:"tests/autologout.test";}s:9:"configure";s:30:"admin/config/people/autologout";s:7:"version";s:7:"7.x-4.6";s:7:"project";s:10:"autologout";s:9:"datestamp";s:10:"1586345251";s:5:"mtime";i:1586345251;s:12:"dependencies";a:0:{}s:7:"package";s:5:"Other";s:3:"php";s:5:"5.3.3";s:9:"bootstrap";i:0;}',
  ])
  ->execute();

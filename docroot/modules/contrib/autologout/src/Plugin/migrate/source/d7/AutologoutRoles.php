<?php

namespace Drupal\autologout\Plugin\migrate\source\d7;

use Drupal\autologout\Plugin\migrate\source\AutologoutRoles as AutologoutRolesGeneral;

/**
 * Drupal 7 Autologout source from database.
 *
 * @MigrateSource(
 *   id = "d7_autologout_roles",
 *   source_module = "autologout",
 * )
 */
class AutologoutRoles extends AutologoutRolesGeneral {}

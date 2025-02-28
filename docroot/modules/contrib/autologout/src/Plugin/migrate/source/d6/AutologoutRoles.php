<?php

namespace Drupal\autologout\Plugin\migrate\source\d6;

use Drupal\autologout\Plugin\migrate\source\AutologoutRoles as AutologoutRolesGeneral;

/**
 * Drupal 6 Autologout source from database.
 *
 * @MigrateSource(
 *   id = "d6_autologout_roles",
 *   source_module = "autologout",
 * )
 */
class AutologoutRoles extends AutologoutRolesGeneral {}

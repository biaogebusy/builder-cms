<?php

namespace Drupal\range\D8Compatibility;

use Drupal\Core\Database\Database;
use Drupal\views\Plugin\views\query\Sql;

/**
 * Gets the database connection to use for the view.
 *
 * This compatibility layer is required in order to support overriding of the
 * database query condition drivers. This change has been committed to both D8
 * and D9 in https://www.drupal.org/node/3113403. Then, the missing
 * getConnection() method was added to the \Drupal\views\Plugin\views\query\Sql
 * in https://www.drupal.org/node/3139353. Finally, creating an instance of the
 * class Drupal\Core\Database\Query\Condition with the new keyword has been
 * deprecated in https://www.drupal.org/node/3130655.
 *
 * However, later two changes have not been committed to D8 making it impossible
 * to have zero deprecations and support both D8 and D9. This trait fixed that.
 */
trait ViewsSqlQueryGetConnectionTrait {

  /**
   * Gets the database connection to use for the view.
   *
   * The returned database connection does not have to be the default database
   * connection. It can also be to another database connection when the view is
   * to an external database or a replica database.
   *
   * @return \Drupal\Core\Database\Connection
   *   The database connection to be used for the query.
   */
  protected function getDatabaseConnection(Sql $query) {
    // Call the getConnection() method directly if exists (D9.1+).
    if (method_exists($query, 'getConnection')) {
      return $query->getConnection();
    }
    // Get the connection (this code is a just copy of the core method).
    else {
      // Set the replica target if the replica option is set for the view.
      $target = empty($query->options['replica']) ? 'default' : 'replica';
      // Use an external database when the view configured to.
      $key = $query->view->base_database ?? 'default';
      return Database::getConnection($target, $key);
    }
  }

}

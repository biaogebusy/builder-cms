<?php

declare(strict_types=1);

namespace Drupal\forms_steps\Repository;

use Drupal\Core\Database\Connection;
use Drupal\Core\Messenger\MessengerTrait;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;

/**
 * Workflow entity repository.
 *
 * @package Drupal\forms_steps
 */
class WorkflowRepository {

  use MessengerTrait;
  use StringTranslationTrait;

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected Connection $connection;

  const FORMS_STEPS_WORKFLOW_DB = 'forms_steps_workflow';

  /**
   * Construct a repository object.
   *
   * @param \Drupal\Core\Database\Connection $connection
   *   The database connection.
   * @param \Drupal\Core\StringTranslation\TranslationInterface $translation
   *   The translation service.
   */
  public function __construct(Connection $connection, TranslationInterface $translation) {
    $this->connection = $connection;
    $this->setStringTranslation($translation);
  }

  /**
   * Save an entry in the database.
   *
   * Exception handling is shown in this example. It could be simplified
   * without the try/catch blocks, but since an insert will throw an exception
   * and terminate your application if the exception is not handled, it is best
   * to employ try/catch.
   *
   * @param array $entry
   *   An array containing all the fields of the database record.
   *
   * @return int
   *   The number of updated rows.
   *
   * @throws \Exception
   *   When the database insert fails.
   *
   * @see db_insert()
   */
  public function insert(array $entry): ?int {
    $return_value = NULL;
    try {
      $return_value = $this->connection->insert(self::FORMS_STEPS_WORKFLOW_DB)
        ->fields($entry)
        ->execute();
    }
    catch (\Exception $e) {
      $this->messenger()->addMessage(
        $this->t(
          'db_insert failed. Message = %message',
          ['%message' => $e->getMessage()]
        ), 'error'
      );
    }
    return $return_value;
  }

  /**
   * Update an entry in the database.
   *
   * @param array $entry
   *   An array containing all the fields of the item to be updated.
   *
   * @return int
   *   The number of updated rows.
   *
   * @see db_update()
   */
  public function update(array $entry): int {
    $count = 0;

    try {
      // Connection->update()...->execute() returns the number of rows updated.
      $count = $this->connection->update(self::FORMS_STEPS_WORKFLOW_DB)
        ->fields($entry)
        ->condition('id', $entry['id'])
        ->execute();
    }
    catch (\Exception $e) {
      $this->messenger()->addMessage(
        $this->t('db_update failed. Message = %message, query= %query', [
          '%message' => $e->getMessage(),
          '%query' => \Drupal::request()->query->get('keys'),
        ]
      ), 'error');
    }
    return (int) $count;
  }

  /**
   * Delete an entry from the database.
   *
   * @param array $entry
   *   An array containing at least the person identifier 'pid' element of the
   *   entry to delete.
   *
   * @see \Drupal\Core\Database\Connection::delete()
   */
  public function delete(array $entry) {
    $this->connection->delete(self::FORMS_STEPS_WORKFLOW_DB)
      ->condition('id', $entry['id'])
      ->execute();
  }

  /**
   * Read workflow from the database using a filter array.
   *
   * @param array $entry
   *   An array containing all the fields used to search the entries in the
   *   table.
   *
   * @return object
   *   An object containing the loaded entries if found.
   *
   * @see \Drupal\Core\Database\Connection::select()
   */
  public function load(array $entry = []): array {
    // Read all the fields from the dbtng_example table.
    $select = $this->connection
      ->select(self::FORMS_STEPS_WORKFLOW_DB)
      // Add all the fields into our select query.
      ->fields(self::FORMS_STEPS_WORKFLOW_DB);

    // Add each field and value as a condition to this query.
    foreach ($entry as $field => $value) {
      $select->condition($field, $value);
    }
    // Return the result in object format.
    return $select->execute()->fetchAll();
  }

}

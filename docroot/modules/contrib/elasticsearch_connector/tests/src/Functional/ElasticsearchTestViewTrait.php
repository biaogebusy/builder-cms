<?php

declare(strict_types=1);

namespace Drupal\Tests\elasticsearch_connector\Functional;

use Behat\Mink\Element\ElementInterface;
use Behat\Mink\Element\NodeElement;
use Behat\Mink\Exception\ElementNotFoundException;
use Behat\Mink\Exception\ExpectationException;

/**
 * Make working with the test_elasticsearch_index_search view easier.
 *
 * The `elasticsearch_connector_test` module defines:
 * 1. a Search API Server named `elasticsearch_server` with a real ElasticSearch
 *    backend;
 * 2. a Search API Index named `test_elasticsearch_index` set up to index
 *    `entity_test_mulrev_changed` content; and;
 * 3. a View named `test_elasticsearch_index_search` on the Search API Index,
 *    with a simple exposed form, showing a table view of matching entities.
 *
 * This trait defines some functions to make it easier to run Functional and
 * FunctionalJavascript tests on the `test_elasticsearch_index_search` view.
 *
 * This trait is intended to be used alongside the
 * \Drupal\Tests\search_api\Functional\ExampleContentTrait. You should run
 * ExampleContentTrait::setUpExampleStructure() and ::insertExampleContent()
 * before testing the view.
 *
 * Note that, in order for the `test_elasticsearch_index` view's config to be
 * installed, both the `views` module, and the `elasticsearch_connector_test`
 * modules must be installed.
 *
 * Note that to view 'entity_test_mulrev_changed' content, a test user must
 * have, at minimum, the `view test entity` permission.
 *
 * Note that the view's 'page_1' display has the route
 * 'view.test_elasticsearch_index_search.page_1'.
 */
trait ElasticsearchTestViewTrait {

  /**
   * Get the HTML ID of the "Body" table column.
   *
   * Convert to a const when we drop support for Drupal 10.
   *
   * @return string
   *   The HTML ID of the "Body" table column.
   */
  protected static function getColumnBody(): string {
    return 'view-body-table-column';
  }

  /**
   * Get the HTML ID of the "Category" table column.
   *
   * Convert to a const when we drop support for Drupal 10.
   *
   * @return string
   *   The HTML ID of the "Category" table column.
   */
  protected static function getColumnCategory(): string {
    return 'view-category-table-column';
  }

  /**
   * Get the HTML ID of the "Changed" table column.
   *
   * Convert to a const when we drop support for Drupal 10.
   *
   * @return string
   *   The HTML ID of the "Changed" table column.
   */
  protected static function getColumnChanged(): string {
    return 'view-changed-table-column';
  }

  /**
   * Get the HTML ID of the "Created" table column.
   *
   * Convert to a const when we drop support for Drupal 10.
   *
   * @return string
   *   The HTML ID of the "Created" table column.
   */
  protected static function getColumnCreated(): string {
    return 'view-created-table-column';
  }

  /**
   * Get the HTML ID of the "Entity ID" table column.
   *
   * Convert to a const when we drop support for Drupal 10.
   *
   * @return string
   *   The HTML ID of the "Entity ID" table column.
   */
  protected static function getColumnEntityId(): string {
    return 'view-id-table-column';
  }

  /**
   * Get the HTML ID of the "Excerpt" table column.
   *
   * @return string
   *   The HTML ID of the "Excerpt" table column.
   */
  protected static function getColumnExcerpt(): string {
    return 'view-search-api-excerpt-table-column';
  }

  /**
   * Get the HTML ID of the "Keywords" table column.
   *
   * Convert to a const when we drop support for Drupal 10.
   *
   * @return string
   *   The HTML ID of the "Keywords" table column.
   */
  protected static function getColumnKeywords(): string {
    return 'view-keywords-table-column';
  }

  /**
   * Get the HTML ID of the "Name" table column.
   *
   * Convert to a const when we drop support for Drupal 10.
   *
   * @return string
   *   The HTML ID of the "Name" table column.
   */
  protected static function getColumnName(): string {
    return 'view-name-table-column';
  }

  /**
   * Get the HTML ID of the "Relevance" table column.
   *
   * Convert to a const when we drop support for Drupal 10.
   *
   * @return string
   *   The HTML ID of the "Relevance" table column.
   */
  protected static function getColumnRelevance(): string {
    return 'view-search-api-relevance-table-column';
  }

  /**
   * Get the HTML ID of the "Width" table column.
   *
   * Convert to a const when we drop support for Drupal 10.
   *
   * @return string
   *   The HTML ID of the "Width" table column.
   */
  protected static function getColumnWidth(): string {
    return 'view-width-table-column';
  }

  /**
   * Get the machine name of the Search API Index used by this view.
   *
   * Convert to a const when we drop support for Drupal 10.
   *
   * @return string
   *   The machine name of the Search API Index used by this view.
   */
  protected static function getIndexId(): string {
    return 'test_elasticsearch_index';
  }

  /**
   * Get XPath with placeholders for a table cell in a given :column with :text.
   *
   * Intended to be used with \Drupal\Tests\WebAssert::buildXPathQuery(), i.e.:
   * by calling `$this->assertSession()->buildXPathQuery()`.
   *
   * Convert to a const when we drop support for Drupal 10.
   *
   * @return string
   *   XPath with placeholders for a table cell in a given :column with :text.
   */
  protected static function getTableCellXpathTemplate(): string {
    return '//td[@headers=:column][text()[contains(.,:text)]]';
  }

  /**
   * Assert that the test view does not show a cell with the given inner HTML.
   *
   * @param string $columnId
   *   The HTML ID of the column that we should look for the given text in.
   * @param string $innerHtml
   *   The inner HTML that we're hoping not to find in the cell.
   * @param \Behat\Mink\Element\ElementInterface|null $container
   *   The container to search in.
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   *    Throws an Expectation exception if we do find the ID in the view.
   */
  protected function assertNotTableCellWithInnerHtml(string $columnId, string $innerHtml, ?ElementInterface $container = NULL): void {
    if (\is_null($container)) {
      $container = $this->getSession()->getPage();
    }

    // Find cells in the given column; then loop through them until we find one
    // with matching inner HTML, and throw an Expectation exception.
    $cellsInColumn = $container->findAll('xpath', $this->assertSession()->buildXPathQuery('//td[@headers=:column]', [
      ':column' => $columnId,
    ]));
    foreach ($cellsInColumn as $cell) {
      if (($cell instanceof ElementInterface)
        && \trim($cell->getHtml()) === $innerHtml
      ) {
        throw new ExpectationException('Found a table cell with the matching inner HTML but expected not to.', $this->getSession()->getDriver());
      }
    }

    // If we get here, then none of the cells matched, which is what we want, so
    // simply return.
  }

  /**
   * Assert that the test view does not show a cell with the given text.
   *
   * @param string $columnId
   *   The HTML ID of the column that we should look for the given text in.
   * @param string $text
   *   The text that we're hoping not to find in the cell.
   * @param \Behat\Mink\Element\ElementInterface|null $container
   *   The container to search in.
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   *   Throws an Expectation exception if we do find the ID in the view.
   */
  protected function assertNotTableCellWithText(string $columnId, string $text, ?ElementInterface $container = NULL): void {
    $this->assertSession()->elementNotExists('xpath',
      $this->assertSession()->buildXPathQuery(self::getTableCellXpathTemplate(), [
        ':column' => $columnId,
        ':text' => $text,
      ]),
      $container
    );
  }

  /**
   * Assert that the test view shows a table cell with given inner HTML.
   *
   * @param string $columnId
   *   The HTML ID of the column that we should look for the given text in.
   * @param string $innerHtml
   *   An HTML string that we should look for.
   * @param \Behat\Mink\Element\ElementInterface|null $container
   *   The container to search in, or NULL if we should search the whole page.
   *
   * @return \Behat\Mink\Element\NodeElement
   *   The table cell with the given inner HTML.
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   *    Throws an Element Not Found exception if we can't find a table row with
   *    with the correct selector.
   */
  protected function assertTableCellWithInnerHtml(string $columnId, string $innerHtml, ?ElementInterface $container = NULL): NodeElement {
    if (\is_null($container)) {
      $container = $this->getSession()->getPage();
    }

    // Find cells in the given column; then loop through them until we find one
    // with matching inner HTML, and return it.
    $cellsInColumn = $container->findAll('xpath', $this->assertSession()->buildXPathQuery('//td[@headers=:column]', [
      ':column' => $columnId,
    ]));
    foreach ($cellsInColumn as $cell) {
      if (($cell instanceof ElementInterface)
        && \trim($cell->getHtml()) === $innerHtml
      ) {
        return $cell;
      }
    }

    // If we get here, then none of the cells matched: throw an Element Not
    // Found exception.
    throw new ElementNotFoundException($this->getSession()->getDriver());
  }

  /**
   * Assert that the test view shows a cell with the given text.
   *
   * @param string $columnId
   *   The HTML ID of the column that we should look for the given text in.
   * @param string $text
   *   The text that we're hoping to find in the cell.
   * @param \Behat\Mink\Element\ElementInterface|null $container
   *   The container to search in.
   *
   * @return \Behat\Mink\Element\NodeElement
   *   The table cell with the given text.
   *
   * @throws \Behat\Mink\Exception\ElementNotFoundException
   *    Throws an Element Not Found exception if we can't find a table row with
   *    with the correct selector.
   */
  protected function assertTableCellWithText(string $columnId, string $text, ?ElementInterface $container = NULL): NodeElement {
    return $this->assertSession()->elementExists('xpath',
      $this->assertSession()->buildXPathQuery(self::getTableCellXpathTemplate(), [
        ':column' => $columnId,
        ':text' => $text,
      ]),
      $container
    );
  }

  /**
   * Ensure the test view's column headers are displayed on the current page.
   *
   * @throws \Behat\Mink\Exception\ElementNotFoundException
   *   Throws an Element Not Found exception if we can't find a views header
   *   with the correct selector.
   * @throws \Behat\Mink\Exception\ElementTextException
   *   Throws an Element Text exception if we can find a view's header with the
   *   correct selector, but the text in the header is not what we expect.
   */
  protected function assertTestViewColumnHeaders(): void {
    $this->assertSession()->elementTextContains('css', 'th#' . self::getColumnEntityId(), '0 ID');
    $this->assertSession()->elementTextContains('css', 'th#' . self::getColumnRelevance(), '1 Relevance');
    $this->assertSession()->elementTextContains('css', 'th#' . self::getColumnExcerpt(), '2 Excerpt');
    $this->assertSession()->elementTextContains('css', 'th#' . self::getColumnName(), '3 Name');
    $this->assertSession()->elementTextContains('css', 'th#' . self::getColumnBody(), '4 Body');
    $this->assertSession()->elementTextContains('css', 'th#' . self::getColumnCategory(), '5 Category');
    $this->assertSession()->elementTextContains('css', 'th#' . self::getColumnKeywords(), '6 Keywords');
    $this->assertSession()->elementTextContains('css', 'th#' . self::getColumnWidth(), '7 Width');
    $this->assertSession()->elementTextContains('css', 'th#' . self::getColumnCreated(), '8 Authored on');
    $this->assertSession()->elementTextContains('css', 'th#' . self::getColumnChanged(), '9 Changed');
  }

  /**
   * Ensure the test view's exposed form is displayed on the current page.
   *
   * @throws \Behat\Mink\Exception\ElementNotFoundException
   *   Throws an Expectation exception if we cannot find the view's exposed form
   *   on the current page.
   */
  protected function assertTestViewExposedForm(): void {
    $this->assertSession()->fieldExists('Fulltext search');
    $this->assertSession()->buttonExists('Apply');
  }

  /**
   * Assert that the test view shows a row for an entity with the given ID.
   *
   * @param string $entityId
   *   The ID of the entity that we're hoping to find in the test view.
   *
   * @return \Behat\Mink\Element\NodeElement
   *   The table row containing the element ID.
   *
   * @throws \Behat\Mink\Exception\ElementNotFoundException
   *   Throws an Element Not Found exception if we can't find a table row with
   *   with the correct selector.
   */
  protected function assertTestViewHasEntityRow(string $entityId): NodeElement {
    return $this->assertSession()->elementExists('xpath',
      $this->assertSession()->buildXPathQuery(self::getTableCellXpathTemplate() . '/ancestor-or-self::tr', [
        ':column' => self::getColumnEntityId(),
        ':text' => $entityId,
      ])
    );
  }

  /**
   * Assert that the test view does not show an entity with a given ID.
   *
   * @param string $entityId
   *   The ID of the entity that we're hoping not to find in the test view.
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   *   Throws an Expectation exception if we do find the ID in the view.
   */
  protected function assertTestViewNotShowsEntity(string $entityId): void {
    $this->assertNotTableCellWithText(self::getColumnEntityId(), $entityId);
  }

  /**
   * Assert that the test view shows an entity with a given ID.
   *
   * @param string $entityId
   *   The ID of the entity that we're hoping to find in the test view.
   *
   * @return \Behat\Mink\Element\NodeElement
   *   The table cell containing the entity ID.
   *
   * @throws \Behat\Mink\Exception\ElementNotFoundException
   *   Throws an Element Not Found exception if we can't find a table cell with
   *   with the correct selector.
   */
  protected function assertTestViewShowsEntity(string $entityId): NodeElement {
    return $this->assertTableCellWithText(self::getColumnEntityId(), $entityId);
  }

}

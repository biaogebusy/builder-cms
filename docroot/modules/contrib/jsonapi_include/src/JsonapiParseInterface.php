<?php

namespace Drupal\jsonapi_include;

/**
 * The interface for parsing the JSON:API Response.
 *
 * @package Drupal\jsonapi_include
 */
interface JsonapiParseInterface {

  /**
   * Parses the JSON:API Response with integrating includes inside the fields.
   *
   * @param \Symfony\Component\HttpFoundation\Response|string $response
   *   A Response object with JSON:API response.
   *   Or string with a Response body (deprecated).
   *
   * @return \Symfony\Component\HttpFoundation\Response|string
   *   Returns the Response object, if the input is a Response object.
   *   Or return a string, if the input is in the string format (deprecated).
   *
   * @todo With 2.0 release remove the string format and explicitly set the
   *   Response as the argument and the return value.
   */
  public function parse($response);

}

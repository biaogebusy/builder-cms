This module provides integration with the <a href="https://www.pendo.io/">Pendo.io</a> service.

<h3>Overview</h3>

By default, the module will collect the following information and send it to pendo:
<ul>
  <li>Application UUID</li>
  <li>User UUID</li>
  <li>User Email</li>
</ul>

<h3>Installation</h3>

<ul>
  <li>Enable the module</li>
  <li>Set the API Key at /admin/config/services/pendo</li>
  <li>Clear caches</li>
</ul>

<h3>Extending</h3>

You can easily add, modify, or remove the data that is sent to Pendo by modifying the drupalSettings object via hook_preprocess_html():

<?php
/**
 * Implements hook_preprocess_HOOK().
 */
function mymodule_preprocess_html(&$variables) {
  $variables['#attached']['drupalSettings']['pendo']['data']['visitor']['foo'] = 'bar'; 
  $variables['#attached']['drupalSettings']['pendo']['data']['account']['foo'] = 'bar';
}
?>

The `visitor` and `account` objects are sent directly to Pendo. Apart from a handful of reserved keys, you can add any key value pair you'd like to those two objects.

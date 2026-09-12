<?php

/**
 * Add CKEditor 5 plugins to the excluded list for bundle that is uploaded
 * to the cloud server. Editor bundle is used to export realtime collaboration
 * document when permissions system is used. Some plugins may make export
 * result with an error. Such plugin should be added to the excluded list.
 *
 * @return string[]
 */
function hook_ckeditor5_premium_features_exclude_bundle_plugins() {
  return [
    'ExamplePlugin',
    'ExamplePluginTwo'
  ];
}

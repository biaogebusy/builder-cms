# JSON:API Extras

This module provides extra functionality on top of JSON:API. You should not
need this module to get an spec compliant JSON:API, this module is to
customize the output of JSON:API.

This module adds the following features:

  - Allows you to customize the URL for your resource under the `/jsonapi`
    prefix.
  - Allows you to customize the resource type to other than
    `${entityTypeId}--${bundle}`.
  - Lets you remove fields from the JSON:API output.

TODO:
  * Auto calculate the dependency of the provider of the entity type and
  bundles in the configuration entity.

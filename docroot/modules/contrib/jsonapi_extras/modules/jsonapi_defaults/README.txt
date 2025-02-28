===================
 JSON API Defaults
===================

The JSON API module provides zero configuration out of the box.
Use JSON API Extras and Defaults to customize your API.
JSON API Defaults provides default includes and filters (or any parameter)
for resources.
Use this module if you want your api client just use simple resource without
any url parameters and still deliver all related objects.

Here are the current features of the JSON API Defaults module:

  - Default includes: if you apply default includes to a resource type,
    then collections of that resource type will apply those includes when
    there is no include in the collection query.
  - Default filters: if you apply default filters to a resource type, then
    collections of that resource type will apply those filters when there is
    no filter in the collection query.

--------------
 Installation
--------------

  - Install the JSON API Defaults module normally.
  - Visit /admin/config/services/jsonapi to overwrite and configure your API.

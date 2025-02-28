CONTENTS OF THIS FILE
---------------------

* Introduction
* Requirements
* Recommended Modules
* Installation
* Configuration


INTRODUCTION
------------

JSON API Include allow merge include and relationship data of JSON API.
Use case:
- Show all data related output content type.
- Use directly data of relationships to import content with Migrate.

REQUIREMENTS
------------

This module requires JSON:API core in Drupal.


RECOMMENDED MODULES
-------------------

JSON:API Extras: https://www.drupal.org/project/jsonapi_extras

Use JSON:API Extras to customize your API.


INSTALLATION
------------

Install the module as you would normally install a contributed Drupal
module. Visit https://www.drupal.org/node/1897420 for further information.


CONFIGURATION
--------------

**Basic case:**
 
Install module and module auto parse include and relationship of jsonapi.

**Advanced use:**

    1. Navigate to Administration>Configuration>Web services>JSON:API Include
    2. Enable "Use jsonapi_include query in url"
    3. Toogle json api include with query jsonapi_include=1
    http://site.com/jsonapi/node/article?include=field_tags&jsonapi_include=1

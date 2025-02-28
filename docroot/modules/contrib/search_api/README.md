# Search API

This module provides a framework for easily creating searches on any entity
known to Drupal, using any kind of search engine. For site administrators, it is
a great alternative to other search solutions, since it already incorporates
faceting support (with [Facets]) and the ability to use the Views module for
displaying search results, filters, etc. Also, with the [Apache Solr
integration][Search API Solr], a high-performance search engine is available for
this module.

[Facets]: https://www.drupal.org/project/facets
[Search API Solr]: https://www.drupal.org/project/search_api_solr

Developers, on the other hand, will be impressed by the large flexibility and
numerous ways of extension the module provides. Hence, the growing number of
additional contrib modules, providing additional functionality or helping users
customize some aspects of the search process.

- For a full description of the module, visit the [project page].
- To submit bug reports and feature suggestions, or to track changes, use the
  [issue queue].

[Project page]: https://www.drupal.org/project/search_api
[issue queue]: https://www.drupal.org/project/issues/search_api


## Table of contents

- Requirements
- Installation
- Configuration
- Information for developers
- Maintainers


## Requirements

No other modules are required. If you want to use a different backend than the
database (for instance, Apache Solr or Elasticsearch), you will need to install
the respective module providing the required backend plugin.


### Recommended modules

There are several very popular modules for extending the functionality of this
module:

- [Facets]: Allows you to place facets (filters with dynamic options lists) on
  searches created by this module.
- [Search API Autocomplete]: Allows you to add autocompletion to searches
  created by this module.
- [Search API Solr]: This is the most popular and well-maintained search backend
  module for the Search API. It provides integration with the [Apache Solr]
  search backend.

[Search API Autocomplete]: https://www.drupal.org/project/search_api_autocomplete
[Apache Solr]: https://solr.apache.org/


## Installation

- Install as you would normally install a contributed Drupal module. For further
  information, see _[Installing Drupal Modules]_.

[Installing Drupal Modules]: https://www.drupal.org/docs/extending-drupal/installing-drupal-modules


## Configuration

After installation, for a quick start, just install the “Database Search
Defaults” module provided with this project. This will automatically set up a
search view for node content, using a database server for indexing.

Otherwise, you need to enable at least a module providing integration with a
search backend (like database, Solr, Elasticsearch, …). Possible options are
listed at _[Server backends and features]_.

[Server backends and features]: https://www.drupal.org/docs/8/modules/search-api/getting-started/server-backends-and-features

Then, go to `/admin/config/search/search-api` on your site and create a search
server and search index. Afterwards, you can create a view based on your index
to enable users to search the content you configured to be indexed. More details
are available in _[Getting started]_. There, you can also find answers to
[frequently asked questions] and [common pitfalls] to avoid.

[Getting started]: https://www.drupal.org/docs/8/modules/search-api/getting-started
[frequently asked questions]: https://www.drupal.org/docs/8/modules/search-api/getting-started/frequently-asked-questions
[common pitfalls]: https://www.drupal.org/docs/8/modules/search-api/getting-started/common-pitfalls


## Information for developers

The Search API provides a lot of ways for developers to extend or customize the
framework.


### Hooks

All available hooks are listed in `search_api.api.php`. They have been
deprecated at this point, though, and replaced by events. Hooks will be removed
from the module in version 2.0.0.


### Events

All events defined by this module are documented in
`\Drupal\search_api\Event\SearchApiEvents`.

In addition, the Search API’s task system (for reliably executing necessary
system tasks) makes use of events. Every time a task is executed, an event will
be fired based on the task’s type and the sub-system that scheduled the task is
responsible for reacting to it. This system is extensible and can therefore also
easily be used by contrib modules based on the Search API. For details, see the
description of the `\Drupal\search_api\Task\TaskManager` class, and the other
classes in `src/Task` for examples.


### Query tags

When trying to modify a specific search query, or set of search queries, it is
useful to know the tags placed on those queries. These will allow you to use the
tag-specific "search_api.query_pre_execute.TAG" events, or identify the search
queries in question in a general "search_api.query_pre_execute" event listener.

The following query tags are known to be used either by this module or other
contrib modules:

- `alter_cache_metadata`: This tag is used by the Search API module’s Views
  integration to mark a "query_pre_execute" event that is only used to collect
  static cache metadata for the view. Therefore, most listeners should ignore
  the "query_pre_execute" event in this case. See [this issue] for details.
- `views`, `views_VIEW_ID`: This tag is placed on all search queries executed by
  the Views integration of this module. Additional query tags can be specified
  with the “Query Tags” option in the view itself.
- `server_index_status`: This tag is placed on the filter-less search query
  executed on a search index’s “View” tab to determine the “Server index status”
  to display. Therefore, this query should not be modified in almost all cases.
- `search_api_autocomplete`: Placed by the [Search API Autocomplete] module on
  all search queries created by that module.
- `mlt`: Used by the [Search API Solr] module to mark search queries executed
  for the “More Like This” functionality.


[this issue]: https://www.drupal.org/node/3295564


### Plugins

The Search API defines several plugin types, all listed in its
`search_api.plugin_type.yml` file. Here is a list of them, along with the
directory in which you can find their definition files (interface, plugin base
and plugin manager):

| Plugin type | Directory        |
|-------------|------------------|
| Backends    | `src/Backend`    |
| Datasources | `src/Datasource` |
| Data types  | `src/DataType`   |
| Displays    | `src/Display`    |
| Parse modes | `src/ParseMode`  |
| Processors  | `src/Processor`  |
| Trackers    | `src/Tracker`    |

The display plugins are a bit of a special case there, because they aren’t
really “extending” the framework, but are rather a way of telling the Search API
(and all modules integrating with it) about search pages your module defines.
They can then be used to provide, for example, faceting support for those pages.
Therefore, if your module provides any search pages, it’s a good idea to provide
display plugins for them. For an example (for Views pages), see
`\Drupal\search_api\Plugin\search_api\display\ViewsPage`.

For more information, see the
[handbook documentation for developers][Developers handbook].

[Developers handbook]: https://www.drupal.org/docs/8/modules/search-api/developer-documentation

To know which parts of the module can be relied upon as its public API, please
read the [Drupal 8 backwards compatibility and internal API policy][Core BC]
and the module’s issue regarding [potential module-specific changes to that
policy][Module BC].

[Core BC]: https://www.drupal.org/core/d8-bc-policy
[Module BC]: https://www.drupal.org/node/2871549


### Server backend features

Server backend features are a way for other contrib modules to cleanly define
ways in which the Search API can be extended. For more information, see
_[Server backends and features]_.

The Search API module itself currently defines two features:

- More Like This (`search_api_mlt`)
  This feature can be used to retrieve a list of search results that are similar
  to a given indexed item. A backend that supports this feature has to recognize
  the `search_api_mlt` query option. If present, it contains an associative
  array with the following keys:
  - `id`: The Search API item ID (consisting of the datasource ID and the
    datasource-specific item ID – passing a plain entity ID will NOT work!) of
    the item for which similar results should be found.
  - `fields`: A simple array of fields which should be used for determining
    similarity. Backends can choose to ignore this field.
  - `field boosts`: (optional) An associative array mapping fields to a numeric
    “boost” value that determines how important they should be considered when
    determining similarity. Backends can choose to ignore this field.

  The feature can be used in the UI via the “More like this” Views contextual
  filter.
- Random Sort (`search_api_random_sort`)
  This feature allows sorting a search query randomly. Backends supporting this
  feature should accept sorts on field `search_api_random` and, if present,
  apply a random sort to the search query. Optionally, they can also check the
  `search_api_random_sort` query option for additional specifications, which (if
  present) will be an associative array with any of the following keys:
  - `seed`: The seed value to use for the random function. This is important to
    support proper paging for randomly sorted search results.


## Maintainers


### Current maintainers

- [Thomas Seidl (drunken monkey)](https://www.drupal.org/u/drunken-monkey)

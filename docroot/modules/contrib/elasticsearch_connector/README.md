Elasticsearch is a powerful, distributed, RESTful search and analytics engine based on [Apache Lucene](https://lucene.apache.org) that supports full-text search, [vector search](https://www.elastic.co/search-labs/blog/category/vector-search), [retrieval augmented generation (RAG)](https://www.elastic.co/search-labs/blog/retrieval-augmented-generation-rag), [facets](https://www.drupal.org/project/facets), [spellchecking](https://www.drupal.org/project/search_api_spellcheck), hit highlighting, [auto-completion](https://www.drupal.org/project/search_api_autocomplete), [location-based searching](https://www.drupal.org/project/search_api_location), and more.

This modules provides a [Search API](https://www.drupal.org/project/search_api) backend for [Elasticsearch](https://www.elastic.co/elasticsearch), using [the official Elasticsearch PHP Client](https://github.com/elastic/elasticsearch-php).

Note that, in January 2021, Amazon forked Elasticsearch (then at version 7.10.2) to create [OpenSearch](https://opensearch.org), and [the two projects have diverged over time](https://bigdataboutique.com/blog/opensearch-vs-elasticsearch-an-up-to-date-comparison-5c1c71). If you are using OpenSearch, please consider using [the Search API OpenSearch module](https://www.drupal.org/project/search_api_opensearch) instead.

### Requirements

Elasticsearch Connector requires the [Search API](https://www.drupal.org/project/search_api) module to function.

### Recommended modules

The following modules are optional, but can extend the functionality of Elasticsearch Connector:

*   [The Facets module](https://www.drupal.org/project/facets), which allows site builders to easily create and manage faceted search interfaces.
*   [The Geofield module](https://www.drupal.org/project/geofield), which allows storing, managing and representing dynamic geographic data in Drupal.
*   [The Search API Autocomplete module](https://www.drupal.org/project/search_api_autocomplete), which provides autocomplete functionality for searches.
*   [The Search API Location module](https://www.drupal.org/project/search_api_location), which allows location-based searching.
*   [The Search API Spellcheck module](https://www.drupal.org/project/search_api_spellcheck), which suggests corrections to misspelled words in the search query ("Did you mean ...?").

### Roadmap

The ElasticSearch Connector maintainers intend to support ElasticSearch only, i.e.: we do not intend to support OpenSearch, because [the Search API OpenSearch module](https://www.drupal.org/project/search_api_opensearch) does that already.

The maintainers intend to support versions of this module that are compatible with the currently-supported versions of Elasticsearch. For more information, please see [Elasticsearch's documentation on _Elastic Product End of Life Dates_](https://www.elastic.co/support/eol).

| Elasticsearch release series | Drupal module release series | Drupal module supported? | Maintenance status |
| --- | --- | --- | --- |
| 8.x | 8.0.\* | Yes | Actively maintained
| 7.x | 8.x-7.\* | Yes | Security and bug fixes only
| 6.x | 8.x-6.\* | No  |
| 5.x | 8.x-5.\*, 7.x-5.\* | 7.x-5.\* only | Security and bug fixes only
| 2.x, 1.x | 8.x-2.\*, 7.x-2.\* | No  |
| 1.x | 7.x-1.\* | No |

### Known problems

If you find a problem, please [let us know by adding an issue](https://www.drupal.org/node/add/project-issue/elasticsearch_connector)!

In the 8.0.\* release series, changing the mapping of an existing field or deleting a field will cause the search index to be cleared, and all items queued for re-indexing. This is a limitation of Elasticsearch: see [the Elasticsearch documentation on their _Update mapping API_](https://www.elastic.co/guide/en/elasticsearch/reference/8.12/indices-put-mapping.html#updating-field-mappings) for more details. We plan to mitigate this by using Elasticsearch's Aliases API to automatically creating a new index and reindexing to it in [#3248665: Support Aliases API and zero downtime mapping updates](https://www.drupal.org/project/elasticsearch_connector/issues/3248665 "Status: Active").

### Credits

[NodeSpark](https://www.drupal.org/node-spark-ltd), [Google Summer of Code (GSoC)](https://www.drupal.org/community/contributor-guide/reference-information/google-summer-of-code) 2014, [FFW](https://www.drupal.org/ffw), and [Utilis.io](https://www.linkedin.com/company/utilis-io) sponsored initial development of Elasticsearch Connector.

[Fame Helsinki](https://www.drupal.org/fame-helsinki), [Ontario Digital Service](https://www.drupal.org/ontario-digital-service), and [Consensus Enterprises](https://www.drupal.org/consensus-enterprises) sponsored maintenance and support for Elasticsearch 8.

This module was created by [Nikolay Ignatov (skek)](https://www.drupal.org/u/skek) who maintains the list of maintainers. If you would like to become a maintainer yourself, please reach out to him directly.

### Similar projects and how they are different

*   [Search API OpenSearch](https://www.drupal.org/project/search_api_opensearch) provides a Search API backend for [OpenSearch](https://opensearch.org/). OpenSearch was created as a fork from Elasticsearch version 7.10.2, but the two projects have diverged, and there are [breaking changes in the API](https://www.elastic.co/guide/en/elasticsearch/reference/current/migrating-8.0.html#breaking-changes-8.0) for indexing and querying.
*   [Azure search](https://www.drupal.org/project/azure_search) provides a Search API backend for [Microsoft Azure AI Search](https://learn.microsoft.com/en-us/azure/search/). _Microsoft Azure AI Search_ has a different set of features, and a different API for indexing and querying than Elasticsearch.
*   [Search API Solr](https://www.drupal.org/project/search_api_solr) provides a Search API backend for [Apache Solr](https://solr.apache.org), another [Lucene](https://lucene.apache.org)\-based search engine. Solr has a different set of features, and a different API for indexing and querying than Elasticsearch.
*   [Elasticsearch - Search API](https://www.drupal.org/project/elasticsearch_search_api) and [Search API Elasticsearch](https://www.drupal.org/project/search_api_elasticsearch): both provide frameworks to set up custom Elasticsearch based search pages, but are only compatible with Drupal 7.
*   [Elasticsearch Helper](https://www.drupal.org/project/elasticsearch_helper) (and [the _Elasticsearch Helper_ ecosystem](https://www.drupal.org/project/elasticsearch_helper/ecosystem)): deliberately avoid building on top of Search API (and [the _Search API_ ecosystem](https://www.drupal.org/project/search_api/ecosystem)), in order to provide tighter integration with ElasticSearch.

Note that there is at few [modules that extends Elasticsearch Connector](https://www.drupal.org/project/elasticsearch_connector/ecosystem):

*   [Elasticsearch Connector Suggester](https://www.drupal.org/project/elasticsearch_connector_suggester) improves suggested [auto-completion](https://www.drupal.org/project/search_api_autocomplete) results by letting you set tokenizer parameters on full-text fields.
*   [Search API Elasticsearch Synonym](https://www.drupal.org/project/search_api_elasticsearch_synonym) provides synonym functionality for Elasticsearch.

### Dependencies

In order to function, this module requires a connection to an [Elasticsearch cluster](https://www.elastic.co/guide/en/elastic-stack/current/installing-elastic-stack.html):

*   _Elasticsearch B.V._, the company that created Elasticsearch and sponsors its development, offers Elasticsearch as a Service through a product called [Elastic Cloud](https://www.elastic.co/cloud) (which can be hosted on Amazon Web Services (AWS), Google Cloud Platform (GCP), and/or Microsoft Azure, and has a 14-day free trial).
*   Some hosting providers (e.g.: [Platform.sh](https://docs.platform.sh/add-services/elasticsearch.html), [Lagoon](https://docs.lagoon.sh/concepts-advanced/service-types/#elasticsearch)) offer Elasticsearch plugins.
*   You can also self-host Elasticsearch with [Docker](https://www.elastic.co/guide/en/elasticsearch/reference/8.13/docker.html) or [Kubernetes](https://www.elastic.co/guide/en/cloud-on-k8s/current/index.html).
*   To test with a single-node cluster in CI, see [this project's `.gitlab-ci.yml` on the `8.0.x` branch](https://git.drupalcode.org/project/elasticsearch_connector/-/blob/8.0.x/.gitlab-ci.yml?ref_type=heads#L52-94).
*   For local development, both [ddev](https://github.com/ddev/ddev-elasticsearch) and [lando](https://docs.lando.dev/plugins/elasticsearch/) provide officially-supported plugins; or you can [run it locally](https://www.elastic.co/guide/en/elasticsearch/reference/current/run-elasticsearch-locally.html).
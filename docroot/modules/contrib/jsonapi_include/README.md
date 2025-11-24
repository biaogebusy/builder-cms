## Table of contents

- Introduction
- Requirements
- Recommended Modules
- Installation
- Configuration
- FAQ


## Introduction

JSON:API Include merges relationship data from JSON:API.

Use cases:

- Easily parse entity references returned by JSON:API (the data of referenced entities will be flattened into the relevant field of the parent entity instead of in a JSON:API relationship).
- Use relationship data directly to import content with Migrate.


## Requirements

This module requires no modules outside of Drupal core's JSON:API module.


## Recommended Modules

[JSON:API Extras](https://www.drupal.org/project/jsonapi_extras): Use the JSON:API Extras module to customize JSON:API responses by specifying automatic includes. For example, if you have a node with a taxonomy term entity reference, you can specify that as an automatic include, which will then be parsed by the JSON:API Include module.


## Installation

Install as you would normally install a contributed Drupal module. For further
information, see
[Installing Drupal Modules](https://www.drupal.org/docs/extending-drupal/installing-drupal-modules).


## CONFIGURATION

**By default, this module automatically parses all responses.** JSON:API includes are flattened to make it easy to parse entity reference data.

Want to opt-in to flattening instead? There's a configuration option for that!

1. Navigate to Administration>Configuration>Web services>JSON:API>JSON:API Include.
2. Enable "Use jsonapi_include query in url".
3. Toggle JSON:API include by adding `jsonapi_include=1` when you want to use this module's parsing.

For example: `https://www.example.com/jsonapi/node/article?include=field_tags&jsonapi_include=1`

When the **Use jsonapi_include query in url** setting is enabled, this module will only parse the response if `jsonapi_include=1` was specified.

If this setting is disabled, all responses will be parsed.


## FAQ

**Q:** What does this module do?

**A:** If you request an entity with included relationships through JSON:API, the output will be a JSON array containing the entity and the contents of the entity's referenced entities. This module adds an additional step before the response is delivered (see `JsonapiParse`). In this step, the module merges the content of the referenced entities into the respective field entries of the original entity. This makes it easier to parse entity references.

**Q:** How can I add a custom parse function?

**A:** This module uses the service `jsonapi_include.parse` to parse JSON:API data. If you want to use a custom parsing function, override the class `JsonapiParse`.
Reference: [Altering existing services (Core docs)](https://www.drupal.org/docs/drupal-apis/services-and-dependency-injection/altering-existing-services-providing-dynamic-services)


## Maintainers

- Thao Huynh Khac - [zipme_hkt](https://www.drupal.org/u/zipme_hkt)
- Patrick Kenny - [ptmkenny](https://www.drupal.org/u/ptmkenny)


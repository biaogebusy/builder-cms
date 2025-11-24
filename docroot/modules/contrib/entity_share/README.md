# Entity Share

This module allows to share entities using the JSON:API. It provides a UI to
use the endpoints provided by the JSON:API module.

You can define one website as a "server" and another website as a "client". A
website can be both "client" and "server".

See also the documentation pages:
- [Supported field types](https://www.drupal.org/docs/contributed-modules/entity-share/supported-field-types)
- [Advanced usages](https://www.drupal.org/docs/contributed-modules/entity-share/advanced-usages)


## Requirements

This module requires no modules outside of Drupal core.

The following core patch is needed for code-defined bundle fields to work:

- ContentEntityDenormalizer uses the field map, and so is unaware of bundle
  fields: https://www.drupal.org/project/drupal/issues/3522951

### Requirements for specific processor plugins

* The redirect processor requires:
  * The [Redirect module](https://www.drupal.org/project/redirect)
  * The patch to the Redirect module at
    https://www.drupal.org/project/redirect/issues/3057679 is recommended, to
    allow the user authenticated on the server to view redirect entities without
    having access to manage them.

## Recommended modules and patches

- [JSON:API Extras](https://www.drupal.org/project/jsonapi_extras):
  To allow to customize the JSON:API endpoints and to enable full pager
  feature. See the documentation page
  [Supported field types](https://www.drupal.org/docs/contributed-modules/entity-share/supported-field-types).
- [Views Bulk Operations](https://www.drupal.org/project/views_bulk_operations):
  To allow to update the import policy without having to reimport entities.
* [Menu Link Content View Access](https://www.drupal.org/project/menu_link_content_view_access)
  To allow view access to the entity share role, so that menu link content
  entities can be pulled.

The following patches may be of some use:

* Paragraph entities can't be pulled directly from a channel because they can't
  be filtered in JSON:API:
  https://www.drupal.org/project/paragraphs/issues/3097493
* The same for Paragraph Library entities:
  https://www.drupal.org/project/paragraphs/issues/3228913

## Installation

Install as you would normally install a contributed Drupal module. For further
information, see
[Installing Drupal Modules](https://www.drupal.org/docs/extending-drupal/installing-drupal-modules).

Install and enable the Entity Share Server module on the site you want to get
content from.

Install and enable the Entity Share Client module on the site you want to put
content on.


## Configuration

See the documentation pages:
- [Installation and configuration](https://www.drupal.org/docs/contributed-modules/entity-share/installation-and-configuration)
- [Authorization methods](https://www.drupal.org/docs/contributed-modules/entity-share/authorization-methods)


## Maintainers

- Thomas Bernard - [ithom](https://www.drupal.org/user/3175403)
- Florent Torregrosa - [Grimreaper](https://www.drupal.org/user/2388214)
- Ivan Vujović - [ivan.vujovic](https://www.drupal.org/user/382945)
- Yarik Lutsiuk - [yarik.lutsiuk](https://www.drupal.org/user/3212333)

Supporting organizations:
- [Smile](https://www.drupal.org/smile) -
  Sponsored initial development, evolutions, maintenance and support.
- [Lullabot](https://www.drupal.org/lullabot) -
  Sponsored development of new features in association with Carnegie Mellon
  University.
- [Carnegie Mellon University](https://www.drupal.org/carnegie-mellon-university)
- [Studi](https://www.studi.com) -
  Sponsored development of new features.
- [Actency](https://www.drupal.org/actency) - Sponsored maintenance time.

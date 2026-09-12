# Simple Hierarchical Select

Simple Hierarchical Select (SHS) provides a hierarchical selection widget for taxonomy term reference fields. Instead of displaying an entire taxonomy tree in one long list, SHS loads each level as a separate select element as the user moves through the hierarchy.

SHS can be used on entity forms, as a field formatter, and with taxonomy filters exposed by Views.

## Features

- Hierarchical taxonomy term selection on entity forms.
- Support for single-value and multi-value taxonomy term reference fields.
- AJAX loading of child terms as a parent term is selected.
- Support for required and optional fields.
- Optional validation requiring selection from the deepest available level.
- A hierarchical field formatter that displays the selected term and its ancestors.
- Optional links from formatted term labels to taxonomy term pages.
- Views integration for:
  - **Has taxonomy terms** filters.
  - **Has taxonomy terms (with depth)** filters.
  - Exposed taxonomy filters using the SHS selection type.
- Search API Views filter integration when Search API is installed.
- Alter hooks for changing widget classes, JavaScript settings, term data, and JSON responses.
- Optional Chosen integration through the included `shs_chosen` submodule.
- Optional Media Views integration through the included `shs_media` submodule.

## Requirements

- Drupal core 10.5 or later, or Drupal 11.1 or later.
- Drupal core Taxonomy module.

Optional integrations have their own dependencies:

- `shs_chosen` requires the contributed [Chosen](https://www.drupal.org/project/chosen) module.
- `shs_media` requires the Drupal core Media module.

## Installation

Install SHS as you would normally install a contributed Drupal module. Composer is recommended:

```bash
composer require drupal/shs:^2.0
```

Enable the main module:

```bash
drush en shs
```

Enable optional integrations as needed:

```bash
drush en shs_chosen
```

```bash
drush en shs_media
```

## Configuration

### Configure a taxonomy reference field

1. Add or edit an entity reference field whose target type is **Taxonomy term**.
2. Configure the field to reference the required vocabulary or vocabularies.
3. Open the entity type's **Manage form display** page.
4. Select **Simple hierarchical select** as the field widget.
5. Save the form display.

SHS supports both limited-cardinality and unlimited-cardinality fields.

### Widget settings

The standard SHS widget provides the following settings:

#### Allow creating new items

This setting is present in the 2.0.x user interface but is disabled because inline term creation is not implemented in this branch.

#### Allow creating new levels

This setting is present in the 2.0.x user interface but is disabled because inline creation of child terms is not implemented in this branch.

#### Force selection of deepest level

When enabled, the submitted value must be a term at the deepest available level of the selected hierarchy. Users cannot stop at an intermediate parent when that parent has children.

### Field formatter

On the **Manage display** page, choose **Simple hierarchical select** as the formatter for a taxonomy term reference field.

The formatter displays the full taxonomy path for each selected term. It can optionally link each term label to the referenced taxonomy term entity.

## Views integration

SHS can replace the normal taxonomy selection control in Views.

1. Add a taxonomy term reference filter, **Has taxonomy terms**, or **Has taxonomy terms (with depth)** to a View.
2. Expose the filter when it should be available to site visitors.
3. Select **Simple hierarchical select** as the selection type.
4. Configure the remaining filter options and save the View.

The depth-aware filter includes content tagged with descendants of the selected term according to the configured depth behavior.

When Search API is installed, SHS 2.0.x also overrides the Search API taxonomy term filter so it can use the SHS interface.

## Chosen integration

The included `shs_chosen` submodule integrates SHS with the contributed Chosen module.

After enabling both modules:

1. Open **Manage form display** for the entity type.
2. Select **Simple hierarchical select: Chosen** as the field widget.
3. Save the form display.

Chosen variants are also available for supported exposed Views taxonomy filters. Chosen-specific exposed-filter settings can override the global Chosen configuration, including search behavior and placeholder text.

## Media Views integration

The included `shs_media` submodule provides an SHS-compatible taxonomy depth filter for Views that query Media entities.

Enable it when a Media View needs hierarchical taxonomy filtering with depth support:

```bash
drush en shs_media
```

## Developer API

SHS provides alter hooks for customizing individual widgets and term responses. See [`shs.api.php`](shs.api.php) for complete examples.

Important hooks include:

- `hook_shs_class_definitions_alter()`
- `hook_shs_FIELDNAME_class_definitions_alter()`
- `hook_shs_js_settings_alter()`
- `hook_shs_FIELDNAME_js_settings_alter()`
- `hook_shs_term_data_alter()`
- `hook_shs__bundle_BUNDLE_NAME__term_data_alter()`
- `hook_shs__field_IDENTIFIER__term_data_alter()`
- `hook_shs_term_data_response_alter()`
- `hook_shs__bundle_BUNDLE_NAME__term_data_response_alter()`
- `hook_shs__field_IDENTIFIER__term_data_response_alter()`

Term data is loaded on demand and returned through the SHS JSON endpoint. Altering uncached term data affects the data before it is cached; response alter hooks run against the serialized response.

## Branch notes

The 2.0.x branch is the stable feature line. It includes the core SHS widget, formatter, Views integration, Search API Views integration, Chosen integration, and Media Views integration.

Inline taxonomy term creation and Select2 integration are not available in this branch.

## Maintainers

- [Joseph Olstad (joseph.olstad)](https://www.drupal.org/u/joseph.olstad)
- [Stephen Mulvihill (smulvih2)](https://www.drupal.org/u/smulvih2)
- [Stefan Borchert (stborchert)](https://www.drupal.org/u/stborchert)

## Sponsorship

This project was previously sponsored by [undpaul](https://www.undpaul.de), Drupal experts providing professional Drupal development services.

Currently under the stewardship of Entreprise 7pro.ca Inc and Cinder Systems Corp.

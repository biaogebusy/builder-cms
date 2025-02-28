# CSV Serialization

The CSV Serialization module provides a CSV encoder for Drupal 8 Serialization
API. This enables the CSV format to be used for data input and output in various
circumstances.

For a full description of the module, visit the
[project page](https://www.drupal.org/project/csv_serialization).

Submit bug reports and feature suggestions, or track changes in the
[issue queue](https://www.drupal.org/project/issues/csv_serialization).

Notes about the CSV encoder:

The CSV format has a number of inherent limitations not present in other formats
(e.g., JSON or XML). Namely, they are:
- A CSV cannot support an array with a depth greater than three
- Each row in a CSV must share a common set of headers with all other rows

For these reasons, the CSV format is not well-suited for encoding all data
structures--only data with a specific structure. The provided CSV encoder
does not support data structures that do not meet these limitations.


## Table of contents

- Requirements
- Installation
- Configuration
- Maintainers


## Requirements

You should [use Composer to manage your Drupal site dependencies](https://www.drupal.org/docs/develop/using-composer/using-composer-to-manage-drupal-site-dependencies#managing-contributed). This may require several modifications to your application's root composer.json. __You must modify your composer.json in accordance with the linked documentation before following the installation instructions__. Please [read the documentation](https://www.drupal.org/docs/develop/using-composer/using-composer-to-manage-drupal-site-dependencies#managing-contributed) if you are not familiar with the specifics of managing a Drupal site with Composer.


## Installation

- Download the csv_serialization module via Composer: `composer require drupal/csv_serialization`. __This will only work if your Drupal application meets the requirements listed above__. Alternatively, you may use [Ludwig](https://www.drupal.org/project/ludwig).


## Configuration

1. Navigate to Administration > Extend and enable the module.
2. Navigate to Administration > Structure > Views and create a new view.
3. Add a "Rest Export" display to the view.
4. Check ONLY "csv" for Accepted request formats under `Format > Serializer > Settings`.
5. Set a path for the View display under Path Settings.
6. Change Format > Show to "fields".
7. Add fields to the view.
8. Verify that content exists which should be displayed in the view.
9. Save the view.

Visit the path that you set for the view and add this additional query string:
"?_format=csv".
A CSV file should be automatically downloaded when you visit the URL


## Maintainers

- Matthew Grasmick - [grasmash](https://www.drupal.org/u/grasmash)
- Mark Dorison - [markdorison](https://www.drupal.org/u/markdorison)

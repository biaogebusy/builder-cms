# Charts

Transform **data** into **information**. The Charts module for Drupal enables
you to build dynamic charts without needing to write a line of code. If you are
comfortable coding, you can use the Charts API to generate or update charts.
Instructions for creating charts are included below.

There are many charting libraries (also sometimes referred to as providers)
that you can find online. Each has their own benefits, drawbacks, and APIs. To
use one of them **without** this module, you would need to 1) be familiar with
the charting library's API, 2) add its JavaScript file(s) in your page, and 3)
write code for every chart you want to include. That's a lot of effort, and it
requires knowledge and access that many Drupal site builders will not have.

Charts is designed so that anyone who wants a chart on their site can have a
chart -- and be happy with it.

## How Does The Charts Module Work (Technically)?

The Charts module takes data and configuration from your site, and with the help
of a submodule, organizes it into a JSON object that is saved as an attribute
on an HTML element on your page; the JSON objected is handed to your selected
charting library, which renders it into a chart.

## Charting Providers / Libraries

Out of the box, you will be able to use 5 charting solutions (referred to as
"providers" or "libraries". Each of them has particular advantages and
disadvantages.

* Billboard.js: This library is a fork of the C3 charting library. It has
additional features, such as radar charts.
* C3.js: This library is a D3-based reusable chart library that makes it easy
  to generate D3-based charts.
* Chart.js: This is a simple yet flexible JavaScript charting for designers &
  developers.
* Google Charts: This library generates interactive charts using SVG and VML.
* Highcharts: This library is one of the premier solutions for generating
  charts. Although it is very powerful and aesthetically pleasing with smooth
  animations, it requires a commercial license. It's free for non-commercial
  use. See http://www.highcharts.com

## Chart Types

Each charting library has its own set of chart types. The Charts module
focuses on chart types that are available in *most* of the libraries.
To avoid a confusing user interface, the Charts module no longer includes
chart types that are not supported by the selected library in the Chart
Type selection. For example, if you select the Chart.js library, you will
not see the "Gauge" chart type.

## Installing Libraries

All the Charts submodules default to using a content delivery network (CDN) to
pull in the necessary JavaScript files unless a local copy is present, or you
have disabled the CDN option on the "Charts configuration" page
(`/admin/config/content/charts`).

Below are a couple outlines for adding the libraries locally.

### Using Composer

1. Ensure that you have the `composer/installers` package installed.
2. Ensure you have an installer-paths for the drupal-library type. Such as in
   the composer.json of
   https://github.com/drupal-composer/drupal-project/blob/9.x/composer.json
3. In each submodule, there is a README.md file that has code to add to your
   site's composer.json file.
4. After updating your project's composer.json file, run the 'composer require'
   command specified in each submodule's README.md file. For example, if you are
   using Google Charts, step three would mean adding:
```json
{
    "type": "package",
    "package": {
        "name": "google/charts",
        "version": "45",
        "type": "drupal-library",
        "extra": {
            "installer-name": "google_charts"
        },
        "dist": {
            "url": "https://www.gstatic.com/charts/loader.js",
            "type": "file"
        },
        "require": {
            "composer/installers": "^1.0 || ^2.0"
        }
    }
}
```
   to your project's composer.json, and then running:
   `composer require --prefer-dist google/charts:45`

### Using Composer and wikimedia/composer-merge-plugin:

1. Ensure that you have the `wikimedia/composer-merge-plugin` package
   installed.
2. Ensure that you have the `oomphinc/composer-installers-extender` package
   installed.
3. Add an "installer-types" section in the "extra" of your project
   composer.json file, make sure you have "npm-asset" listed. For example:
```json
"installer-types": [
    "npm-asset"
],
```
5. In the "installer-paths" section in the "extra" of your project
   composer.json file, ensure you have the types drupal-library and npm-asset.
   For example:
```json
"web/libraries/{$name}": [
    "type:drupal-library",
    "type:npm-asset"
],
```
6. Add a "merge-plugin" section in the "extra" of your project composer.json
   file, so that the composer.json file of the submodules you want is included.
   For example:
```json
"merge-plugin": {
    "include": [
        "web/modules/contrib/charts/modules/charts_highcharts/composer.json"
    ]
},
```
7. Run the `composer require` specified in the submodule's README.md file

## Creating Charts in the UI

This module provides a configuration page at /admin/config/content/charts.
You may set site-wide defaults on this page (for example: set the default color
scheme). It also has library-specific configuration available after saving a
default library.

There are three options for creating a chart within the UI:
1. Using Views
2. Using a Chart Field
3. Using a Chart Block

### Creating Charts with Views

1. Create a new view:
   Visit admin/structure/views/add and select the display format of "Chart" for
   your new page or block.

2. Add a label field:
   Under the "Fields" section, add a field you would like to be used as labels
   along one axis of the chart (or slices of the pie). If you are visualizing
   an Event content type, you might select `title`.

3. Add a data field or fields:
   Now add a second field that will be used to determine the data values. If you
   are visualizing an Event content type, this field might be
   `field_number_attendees`. The label you give this field will be used in the
   chart's legend to represent this series. Do this again for each different
   quantity you would like to chart. Note that some chart types (e.g. "pie")
   only support a single data column.

4. Configure the chart display:
   Click on the "Settings" link in the "Format" section to configure the chart.
   Select your chart library, type, the label field, and the data provider.
   There are many other options to customize your chart. Some options may not
   be available to all chart types and will be adjusted based on the type
   selected.

5. Save your view.

As described in the steps above - if you have a field representing a number, you
do not necessarily need to aggregate your view. If the content you want to
visualize does not include a numeric field, you may need to aggregate your view
and use the `count` function to generate a number for your data field.

**Tip:** You may find it easier to start with a "Table" display and convert it
to a chart display after setting up the data. It can be easier to visualize
what the result of the chart will be if it's been laid out in a table first.

### Creating Combo Charts

If you are creating your chart in Views and want to a chart that includes
multiple chart types (such as columns and a line - aka a "combo" chart), add a
new display of type "Chart attachment". Use the middle column of this display
to attach the "Chart attachment" to a parent display (this is required). In
this section, you can also instruct the "Chart attachment" to inherit exposed
or contextual filters and if it should use the primary y-axis or a secondary
y-axis. You still need to configure the "Settings" in the "Format" section.

## Creating Charts in the UI with a Chart Field

If you want a chart in your entity (e.g. "node"), but don't need its data to be
dynamic (like Views could generate), you can use a Chart field. Add this field
to your bundle (e.g. "content type") via the "Manage Fields" tab - like you
would any other field. When you add a new entity, you can select the charting
library, chart type, and other configurations. You can add data using a CSV or
by manually adding it into the table input. The chart will show on your entity
after saving.

## Creating Charts in the UI with a Chart Block

If you are using Layout Builder or want to place a chart on several pages, and
the chart does not need dynamic data (like Views could generate), you can use a
Chart Block. When you add a Chart Block, you can select the charting library,
chart type, and other configurations. You can add data using a CSV or by
manually adding it into the table input.

## Creating Charts Using the API

Charts are elements that can be rendered using a Drupal render array. Please
refer to the `charts.api.php` file and also look at the included submodule,
`charts_api_examples` (specifically the controller, which has many examples).

## Debugging Your Charts

There is a checkbox in the "Advanced" tab of the config form
(/admin/config/content/charts/advanced) that enabled debugging. Checking this
box will enable a collapsible "details" element that includes the JSON
object generated by the Charts module - this can often be added directly
into a code playground provided by the charting library. If you are
implementing your own Charts submodule, you can add a line like this into
your charts_MYMODULE.js file (it may need modification):
```
if (element.nextElementSibling &&
element.nextElementSibling.hasAttribute('data-charts-debug-container')) {
   element.nextElementSibling.querySelector('code').innerText = JSON.stringify(
       config,
       null,
       ' '
   );
}
```

## Support

For bug reports and feature requests please use the Drupal.org issue tracker:
https://www.drupal.org/project/issues/charts.

We welcome your support in improving code documentation, tests, and providing
example use-cases not addressed by the existing module.

If you are interested in creating your own submodule for a library not
currently supported, please contact
[@andileco](https://www.drupal.org/u/andileco).

#Charts API Example

This module shows you how to create charts using render arrays. There are
numerous examples in the `ChartsApiExample.php` controller file. In the
`charts_api_example.module` file, you can also see how some hooks described in
`charts.api.php` can be used. One hook overrides a chart using PHP. Another
overrides with JavaScript - this is necessary in some cases when the charting
library API is expecting a function rather than a string. You can read more
about overriding with JavaScript here:
https://www.drupal.org/project/charts/issues/3197574#comment-13999765

To see the examples in your site, navigate to: `/charts/example/display`

Settings for these charts are set in the Charts configuration page
(`/admin/config/content/charts`).

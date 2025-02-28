# Excel Serialization

This module provides an Excel encoder for the Drupal 8 Serialization API. This
enables the XLS format to be used for data output (and potentially input,
eventually). For example:

  * Drupal 8's REST module can return data in XLS format.
  * Views can output XLS data via a 'REST Export' view, or a
    [Data Export](https://www.drupal.org/project/views_data_export) view.
  * Module developers can leverage XLS as a format when using the Serialization
    API.

#### Installation

  * Download and install
    [PHPOffice/SpreadSheet](https://github.com/PHPOffice/PhpSpreadsheet). The preferred
    installation method is to
    [use Composer](https://www.drupal.org/node/2404989).
  * Enable the `xls_serialization` module.

#### Creating an XLS view

  1. Create a new view
  2. Add an *Excel Export* display for more formatting options.
     Otherwise add a *REST Export* view.
  3. Check either 'xlsx' or 'xls' for the accepted request formats under
     `Format -> Excel export -> Settings`.
  4. Add desired fields to the view.
  5. Add a path, and optionally, a filename pattern.

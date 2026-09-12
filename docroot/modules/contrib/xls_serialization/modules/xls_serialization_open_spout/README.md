# Serialization (Excel using OpenSpout library)

This module provides an alternative implementation of the Excel encoder for the
Drupal Serialization API. This enables the XLSX format to be used for data
output using the OpenSpout library.

## Summary

PHPSpreadsheet, used by the Excel Serialization module, is not very performant
for large files. This submodule replaces the XLSX serializer with an alternative
that uses the much faster OpenSpout library. The use of OpenSpout comes at the
expense of many features provided by XLSX serializer, such as custom metadata,
conditional styles, auto sizing columns and auto sizing rows.

#### Installation

* Download and install
  [OpenSpout/OpenSpout](https://github.com/openspout/openspout). The preferred
  installation method is to
  [use Composer](https://www.drupal.org/node/2404989).

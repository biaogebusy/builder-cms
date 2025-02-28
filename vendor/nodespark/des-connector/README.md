# DESConnector

[![Build Status](https://travis-ci.org/nodespark/des-connector.svg?branch=5.x)](https://travis-ci.org/nodespark/des-connector)

Drupal Elasticsearch Connector for Drupal provides and abstraction of
the Elasticsearch-PHP library (https://github.com/elastic/elasticsearch-php)
and Elastica library (https://github.com/ruflin/Elastica).

Version 6.x of the DES Connector change the primary library to Elastica because it is using the
official library in the core and provide a good abstraction over it.

DES Connector will add one additional level of abstraction in order to help with Drupal integration
and make sure we have the correct interfaces so we can safely change the services when needed.

This library will be used in combination with Elasticsearch Connector
module for Drupal (https://www.drupal.org/project/elasticsearch_connector)
and will allow for better and faster D7 and D8 development.


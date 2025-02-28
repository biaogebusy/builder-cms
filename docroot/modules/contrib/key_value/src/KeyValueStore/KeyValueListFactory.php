<?php

namespace Drupal\key_value\KeyValueStore;

use Drupal\Core\KeyValueStore\KeyValueFactory;

class KeyValueListFactory extends KeyValueFactory implements KeyValueListFactoryInterface {

  const DEFAULT_SERVICE = 'keyvalue.list.database';

  const SPECIFIC_PREFIX = 'keyvalue_list_service_';

  const DEFAULT_SETTING = 'keyvalue_list_default';

}

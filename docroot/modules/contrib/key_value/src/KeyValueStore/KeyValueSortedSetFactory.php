<?php

namespace Drupal\key_value\KeyValueStore;

use Drupal\Core\KeyValueStore\KeyValueFactory;

class KeyValueSortedSetFactory extends KeyValueFactory implements KeyValueSortedSetFactoryInterface {

  const DEFAULT_SERVICE = 'keyvalue.sorted_set.database';

  const SPECIFIC_PREFIX = 'keyvalue_sorted_set_service_';

  const DEFAULT_SETTING = 'keyvalue_sorted_set_default';

}

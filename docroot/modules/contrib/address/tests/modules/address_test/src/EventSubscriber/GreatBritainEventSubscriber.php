<?php

namespace Drupal\address_test\EventSubscriber;

use CommerceGuys\Addressing\AddressFormat\AdministrativeAreaType;
use Drupal\address\Event\AddressEvents;
use Drupal\address\Event\AddressFormatEvent;
use Drupal\address\Event\SubdivisionsEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Adds a county field and a predefined list of counties for Great Britain.
 *
 * Counties are not provided by the library because they're not used for
 * addressing. However, sites might want to add them for other purposes.
 */
class GreatBritainEventSubscriber implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[AddressEvents::ADDRESS_FORMAT][] = ['onAddressFormat'];
    $events[AddressEvents::SUBDIVISIONS][] = ['onSubdivisions'];
    return $events;
  }

  /**
   * Alters the address format for Great Britain.
   *
   * @param \Drupal\address\Event\AddressFormatEvent $event
   *   The address format event.
   */
  public function onAddressFormat(AddressFormatEvent $event) {
    $definition = $event->getDefinition();
    if ($definition['country_code'] == 'GB') {
      $definition['format'] = $definition['format'] . "\n%administrativeArea";
      $definition['administrative_area_type'] = AdministrativeAreaType::COUNTY;
      $definition['subdivision_depth'] = 1;
      $event->setDefinition($definition);
    }
  }

  /**
   * Provides the subdivisions for Great Britain.
   *
   * Note: Provides just the Welsh counties. A real subscriber would include
   * the full list, taken from https://www.iso.org/obp/ui/#iso:code:3166:GB.
   *
   * @param \Drupal\address\Event\SubdivisionsEvent $event
   *   The subdivisions event.
   */
  public function onSubdivisions(SubdivisionsEvent $event) {
    // For administrative areas $parents is an array with just the country code.
    // Otherwise it also contains the parent subdivision codes. For example,
    // if we were defining cities in California, $parents would be ['US', 'CA'].
    $parents = $event->getParents();
    if ($event->getParents() != ['GB']) {
      return;
    }

    $definitions = [
      'country_code' => $parents[0],
      'parents' => $parents,
      'subdivisions' => [
        // Subdivisions can be keyed by name, or by an ISO code such as "CRF".
        // If the subdivision code (displayed on the formatted address) or the
        // subdivision name (displayed in dropdowns) do not match the key
        // (e.g. "Cardiff"), they should be explicitly set in the array.
        'Anglesey' => [],
        'Blaenau Gwent' => [],
        'Bridgend' => [],
        'Caerphilly' => [],
        'Cardiff' => [],
        'Carmarthenshire' => [],
        'Ceredigion' => [],
        'Conwy' => [],
        'Denbighshire' => [],
        'Flintshire' => [],
        'Gwynedd' => [],
        'Merthyr Tydfil' => [],
        'Monmouthshire' => [],
        'Neath Port Talbot' => [],
        'Newport' => [],
        'Pembrokeshire' => [],
        'Powys' => [],
        'Rhondda Cynon Taf' => [],
        'Swansea' => [],
        'Tarfaen' => [],
        'Vale of Glamorgan' => [],
        'Wrexham' => [],
      ],
    ];
    $event->setDefinitions($definitions);
  }

}

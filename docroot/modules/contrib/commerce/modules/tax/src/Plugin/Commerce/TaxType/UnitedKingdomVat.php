<?php

namespace Drupal\commerce_tax\Plugin\Commerce\TaxType;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\commerce_tax\Attribute\CommerceTaxType;
use Drupal\commerce_tax\TaxZone;

/**
 * Provides the United Kingdom VAT tax type.
 */
#[CommerceTaxType(
  id: "united_kingdom_vat",
  label: new TranslatableMarkup("United Kingdom VAT"),
)]
class UnitedKingdomVat extends LocalTaxTypeBase {

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);
    $form['rates'] = $this->buildRateSummary();
    // Replace the phrase "tax rates" with "VAT rates" to be more precise.
    $form['rates']['#markup'] = $this->t('The following VAT rates are provided:');

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function buildZones() {
    $zones['gb'] = new TaxZone([
      'id' => 'gb',
      'label' => $this->t('United Kingdom'),
      'display_label' => $this->t('VAT'),
      'territories' => [
        ['country_code' => 'GB'],
        ['country_code' => 'IM'],
      ],
      'rates' => [
        [
          'id' => 'standard',
          'label' => $this->t('Standard'),
          'percentages' => [
            ['number' => '0.2', 'start_date' => '2011-01-04'],
          ],
          'default' => TRUE,
        ],
        [
          'id' => 'reduced',
          'label' => $this->t('Reduced'),
          'percentages' => [
            ['number' => '0.05', 'start_date' => '1997-09-01'],
          ],
        ],
        [
          'id' => 'zero',
          'label' => $this->t('Zero'),
          'percentages' => [
            ['number' => '0', 'start_date' => '1973-01-01'],
          ],
        ],
      ],
    ]);

    return $zones;
  }

}

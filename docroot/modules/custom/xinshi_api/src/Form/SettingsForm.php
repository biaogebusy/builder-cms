<?php

namespace Drupal\xinshi_api\Form;

use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\node\Entity\NodeType;
use Drupal\taxonomy\Entity\Vocabulary;

class SettingsForm extends ConfigFormBase {

  private $configName = 'xinshi_api.settings';

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'xinshi_api_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [$this->configName];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $config = $this->configFactory->get($this->configName);
    $form['page'] = [
      '#type' => 'details',
      '#title' => t('Page Settings'),
    ];

    $form['page']['not_found'] = [
      '#type' => 'text_format',
      '#title' => t('Page Not Found Json'),
      '#format' => 'json',
      '#allowed_formats' => ['json'],
      '#default_value' => $config->get('page')['not_found'] ?? '',
    ];

    $form['page']['access_denied'] = [
      '#type' => 'text_format',
      '#title' => t('Access Denied Json'),
      '#format' => 'json',
      '#allowed_formats' => ['json'],
      '#default_value' => $config->get('page')['access_denied'] ?? '',
    ];

    $form['cache_enable'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable entity context cache'),
      '#default_value' => $config->get('cache_enable'),
    ];

    $form['entity'] = [
      '#type' => 'details',
      '#title' => t('Entity Cache Context'),
      '#open' => TRUE,
    ];


    $form['entity']['node_cache'] = [
      '#type' => 'table',
      '#caption' => $this->t('Node context cache'),
      '#header' => [$this->t('Content type'), $this->t('Cache')],
      '#states' => [
        'visible' => [
          ':input[name="cache_enable"]' => ['checked' => TRUE],
        ],
      ],
    ];
    /** @var NodeType[] $node_types */
    $types = NodeType::loadMultiple();
    foreach ($types as $key => $type) {
      $cache_context = $config->get('node_cache');
      $form['entity']['node_cache'][$key]['name'] = [
        '#type' => 'label',
        '#title' => $type->label(),
      ];
      $form['entity']['node_cache'][$key]['context'] = [
        '#type' => 'checkboxes',
        '#title_display' => 'invisible',
        '#options' => [
          'user' => $this->t('User'),
        ],
        '#default_value' => isset($cache_context[$key]['context']) ? $cache_context[$key]['context'] : [],
      ];
    }

    $form['entity']['taxonomy_term_cache'] = [
      '#type' => 'table',
      '#caption' => $this->t('Vocabulary context cache'),
      '#header' => [$this->t('vocabulary'), $this->t('Cache')],
    ];
    /** @var Vocabulary[] $node_types */
    $types = Vocabulary::loadMultiple();
    foreach ($types as $key => $type) {
      $cache_context = $config->get('taxonomy_term_cache');
      $form['entity']['taxonomy_term_cache'][$key]['name'] = [
        '#type' => 'label',
        '#title' => $type->label(),
      ];
      $form['entity']['taxonomy_term_cache'][$key]['context'] = [
        '#type' => 'checkboxes',
        '#title_display' => 'invisible',
        '#options' => [
          'user' => $this->t('User'),
        ],
        '#default_value' => isset($cache_context[$key]['context']) ? $cache_context[$key]['context'] : [],
      ];
    }

    $form['entity']['user_cache'] = [
      '#type' => 'table',
      '#caption' => $this->t('User cache'),
      '#header' => [$this->t('vocabulary'), $this->t('Cache')],
    ];

    $key = 'user';
    $cache_context = $config->get('user_cache');
    $form['entity']['user_cache'][$key]['name'] = [
      '#type' => 'label',
      '#title' => $this->t("User"),
    ];
    $form['entity']['user_cache'][$key]['context'] = [
      '#type' => 'checkboxes',
      '#title_display' => 'invisible',
      '#options' => [
        'user' => $this->t('User'),
      ],
      '#default_value' => isset($cache_context[$key]['context']) ? $cache_context[$key]['context'] : [],
    ];


    $form['debug'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Debug'),
      '#default_value' => $config->get('debug'),
    ];
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $url = $form_state->getValue('front_domain');
    if ($url && !UrlHelper::isExternal($url)) {
      $form_state->setErrorByName('front_domain', t('Invalid url'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->configFactory->getEditable($this->configName);
    $config->set('page', [
      'not_found' => $form_state->getValue(['not_found', 'value']),
      'access_denied' => $form_state->getValue(['access_denied', 'value'])
    ])
      ->set('node_cache', $form_state->getValue('node_cache'))
      ->set('taxonomy_term_cache', $form_state->getValue('taxonomy_term_cache'))
      ->set('cache_enable', $form_state->getValue('cache_enable'))
      ->set('user_cache', $form_state->getValue('user_cache'))
      ->set('debug', $form_state->getValue('debug'))
      ->save();
    parent::submitForm($form, $form_state);
  }
}

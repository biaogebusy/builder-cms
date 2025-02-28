<?php

namespace Drupal\layout_library\Form;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines a form for adding a layout library layout.
 */
class LayoutAddForm extends EntityForm {

  /**
   * Entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Bundle info.
   *
   * @var \Drupal\Core\Entity\EntityTypeBundleInfoInterface
   */
  protected $bundleInfo;

  /**
   * Messenger.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * Constructs a new LayoutAddForm object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   Entity type manager.
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $bundleInfo
   *   Bundle info.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   Messenger.
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager, EntityTypeBundleInfoInterface $bundleInfo, MessengerInterface $messenger) {
    $this->entityTypeManager = $entityTypeManager;
    $this->bundleInfo = $bundleInfo;
    $this->messenger = $messenger;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('entity_type.bundle.info'),
      $container->get('messenger')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);

    $form['#title'] = $this->t('Add layout');

    $form['label'] = [
      '#type' => 'textfield',
      '#title' => t('Label'),
      '#maxlength' => 255,
      '#description' => t("Provide a label for this layout to help identify it in the administration pages."),
      '#required' => TRUE,
    ];
    $form['id'] = [
      '#type' => 'machine_name',
      '#machine_name' => [
        'exists' => '\Drupal\layout_library\Entity\Layout::load',
      ],
      '#maxlength' => EntityTypeInterface::BUNDLE_MAX_LENGTH,
    ];

    $entityTypes = $this->entityTypeManager->getDefinitions();
    $options = [];
    foreach ($entityTypes as $id => $entityType) {
      if (!$entityType->entityClassImplements(ContentEntityInterface::class)) {
        continue;
      }
      foreach ($this->bundleInfo->getBundleInfo($id) as $bundleId => $bundle) {
        $options[(string) $entityType->getLabel()]["$id:$bundleId"] = $bundle['label'];
      }
    }

    $form['_entity_type'] = [
      '#type' => 'select',
      '#options' => $options,
      '#title' => $this->t('Entity Type'),
      '#description' => $this->t('Choose the entity type and bundle that this layout will be used for.'),
    ];

    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => t('Save'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function buildEntity(array $form, FormStateInterface $form_state) {
    $entity = parent::buildEntity($form, $form_state);
    list($entity_type_id, $bundle) = explode(':', $form_state->getValue('_entity_type'), 2);
    $entity->set('targetEntityType', $entity_type_id);
    $entity->set('targetBundle', $bundle);
    return $entity;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $layout = $this->entity;
    $layout->save();

    // @todo initialize the layout with each field available for the entity?
    $this->messenger->addMessage($this->t('Layout %label has been added.', ['%label' => $layout->label()]));
    $this->logger('layout_library')->notice('Layout %label has been added.', ['%label' => $layout->label()]);

    // Redirect to edit the layout.
    $form_state->setRedirectUrl($this->entity->toUrl('layout-builder'));
  }

}

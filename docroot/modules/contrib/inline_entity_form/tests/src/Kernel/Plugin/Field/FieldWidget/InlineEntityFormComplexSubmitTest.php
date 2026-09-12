<?php

declare(strict_types=1);

namespace Drupal\Tests\inline_entity_form\Kernel\Plugin\Field\FieldWidget;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormState;
use Drupal\inline_entity_form\Plugin\Field\FieldWidget\InlineEntityFormComplex;
use Drupal\KernelTests\KernelTestBase;

/**
 * Tests submitConfirmRemove() delete decision logic.
 *
 * @group inline_entity_form
 */
class InlineEntityFormComplexSubmitTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'inline_entity_form',
    'system',
    'user',
  ];

  /**
   * Tests delete queue decisions for submitConfirmRemove().
   *
   * @dataProvider providerSubmitConfirmRemoveDeleteDecisions
   */
  public function testSubmitConfirmRemoveDeleteDecisions(string $removed_reference, array $row_form_values, bool $should_queue_delete): void {
    $entity = $this->createMock(EntityInterface::class);
    $entity->expects($this->once())
      ->method('id')
      ->willReturn(1);

    $form = [
      'ief_widget' => [
        '#ief_root' => TRUE,
        '#ief_id' => 'ief_test',
        'entities' => [
          0 => [
            'form' => [
              '#entity' => $entity,
              '#parents' => ['entity_form', 'entities', 0, 'form'],
            ],
          ],
        ],
      ],
    ];

    $form_state = new FormState();
    $form_state->setValues([
      'entity_form' => [
        'entities' => [
          0 => [
            'form' => $row_form_values,
          ],
        ],
      ],
    ]);
    $form_state->setTriggeringElement([
      '#ief_row_delta' => 0,
      '#removed_reference' => $removed_reference,
      '#array_parents' => ['ief_widget', 'actions', 'ief_remove_confirm'],
    ]);
    $form_state->set([
      'inline_entity_form',
      'ief_test',
    ], [
      'entities' => [
        0 => [
          'entity' => $entity,
        ],
      ],
      'delete' => [],
    ]);

    InlineEntityFormComplex::submitConfirmRemove($form, $form_state);

    $widget_state = $form_state->get(['inline_entity_form', 'ief_test']);
    $this->assertArrayNotHasKey(0, $widget_state['entities']);
    $this->assertSame($should_queue_delete, !empty($widget_state['delete']));
  }

  /**
   * Provides cases for submitConfirmRemove() delete decision logic.
   */
  public static function providerSubmitConfirmRemoveDeleteDecisions(): array {
    return [
      'optional without delete key' => [
        InlineEntityFormComplex::REMOVED_OPTIONAL,
        [],
        FALSE,
      ],
      'optional with delete key set' => [
        InlineEntityFormComplex::REMOVED_OPTIONAL,
        [InlineEntityFormComplex::REMOVED_DELETE => 1],
        TRUE,
      ],
      'delete always without delete key' => [
        InlineEntityFormComplex::REMOVED_DELETE,
        [],
        TRUE,
      ],
      'keep always without delete key' => [
        InlineEntityFormComplex::REMOVED_KEEP,
        [],
        FALSE,
      ],
    ];
  }

}

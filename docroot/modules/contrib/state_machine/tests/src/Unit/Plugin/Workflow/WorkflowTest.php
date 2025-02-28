<?php

namespace Drupal\Tests\state_machine\Unit\Plugin\Workflow;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\state_machine\Guard\GuardFactoryInterface;
use Drupal\state_machine\Guard\GuardInterface;
use Drupal\state_machine\Plugin\Workflow\Workflow;
use Drupal\Tests\UnitTestCase;
use Prophecy\Argument;

/**
 * @coversDefaultClass \Drupal\state_machine\Plugin\Workflow\Workflow
 * @group state_machine
 */
class WorkflowTest extends UnitTestCase {

  /**
   * @covers ::getId
   */
  public function testGetId() {
    $guard_factory = $this->prophesize(GuardFactoryInterface::class);
    $plugin_definition = [
      'id' => 'test id',
      'label' => 'test label',
      'states' => [],
      'transitions' => [],
    ];
    $workflow = new Workflow([], 'test', $plugin_definition, $guard_factory->reveal());

    $this->assertEquals('test id', $workflow->getId());
  }

  /**
   * @covers ::getLabel
   */
  public function testGetLabel() {
    $guard_factory = $this->prophesize(GuardFactoryInterface::class);
    $plugin_definition = [
      'states' => [],
      'transitions' => [],
      'label' => 'test label',
    ];
    $workflow = new Workflow([], 'test', $plugin_definition, $guard_factory->reveal());

    $this->assertEquals('test label', $workflow->getLabel());
  }

  /**
   * @covers ::getStates
   * @covers ::getState
   */
  public function testGetStates() {
    $guard_factory = $this->prophesize(GuardFactoryInterface::class);
    $translation = $this->prophesize(TranslationInterface::class);
    $translation->translateString(Argument::type(TranslatableMarkup::class))
      ->willReturn('Draft');
    $plugin_definition = [
      'states' => [
        'draft' => [
          'label' => 'Draft',
        ],
      ],
      'transitions' => [],
    ];
    $workflow = new Workflow([], 'test', $plugin_definition, $guard_factory->reveal());
    $workflow->setStringTranslation($translation->reveal());

    $state = $workflow->getState('draft');
    $this->assertEquals('draft', $state->getId());
    $state->setStringTranslation($translation->reveal());
    $this->assertEquals('Draft', $state->getLabel());
    $this->assertEquals(['draft' => $state], $workflow->getStates());
  }

  /**
   * @covers ::getTransitions
   * @covers ::getTransition
   */
  public function testGetTransitions() {
    $translation = $this->prophesize(TranslationInterface::class);
    $translation->translateString(Argument::type(TranslatableMarkup::class))
      ->willReturn('Publish');
    $guard_factory = $this->prophesize(GuardFactoryInterface::class);
    $plugin_definition = [
      'states' => [
        'draft' => [
          'label' => 'Draft',
        ],
        'published' => [
          'label' => 'Published',
        ],
      ],
      'transitions' => [
        'publish' => [
          'label' => 'Publish',
          'from' => ['draft'],
          'to' => 'published',
        ],
      ],
    ];
    $workflow = new Workflow([], 'test', $plugin_definition, $guard_factory->reveal());

    $transition = $workflow->getTransition('publish');
    $transition->setStringTranslation($translation->reveal());
    $this->assertEquals('publish', $transition->getId());
    $this->assertEquals('Publish', $transition->getLabel());
    $this->assertEquals(['draft' => $workflow->getState('draft')], $transition->getFromStates());
    $this->assertEquals($workflow->getState('published'), $transition->getToState());
    $this->assertEquals(['publish' => $transition], $workflow->getTransitions());
  }

  /**
   * @covers ::getPossibleTransitions
   */
  public function testGetPossibleTransitions() {
    $guard_factory = $this->prophesize(GuardFactoryInterface::class);
    $plugin_definition = [
      'states' => [
        'draft' => [
          'label' => 'Draft',
        ],
        'review' => [
          'label' => 'Review',
        ],
        'published' => [
          'label' => 'Published',
        ],
      ],
      'transitions' => [
        'send_to_review' => [
          'label' => 'Send to review',
          'from' => ['draft'],
          'to' => 'review',
        ],
        'publish' => [
          'label' => 'Publish',
          'from' => ['review'],
          'to' => 'published',
        ],
      ],
    ];
    $workflow = new Workflow([], 'test', $plugin_definition, $guard_factory->reveal());

    $transition = $workflow->getTransition('send_to_review');
    $this->assertEquals(['send_to_review' => $transition], $workflow->getPossibleTransitions('draft'));
    $transition = $workflow->getTransition('publish');
    $this->assertEquals(['publish' => $transition], $workflow->getPossibleTransitions('review'));
    $this->assertEquals([], $workflow->getPossibleTransitions('published'));
    // Passing an empty state should return all transitions.
    $this->assertEquals($workflow->getTransitions(), $workflow->getPossibleTransitions(''));
  }

  /**
   * @covers ::getAllowedTransitions
   */
  public function testGetAllowedTransitions() {
    $plugin_definition = [
      'states' => [
        'draft' => [
          'label' => 'Draft',
        ],
        'review' => [
          'label' => 'Review',
        ],
        'published' => [
          'label' => 'Published',
        ],
      ],
      'transitions' => [
        'send_to_review' => [
          'label' => 'Send to review',
          'from' => ['draft'],
          'to' => 'review',
        ],
        'publish' => [
          'label' => 'Publish',
          'from' => ['review'],
          'to' => 'published',
        ],
      ],
      'group' => 'default',
    ];
    $guard_allow = $this->prophesize(GuardInterface::class);
    $guard_allow
      ->allowed(Argument::cetera())
      ->willReturn(TRUE);
    $guard_deny_publish = $this->prophesize(GuardInterface::class);
    $guard_deny_publish
      ->allowed(Argument::cetera())
      ->will(function ($args) {
        // Allow only the send_to_review transition.
        return $args[0]->getId() == 'send_to_review';
      });
    $guard_factory = $this->prophesize(GuardFactoryInterface::class);
    $guard_factory
      ->get('default')
      ->willReturn([$guard_allow->reveal(), $guard_deny_publish->reveal()]);
    $workflow = new Workflow([], 'test', $plugin_definition, $guard_factory->reveal());

    $entity = $this->prophesize(EntityInterface::class)->reveal();
    $transition = $workflow->getTransition('send_to_review');
    $this->assertEquals(['send_to_review' => $transition], $workflow->getAllowedTransitions('draft', $entity));
    $this->assertEquals([], $workflow->getAllowedTransitions('review', $entity));
  }

  /**
   * @covers ::findTransition
   */
  public function testFindTransition() {
    $guard_factory = $this->prophesize(GuardFactoryInterface::class);
    $plugin_definition = [
      'states' => [
        'draft' => [
          'label' => 'Draft',
        ],
        'review' => [
          'label' => 'Review',
        ],
        'published' => [
          'label' => 'Published',
        ],
      ],
      'transitions' => [
        'send_to_review' => [
          'label' => 'Send to review',
          'from' => ['draft'],
          'to' => 'review',
        ],
        'publish' => [
          'label' => 'Publish',
          'from' => ['review'],
          'to' => 'published',
        ],
      ],
    ];
    $workflow = new Workflow([], 'test', $plugin_definition, $guard_factory->reveal());

    $transition = $workflow->getTransition('send_to_review');
    $this->assertEquals($transition, $workflow->findTransition('draft', 'review'));
    $this->assertNull($workflow->findTransition('foo', 'bar'));
  }

}

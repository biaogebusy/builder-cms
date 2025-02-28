<?php

namespace Drupal\Tests\jsonapi_extras\Kernel;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\file\Entity\File;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\Tests\image\Kernel\ImageFieldCreationTrait;
use Drupal\Tests\jsonapi\Kernel\JsonapiKernelTestBase;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;
use Drupal\user\RoleInterface;

/**
 * @coversDefaultClass \Drupal\jsonapi_extras\EntityToJsonApi
 * @group jsonapi
 * @group jsonapi_serializer
 * @group legacy
 *
 * @internal
 */
class EntityToJsonApiTest extends JsonapiKernelTestBase {

  use ImageFieldCreationTrait;

  /**
   * System under test.
   *
   * @var \Drupal\jsonapi_extras\EntityToJsonApi
   */
  protected $sut;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'jsonapi',
    'jsonapi_extras',
    'field',
    'node',
    'serialization',
    'system',
    'taxonomy',
    'text',
    'user',
    'file',
    'image',
  ];

  /**
   * Node type.
   *
   * @var \Drupal\node\Entity\NodeType
   */
  private $nodeType;

  /**
   * Vocabulary.
   *
   * @var \Drupal\taxonomy\Entity\Vocabulary
   */
  private $vocabulary;

  /**
   * Node.
   *
   * @var \Drupal\node\Entity\Node
   */
  private $node;

  /**
   * A user instance for the test.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $user;

  /**
   * A user instance for the test.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $user2;

  /**
   * Taxonomy term.
   *
   * @var \Drupal\taxonomy\TermInterface
   */
  protected $term1;

  /**
   * Taxonomy term.
   *
   * @var \Drupal\taxonomy\TermInterface
   */
  protected $term2;

  /**
   * File entity.
   *
   * @var \Drupal\file\FileInterface
   */
  protected $file;

  /**
   * Role.
   *
   * @var \Drupal\user\RoleInterface
   */
  protected $role;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    // Add the entity schemas.
    $this->installEntitySchema('node');
    $this->installEntitySchema('user');
    $this->installEntitySchema('taxonomy_term');
    $this->installEntitySchema('file');
    // Add the additional table schemas.
    $this->installSchema('system', ['sequences']);
    $this->installSchema('node', ['node_access']);
    $this->installSchema('user', ['users_data']);
    $this->installSchema('file', ['file_usage']);
    $this->nodeType = NodeType::create([
      'type' => 'article',
    ]);
    $this->nodeType->save();
    $this->createEntityReferenceField(
      'node',
      'article',
      'field_tags',
      'Tags',
      'taxonomy_term',
      'default',
      ['target_bundles' => ['tags']],
      FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED
    );

    $this->createImageField('field_image', 'article');

    $this->user = User::create([
      'name' => 'user1',
      'mail' => 'user@localhost',
    ]);
    $this->user2 = User::create([
      'name' => 'user2',
      'mail' => 'user2@localhost',
    ]);

    $this->user->save();
    $this->user2->save();

    $this->vocabulary = Vocabulary::create(['name' => 'Tags', 'vid' => 'tags']);
    $this->vocabulary->save();

    $this->term1 = Term::create([
      'name' => 'term1',
      'vid' => $this->vocabulary->id(),
    ]);
    $this->term2 = Term::create([
      'name' => 'term2',
      'vid' => $this->vocabulary->id(),
    ]);

    $this->term1->save();
    $this->term2->save();

    $this->file = File::create([
      'uri' => 'public://example.png',
      'filename' => 'example.png',
    ]);
    $this->file->save();

    $this->node = Node::create([
      'title' => 'dummy_title',
      'type' => 'article',
      'uid' => 1,
      'field_tags' => [
        ['target_id' => $this->term1->id()],
        ['target_id' => $this->term2->id()],
      ],
      'field_image' => [
        [
          'target_id' => $this->file->id(),
          'alt' => 'test alt',
          'title' => 'test title',
          'width' => 10,
          'height' => 11,
        ],
      ],
    ]);

    $this->node->save();

    $this->nodeType = NodeType::load('article');

    $this->role = Role::create([
      'id' => RoleInterface::ANONYMOUS_ID,
      'permissions' => [
        'access content',
      ],
    ]);
    $this->role->save();
    $this->sut = $this->container->get('jsonapi_extras.entity.to_jsonapi');
  }

  /**
   * @covers ::serialize
   * @covers ::normalize
   */
  public function testSerialize() {
    $entities = [
      [
        $this->node,
        ['field_tags'],
        [
          [
            'type' => 'taxonomy_term--tags',
            'id' => $this->term1->uuid(),
            'attributes' => [
              'drupal_internal__tid' => (int) $this->term1->id(),
              'name' => $this->term1->label(),
            ],
          ],
          [
            'type' => 'taxonomy_term--tags',
            'id' => $this->term2->uuid(),
            'attributes' => [
              'drupal_internal__tid' => (int) $this->term2->id(),
              'name' => $this->term2->label(),
            ],
          ],
        ],
      ],
      [$this->user, [], []],
      [$this->file, [], []],
      [$this->term1, [], []],
      // Make sure we also support configuration entities.
      [$this->nodeType, [], []],
    ];

    array_walk(
      $entities,
      function ($data) {
        [$entity, $include_fields, $expected_includes] = $data;
        $this->assertEntity($entity, $include_fields, $expected_includes);
      }
    );
  }

  /**
   * Test if the request doesn't linger on the request stack.
   *
   * @see https://www.drupal.org/project/jsonapi_extras/issues/3135950
   * @see https://www.drupal.org/project/jsonapi_extras/issues/3124805
   */
  public function testRequestStack() {
    /** @var \Symfony\Component\HttpFoundation\RequestStack $request_stack */
    $request_stack = $this->container->get('request_stack');
    $this->sut->serialize($this->node);
    $request = $request_stack->pop();
    $this->assertNotEquals($request->getPathInfo(), '/jsonapi/node/' . $this->nodeType->id() . '/' . $this->node->uuid(), 'The request from jsonapi_extras.entity.to_jsonapi should not linger in the request stack.');
  }

  /**
   * Checks entity's serialization/normalization.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to serialize/normalize.
   * @param string[] $include_fields
   *   The list of fields to include.
   * @param array[] $expected_includes
   *   The list of partial structures of the "included" key.
   */
  protected function assertEntity(
    EntityInterface $entity,
    array $include_fields = [],
    array $expected_includes = []
  ) {
    $output = $this->sut->serialize($entity, $include_fields);

    $this->assertTrue(is_string($output));
    $this->assertJsonApi(Json::decode($output));

    $output = $this->sut->normalize($entity, $include_fields);

    $this->assertTrue(is_array($output));
    $this->assertJsonApi($output);

    // Check the includes if they were passed.
    if (!empty($include_fields)) {
      $this->assertJsonApiIncludes($output, $expected_includes);
    }
  }

  /**
   * Helper to assert if a string is valid JSON:API.
   *
   * @param array $structured
   *   The JSON:API data to check.
   */
  protected function assertJsonApi(array $structured) {
    static::assertNotEmpty($structured['data']['type']);
    static::assertNotEmpty($structured['data']['id']);
    static::assertNotEmpty($structured['data']['attributes']);
    $this->assertTrue(is_string($structured['data']['links']['self']['href']));
  }

  /**
   * Shallowly checks the list of includes.
   *
   * @param array $structured
   *   The JSON:API data to check.
   * @param array[] $includes
   *   The list of partial structures of the "included" key.
   */
  protected function assertJsonApiIncludes(array $structured, array $includes) {
    static::assertFalse(
      empty($structured['included']),
      'The list of includes should is empty.'
    );

    foreach ($includes as $i => $include) {
      static::assertFalse(
        empty($structured['included'][$i]),
        sprintf('The include #%d does not exist.', $i)
      );
      static::assertSame(
        $include['type'],
        $structured['included'][$i]['type'],
        sprintf('The type of include #%d does not match expected value.', $i)
      );

      foreach ($include['attributes'] as $attribute => $expected_value) {
        static::assertSame(
          $expected_value,
          $structured['included'][$i]['attributes'][$attribute],
          sprintf(
            'The "%s" of include #%d doest match the expected value.',
            $attribute,
            $i
          )
        );
      }
    }
  }

}

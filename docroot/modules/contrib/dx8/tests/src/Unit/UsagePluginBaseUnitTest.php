<?php

namespace Drupal\Tests\cohesion\Unit;

use Drupal\Core\Extension\ThemeHandlerInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\StreamWrapper\StreamWrapperManager;
use Drupal\Core\Database\Connection;
use Drupal\cohesion\UsagePluginInterface;

/**
 * Class MockEntity
 *
 * @package Drupal\Tests\cohesion\Unit
 */
class MockEntity {
  protected $id;

  public function __construct($id) {
    $this->id = $id;
  }

  public function uuid() {
    return 'uuid=' . $this->id;
  }

  public function getFileUri() {
    return $this->id;
  }
}

/**
 * Class MockQuery
 *
 * @package Drupal\Tests\cohesion\Unit
 */
class MockQuery {
  protected $entities = [];

  public function condition($where, $is) {
    if ($where == 'class_name') {
      $this->entities[] = $is;
      return $this;
    }

    if ($where == 'default') {
      $this->entities[] = 'default';
      return $this;
    }
  }

  public function execute() {
    return $this->entities;
  }
}

/**
 * Class MockStreamWrapperInstance
 *
 * @package Drupal\Tests\cohesion\Unit
 */
class MockStreamWrapperInstance {
  protected $id;

  public function __construct($id) {
    $this->id = $id;
  }

  public function getDirectoryPath() {
    return 'directory/' . $this->id . '/';
  }
}

/**
 * This abstract is used by other tests.
 *
 * @group Cohesion
 */
abstract class UsagePluginBaseUnitTest extends UnitTestCase {

  /**
   * @var UsagePluginInterface
   */
  protected $unit;

  protected $configuration;
  protected $plugin_id;
  protected $plugin_definition;
  protected $entity_type_manager_mock;
  protected $stream_wrapper_manager_mock;
  protected $database_connection_mock;
  protected $theme_handler_mock;

  /**
   * Create mocks of the objects that the plugin needs.
   */
  public function setUp() {
    // Mock config.
    $this->configuration = [];
    $this->plugin_id = 'mockup_plugin_id';
    $this->plugin_definition = [
      'name' => 'Mock',
      'entity_type' => 'mock_entity'
    ];

    // Mock service.
    $prophecy = $this->prophesize(\Drupal\Core\Entity\EntityStorageInterface::CLASS);
    // Mock function call.
    $prophecy->load(\Prophecy\Argument::type('string'))->will(function ($args) {
      // Just return the ID of the entity sent to ->load()
      return new MockEntity($args[0]);
    });
    $prophecy->loadByProperties(\Prophecy\Argument::type('array'))->will(function ($args) {
      $key = key($args[0]);
      // Just return the whatever key and value of the entity sent to ->load()
      return [
        new MockEntity($key . '-' . $args[0][$key])
      ];
    });
    // Mock function call.
    $prophecy->getQuery(\Prophecy\Argument::type('string'))->will(function ($args) {
      // Just return the ID of the entity sent to ->load()
      return new MockQuery();
    });
    // Mock function call.
    $prophecy->loadMultiple(\Prophecy\Argument::type('array'))->will(function ($args) {
      return array_map(function($id) {
        return new MockEntity($id[0]);
      }, $args[0]);
    });
    $storage_manager_mock = $prophecy->reveal();

    // Mock service.
    $prophecy = $this->prophesize(EntityTypeManagerInterface::CLASS);
    $prophecy->getStorage($this->plugin_definition['entity_type'])->willReturn($storage_manager_mock);
    $this->entity_type_manager_mock = $prophecy->reveal();

    // Mock service.
    $prophecy = $this->prophesize(StreamWrapperManager::CLASS);
    //$prophecy->generate()->willReturn('0000-0000-0000-0000');
    $prophecy->getWrappers()->willReturn([]);
    $prophecy->getViaUri(\Prophecy\Argument::type('string'))->will(function ($args) {
      // Just return the ID of the entity sent to ->load()
      return new MockStreamWrapperInstance($args[0]);
    });
    $this->stream_wrapper_manager_mock = $prophecy->reveal();

    // Mock service.
    $prophecy = $this->prophesize(Connection::CLASS);
    //$prophecy->generate()->willReturn('0000-0000-0000-0000');
    $this->database_connection_mock = $prophecy->reveal();

    // Mock service.
    $prophecy = $this->prophesize(ThemeHandlerInterface::CLASS);
    //$prophecy->generate()->willReturn('0000-0000-0000-0000');
    $this->theme_handler_mock = $prophecy->reveal();

  }

  /**
   * Once test method has finished running, whether it succeeded or failed, tearDown() will be invoked.
   * Unset the $unit object.
   */
  public function tearDown() {
    unset($this->unit);
  }
}

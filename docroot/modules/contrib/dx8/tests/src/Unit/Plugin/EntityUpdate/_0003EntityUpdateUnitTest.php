<?php

namespace Drupal\Tests\cohesion\Unit\Plugin\EntityUpdate;

use Drupal\cohesion\Entity\EntityJsonValuesInterface;
use Drupal\cohesion\Plugin\EntityUpdate\_0003EntityUpdate;
use Drupal\Component\Serialization\Json;
use Drupal\Tests\UnitTestCase;

/**
 * Class MockUpdateEntity
 *
 * @package Drupal\Tests\cohesion\Unit
 */
class MockUpdateCanvasEntity implements EntityJsonValuesInterface {

  protected $jsonValues;

  public function __construct($json_values) {
    $this->jsonValues = $json_values;
  }

  public function getJsonValues() {
    return $this->jsonValues;
  }

  public function setJsonValue($json_values) {
    $this->jsonValues = $json_values;
    return $this;
  }

  public function process() {
  }

  public function jsonValuesErrors() {
  }

  public function getDecodedJsonValues($as_object = FALSE) {
    try {
      if ($as_object) {
        return json_decode($this->getJsonValues());
      }
      else {
        return Json::decode($this->getJsonValues());
      }
    } catch (\Exception $e) {
      return [];
    }
  }

  public function isLayoutCanvas(){

  }

  public function getLayoutCanvasInstance(){

  }
}

/**
 * @group Cohesion
 */
class _0003EntityUpdateUnitTest extends UnitTestCase {

  /** @var $unit _0003EntityUpdate  */
  protected $unit;

  private $fixture = '{ "canvas": [ { "uid": "4f582610", "type": "component", "title": "Section: Heading", "enabled": true, "category": "general", "componentId": "4f582610", "componentType": "container", "parentIndex": 0, "uuid": "e56e65fc-d0cc-491d-90fa-c6b32d773e94", "parentUid": "root", "isContainer": 0, "children": [], "componentContentId": "cc_eeecbef0-f4f0-468a-b3a3-090d1a5c5333", "status": {} } ], "model": { "e56e65fc-d0cc-491d-90fa-c6b32d773e94": { "settings": { "title": "Section: Heading" }, "1d7ef21d-f585-4961-9f8e-aa6561ca9179": "Section heading here", "isVariableMode": false, "af30a7c6-76a5-409f-9b65-c38c02007dba": "" } }, "isVariableMode": false }';

  public function setUp() {
    $this->unit = new _0003EntityUpdate([], null, null);
  }

  /**
   * @covers \Drupal\cohesion\Plugin\EntityUpdate\_0002EntityUpdate::runUpdate
   */
  public function testRunUpdate() {
    $layout = new MockUpdateCanvasEntity($this->fixture);

    // Run the update.
    $this->unit->updateEntity($layout);

    $this->assertNotContains($layout->getJsonValues(), 'componentContentId');
    $this->assertNotContains($layout->getJsonValues(), 'cc_eeecbef0-f4f0-468a-b3a3-090d1a5c5333');
  }
}
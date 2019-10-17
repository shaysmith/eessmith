<?php

namespace Drupal\cohesion\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\Request;
use Drupal\cohesion\CohesionJsonResponse;
use Drupal\cohesion\Plugin\Api\PreviewApi;
use Drupal\Component\Serialization\Json;

/**
 * Class PreviewStyleEndpoint
 *
 * Makes a request to the API to create a stylesheet for the element preview.
 *
 * @package Drupal\cohesion\Controller
 */
class PreviewStyleEndpoint extends ControllerBase {

  /**
   * @param \Symfony\Component\HttpFoundation\Request $request
   *
   * @return \Drupal\cohesion\CohesionJsonResponse
   */
  public function index(Request $request) {

    $entity_type_id = $request->attributes->get('entity_type_id');

    // Build generic response data.
    $data = [];
    // Sanitize the style JSON form data sent from Angular.
    $style_model = Json::decode($request->getContent());

    /** @var PreviewApi $send_to_api */
    $send_to_api = \Drupal::service('plugin.manager.api.processor')->createInstance('preview_api');

    $send_to_api->setupPreview($entity_type_id, $style_model);
    $success = $send_to_api->send();
    $response = $send_to_api->getData();

    $error = TRUE;
    $status = 400;
    if ($success) {
      if (is_array($response)) {
        if ($entity_type_id == 'cohesion_custom_style') {
          $data = isset($response['theme']) ? $response['theme'] : [];
        }
        else {
          $data = isset($response['base']) ? $response['base'] : [];
        }
      }
      $error = FALSE;
      $status = 200;
    }
    elseif (isset($response['error'])) {
      $data['error'] = $response['error'];
    }
    return new CohesionJsonResponse([
      'status' => !$error ? 'success' : 'error',
      'data' => $data,
    ], $status);
  }

}

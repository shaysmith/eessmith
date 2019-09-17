<?php

namespace Drupal\cohesion\Plugin\LayoutCanvas;

use Drupal\cohesion_elements\Entity\Component;
use Drupal\cohesion_website_settings\Entity\WebsiteSettings;

/**
 * Class LayoutCanvas
 *
 * @package Drupal\cohesion
 *
 * @Api(
 *   id = "cohesion_layout_canvas",
 *   name = @Translation("Layout canvas object"),
 * )
 */
class ElementModel implements \JsonSerializable {

  const MATCH_COMPONENT_FIELD = '/\[field.([0-9a-f]{7,8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12})\]/';

  const MATCH_UUID = '/^[0-9A-F]{8}-[0-9A-F]{4}-4[0-9A-F]{3}-[89AB][0-9A-F]{3}-[0-9A-F]{12}$/i';

  protected $hashed_content = [];

  protected $element = NULL;

  protected $is_api_ready = FALSE;

  /**
   * @var $model \stdClass The model properties
   */
  protected $model = NULL;

  public function __construct($model, Element $element) {
    $this->element = $element;

    $this->model = new \stdClass();
    if (is_object($model)) {
      $this->model = $model;
    }
  }

  public function getHashedContent() {
    return $this->hashed_content;
  }

  public function getElement() {
    return $this->element;
  }

  public function getUUID() {
    return $this->getElement()->getUUID();
  }

  /**
   *
   * Find a property in a model
   *
   * @param string|array $path_to_property
   *  The path in the model to get this property. Specify a string if top
   *   level, or an array to search in leaves
   *
   * @return mixed|null
   */
  public function getProperty($path_to_property) {

    $property_names = [];

    if (is_string($path_to_property)) {
      $property_names = [$path_to_property];
    }
    elseif (is_array($path_to_property)) {
      $property_names = $path_to_property;
    }

    $current_pointer = $this->model;
    foreach ($property_names as $property_name) {
      if (is_object($current_pointer) && property_exists($current_pointer, $property_name)) {
        $current_pointer = $current_pointer->{$property_name};
      }
      else {
        return NULL;
      }
    }

    return $current_pointer;
  }

  /**
   * @return array|\stdClass
   */
  public function getValues() {
    if (is_object($this->model)) {
      return $this->model;
    }

    return [];
  }

  public function prepareDataForAPI() {

    // Handle background images inheritance
    if ($this->getProperty(['styles', 'styles'])) {
      $previous_bp = [];
      // Loop over each breakpoint in the style
      $responsive_grid_settings = WebsiteSettings::load('responsive_grid_settings');
      $responsive_grid_json = $responsive_grid_settings->getDecodedJsonValues();
      foreach ($responsive_grid_json['breakpoints'] as $bp_key => $bp) {
        if (property_exists($this->model->styles->styles, $bp_key)) {
          $value = $this->model->styles->styles->{$bp_key};
          $current_bp = [];
          // Check if the breakpoint has background image/gradient and loop over
          if (is_object($value) && property_exists($value, 'background-image-settings') && is_array($value->{'background-image-settings'})) {
            foreach ($value->{'background-image-settings'} as $key => &$background) {
              // If the current breakpoint background is a background image but empty populate it with the previous breakpoint/index in the array image otherwise if it has an image store it for lower breakpoints
              if (property_exists($background, 'backgroundImage') && property_exists($background, 'backgroundLayerType') && property_exists($background->backgroundLayerType, 'value') && $background->backgroundLayerType->value == 'image' && is_object($background->backgroundImage)) {
                if ((!property_exists($background->backgroundImage, 'value') || $background->backgroundImage->value == '') && property_exists($background->backgroundImage, 'imageStyle') && isset($previous_bp[$key])) {
                  $background->backgroundImage->value = $previous_bp[$key];
                }
                elseif(property_exists($background->backgroundImage, 'value')) {
                  $current_bp[$key] = $background->backgroundImage->value;
                }
              }
            }
          }
          $this->model->styles->styles->{$bp_key} = $value;
          $previous_bp = $current_bp;
        }
      }
    }

    foreach ($this->getLeavesWithPathToRoot() as $model_value) {
      // Scan for and set up genuine Drupal tokens.
      $this->processToken($model_value['value']);
      // Replace content with UUIDs so the API never sees any sensitive content.
      $this->hashContent($model_value['value'], $model_value['path']);
    }

    $this->hashContentComponent($this->getElement());


    $this->is_api_ready = TRUE;
  }

  /**
   * Return whether the model ready to be sent to the API
   *
   * @return bool
   */
  public function isApiReady() {
    return $this->is_api_ready;
  }

  /**
   * Hash component field content in the current model
   * If the element is a component it will be the path in the canvas of the
   * component entity to assert if hashing is needed in the current model
   *
   * @param $current_element \Drupal\cohesion\Plugin\LayoutCanvas\Element
   */
  private function hashContentComponent(Element $current_element, $is_nested_component = FALSE) {

    $vars = [];

    // Load the Component entity if the element is a component
    if ($current_element->isComponent()) {
      if ($component = Component::load($current_element->getComponentId())) {
        $componentLayoutCanvas = $component->getLayoutCanvasInstance();
        if ($componentLayoutCanvas) {
          /** @var \Drupal\cohesion\Plugin\LayoutCanvas\LayoutCanvas $componentLayoutCanvas */ // Iterate through each model of each element in the component entity
          // and send the content to be hash if the value is linked to a component field
          foreach ($componentLayoutCanvas->iterateCanvas() as $element) {

            if ($element->getModel()) {

              $inner_component_model_values = [];
              // If the element is a component (component in component)
              // get the component model path/value/key (@see iterateValuesWithPath) so we can match
              // the parent component field uuid with the real path to it's content
              if ($element->isComponent()) {
                $inner_component_model_values = $this->hashContentComponent($element, TRUE);
              }

              // Iterate through each form element field and get their value and path in the model
              foreach ($element->getModel()->getLeavesWithPathToRoot() as $component_model_value) {
                // Check if the form element field is attached to one or more component field (field.[uuid])
                if (preg_match_all(self::MATCH_COMPONENT_FIELD, $component_model_value['value'], $matches) && isset($matches[1])) {
                  foreach ($matches[1] as $uuid) {
                    // If it is a component in component
                    if ($is_nested_component) {
                      // If the key exists in the component inside this current component, get the path/value/key and pass it to it's parent component
                      // Other it's the latest component in the chain so pass the current path/value/key to it's parent
                      if (isset($inner_component_model_values[$component_model_value['key']])) {
                        $vars[$uuid] = $inner_component_model_values[$component_model_value['key']];
                      }
                      else {
                        $vars[$uuid] = $component_model_value;
                      }
                    }
                    elseif ($this->getProperty($uuid)) {

                      // If the model value is an object or array loop over it to hash each value
                      // If in the outer most component and the key in the model exists in a child component, get the path from the child
                      // Otherwise get the field path from the element
                      if (is_object($this->model->{$uuid}) || is_array($this->model->{$uuid})) {
                        foreach ($this->model->{$uuid} as $key => $sub_value) {
                          if ($element->isComponent() && isset($inner_component_model_values[$component_model_value['key']])) {
                            $this->hashContent($this->model->{$uuid}->{$key}, array_merge($inner_component_model_values[$component_model_value['key']]['path'], [$key]));
                          }
                          else {
                            $this->hashContent($this->model->{$uuid}->{$key}, array_merge($component_model_value['path'], [$key]));
                          }
                        }
                      }
                      else {
                        if ($element->isComponent() && isset($inner_component_model_values[$component_model_value['key']])) {
                          $this->hashContent($this->model->{$uuid}, $inner_component_model_values[$component_model_value['key']]['path']);
                        }
                        else {
                          $this->hashContent($this->model->{$uuid}, $component_model_value['path']);
                        }
                      }
                    }
                  }
                }
              }
            }
          }
        }
      }
    }

    return $vars;
  }

  /**
   * Return an array of each leaf value with the path from the root node
   *
   * @param $model NULL|object
   * @param $path array
   *
   * @return array
   */
  public function getLeavesWithPathToRoot($model = NULL, $path = []) {
    if (is_null($model)) {
      $model = $this->model;
    }
    $values = [];
    foreach ($model as $key => &$value) {
      $current_path = $path;
      $current_path[] = $key;

      if (is_array($value) || is_object($value)) {
        $values = array_merge($values, $this->getLeavesWithPathToRoot($value, $current_path));
      }
      else {
        $values[] = [
          'path' => $current_path,
          'value' => &$value,
          'key' => $key,
        ];
      }
    }
    return $values;
  }

  /**
   * Perform the Drupal token replacement.
   *
   * @param $value
   *
   */
  private function processToken(&$value) {
    if (is_string($value)) {
      $token_service = \Drupal::token();

      $token_info = $token_service->getInfo();

      if ($found_tokens = $token_service->scan($value)) {
        foreach ($found_tokens as $context => $token_group) {
          if (in_array($context, array_keys($token_info['types']))) {
            foreach ($token_group as $token) {
              $context_variable = $context;

              \Drupal::moduleHandler()->alter('dx8_' . $context . '_drupal_token_context', $context_variable);

              // If token has been detected replace potential breaking chars with nothing as they are not valid
              $context = str_replace(['[', ']', '{', '}'], '', $context);

              $twig_token = '[token.' . str_replace([
                  '[',
                  ']',
                  '{',
                  '}',
                ], '', $token) . '|' . $context . '|' . $context_variable . ']';
              $value = str_replace($token, $twig_token, $value);
            }
          }
        }
      }
    }
  }

  /**
   * Replace content with UUIDs so the API never sees any sensitive content.
   * These UUIDs get replaced with the content when the call to the API returns.
   *
   * @param string &$value
   * @param array $path
   */
  private function hashContent(&$value, $path) {
    // Hash only string content and if value has not already been hashed
    if (is_string($value) && !preg_match(self::MATCH_UUID, $value)) {
      // Import the content paths.
      $dx8_content_paths = \Drupal::keyValue('cohesion.assets.static_assets')->get('dx8_content_paths');

      // Hash if the path is registered as content
      if ($dx8_content_paths && in_array($path, $dx8_content_paths)) {
        // Component fields and tokens are not content so they should not be hashed
        // Now scan for strings NOT surrounding [field.*] and [token.*]
        // This extracts all part of a string not containing [field.*] or [token.*] so they can be hashed
        if ($content_parts = preg_split('((\[token\.(.*?)\])|(\[field\.(.*?)\]))', $value)) {

          // Perform the replacement, building the list of UUIDs up
          foreach (array_filter($content_parts) as $string) {

            if (!preg_match(self::MATCH_UUID, $string) && preg_match('/\w/', $string)) {
              // Create a UUID.
              $uuid = \Drupal::service('uuid')->generate();

              // Replace the outbound string partial.
              $value = str_replace($string, $uuid, $value);

              // Save the hash.
              $this->hashed_content[$uuid] = $string;
            }
          }
        }
      }
    }
  }

  /**
   * @inheritdoc
   */
  public function jsonSerialize() {
    return $this->model;
  }
}
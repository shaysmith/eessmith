<?php

namespace Drupal\cohesion_website_settings\Entity;

use Drupal\cohesion\Entity\CohesionConfigEntityBase;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Component\Serialization\Json;
use Drupal\Core\Cache\Cache;
use Drupal\cohesion_website_settings\Plugin\Api\WebsiteSettingsApi;
use Drupal\cohesion\Entity\CohesionSettingsInterface;

/**
 * Defines the Cohesion website settings entity.
 *
 * @ConfigEntityType(
 *   id = "cohesion_font_library",
 *   label = @Translation("Font library"),
 *   label_singular = @Translation("Font library"),
 *   label_plural = @Translation("Font libraries"),
 *   label_collection = @Translation("Font libraries"),
 *   label_count = @PluralTranslation(
 *     singular = "@count font library",
 *     plural = "@count font libraries",
 *   ),
 *   fieldable = TRUE,
 *   handlers = {
 *     "route_provider" = {
 *       "html" = "Drupal\cohesion\CohesionHtmlRouteProvider",
 *     },
 *   },
 *   config_prefix = "cohesion_font_library",
 *   admin_permission = "administer website settings",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *     "uuid" = "uuid"
 *   },
 *   links = {
 *     "in-use" = "/admin/cohesion/cohesion_font_library/{cohesion_font_library}/in_use",
 *     "collection" = "/admin/cohesion/cohesion_website_settings"
 *   }
 * )
 */
class FontLibrary extends WebsiteSettingsEntityBase implements CohesionSettingsInterface {

  use WebsiteSettingsSourceTrait;

  const ASSET_GROUP_ID = 'website_settings';

  /**
   * Return all the icons combined for the form[]
   *
   * @return array|\stdClass|string
   */
  public function getResourceObject() {
    /** @var WebsiteSettingsApi $send_to_api */
    $send_to_api = \Drupal::service('plugin.manager.api.processor')->createInstance('website_settings_api');

    return $send_to_api->getFontGroup();
  }

  /**
   * {@inheritdoc}
   */
  public function process() {
    /** @var WebsiteSettingsApi $send_to_api */
    $send_to_api = \Drupal::service('plugin.manager.api.processor')->createInstance('website_settings_api');
    $send_to_api->setEntity($this);
    $send_to_api->send('style');
    $send_to_api->getData();
  }

  /**
   * {@inheritdoc}
   */
  public function jsonValuesErrors() {
  }

  /**
   * {@inheritdoc}
   */
  public function preSave(EntityStorageInterface $storage) {
    parent::preSave($storage);

    if ($this->getOriginalId()) {
      $original = $storage->loadUnchanged($this->getOriginalId());
      if ($original instanceof FontLibrary) {
        $original_json_values = $original->getDecodedJsonValues();
        $json_values = $this->getDecodedJsonValues();

        // Clear the previous font files if the new font library files are different or as no files,
        if (isset($json_values['fontFiles'])) {
          if ((isset($original_json_values['fontFiles']) && $json_values['fontFiles'] != $original_json_values['fontFiles'])) {
            $this->clearFontFiles($original_json_values);
          }
        } else {
          $this->clearFontFiles($original_json_values);
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function postSave(EntityStorageInterface $storage, $update = TRUE) {
    parent::postSave($storage, $update);

    // Send settings to API if enabled and modified!.
    $this->process();

    // Invalidate settings endpoint shared cache entries.
    $tags = ('font_libraries' == $this->id()) ? [$this->id(), 'font_stack',] : [$this->id()];
    Cache::invalidateTags($tags);
  }

  /**
   * Return a description.
   */
  public function getLabel() {
    return $this->label;
  }

  /**
   * {@inheritdoc}
   */
  public function getInUseMessage() {
    return ['message' => ['#markup' => t('This font library has been tracked as in use in the places listed below. You should not delete it until you have removed its use.'),],];
  }

  public function clearData() {
    $json_value = $this->getDecodedJsonValues();
    $this->clearFontFiles($json_value);
  }

  /**
   * Clear font files for a given json values of a font library
   *
   * @param $json_value
   */
  private function clearFontFiles($json_value) {
    if (isset($json_value['fontFiles']) && is_array($json_value['fontFiles'])) {
      foreach ($json_value['fontFiles'] as $fontFile) {
        if (is_array($fontFile) && isset($fontFile['uri']) && file_exists($fontFile['uri'])) {
          \Drupal::service('cohesion.local_files_manager')->deleteFileByURI($fontFile['uri']);
        }
      }
    }
  }

  /**
   * @inheritdoc
   */
  public function isLayoutCanvas() {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function setDefaultValues() {
    parent::setDefaultValues();

    $this->modified = TRUE;
    $this->status = TRUE;
  }
}

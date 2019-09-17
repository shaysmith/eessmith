<?php

namespace Drupal\cohesion_website_settings\Entity;

use Drupal\cohesion\Entity\CohesionConfigEntityBase;
use Drupal\cohesion\EntityHasResourceObjectTrait;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Component\Serialization\Json;
use Drupal\Core\Cache\Cache;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\cohesion\Entity\CohesionSettingsInterface;
use Drupal\cohesion_website_settings\Plugin\Api\WebsiteSettingsApi;

/**
 * Defines the Cohesion website settings entity.
 *
 * @ConfigEntityType(
 *   id = "cohesion_icon_library",
 *   label = @Translation("Icon library"),
 *   label_singular = @Translation("Icon library"),
 *   label_plural = @Translation("Icon libraries"),
 *   label_collection = @Translation("Icon libraries"),
 *   label_count = @PluralTranslation(
 *     singular = "@count icon library",
 *     plural = "@count icon libraries",
 *   ),
 *   fieldable = TRUE,
 *   handlers = {
 *     "route_provider" = {
 *       "html" = "Drupal\cohesion\CohesionHtmlRouteProvider",
 *     },
 *   },
 *   config_prefix = "cohesion_icon_library",
 *   admin_permission = "administer website settings",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *     "uuid" = "uuid"
 *   },
 *   links = {
 *     "in-use" = "/admin/cohesion/cohesion_icon_library/{cohesion_icon_library}/in_use",
 *     "collection" = "/admin/cohesion/cohesion_website_settings"
 *   }
 * )
 */
class IconLibrary extends WebsiteSettingsEntityBase implements CohesionSettingsInterface {

  use EntityHasResourceObjectTrait;
  use WebsiteSettingsSourceTrait;

  const ASSET_GROUP_ID = 'website_settings';

  /**
   * The human-readable label for a collection of entities of the type.
   *
   * @var string
   *
   * @see \Drupal\Core\Entity\EntityTypeInterface::getCollectionLabel()
   */
  protected $label_collection = '';

  /**
   * Return all the icons combined for the form[]
   *
   * @return array|\stdClass|string
   */
  public function getResourceObject() {
    /** @var WebsiteSettingsApi $send_to_api */
    $send_to_api = \Drupal::service('plugin.manager.api.processor')->createInstance('website_settings_api');

    return $send_to_api->getIconGroup();
  }

  /**
   * {@inheritdoc}
   */
  public function preSave(EntityStorageInterface $storage) {
    parent::preSave($storage);
    if ($this->getOriginalId()) {
      $original = $storage->loadUnchanged($this->getOriginalId());
      if ($original instanceof IconLibrary) {
        $original_json_values = $original->getDecodedJsonValues();
        $json_values = $this->getDecodedJsonValues();

        // Clear the previous font files if the new icon library files are different or as no files,
        if (isset($json_values['fontFiles'])) {
          if ((isset($original_json_values['fontFiles']) && $json_values['fontFiles'] != $original_json_values['fontFiles'])) {
            $this->clearIconFontFiles($original_json_values);
          }
        } else {
          $this->clearIconFontFiles($original_json_values);
        }

        if (isset($json_values['iconJSON']['json'])) {
          if ((isset($original_json_values['iconJSON']['json']) && $json_values['iconJSON']['json'] != $original_json_values['iconJSON']['json'])) {
            $this->clearSelectionJson($original_json_values);
          }
        } else {
          $this->clearSelectionJson($original_json_values);
        }
      }
    }
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
  public function postSave(EntityStorageInterface $storage, $update = TRUE) {
    parent::postSave($storage, $update);

    // Send settings to API if enabled and modified!.
    if ($this->status()) {  // && $this->isModified()) {
      $this->process();
    }

    // Invalidate settings endpoint shared cache entries.
    // Cache::invalidateTags($tags);
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
  public function getCollectionLabel() {
    if (empty($this->label_collection)) {
      $label = $this->getLabel();
      $this->label_collection = new TranslatableMarkup('@label', ['@label' => $label], [], $this->getStringTranslation());
    }
    return $this->label_collection;
  }

  /**
   * {@inheritdoc}
   */
  public function getInUseMessage() {
    return ['message' => ['#markup' => t('This icon library has been tracked as in use in the places listed below. You should not delete it until you have removed its use.'),],];
  }

  public function clearData() {
    $json_value = $this->getDecodedJsonValues();
    $this->clearIconFontFiles($json_value);
    $this->clearSelectionJson($json_value);

  }

  /**
   * Clear font files for a given json values of a icon library
   *
   * @param $json_value
   */
  private function clearIconFontFiles($json_value) {
    if (isset($json_value['fontFiles']) && is_array($json_value['fontFiles'])) {
      foreach ($json_value['fontFiles'] as $fontFile) {
        if (is_array($fontFile) && isset($fontFile['uri']) && file_exists($fontFile['uri'])) {
          \Drupal::service('cohesion.local_files_manager')->deleteFileByURI($fontFile['uri']);
        }
      }
    }
  }

  /**
   * Clear selection json for a given json values of a icon library
   *
   * @param $json_value
   */
  private function clearSelectionJson($json_value) {
    if (isset($json_value['iconJSON']['json'])) {
      \Drupal::service('cohesion.local_files_manager')->deleteFileByURI($json_value['iconJSON']['json']);
    }
  }

  /**
   * @inheritdoc
   */
  public function isLayoutCanvas() {
    return FALSE;
  }
}

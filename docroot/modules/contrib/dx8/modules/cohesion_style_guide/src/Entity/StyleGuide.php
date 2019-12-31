<?php

namespace Drupal\cohesion_style_guide\Entity;

use Drupal\cohesion\Entity\CohesionConfigEntityBase;
use Drupal\cohesion\Entity\CohesionSettingsInterface;
use Drupal\Console\Bootstrap\Drupal;
use Drupal\Core\Entity\EntityStorageInterface;

/**
 * Defines the Cohesion style guide entity.
 *
 * @ConfigEntityType(
 *   id = "cohesion_style_guide",
 *   label = @Translation("Style guide"),
 *   label_singular = @Translation("Style guide"),
 *   label_plural = @Translation("Style guides"),
 *   label_collection = @Translation("Style guides"),
 *   label_count = @PluralTranslation(
 *     singular = "@count style guide",
 *     plural = "@count style guides",
 *   ),
 *   handlers = {
 *     "list_builder" = "Drupal\cohesion_style_guide\StyleGuideListBuilder",
 *     "form" = {
 *       "default" = "Drupal\cohesion_style_guide\Form\StyleGuideForm",
 *       "add" = "Drupal\cohesion_style_guide\Form\StyleGuideForm",
 *       "edit" = "Drupal\cohesion_style_guide\Form\StyleGuideForm",
 *       "duplicate" = "Drupal\cohesion_style_guide\Form\StyleGuideForm",
 *       "delete" = "Drupal\cohesion_style_guide\Form\StyleGuideDeleteForm",
 *       "disable" = "Drupal\cohesion_style_guide\Form\StyleGuideDisableForm",
 *       "enable" = "Drupal\cohesion_style_guide\Form\StyleGuideEnableForm",
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\cohesion\CohesionHtmlRouteProvider",
 *     },
 *   },
 *   config_prefix = "cohesion_style_guide",
 *   admin_permission = "administer style_guide",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *     "uuid" = "uuid",
 *     "status" = "status",
 *     "weight" = "weight",
 *   },
 *   links = {
 *     "edit-form" =
 *   "/admin/cohesion/style_guides/{cohesion_style_guide}/edit",
 *     "add-form" = "/admin/cohesion/style_guides/add",
 *     "delete-form" =
 *   "/admin/cohesion/style_guides/{cohesion_style_guide}/delete",
 *     "collection" = "/admin/cohesion/style_guides",
 *     "duplicate-form" =
 *   "/admin/cohesion/style_guides/{cohesion_style_guide}/duplicate",
 *     "in-use" = "/admin/cohesion/style_guides/{cohesion_style_guide}/in_use",
 *     "disable" =
 *   "/admin/cohesion/style_guides/{cohesion_style_guide}/disable",
 *     "enable" = "/admin/cohesion/style_guides/{cohesion_style_guide}/enable",
 *   }
 * )
 */
class StyleGuide extends CohesionConfigEntityBase implements CohesionSettingsInterface {

  const ASSET_GROUP_ID = 'style_guide';

  /**
   * @var int
   */
  protected $weight;

  /**
   * {@inheritdoc}
   */
  public function getAssetName() {
    return self::getAssetGroupId();
  }

  /**
   * Getter.
   *
   * @return int
   */
  public function getWeight() {
    return $this->weight ? $this->weight : 0;
  }

  /**
   * Setter.
   *
   * @param $weight
   */
  public function setWeight($weight) {
    $this->weight = $weight;
  }

  /**
   * @inheritdoc
   */
  public function isLayoutCanvas() {
    return TRUE;
  }

  public function jsonValuesErrors() {
    // @todo to implement
    return FALSE;
  }

  public function process() {
    return NULL;
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function delete() {
    parent::delete();

    // Upon delete of style guide also delete all style guide manager instances referencing this style guide entity
    $style_guide_manager_storage = $this->entityTypeManager()
      ->getStorage('cohesion_style_guide_manager');
    $style_guide_manager_ids = $style_guide_manager_storage->getQuery()
      ->condition('style_guide_uuid', $this->uuid())->execute();

    if ($style_guide_managers = $style_guide_manager_storage->loadMultiple($style_guide_manager_ids)) {
      foreach ($style_guide_managers as $style_guide_manager) {
        $style_guide_manager->delete();
      }
    }

    token_clear_cache();
  }

  public function postSave(EntityStorageInterface $storage, $update = TRUE) {
    parent::postSave($storage, $update);
    token_clear_cache();
  }

}

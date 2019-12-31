<?php

namespace Drupal\cohesion_style_helpers\Entity;

use Drupal\cohesion\Entity\CohesionConfigEntityBase;
use Drupal\cohesion\Entity\CohesionSettingsInterface;
use Drupal\cohesion\EntityHasResourceObjectTrait;
use Drupal\Component\Serialization\Json;
use Drupal\cohesion\Plugin\Api\PreviewApi;

/**
 * Defines the DX8 Style Helpers entity.
 *
 * @ConfigEntityType(
 *   id = "cohesion_style_helper",
 *   label = @Translation("Style helper"),
 *   label_singular = @Translation("Style helper"),
 *   label_plural = @Translation("Style helpers"),
 *   label_collection = @Translation("Style helpers"),
 *   label_count = @PluralTranslation(
 *     singular = "@count style helper",
 *     plural = "@count style helpers",
 *   ),
 *   handlers = {
 *     "list_builder" = "Drupal\cohesion_style_helpers\StyleHelpersListBuilder",
 *     "form" = {
 *       "add" = "Drupal\cohesion_style_helpers\Form\StyleHelpersForm",
 *       "edit" = "Drupal\cohesion_style_helpers\Form\StyleHelpersForm",
 *       "duplicate" = "Drupal\cohesion_style_helpers\Form\StyleHelpersForm",
 *       "delete" = "Drupal\cohesion_style_helpers\Form\StyleHelpersDeleteForm",
 *       "enable-selection" = "Drupal\cohesion_style_helpers\Form\StyleHelpersEnableSelectionForm",
 *       "disable-selection" = "Drupal\cohesion_style_helpers\Form\StyleHelpersDisableSelectionForm",
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\cohesion_style_helpers\StyleHelpersHtmlRouteProvider",
 *     },
 *   },
 *   config_prefix = "cohesion_style_helper",
 *   admin_permission = "administer style helpers",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *     "uuid" = "uuid",
 *     "bundle" = "custom_style_type",
 *     "selectable" = "selectable",
 *   },
 *   links = {
 *     "edit-form" = "/admin/cohesion/styles/cohesion_style_helpers/{cohesion_style_helper}/edit",
 *     "add-form" = "/admin/cohesion/styles/cohesion_style_helpers/add/{custom_style_type}",
 *     "delete-form" = "/admin/cohesion/styles/cohesion_style_helpers/{cohesion_style_helper}/delete",
 *     "add-page" = "/admin/cohesion/styles/cohesion_style_helpers/add",
 *     "collection" = "/admin/cohesion/styles/cohesion_style_helpers",
 *     "duplicate-form" = "/admin/cohesion/styles/cohesion_style_helpers/{cohesion_style_helper}/duplicate",
 *     "enable-selection" = "/admin/cohesion/styles/cohesion_style_helpers/{cohesion_style_helper}/enable-selection",
 *     "disable-selection" = "/admin/cohesion/styles/cohesion_style_helpers/{cohesion_style_helper}/disable-selection",
 *   }
 * )
 */
class StyleHelper extends CohesionConfigEntityBase implements CohesionSettingsInterface {

  use EntityHasResourceObjectTrait {
    getResourceObject as protected getResourceObjectDefault;
  }

  const ASSET_GROUP_ID = 'style_helpers';

  const entity_machine_name_prefix = 'style_hlp_';

  /**
   * The CustomStyleType
   *
   * @var string
   */
  protected $custom_style_type;

  /**
   * style helper getter.
   */
  public function getCustomStyleType() {
    return $this->custom_style_type;
  }

  /**
   * {@inheritdoc}
   */
  public function getResourceObject() {
    $entity_values = $this->getResourceObjectDefault();
    $entity_values->custom_style_type = $this->get('custom_style_type');

    return $entity_values;
  }

  /**
   * @inheritdoc
   */
  public function setDefaultValues() {
    parent::setDefaultValues();

    $this->set('custom_style_type', '');
  }

  /**
   * {@inheritdoc}
   */
  public function clearData() {
    // Style helpers doesn't generate any data so leave this empty
  }

  /**
   * @return array|bool
   */
  public function jsonValuesErrors() {
    /** @var PreviewApi $send_to_api */
    $send_to_api = $this->apiProcessorManager()->createInstance('preview_api');

    // Use the style preview endpoint to validate the data.
    $style_model = $this->getDecodedJsonValues();
    $send_to_api->setupPreview($this->getEntityTypeId(), $style_model);
    $success = $send_to_api->sendWithoutSave();
    $responseData = $send_to_api->getData();

    if ($success === TRUE) {
      return FALSE;
    }
    else {
      return $responseData;
    }
  }

  /**
   * @inheritdoc
   */
  public function isLayoutCanvas() {
    return FALSE;
  }

}

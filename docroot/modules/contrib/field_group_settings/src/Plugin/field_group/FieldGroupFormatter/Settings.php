<?php

namespace Drupal\field_group_settings\Plugin\field_group\FieldGroupFormatter;

use Drupal\Core\Render\Element;
use Drupal\field_group\FieldGroupFormatterBase;

/**
 * Plugin implementation of the 'settings' formatter.
 *
 * @FieldGroupFormatter(
 *   id = "settings",
 *   label = @Translation("Settings"),
 *   description = @Translation("Renders a field group as collapsible settings."),
 *   supported_contexts = {
 *     "form",
 *   }
 * )
 */
class Settings extends FieldGroupFormatterBase {

  /**
   * {@inheritdoc}
   */
  public function preRender(&$element, $rendering_object) {
    $element += [
      '#type' => 'field_group_settings',
      '#options' => [
        'attributes' => [
          'class' => $this->getClasses(),
        ],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function getClasses() {
    $classes = ['field-group-settings'];
    $classes = array_merge($classes, parent::getClasses());
    return $classes;
  }
}

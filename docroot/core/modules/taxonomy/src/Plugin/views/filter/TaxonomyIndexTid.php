<?php

namespace Drupal\taxonomy\Plugin\views\filter;

use Drupal\Core\Form\FormStateInterface;
use Drupal\views\Plugin\views\filter\EntityReference;

/**
 * Filter by term id.
 *
 * @ingroup views_filter_handlers
 *
 * @ViewsFilter("taxonomy_index_tid")
 */
class TaxonomyIndexTid extends EntityReference {

  protected function defineOptions() {
    $options = parent::defineOptions();

    $options['hierarchy'] = ['default' => FALSE];
    $options['error_message'] = ['default' => TRUE];

    return $options;
  }

  public function buildExtraOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildExtraOptionsForm($form, $form_state);
    $form['hierarchy'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show hierarchy in dropdown'),
      '#default_value' => !empty($this->options['hierarchy']),
      '#states' => [
        'visible' => [
          ':input[name="options[widget]"]' => ['value' => self::WIDGET_SELECT],
        ],
      ],
    ];
  }

  protected function buildReferenceEntityOptions() {
    $handler_settings = $this->options['handler_settings'];
    $handler_settings += [
      'target_type' => 'taxonomy_term',
      'handler' => $this->options['handler'],
      'hierarchy' => $this->options['hierarchy'],
    ];
    $entities = $this->selectionPluginManager->getInstance($handler_settings)->getReferenceableEntities();

    $options = [];
    foreach ($entities as $bundle) {
      foreach ($bundle as $id => $entity_label) {
        $options[$id] = $entity_label;
      }
    }
    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts() {
    $contexts = parent::getCacheContexts();
    // The result potentially depends on term access and so is just cacheable
    // per user.
    // @todo See https://www.drupal.org/node/2352175.
    $contexts[] = 'user';

    return $contexts;
  }

  /**
   * Gets the target referenced entity type by this field.
   *
   * @return \Drupal\Core\Entity\EntityTypeInterface
   *   Entity type.
   */
  protected function getReferencedEntityType() {
    return $this->entityTypeManager->getDefinition('taxonomy_term');
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies() {
    $dependencies = parent::calculateDependencies();

    $handler_settings = $this->options['handler_settings'];
    if (isset($handler_settings['target_bundles'])) {
      $bundle_entity_type_id = $this->getReferencedEntityType()->getBundleEntityType();
      $target_bundles = $this->entityTypeManager->getStorage($bundle_entity_type_id)->loadMultiple($handler_settings['target_bundles']);
      /** @var \Drupal\Core\Entity\EntityInterface $bundle */
      foreach ($target_bundles as $bundle) {
        $dependencies[$bundle->getConfigDependencyKey()][] = $bundle->getConfigDependencyName();
      }
    }

    return $dependencies;
  }

}

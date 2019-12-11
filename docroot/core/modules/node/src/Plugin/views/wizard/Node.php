<?php

namespace Drupal\node\Plugin\views\wizard;

use Drupal\Core\Form\FormStateInterface;
use Drupal\views\Plugin\views\wizard\WizardPluginBase;

/**
 * @todo: replace numbers with constants.
 */

/**
 * Tests creating node views with the wizard.
 *
 * @ViewsWizard(
 *   id = "node",
 *   base_table = "node_field_data",
 *   title = @Translation("Content")
 * )
 */
class Node extends WizardPluginBase {

  /**
   * Set the created column.
   *
   * @var string
   */
  protected $createdColumn = 'node_field_data-created';

  /**
   * Overrides Drupal\views\Plugin\views\wizard\WizardPluginBase::getAvailableSorts().
   *
   * @return array
   *   An array whose keys are the available sort options and whose
   *   corresponding values are human readable labels.
   */
  public function getAvailableSorts() {
    // You can't execute functions in properties, so override the method
    return [
      'node_field_data-title:ASC' => $this->t('Title'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function rowStyleOptions() {
    $options = [];
    $options['teasers'] = $this->t('teasers');
    $options['full_posts'] = $this->t('full posts');
    $options['titles'] = $this->t('titles');
    $options['titles_linked'] = $this->t('titles (linked)');
    $options['fields'] = $this->t('fields');
    return $options;
  }

  /**
   * {@inheritdoc}
   */
  protected function defaultDisplayOptions() {
    $display_options = parent::defaultDisplayOptions();

    // Add permission-based access control.
    $display_options['access']['type'] = 'perm';
    $display_options['access']['options']['perm'] = 'access content';

    // Remove the default fields, since we are customizing them here.
    unset($display_options['fields']);

    // Add the title field, so that the display has content if the user switches
    // to a row style that uses fields.
    /* Field: Content: Title */
    $display_options['fields']['title']['id'] = 'title';
    $display_options['fields']['title']['table'] = 'node_field_data';
    $display_options['fields']['title']['field'] = 'title';
    $display_options['fields']['title']['entity_type'] = 'node';
    $display_options['fields']['title']['entity_field'] = 'title';
    $display_options['fields']['title']['label'] = '';
    $display_options['fields']['title']['alter']['alter_text'] = 0;
    $display_options['fields']['title']['alter']['make_link'] = 0;
    $display_options['fields']['title']['alter']['absolute'] = 0;
    $display_options['fields']['title']['alter']['trim'] = 0;
    $display_options['fields']['title']['alter']['word_boundary'] = 0;
    $display_options['fields']['title']['alter']['ellipsis'] = 0;
    $display_options['fields']['title']['alter']['strip_tags'] = 0;
    $display_options['fields']['title']['alter']['html'] = 0;
    $display_options['fields']['title']['hide_empty'] = 0;
    $display_options['fields']['title']['empty_zero'] = 0;
    $display_options['fields']['title']['settings']['link_to_entity'] = 1;
    $display_options['fields']['title']['plugin_id'] = 'field';

    return $display_options;
  }

  /**
   * {@inheritdoc}
   */
  protected function defaultDisplayFiltersUser(array $form, FormStateInterface $form_state) {
    $filters = parent::defaultDisplayFiltersUser($form, $form_state);

    $tids = [];
    if ($values = $form_state->getValue(['show', 'tagged_with'])) {
      foreach ($values as $value) {
        $tids[] = $value['target_id'];
      }
    }
    if (!empty($tids)) {
      $selection_settings = $form['displays']['show']['tagged_with']['#selection_settings'];
      $filters['tid'] = [
        'id' => 'tid',
        'table' => 'taxonomy_index',
        'field' => 'tid',
        'value' => $tids,
        'widget' => 'autocomplete',
        'handler_settings' => $selection_settings,
        'plugin_id' => 'taxonomy_index_tid',
      ];
      // If the user entered more than one valid term in the autocomplete
      // field, they probably intended both of them to be applied.
      if (count($tids) > 1) {
        $filters['tid']['operator'] = 'and';
        // Sort the terms so the filter will be displayed as it normally would
        // on the edit screen.
        sort($filters['tid']['value']);
      }
    }

    return $filters;
  }

  /**
   * {@inheritdoc}
   */
  protected function pageDisplayOptions(array $form, FormStateInterface $form_state) {
    $display_options = parent::pageDisplayOptions($form, $form_state);
    $row_plugin = $form_state->getValue(['page', 'style', 'row_plugin']);
    $row_options = $form_state->getValue(['page', 'style', 'row_options'], []);
    $this->display_options_row($display_options, $row_plugin, $row_options);
    return $display_options;
  }

  /**
   * {@inheritdoc}
   */
  protected function blockDisplayOptions(array $form, FormStateInterface $form_state) {
    $display_options = parent::blockDisplayOptions($form, $form_state);
    $row_plugin = $form_state->getValue(['block', 'style', 'row_plugin']);
    $row_options = $form_state->getValue(['block', 'style', 'row_options'], []);
    $this->display_options_row($display_options, $row_plugin, $row_options);
    return $display_options;
  }

  /**
   * Set the row style and row style plugins to the display_options.
   */
  protected  function display_options_row(&$display_options, $row_plugin, $row_options) {
    switch ($row_plugin) {
      case 'full_posts':
        $display_options['row']['type'] = 'entity:node';
        $display_options['row']['options']['view_mode'] = 'full';
        break;
      case 'teasers':
        $display_options['row']['type'] = 'entity:node';
        $display_options['row']['options']['view_mode'] = 'teaser';
        break;
      case 'titles_linked':
      case 'titles':
        $display_options['row']['type'] = 'fields';
        $display_options['fields']['title']['id'] = 'title';
        $display_options['fields']['title']['table'] = 'node_field_data';
        $display_options['fields']['title']['field'] = 'title';
        $display_options['fields']['title']['settings']['link_to_entity'] = $row_plugin === 'titles_linked';
        $display_options['fields']['title']['plugin_id'] = 'field';
        break;
    }
  }

  /**
   * Overrides Drupal\views\Plugin\views\wizard\WizardPluginBase::buildFilters().
   *
   * Add some options for filter by taxonomy terms.
   */
  protected function buildFilters(&$form, FormStateInterface $form_state) {
    parent::buildFilters($form, $form_state);

    if (isset($form['displays']['show']['type'])) {
      $selected_bundle = static::getSelected($form_state, ['show', 'type'], 'all', $form['displays']['show']['type']);
    }

    // Add the "tagged with" filter to the view.

    // Find all "tag-like" taxonomy fields associated with the view's
    // entities. If a particular entity type (i.e., bundle) has been
    // selected above, then we only search for taxonomy fields associated
    // with that bundle. Otherwise, we use all bundles.
    $bundles = array_keys($this->bundleInfoService->getBundleInfo($this->entityTypeId));
    // Double check that this is a real bundle before using it (since above
    // we added a dummy option 'all' to the bundle list on the form).
    if (isset($selected_bundle) && in_array($selected_bundle, $bundles)) {
      $bundles = [$selected_bundle];
    }
    $tag_fields = [];
    foreach ($bundles as $bundle) {
      $display = entity_get_form_display($this->entityTypeId, $bundle, 'default');
      $taxonomy_fields = array_filter(\Drupal::entityManager()->getFieldDefinitions($this->entityTypeId, $bundle), function ($field_definition) {
        return $field_definition->getType() == 'entity_reference' && $field_definition->getSetting('target_type') == 'taxonomy_term';
      });
      foreach ($taxonomy_fields as $field_name => $field) {
        $widget = $display->getComponent($field_name);
        // We define "tag-like" taxonomy fields as ones that use the
        // "Autocomplete (Tags style)" widget.
        if ($widget['type'] == 'entity_reference_autocomplete_tags') {
          $tag_fields[$field_name] = $field;
        }
      }
    }
    if (!empty($tag_fields)) {
      // If there is more than one "tag-like" taxonomy field available to
      // the view, we make our filter apply to all of them, and to all their
      // vocabularies.
      $target_bundles = [];
      foreach ($tag_fields as $tag_field) {
        foreach ($tag_field->getSetting('handler_settings')['target_bundles'] as $vid) {
          $target_bundles[$vid] = $vid;
        }
      }
      // Add the autocomplete textfield to the wizard.
      $form['displays']['show']['tagged_with'] = [
        '#type' => 'entity_autocomplete',
        '#title' => $this->t('tagged with'),
        '#target_type' => 'taxonomy_term',
        '#selection_settings' => [
          'target_bundles' => $target_bundles,
          'sort' => [
            'field' => 'name',
            'direction' => 'asc',
          ],
          'auto_create' => FALSE,
          'auto_create_bundle' => '',
        ],
        '#tags' => TRUE,
        '#size' => 30,
        '#maxlength' => 1024,
      ];
    }
  }

}

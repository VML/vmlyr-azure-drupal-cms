<?php

namespace Drupal\Tests\views_ui\Functional;

use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\Component\Render\FormattableMarkup;
use Drupal\node\NodeInterface;
use Drupal\views\Entity\View;

/**
 * Test the entity reference filter UI.
 *
 * @group views_ui
 * @see \Drupal\views\Plugin\views\filter\EntityReference
 */
class FilterEntityReferenceWebTest extends UITestBase {

  /**
   * Entity type and referenceable type.
   *
   * @var \Drupal\node\NodeTypeInterface
   */
  protected $entityType;

  /**
   * Referenceable entity type.
   *
   * @var \Drupal\node\NodeTypeInterface
   */
  protected $referenceableType;

  /**
   * Referenceable content.
   *
   * @var \Drupal\node\NodeInterface[]
   */
  protected $nodes;

  /**
   * {@inheritdoc}
   */
  public static $testViews = ['test_filter_entity_reference'];

  /**
   * {@inheritdoc}
   */
  protected function setUp($import_test_views = TRUE) {
    parent::setUp($import_test_views);

    // Create an entity type, and a referenceable type. Since these are coded
    // into the test view, they are not randomly named.
    $this->entityType = $this->drupalCreateContentType(['type' => 'page']);
    $this->referenceableType = $this->drupalCreateContentType(['type' => 'article']);

    $field_storage = FieldStorageConfig::create([
      'entity_type' => 'node',
      'field_name' => 'field_test',
      'type' => 'entity_reference',
      'settings' => [
        'target_type' => 'node',
      ],
      'cardinality' => FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED,
    ]);
    $field_storage->save();

    $field = FieldConfig::create([
      'entity_type' => 'node',
      'field_name' => 'field_test',
      'bundle' => $this->entityType->id(),
      'settings' => [
        'handler' => 'default',
        'handler_settings' => [
          // Note, this has no impact on Views at this time.
          'target_bundles' => [
            $this->referenceableType->id() => $this->referenceableType->label(),
          ],
        ],
      ],
    ]);
    $field->save();

    // Create 10 referenceable nodes.
    for ($i = 0; $i < 10; $i++) {
      $node = $this->drupalCreateNode(['type' => $this->referenceableType->id()]);
      $this->nodes[$node->id()] = $node;
    }
  }

  /**
   * Tests the filter UI.
   */
  public function testFilterUi() {
    $this->drupalGet('admin/structure/views/nojs/handler/test_filter_entity_reference/default/filter/field_test_target_id');

    $options = $this->getUiOptions();
    // Should be sorted by title ASC.
    uasort($this->nodes, function (NodeInterface $a, NodeInterface $b) {
      return strnatcasecmp($a->getTitle(), $b->getTitle());
    });
    $found_all = TRUE;
    $i = 0;
    foreach ($this->nodes as $nid => $node) {
      $option = $options[$i];
      $label = $option['label'];
      $found_all = $found_all && $label == $node->label() && $nid == $option['nid'];
      $this->assertEqual($label, $node->label(), new FormattableMarkup('Expected referenceable label found for option :option', [':option' => $i]));
      $i++;
    }
    $this->assertTrue($found_all, 'All referenceable nodes were available as a select list properly ordered.');

    // Change the sort field and direction.
    $view = View::load('test_filter_entity_reference');
    $display = & $view->getDisplay('default');
    $display['display_options']['filters']['field_test_target_id']['handler_settings']['sort']['field'] = 'nid';
    $display['display_options']['filters']['field_test_target_id']['handler_settings']['sort']['direction'] = 'DESC';
    $view->save();

    $this->drupalGet('admin/structure/views/nojs/handler/test_filter_entity_reference/default/filter/field_test_target_id');
    // Items should now be in reverse nid order.
    krsort($this->nodes);
    $options = $this->getUiOptions();
    $found_all = TRUE;
    $i = 0;
    foreach ($this->nodes as $nid => $node) {
      $option = $options[$i];
      $label = $option['label'];
      $found_all = $found_all && $label == $node->label() && $nid == $option['nid'];
      $this->assertEqual($label, $node->label(), new FormattableMarkup('Expected referenceable label found for option :option', [':option' => $i]));
      $i++;
    }
    $this->assertTrue($found_all, 'All referenceable nodes were available as a select list properly ordered.');
  }

  /**
   * Helper method to parse options from the UI.
   *
   * @return array
   *   Array of keyed arrays containing `nid` and `label` of each option.
   */
  protected function getUiOptions() {
    /** @var \Behat\Mink\Element\NodeElement[] $result */
    $result = $this->xpath('//select[@name="options[value][]"]/option');
    $this->assertNotEmpty($result, 'Options found');

    $options = [];
    foreach ($result as $option) {
      $nid = (int) $option->getValue();
      $options[] = [
        'nid' => $nid,
        'label' => (string) $this->getSession()->getDriver()->getText($option->getXpath()),
      ];
    }

    return $options;
  }

}

<?php

namespace Drupal\Tests\linkit\FunctionalJavascript;

use Drupal\entity_test\Entity\EntityTestMul;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\FunctionalJavascriptTests\JavascriptTestBase;
use Drupal\linkit\Tests\ProfileCreationTrait;
use Drupal\node\Entity\NodeType;

/**
 * Tests the widget and formatter for Link fields.
 *
 * @group linkit
 */
class LinkFieldTest extends JavascriptTestBase {

  use ProfileCreationTrait;

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'node',
    'language',
    'field_ui',
    'entity_test',
    'link',
    'linkit',
  ];

  /**
   * A linkit profile.
   *
   * @var \Drupal\linkit\ProfileInterface
   */
  protected $linkitProfile;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $matcherManager = $this->container->get('plugin.manager.linkit.matcher');
    /** @var \Drupal\linkit\MatcherInterface $plugin */

    $this->linkitProfile = $this->createProfile();
    $plugin = $matcherManager->createInstance('entity:entity_test_mul');
    $this->linkitProfile->addMatcher($plugin->getConfiguration());
    $this->linkitProfile->save();

    // Create a node type for testing.
    NodeType::create(['type' => 'page', 'name' => 'page'])->save();

    // Create a link field.
    $storage = FieldStorageConfig::create([
      'field_name' => 'field_test_link',
      'entity_type' => 'node',
      'type' => 'link',
    ]);
    $storage->save();
    FieldConfig::create([
      'bundle' => 'page',
      'entity_type' => 'node',
      'field_name' => 'field_test_link',
    ])->save();

    // Define our widget and formatter for this field.
    entity_get_form_display('node', 'page', 'default')
      ->setComponent('field_test_link', [
        'type' => 'linkit',
      ])
      ->save();
    entity_get_display('node', 'page', 'default')
      ->setComponent('field_test_link', [
        'type' => 'linkit',
      ])
      ->save();

    $account = $this->drupalCreateUser([
      'administer node fields',
      'administer node display',
      'administer nodes',
      'bypass node access',
      'view test entity',
    ]);

    $this->drupalLogin($account);
  }

  /**
   * Test the "linkit" widget and formatter.
   */
  public function testLinkFieldWidgetAndFormatter() {
    $session = $this->getSession();
    $assert_session = $this->assertSession();
    $page = $session->getPage();

    // Create a test entity to be used as target.
    /** @var \Drupal\Core\Entity\EntityInterface $entity */
    $entity = EntityTestMul::create(['name' => 'Foo']);
    $entity->save();

    // Test the widget behavior.
    $this->drupalGet('node/add/page');

    $assert_session->elementContains('css', '#edit-field-test-link-wrapper', 'Start typing to find content or paste a URL and click on the suggestion below.');
    $widget_wrapper = $assert_session->elementExists('css', '#edit-field-test-link-wrapper');
    $uri_input = $assert_session->elementExists('css', 'input[name="field_test_link[0][uri]"]', $widget_wrapper);
    $uri_input->setValue('f');
    $session->getDriver()->keyDown($uri_input->getXpath(), ' ');
    $assert_session->waitOnAutocomplete();

    // With the default profile no results are found.
    $autocomplete_results_wrapper = $assert_session->elementExists('css', 'ul.linkit-ui-autocomplete');
    $this->assertTrue($autocomplete_results_wrapper->isVisible());
    $result_description = $assert_session->elementExists('css', 'li.linkit-result-line .linkit-result-line--description', $autocomplete_results_wrapper);
    $this->assertEquals('Linkit could not find any suggestions. This URL will be used as is.', $result_description->getText());

    // Set the widget to use our profile and try again.
    entity_get_form_display('node', 'page', 'default')
      ->setComponent('field_test_link', [
        'type' => 'linkit',
        'settings' => [
          'linkit_profile' => $this->linkitProfile->id(),
        ],
      ])
      ->save();
    $this->drupalGet('node/add/page');
    $widget_wrapper = $assert_session->elementExists('css', '#edit-field-test-link-wrapper');
    $uri_input = $assert_session->elementExists('css', 'input[name="field_test_link[0][uri]"]', $widget_wrapper);
    $uri_input->setValue('f');
    $session->getDriver()->keyDown($uri_input->getXpath(), 'o');
    $assert_session->waitOnAutocomplete();
    $first_result = $assert_session->elementExists('css', 'ul.linkit-ui-autocomplete li.linkit-result-line span.linkit-result-line--title');
    $first_result->click();
    $assert_session->assertWaitOnAjaxRequest();

    // Check that the URL input field value shows the entity label.
    $url_input = $assert_session->elementExists('css', 'input[name="field_test_link[0][uri]"]', $widget_wrapper);
    $this->assertEquals('Foo', $url_input->getValue());
    // Check that the title was populated automatically.
    $title_input = $assert_session->elementExists('css', 'input[name="field_test_link[0][title]"]', $widget_wrapper);
    $this->assertEquals('Foo', $title_input->getValue());

    // Give the node a title and save the page.
    $page->fillField('title[0][value]', 'Host test node 1');
    $page->pressButton('Save');
    $assert_session->pageTextContains('Host test node 1 has been created');

    // Check that we are viewing the node, and the formatter displays what we
    // expect.
    $assert_session->titleEquals('Host test node 1 | Drupal');
    $field_wrapper = $assert_session->elementExists('css', '.field--type-link.field--name-field-test-link');
    $link_element = $assert_session->elementExists('css', 'a', $field_wrapper);
    $this->assertEquals('Foo', $link_element->getText());
    $href_value = $link_element->getAttribute('href');
    $this->assertContains("/entity_test_mul/manage/{$entity->id()}", $href_value);

    // Test internal entity targets with anchors.
    /** @var \Drupal\Core\Entity\EntityInterface $entity */
    $entity2 = EntityTestMul::create(['name' => 'Anchored Entity']);
    $entity2->save();

    // Test the widget behavior.
    $this->drupalGet('node/add/page');

    $widget_wrapper = $assert_session->elementExists('css', '#edit-field-test-link-wrapper');
    $uri_input = $assert_session->elementExists('css', 'input[name="field_test_link[0][uri]"]', $widget_wrapper);
    $uri_input->setValue('Anchored');
    $session->getDriver()->keyDown($uri_input->getXpath(), ' ');
    $assert_session->waitOnAutocomplete();
    $first_result = $assert_session->elementExists('css', 'ul.linkit-ui-autocomplete li.linkit-result-line span.linkit-result-line--title');
    $first_result->click();
    $assert_session->assertWaitOnAjaxRequest();

    // Check that the URL input field value shows the entity label.
    $url_input = $assert_session->elementExists('css', 'input[name="field_test_link[0][uri]"]', $widget_wrapper);
    $this->assertEquals('Anchored Entity', $url_input->getValue());
    // Check that the title was populated automatically.
    $title_input = $assert_session->elementExists('css', 'input[name="field_test_link[0][title]"]', $widget_wrapper);
    $this->assertEquals('Anchored Entity', $title_input->getValue());

    // Add an anchor to the URL field.
    $url_input->setValue('Anchored Entity#with-anchor');

    // Give the node a title and save the page.
    $page->fillField('title[0][value]', 'Host test node 2');
    $page->pressButton('Save');
    $assert_session->pageTextContains('Host test node 2 has been created');

    // Check that we are viewing the node, and the formatter displays what we
    // expect.
    $assert_session->titleEquals('Host test node 2 | Drupal');
    $field_wrapper = $assert_session->elementExists('css', '.field--type-link.field--name-field-test-link');
    $link_element = $assert_session->elementExists('css', 'a', $field_wrapper);
    $this->assertEquals('Anchored Entity', $link_element->getText());
    $href_value = $link_element->getAttribute('href');
    $this->assertContains("/entity_test_mul/manage/{$entity2->id()}#with-anchor", $href_value);

    // Test external URLs.
    $this->drupalGet('node/add/page');

    $widget_wrapper = $assert_session->elementExists('css', '#edit-field-test-link-wrapper');
    $uri_input = $assert_session->elementExists('css', 'input[name="field_test_link[0][uri]"]', $widget_wrapper);
    $uri_input->setValue('https://google.com#foobar');
    $session->getDriver()->keyDown($uri_input->getXpath(), ' ');
    $assert_session->waitOnAutocomplete();
    $autocomplete_results_wrapper = $assert_session->elementExists('css', 'ul.linkit-ui-autocomplete');
    $this->assertTrue($autocomplete_results_wrapper->isVisible());
    $result_description = $assert_session->elementExists('css', 'li.linkit-result-line .linkit-result-line--description', $autocomplete_results_wrapper);
    $this->assertEquals('Linkit could not find any suggestions. This URL will be used as is.', $result_description->getText());

    // Set a manual value for the title.
    $title_input = $assert_session->elementExists('css', 'input[name="field_test_link[0][title]"]', $widget_wrapper);
    $title_input->setValue('This is google');

    // Give the node a title and save the page.
    $page->fillField('title[0][value]', 'Host test node 3');
    $page->pressButton('Save');
    $assert_session->pageTextContains('Host test node 3 has been created');

    // Check that we are viewing the node, and the formatter displays what we
    // expect.
    $assert_session->titleEquals('Host test node 3 | Drupal');
    $field_wrapper = $assert_session->elementExists('css', '.field--type-link.field--name-field-test-link');
    $link_element = $assert_session->elementExists('css', 'a', $field_wrapper);
    $this->assertEquals('This is google', $link_element->getText());
    $href_value = $link_element->getAttribute('href');
    $this->assertContains('https://google.com#foobar', $href_value);
  }

}

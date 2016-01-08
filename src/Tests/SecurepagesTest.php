<?php

/**
 * @file
 * Contains \Drupal\securepages\Tests\SecurepagesTest.
 */

namespace Drupal\securepages\Tests;

use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\securepages\Securepages;
use Drupal\simpletest\WebTestBase;

/**
 * Test Secure Pages redirects.
 *
 * @group securepages
 */
class SecurepagesTest extends WebTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = ['securepages', 'node', 'comment', 'locale', 'path'];

  /**
   * List of default pages protected.
   *
   * @var array
   *   List of strings.
   */
  protected $pages_default = ['/node/add*', '/node/*/edit', '/node/*/delete', '/user', '/user/*', '/admin', '/admin/*'];

  /**
   * Runs all the tests in a sequence to avoid multiple re-installs.
   */
  function testSecurePages() {
    $this->_testSettingsForm();
    $this->_testMatch();
    $this->_testLocale();
    $this->_testAnonymous();
    $this->_testFormAlter();
    $this->_testCachedResponse();
    $this->_testPathAlias();

    // @todo given no GET q in Drupal 8 and otherwise the format tested not
    // being a valid URL, not sure how would securepages be used for open
    // redirection.
    // $this->_testOpenRedirect();

    $this->_testXHR();
    $this->_testRoles();
    $this->_testPathNorms();
  }

  /**
   * Test submitting the settings form.
   */
  function _testSettingsForm() {
    // Need to log in and use the HTTPS form so we can enable the feature there.
    $this->drupalLoginHttps($this->drupalCreateUser(['administer site configuration']));
    $this->drupalPostForm(
      Securepages::getUrl('securepages.admin_settings'),
      ['enable' => 1],
      t('Save configuration')
    );
    $this->assertRaw(t('The configuration options have been saved.'));

    // Clean up.
    $this->drupalLogout();
  }

  /**
   * Tests the securepages_match() function.
   */
  function _testMatch() {
    \Drupal::configFactory()->getEditable('securepages.settings')->set('ignore', ['*/autocomplete/*'])->save();

    $this->assertTrue(Securepages::matchPath('/user'), 'user matches.');
    $this->assertTrue(Securepages::matchPath('/user/login'), 'user/login matches.');
    $this->assertTrue(Securepages::matchPath('/admin/modules'), 'path admin/modules matches.');
    $this->assertFalse(Securepages::matchPath('/node'), 'path node does not match.');

    $request = \Drupal::requestStack()->getCurrentRequest();
    $this->assertEqual(Securepages::matchPath('/user/autocomplete/alice'), $request->isSecure() ? TRUE : FALSE, 'autocomplete path is ignored.');

    // Clean up.
    \Drupal::configFactory()->getEditable('securepages.settings')->set('ignore', [])->save();
  }

  /**
   * Tests correct operation with locale module.
   */
  function _testLocale() {
    $french = ConfigurableLanguage::createFromLangcode('fr');
    $french->save();
    $this->drupalGet('fr/user');
    $this->assertResponse(200);
    $this->assertUrl(Url::fromRoute('<front>', [], ['https' => TRUE, 'absolute' => TRUE])->toString() . 'fr/user/login');
    $this->assertTrue(strstr($this->url, 'fr/user'), t('URL contains language prefix.'));

    $this->drupalGet('fr');
    $this->assertResponse(200);
    $this->assertUrl(Url::fromRoute('<front>', [], ['https' => TRUE, 'absolute' => TRUE])->toString() . 'fr');
  }

  /**
   * Tests for anonymous browsing with securepages.
   */
  function _testAnonymous() {
    // Visit the home page and /node with plain HTTP.
    $this->drupalGet('', ['https' => FALSE]);
    $this->assertResponse(200);
    $this->assertUrl(Url::fromRoute('<front>', [], ['https' => FALSE, 'absolute' => TRUE]));
    $this->drupalGet('node', ['https' => FALSE]);
    $this->assertResponse(200);
    $this->assertUrl(Url::fromUri('internal:/node', [], ['https' => FALSE, 'absolute' => TRUE]));

    // Visit the login page and confirm that browser is redirected to HTTPS.
    $this->drupalGet('user', ['https' => FALSE]);
    $this->assertResponse(200);
    $this->assertUrl(Url::fromRoute('user.login', [], ['https' => TRUE, 'absolute' => TRUE]));

    // Visit the home page and /node with HTTPS and confirm that no redirection happens.
    $this->drupalGet('', ['https' => TRUE]);
    $this->assertResponse(200);
    $this->assertUrl(Url::fromRoute('<front>', [], ['https' => TRUE, 'absolute' => TRUE]));
    $this->drupalGet('node', ['https' => TRUE]);
    $this->assertResponse(200);
    $this->assertUrl(Url::fromUri('internal:/node', [], ['https' => TRUE, 'absolute' => TRUE]));

    // Enable "Switch back to http pages when there are no matches".
    \Drupal::configFactory()->getEditable('securepages.settings')->set('switch', TRUE)->save();

    // Visit the home page and /node with HTTPS and confirm that switch-back happens.
    $this->drupalGet('', ['https' => TRUE]);
    $this->assertResponse(200);
    $this->assertUrl(Url::fromRoute('<front>', [], ['https' => FALSE, 'absolute' => TRUE]));
    $this->drupalGet('node', ['https' => TRUE]);
    $this->assertResponse(200);
    $this->assertUrl(Url::fromRoute('<front>', [], ['https' => FALSE, 'absolute' => TRUE]));

    // Clean up.
    \Drupal::configFactory()->getEditable('securepages.settings')->set('switch', FALSE)->save();
  }

  /**
   * Tests the ability to alter form actions.
   *
   * Uses the comment form, since it has an #action set.
   */
  function _testFormAlter() {
    $config = \Drupal::configFactory()->getEditable('securepages.settings');
    $config->set('switch', TRUE)->save();

    // Enable anonymous user comments.
    user_role_change_permissions(AccountInterface::ANONYMOUS_ROLE, [
      'access comments' => TRUE,
      'post comments' => TRUE,
      'skip comment approval' => TRUE,
    ]);

    $account = $this->drupalCreateUser(['access content', 'access comments', 'post comments', 'skip comment approval']);
    $node = $this->drupalCreateNode(['type' => 'article', 'promote' => 1]);

    foreach (array('anonymous', 'authenticated') as $mode) {
      if ($mode == 'authenticated') {
        $this->drupalLogin($account);
      }

      // Test plain HTTP posting to HTTPS.
      $config->set('pages', ['/comment/reply/*', '/user*'])->save();
      $this->drupalGet('node/' . $node->id(), ['https' => FALSE]);
      $this->assertFieldByXPath('//form[@class="comment-form" and starts-with(@action, "https:")]', NULL, "The $mode comment form action is https.");
      $this->drupalPostForm(NULL, ['comment_body[und][0][value]' => 'test comment'], t('Save'));
      $this->assertRaw(t('Your comment has been posted.'));

      // Test HTTPS posting to plain HTTP.
      $config->set('pages', ['/node/*', '/user*'])->save();
      $this->drupalGet('node/' . $node->id(), ['https' => TRUE]);
      $this->assertUrl(Url::fromRoute('entity.node.canonical', ['node' => $node->id()], ['https' => TRUE, 'absolute' => TRUE]));
      $this->assertFieldByXPath('//form[@class="comment-form" and starts-with(@action, "http:")]', NULL, "The $mode comment form action is http.");
      $this->drupalPostForm(NULL, ['comment_body[und][0][value]' => 'test'], t('Save'));
      $this->assertRaw(t('Your comment has been posted.'));
    }
    $this->drupalLogout();

    // Test the user login block.
    $this->drupalGet('');
    $edit = [
      'name' => $account->getAccountName(),
      'pass' => $account->pass_raw,
    ];
    $this->drupalPostForm(NULL, $edit, t('Log in'));
    $this->drupalGet('user/' . $account->id() . '/edit');
    $this->assertResponse(200);

    // Clean up.
    $this->drupalLogout();
    $config
      ->set('switch', FALSE)
      ->set('pages', $this->pages_default)
      ->save();
  }

  function _testCachedResponse() {
    // Enable the page cache and fetch the login page.
    $this->installModule('page_cache');
    $url = Url::fromRoute('user.login', [], ['absolute' => TRUE, 'https' => FALSE]);
    $this->drupalGet($url);

    // Short-circuit redirects within the simpletest browser.
    $maximum_redirects = $this->maximumRedirects;
    $this->maximumRedirects = 0;
    $this->drupalGet($url);
    $this->assertResponse(302);
    $this->assertEqual($this->drupalGetHeader('Location'), Url::fromRoute('user.login', [], ['https' => TRUE, 'absolute' => TRUE])->toString());
    $this->assertEqual($this->drupalGetHeader('X-Drupal-Cache'), 'HIT', 'Page was cached.');

    // Clean up.
    $this->maximumRedirects = $maximum_redirects;
    $this->uninstallModule('page_cache');
  }

  /**
   * Test redirection on aliased paths.
   */
  function _testPathAlias() {
    $config = \Drupal::configFactory()->getEditable('securepages.settings');
    $config->set('pages', ['/node/*', '/user*'])->save();

    // Create test user and login.
    $this->drupalLogin($this->drupalCreateUser(['administer url aliases', 'create url aliases']));

    // Create test node.
    $node = $this->drupalCreateNode();

    // Create alias.
    $edit = [];
    $edit['source'] = 'node/' . $node->id();
    $edit['alias'] = $this->randomMachineName();
    $this->drupalPostForm('admin/config/search/path/add', $edit, t('Save'));

    // Short-circuit redirects within the simpletest browser.
    $maximum_redirects = $this->maximumRedirects;
    $this->maximumRedirects = 0;
    $this->drupalGet($edit['alias'], ['absolute' => TRUE, 'https' => FALSE]);
    $this->assertResponse(302);
    $this->assertEqual($this->drupalGetHeader('Location'), Url::fromRoute('<front>', [], ['https' => TRUE, 'absolute' => TRUE])->toString() . $edit['alias']);

    // Clean up.
    $this->maximumRedirects = $maximum_redirects;
    $this->drupalLogout();
    $config->set('pages', $this->pages_default)->save();
  }

  /**
   * Verifies that securepages is not an open redirect.
   */
  function _testOpenRedirect() {
    // Short-circuit redirects within the simpletest browser.
    variable_set('simpletest_maximum_redirects', 0);
    variable_set('securepages_switch', TRUE);

    global $base_url, $base_path;
    $secure_base_url = str_replace('http', 'https', $base_url);
    $this->drupalGet($secure_base_url . $base_path . '?q=http://example.com/', array('external' => TRUE));
    $this->assertResponse(302);
    $this->assertTrue(strstr($this->drupalGetHeader('Location'), $base_url), t('Open redirect test passed.'));

    $this->drupalGet($secure_base_url . $base_path . '?q=' . urlencode('http://example.com/'), array('external' => TRUE));
    $this->assertResponse(302);
    $this->assertTrue(strstr($this->drupalGetHeader('Location'), $base_url), t('Open redirect test passed.'));

    // Clean up
    variable_del('simpletest_maximum_redirects');
    variable_del('securepages_switch');
  }

  /**
   * Test detection of XHR requests.
   */
  function _testXHR() {
    // Without XHR header.
    $this->drupalGet('user', ['https' => FALSE]);
    $this->assertResponse(200);
    $this->assertUrl(Url::fromRoute('user.login', [], ['https' => TRUE, 'absolute' => TRUE]));

    // With XHR header.
    $this->drupalGet('user', ['https' => FALSE], ['X-Requested-With: XMLHttpRequest']);
    $this->assertResponse(200);
    $this->assertUrl(Url::fromRoute('user.login', [], ['https' => FALSE, 'absolute' => TRUE]));
  }

  /**
   * Test role-based switching.
   */
  function _testRoles() {
    $account = $this->drupalCreateUser(['access content']);
    $role = current($account->getRoles(TRUE));

    $this->drupalLoginHttps($account);
    $config = \Drupal::configFactory()->getEditable('securepages.settings');
    $config->set('switch', TRUE)->set('roles', [$role])->set('pages', ['/admin*'])->save();

    // Visit the home page and /user with HTTPS and confirm that redirection happens.
    $this->drupalGet('', ['https' => FALSE]);
    $this->assertResponse(200);
    $this->assertUrl(Url::fromRoute('<front>', [], ['https' => TRUE, 'absolute' => TRUE]));
    $this->drupalGet('user', ['https' => FALSE]);
    $this->assertResponse(200);
    $this->assertUrl(Url::fromRoute('user.page', [], ['https' => TRUE, 'absolute' => TRUE]));

    // Test that forms actions aren't switched back to http.
    $node = $this->drupalCreateNode(['type' => 'article', 'promote' => 1]);
    $this->drupalGet('node/' . $node->id(), ['https' => TRUE]);
    $this->assertFieldByXPath('//form[@class="comment-form" and starts-with(@action, "/")]', NULL, "The comment form action is https.");

    // Clean up.
    $config->set('switch', FALSE)->set('roles', [])->set('pages', $this->pages_default)->save();
    $this->drupalLogout();
  }

  /**
   * Test path normalization checks.
   */
  function _testPathNorms() {
    $config = \Drupal::configFactory()->getEditable('securepages.settings');
    $config->set('switch', TRUE)->set('pages', ['/user'])->save();

    // Test mixed-case path.
    $this->drupalGet('UsEr');
    $this->assertUrl('UsEr', ['https' => TRUE, 'absolute' => TRUE]);
    $this->assertFieldByXPath('//form[@id="user-login" and starts-with(@action, "/")]', NULL, 'The user login form action is https.');

    // Test that a trailing slash will not force a protected form's action to
    // http. A http based 'user/' path will become 'user' when doing the
    // redirect, so best to ensure that the test gets the right conditions the
    // path should be https based.
    $this->drupalGet('user/', ['https' => TRUE, 'absolute' => TRUE]);
    $this->assertUrl('user/', ['https' => TRUE, 'absolute' => TRUE]);
    $this->assertFieldByXPath('//form[@id="user-login" and starts-with(@action, "/")]', NULL, 'The user login form action is https.');

    // Clean up.
    $config->set('switch', FALSe)->set('pages', $this->pages_default)->save();
  }

  /**
   * Log in a user with the internal browser using HTTPS.
   *
   * @see \Drupal\simpletest\WebTestBase::drupalLogin().
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   User object representing the user to log in.
   */
  function drupalLoginHttps($account) {
    if ($this->loggedInUser) {
      $this->drupalLogout();
    }

    $edit = array(
      'name' => $account->getAccountName(),
      'pass' => $account->pass_raw
    );
    $this->drupalPostForm(Securepages::getUrl('user.login'), $edit, t('Log in'));

    // @see WebTestBase::drupalUserIsLoggedIn()
    if (isset($this->sessionId)) {
      $account->session_id = $this->sessionId;
    }
    $pass = $this->assert($this->drupalUserIsLoggedIn($account), t('User %name successfully logged in.', array('%name' => $account->getAccountName())), 'User login');
    if ($pass) {
      $this->loggedInUser = $account;
      $this->container->get('current_user')->setAccount($account);
    }
  }

  /**
   * Installs a module.
   *
   * @param string $module
   *   The module name.
   */
  protected function installModule($module) {
    $this->container->get('module_installer')->install(array($module));
    $this->container = \Drupal::getContainer();
  }

  /**
   * Uninstalls a module.
   *
   * @param string $module
   *   The module name.
   */
  protected function uninstallModule($module) {
    $this->container->get('module_installer')->uninstall(array($module));
    $this->container = \Drupal::getContainer();
  }

}

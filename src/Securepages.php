<?php
/**
 * @file
 * Contains \Drupal\securepages\Securepages.
 */

namespace Drupal\securepages;

use Drupal\Component\Utility\Unicode;
use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Url;
use GuzzleHttp\Exception\RequestException;

/**
 * Utility class for global functionality.
 */
class Securepages {

  /**
   * Checks the current request and see if we need to redirect.
   *
   * @return bool
   *   TRUE if we need to redirect to HTTPS, FALSE to redirect to HTTP,
   *   NULL if no redirect should happen.
   */
  public static function checkRedirect() {
    $request = \Drupal::requestStack()->getCurrentRequest();
    $current_path_stack = \Drupal::service('path.current');
    $path = $current_path_stack->getPath($request);
    $is_xmlhttp = $request->headers->get('HTTP_X_REQUESTED_WITH') == 'XMLHttpRequest';
    $is_https = $request->isSecure();
    $switch = \Drupal::config('securepages.settings')->get('switch');

    // Only redirect if this was not a post request.
    if ($request->getMethod() != 'POST' && !$is_xmlhttp) {
      $role_match = Securepages::matchCurrentUser();
      $page_match = Securepages::matchCurrentPath();

      if ($role_match && !$is_https) {
        Securepages::log('Redirect user to secure on @path.', $path);
        return TRUE;
      }
      elseif ($page_match && !$is_https) {
        Securepages::log('Redirect path to secure on @path.', $path);
        return TRUE;
      }
      elseif ($page_match === FALSE && $is_https && $switch && !$role_match) {
        Securepages::log('Redirect path to insecure on @path.', $path);
        return FALSE;
      }
    }

    return NULL;
  }

  /**
   * Checks if the user is in a role that is always forced onto HTTPS.
   *
   * @return int
   *   The number of roles set on the user that require HTTPS enforcing.
   */
  public static function matchCurrentUser() {
    $account = \Drupal::currentUser();
    $config_roles = \Drupal::config('securepages.settings')->get('roles');
    $account_roles = $account->getRoles();
    return count(array_intersect($account_roles, $config_roles));
  }

  /**
   * Match the current path against the configured settings.
   *
   * @return bool|NULL
   *   - FALSE: Path should be non-secure.
   *   - TRUE: Path should be secure.
   *   - NULL: No explicit information.
   */
  public static function matchCurrentPath() {
    $request = \Drupal::requestStack()->getCurrentRequest();
    /** @var \Drupal\Core\Path\CurrentPathStack $current_path */
    $current_path_stack = \Drupal::service('path.current');
    return Securepages::matchPath($current_path_stack->getPath($request));
  }

  /**
   * Match path against securepages settings.
   *
   * @param string $path
   *   Path to match against settings.
   *
   * @return bool|NULL
   *   - FALSE: Path should be non-secure.
   *   - TRUE: Path should be secure.
   *   - NULL: No explicit information.
   */
  public static function matchPath($path) {
    // Convert paths to lowercase. This allows comparison of the same path
    // with different case. Ex: /Page, /page, /PAGE.
    $config = \Drupal::config('securepages.settings');
    $pages = Unicode::strtolower(implode("\n", $config->get('pages')));
    $ignore = Unicode::strtolower(implode("\n", $config->get('ignore')));

    $request = \Drupal::requestStack()->getCurrentRequest();
    /** @var \Drupal\Core\Path\AliasManager $alias_manager */
    $alias_manager = \Drupal::service('path.alias_manager');
    /** @var \Drupal\Core\Path\PathMatcher $path_matcher */
    $path_matcher = \Drupal::service('path.matcher');

    // Compare the lowercase path alias (if any) and internal path.
    $path = rtrim($path, '/');
    $path_alias = Unicode::strtolower($alias_manager->getAliasByPath($path));

    // Checks to see if the page matches the current settings.
    if ($ignore) {
      if ($path_matcher->matchPath($path_alias, $ignore) || (($path != $path_alias) && $path_matcher->matchPath($path, $ignore))) {
        //Securepages::log('Ignored path (Path: "@path", Line: @line, Pattern: "@pattern")', $path, $ignore);
        return $request->isSecure();
      }
    }
    if ($pages) {
      $result = $path_matcher->matchPath($path_alias, $pages) || (($path != $path_alias) && $path_matcher->matchPath($path, $pages));
      if (!($config->get('secure') xor $result)) {
        //Securepages::log('Secure path (Path: "@path", Line: @line, Pattern: "@pattern")', $path, $pages);
      }
      return !($config->get('secure') xor $result);
    }
    else {
      return NULL;
    }
  }

  /**
   * Match form identifier against forms configured.
   *
   * @param string $form_id
   *   The form identifier.
   *
   * @return bool
   *   Whether the form identifier matched the configured identifiers.
   */
  public static function matchFormId($form_id) {
    $config = \Drupal::config('securepages.settings');
    $forms = Unicode::strtolower(implode("\n", $config->get('forms')));

    /** @var \Drupal\Core\Path\PathMatcher $path_matcher */
    $path_matcher = \Drupal::service('path.matcher');

    if ($path_matcher->matchPath($form_id, $forms)) {
      //Securepages::log('Secure Form (Form: "@path", Line: @line, Pattern: "@pattern")', $form_id, $forms);
      return TRUE;
    }

    return FALSE;
  }

  /**
   * Get base URL from configuration or compute it if not set.
   *
   * @param string $route_name
   *   The route name. Defaults to the front page.
   * @param array $route_parameters
   *   (optional) An associative array of route parameter names and values.
   * @param array $options
   *   (optional) An associative array of additional URL options, see
   *   \Drupal\Core\Url::fromRoute().
   * @param bool $secure
   *   (optional) Whether to generate the secure URL or the insecure URL.
   *   Defaults to secure.
   *
   * @return \Drupal\Core\Url
   *   The secure or non-secure base URL.
   */
  public static function getUrl($route_name = '<front>', $route_parameters = [], $options = [], $secure = TRUE) {
    $options += ['https' => $secure, 'absolute' => TRUE];
    if ($config_url = \Drupal::config('securepages.settings')->get('basepath' . ($secure ? '_ssl' : ''))) {
      $options['base_url'] = $config_url;
    }
    return Url::fromRoute($route_name, $route_parameters, $options);
  }

  /**
   * Perform an HTTPS test with the request of the test page is necessary.
   *
   * @return bool
   *   TRUE if HTTPS is supposed, FALSE otherwise.
   */
  public static function isHTTPSSupported() {
    // If this request was already HTTPS, it is supported.
    if (\Drupal::requestStack()->getCurrentRequest()->isSecure()) {
      return TRUE;
    }

    // Otherwise, attempt to load the test page with HTTPS.
    /** @var \GuzzleHttp\Client $client */
    try {
      $client = \Drupal::httpClient();
      $response = $client->request(
        'GET',
        Securepages::getUrl('<front>')->toString(),
        // Ignore self-signed certificate for this test.
        ['verify' => FALSE]
      );
      return $response->getStatusCode() === 200;
    }
    catch (RequestException $e) {
      return FALSE;
    }
  }

  /**
   * Checks the URL to make sure it is a URL that can be altered.
   *
   * @param string $url
   *   URL to check.
   */
  public static function canAlterUrl($url) {
    $base = Url::fromRoute('<front>', [], ['absolute' => TRUE])->toString();
    return (!UrlHelper::isExternal($url)) || UrlHelper::externalIsLocal($url, $base);
  }

  /**
   * Log a message for debugging purposes.
   *
   * @param string $message
   *   Message to be logged. Potential placeholders include @path and @pattern.
   * @param string $path
   *   Path being redirected.
   * @param string $pattern
   *   (Optional) pattern being matched, such as form ids or paths compared.
   */
  protected static function log($message, $path, $pattern = NULL) {
    if (\Drupal::config('securepages.settings')->get('debug')) {
      $options = [
        '@path' => $path,
        '@pattern' => $pattern,
      ];
      debug(t($message, $options));
    }
  }

}

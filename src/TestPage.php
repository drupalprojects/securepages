<?php
/**
 * @file
 * Contains \Drupal\securepages\TestPage.
 */

namespace Drupal\securepages;

use Drupal\Core\Url;
use GuzzleHttp\Exception\RequestException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Controller class to help test HTTP support.
 */
class TestPage {

  /**
   * HTTPS test page controller.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   Response with 404 or 200 status depending on security of request.
   */
  public function page() {
    /** @var \Symfony\Component\HttpFoundation\RequestStack $request_stack */
    $request_stack = \Drupal::service('request_stack');
    $request = $request_stack->getCurrentRequest();
    return new Response('', $request->isSecure() ? 200 : 404);
  }

  /**
   * Perform an HTTPS test with the request of the test page is necessary.
   *
   * @return bool
   *   TRUE if HTTPS is supposed, FALSE otherwise.
   */
  public static function isHTTPSSupported() {
    /** @var \Symfony\Component\HttpFoundation\RequestStack $request_stack */
    $request_stack = \Drupal::service('request_stack');
    $request = $request_stack->getCurrentRequest();
    if ($request->isSecure()) {
      // If this request was already HTTPS, it is supported.
      return TRUE;
    }

    // Otherwise, attempt to load the test page with HTTPS.
    /** @var \GuzzleHttp\Client $client */
    try {
      $client = \Drupal::httpClient();
      $response = $client->request(
        'GET',
        Url::fromRoute('securepages.admin_test', [], ['https' => TRUE, 'absolute' => TRUE])->toString()
      );
      return $response->getStatusCode() === 200;
    }
    catch (RequestException $e) {
      return FALSE;
    }
  }

}

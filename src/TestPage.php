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

}

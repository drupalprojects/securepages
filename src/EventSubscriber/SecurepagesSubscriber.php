<?php
/**
 * @file
 * Contains \Drupal\securepages\EventSubscriber\SecurepagesSubscriber.
 */

namespace Drupal\securepages\EventSubscriber;

use Drupal\Core\Routing\RouteMatch;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Drupal\securepages\Securepages;

class SecurepagesSubscriber implements EventSubscriberInterface {

  /**
   * Event handler for request processing. Redirects as needed.
   *
   * @param \Symfony\Component\HttpKernel\Event\GetResponseEvent $event
   */
  public function checkForRedirection(GetResponseEvent $event) {
    $config = \Drupal::config('securepages.settings');
    if ($config->get('enable') && php_sapi_name() != 'cli') {
      $redirect = Securepages::checkRedirect();
      if ($redirect !== NULL) {
        $request = $event->getRequest();
        $route_match = RouteMatch::createFromRequest($request);
        $route_name = $route_match->getRouteName();
        $route_parameters = $route_match->getParameters()->all();
        $qs = $request->getQueryString();
        $url = Securepages::getUrl($route_name, $route_parameters, [], $redirect) . ($qs ? '?' . $qs : '');
        $event->setResponse(new TrustedRedirectResponse($url));
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  static function getSubscribedEvents() {
    $events[KernelEvents::REQUEST][] = array('checkForRedirection');
    return $events;
  }
}

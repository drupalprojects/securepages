<?php
/**
 * @file
 * Contains \Drupal\securepages\EventSubscriber\SecurepagesSubscriber.
 */

namespace Drupal\securepages\EventSubscriber;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Routing\RouteMatch;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Drupal\securepages\Securepages;
use Symfony\Component\HttpKernel\Event\FilterResponseEvent;
use Symfony\Component\HttpFoundation\RedirectResponse;

class SecurepagesSubscriber implements EventSubscriberInterface {

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Constructs a new SecurepagesSubscriber.
   */
  public function __construct(ConfigFactoryInterface $config_factory) {
    $this->configFactory = $config_factory;
  }

  /**
   * Event handler for request processing. Redirects as needed.
   *
   * @param \Symfony\Component\HttpKernel\Event\GetResponseEvent $event
   */
  public function checkRequestRedirection(GetResponseEvent $event) {
    $config = $this->configFactory->get('securepages.settings');
    if ($config->get('enable') && php_sapi_name() != 'cli') {
      $redirect = Securepages::checkRedirect();
      if ($redirect !== NULL) {
        $request = $event->getRequest();
        $route_match = RouteMatch::createFromRequest($request);
        $route_name = $route_match->getRouteName();
        $route_parameters = $route_match->getRawParameters()->all();
        $qs = $request->getQueryString();
        $url = Securepages::getUrl($route_name, $route_parameters, [], $redirect)->toString() . ($qs ? '?' . $qs : '');
        $event->setResponse(new TrustedRedirectResponse($url));
      }
    }
  }

  /**
   * Event handler for response processing. Alters redirects if needed.
   *
   * @param \Symfony\Component\HttpKernel\Event\FilterResponseEvent $event
   */
  public function checkResponseRedirection(FilterResponseEvent $event) {
    $response = $event->getResponse();
    if ($response instanceOf RedirectResponse) {
      $config = $this->configFactory->get('securepages.settings');
      if ($config->get('enable')) {
        $role_match = Securepages::matchCurrentUser();
        $page_match = Securepages::matchCurrentPath();
        $is_https = $event->getRequest()->isSecure();
        $target = $response->getTargetUrl();

        if ($role_match || $page_match) {
          // If we are not already on HTTPS and the redirect target is HTTP,
          // replace the non-secure base with a secure base.
          if (!$is_https && (strpos($target, 'http://') === 0)) {
            $target = str_replace(
              Securepages::getUrl('<front>', [], [], FALSE)->toString(),
              Securepages::getUrl('<front>')->toString(),
              $target
            );
            $response->setTargetUrl($target);
          }
        }
        elseif ($page_match === FALSE && $is_https && $config->get('switch') && (strpos($target, 'https://') === 0)) {
          // If we are not already on HTTP, should switch to HTTP and the
          // redirect target is HTTPS, replace the secure base with a non-secure
          // base.
          $target = str_replace(
            Securepages::getUrl('<front>')->toString(),
            Securepages::getUrl('<front>', [], [], FALSE)->toString(),
            $target
          );
          $response->setTargetUrl($target);
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  static function getSubscribedEvents() {
    $events[KernelEvents::REQUEST][] = array('checkRequestRedirection');
    $events[KernelEvents::RESPONSE][] = array('checkResponseRedirection');
    return $events;
  }

}

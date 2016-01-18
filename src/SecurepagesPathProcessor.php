<?php

/**
 * @file
 * Contains Drupal\securepages\SecurepagesPathProcessor.
 */

namespace Drupal\securepages;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\PathProcessor\OutboundPathProcessorInterface;
use Drupal\Core\Render\BubbleableMetadata;
use Symfony\Component\HttpFoundation\Request;

/**
 * Securepages path processor.
 *
 * This path processor applies a configured secure base URL. It is useful for
 * sites that have multiple insecure base URLs and an SSL certificate valid only
 * for one secure base URL.
 */
class SecurepagesPathProcessor implements OutboundPathProcessorInterface {

  /**
   * The configured secure login base URL.
   */
  protected $baseUrl;

  /**
   * Constructs secure login path processor.
   */
  public function __construct(ConfigFactoryInterface $config_factory) {
    $this->baseUrl = $config_factory->get('securepages.settings')->get('basepath_ssl');
  }

  /**
   * {@inheritdoc}
   */
  public function processOutbound($path, &$options = [], Request $request = NULL, BubbleableMetadata $bubbleable_metadata = NULL) {
    if (!empty($options['https']) && $this->baseUrl) {
      $options['absolute'] = TRUE;
      $options['base_url'] = $this->baseUrl;
    }
    return $path;
  }

}
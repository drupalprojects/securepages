<?php
/**
 * @file
 * Contains \Drupal\securepages\SecurepagesSessionServiceProvider.
 */

namespace Drupal\securepages;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderBase;

/**
 * Service Provider for File entity.
 */
class SecurepagesSessionServiceProvider extends ServiceProviderBase {

  /**
   * {@inheritdoc}
   */
  public function alter(ContainerBuilder $container) {
    $definition = $container->getDefinition('session_manager');
    $definition->setClass('Drupal\securepages\SecurepagesSessionManager');
  }
}

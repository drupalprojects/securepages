<?php
/**
 * @file
 * Contains \Drupal\securepages\Form\SecurepagesSettingsForm.
 */

namespace Drupal\securepages\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\securepages\Securepages;
use Symfony\Component\HttpFoundation\Request;

/**
 * Defines a form that configures forms module settings.
 */
class SecurepagesSettingsForm  extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'securepages_admin_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['securepages.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, Request $request = NULL) {
    $config = $this->config('securepages.settings');
    $secure = 0;
    if($config->get('secure')) {
      $secure = 1;
    }
    $form['enable'] = array(
      '#type' => 'checkbox',
      '#title' => t('Enable Secure Pages'),
      '#default_value' => $config->get('enable'),
      '#disabled' => !Securepages::isHTTPSSupported(),
      '#description' => $this->t('To start using secure pages this setting must be enabled. This setting will only be possible to change when the web server has been configured for HTTPS. You may need to set the secure base URL below in case of a custom port. <a href=":url">You can manually visit the site in HTTPS too</a>.', array(':url' => Securepages::getUrl('<front>')->toString())),
    );

    $form['switch'] = array(
      '#type' => 'checkbox',
      '#title' => $this->t('Switch back to HTTP pages when there are no matches'),
      '#default_value' => $config->get('switch'),
    );

    $form['basepath'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Non-secure base URL'),
      '#default_value' => $config->get('basepath'),
      '#size' => 100,
    );
    
    $form['basepath_ssl'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Secure base URL'),
      '#default_value' => $config->get('basepath_ssl'),
      '#size' => 100,
    );

    $active_options = [
      0 => $this->t('Pages not matching the patterns should be secure'),
      1 => $this->t('Pages matching the patterns should be secure')
    ];
    $form['secure'] = array(
      '#type' => 'radios',
      '#title' => $this->t('Which pages will be secure'),
      '#default_value' => $secure,
      '#options' => $active_options,
    );
    $form['pages'] = array(
      '#title' => $this->t('Pages'),
      '#type' => 'textarea',
      '#default_value' => implode("\n", $config->get('pages')),
      '#cols' => 40,
      '#rows' => 5,
      '#description' => $this->t("Enter one page per line as Drupal paths. The '*' character is a wildcard. Example paths are '<em>blog</em>' for the main blog page and '<em>blog/*</em>' for every personal blog. '<em>&lt;front&gt;</em>' is the front page."),
    );

    $form['ignore'] = array(
      '#type' => 'textarea',
      '#title' => $this->t('Ignore pages'),
      '#default_value' => implode("\n", $config->get('ignore')),
      '#cols' => 40,
      '#rows' => 5,
      '#description' => $this->t("The pages listed here will be ignored and be either returned in HTTP or HTTPS. Enter one page per line as Drupal paths. The '*' character is a wildcard. Example paths are '<em>blog</em>' for the blog page and '<em>blog/*</em>' for every personal blog. '<em>&lt;front&gt;</em>' is the front page."),
    );

    $form['roles'] = array(
      '#type' => 'checkboxes',
      '#title' => $this->t('User roles'),
      '#description' => $this->t('Users with the chosen role(s) are always redirected to HTTPS, regardless of path rules.'),
      '#options' => user_role_names(),
      '#default_value' => $config->get('roles'),
    );

    $form['forms'] = array(
      '#type' => 'textarea',
      '#title' => $this->t('Secure forms'),
      '#default_value' => implode("\n", $config->get('forms')),
      '#cols' => 40,
      '#rows' => 5,
      '#description' => $this->t('List of form ids which will have the HTTPS flag set to TRUE.'),
    );

    $form['debug'] = array(
      '#type' => 'checkbox',
      '#title' => $this->t('Enable debugging'),
      '#default_value' => $config->get('debug'),
      '#description' => $this->t('Turn on debugging to allow easier testing of settings.'),
    );
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('securepages.settings')
      ->set('enable', $form_state->getValue('enable'))
      ->set('switch', $form_state->getValue('switch'))
      ->set('basepath', $form_state->getValue('basepath'))
      ->set('basepath_ssl', $form_state->getValue('basepath_ssl'))
      ->set('secure', $form_state->getValue('secure'))
      ->set('pages', $this::explodeValues($form_state->getValue('pages')))
      ->set('ignore', $this::explodeValues($form_state->getValue('ignore')))
      ->set('roles', array_filter($form_state->getValue('roles')))
      ->set('forms', $this::explodeValues($form_state->getValue('forms')))
      ->set('debug', $form_state->getValue('debug'))
      ->save();

    parent::submitForm($form, $form_state);
  }

  /**
   * Explode $values and returned a clean array with options.
   *
   * @param string $values
   *   Values as entered in the form, separated by newlines.
   *
   * @return array
   *   Array formatted trimmed values with empty items removed.
   */
  private static function explodeValues($values) {
    // Convert string to an array, trim whitespace on each item, remove
    // empty items and reindex array for clean export.
    return array_values(array_filter(array_map('trim', explode("\n", $values))));
  }

}

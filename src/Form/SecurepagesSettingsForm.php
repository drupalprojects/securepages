<?php
/**
 * @file
 * Contains \Drupal\securepages\Form\SecurepagesSettingsForm.
 */

namespace Drupal\securepages\Form;

use Drupal\Core\Form\ConfigFormBase;
use Symfony\Component\HttpFoundation\Request;
use Drupal\Core\Form\FormStateInterface;

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

    $form['enable'] = array(
      '#type' => 'radios',
      '#title' => t('Enable Secure Pages'),
      '#default_value' => $config->get('enable'),
      '#options' => array(t('Disabled'), t('Enabled')),
      //'#disabled' => !securepages_test(),
      //'#description' => $this->t('To start using secure pages this setting must be enabled. This setting will only be able to changed when the web server has been configured for SSL.<br /><a href=":url">If this test has failed then go here</a>.', array(':url' => preg_replace(';^http://;i', 'https://', url($_GET['q'], array('absolute' => TRUE))))),
    );

    $form['switch'] = array(
      '#type' => 'checkbox',
      '#title' => $this->t('Switch back to http pages when there are no matches'),
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
      $this->t('Make secure every page except the listed pages.'),
      $this->t('Make secure only the listed pages.')
    ];
    $form['secure'] = array(
      '#type' => 'radios',
      '#title' => $this->t('Pages which will be be secure'),
      '#default_value' => $config->get('secure'),
      '#options' => $active_options,
    );
    $form['pages'] = array(
      '#title' => $this->t('Pages'),
      '#type' => 'textarea',
      '#default_value' => $config->get('pages'),
      '#cols' => 40,
      '#rows' => 5,
      '#description' => $this->t("Enter one page per line as Drupal paths. The '*' character is a wildcard. Example paths are '<em>blog</em>' for the main blog page and '<em>blog/*</em>' for every personal blog. '<em>&lt;front&gt;</em>' is the front page."),
    );

    $form['ignore'] = array(
      '#type' => 'textarea',
      '#title' => $this->t('Ignore pages'),
      '#default_value' => $config->get('ignore'),
      '#cols' => 40,
      '#rows' => 5,
      '#description' => $this->t("The pages listed here will be ignored and be either returned in http or https. Enter one page per line as Drupal paths. The '*' character is a wildcard. Example paths are '<em>blog</em>' for the blog page and '<em>blog/*</em>' for every personal blog. '<em>&lt;front&gt;</em>' is the front page."),
    );

    $form['roles'] = array(
      '#type' => 'checkboxes',
      '#title' => 'User roles',
      '#description' => $this->t('Users with the chosen role(s) are always redirected to https, regardless of path rules.'),
      '#options' => user_role_names(),
      '#default_value' => $config->get('roles'),
    );

    $form['forms'] = array(
      '#type' => 'textarea',
      '#title' => $this->t('Secure forms'),
      '#default_value' => $config->get('forms'),
      '#cols' => 40,
      '#rows' => 5,
      '#description' => $this->t('List of form ids which will have the https flag set to TRUE.'),
    );

    $form['debug'] = array(
      '#type' => 'checkbox',
      '#title' => $this->t('Enable Debugging'),
      '#default_value' => $config->get('debug'),
      '#description' => $this->t('Turn on debugging to allow easier testing of settings.'),
    );
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);
    $this->config('securepages.settings')
      ->set('switch', $form_state->getValue('switch'))
      ->set('basepath', $form_state->getValue('basepath'))
      ->set('basepath_ssl', $form_state->getValue('basepath_ssl'))
      ->set('secure', $form_state->getValue('secure'))
      ->set('pages', $form_state->getValue('pages'))
      ->set('ignore', $form_state->getValue('ignore'))
      ->set('roles', $form_state->getValue('roles'))
      ->set('forms', $form_state->getValue('forms'))
      ->set('debug', $form_state->getValue('debug'))
      ->save();
  }

}
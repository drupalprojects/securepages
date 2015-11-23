<?php
/**
 * @file
 * Contains \Drupal\securepages\Form\SecurepagesConfigForm.
 */

namespace Drupal\securepages\Form;

use Drupal\Core\Form\ConfigFormBase;
use Symfony\Component\HttpFoundation\Request;
use Drupal\Core\Form\FormStateInterface;

/**
 * Defines a form that configures forms module settings.
 */
class SecurepagesConfigForm  extends ConfigFormBase{

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
		return [
				'securepages.settings',
		];
	}
	
	/**
	 * {@inheritdoc}
	 */
	public function buildForm(array $form, FormStateInterface $form_state, Request $request = NULL) {
		$config = $this->config('securepages.settings');
		$userData =  user_role_names();
		$form['securepages_switch'] = array(
				'#type' => 'checkbox',
				'#title' => $this->t('Switch back to http pages when there are no matches'),
				'#description' => $this->t('Switch back to http pages when there are no matches'),
				'#default_value' => $config->get('securepages_switch'),
		);
		
		$form['securepages_basepath'] = array(
				'#type' => 'textfield',
				'#title' => $this->t('Non-secure Base URL'),
				'#description' => t('Non-secure Base URL.'),
				'#default_value' => $config->get('securepages_basepath'),
				'#size' => 100,
		);
		
		$form['securepages_basepath_ssl'] = array(
				'#type' => 'textfield',
				'#title' => $this->t('Secure Base URL'),
				'#description' => t('Secure Base URL.'),
				'#default_value' => $config->get('securepages_basepath_ssl'),
				'#size' => 100,
		);
		
		$active_options = array(0 => t('Make secure every page except the listed pages.'), 1 => t('Make secure only the listed pages.'));
		$form['securepages_secure'] = array(
				'#type' => 'radios',
				'#title' => t('Pages which will be be secure'),
				'#default_value' => $config->get('securepages_secure'),
				'#options' => $active_options,
				'#description' => t('Make secure every page except the listed pages. Make secure only the listed pages.'),
				'#access' => $admin,
		);
		
		$form['securepages_pages'] = array(
				'#title' => t('Securepages Pages Url'),		
				'#type' => 'textarea',		
				'#description' => t('The comment will be unpublished if it contains any of the phrases above. Use a case-sensitive, comma-separated list of phrases. Example: funny, bungee jumping, "Company, Inc."'),
				'#default_value' => $config->get('securepages_pages'),
		);
		
		$form['securepages_ignore'] = array(
				'#type' => 'textarea',
				'#title' => t('Ignore pages'),
				'#default_value' => $config->get('securepages_ignore'),
				'#cols' => 40,
				'#rows' => 5,
				'#description' => t("The pages listed here will be ignored and be either returned in http or https. Enter one page per line as Drupal paths. The '*' character is a wildcard. Example paths are '<em>blog</em>' for the blog page and '<em>blog/*</em>' for every personal blog. '<em>&lt;front&gt;</em>' is the front page."),
		);
		
		$form['securepages_roles'] = array(
				'#type' => 'checkboxes',
				'#title' => 'User roles',
				'#description' => t('Users with the chosen role(s) are always redirected to https, regardless of path rules.'),
				'#options' => $userData,
				'#default_value' => $config->get('securepages_roles'),
		);
		
		$form['securepages_forms'] = array(
				'#type' => 'textarea',
				'#title' => t('Secure forms'),
				'#default_value' => $config->get('securepages_forms'),
				'#cols' => 40,
				'#rows' => 5,
				'#description' => t('List of form ids which will have the https flag set to TRUE.'),
		);
		
		$form['securepages_debug'] = array(
				'#type' => 'checkbox',
				'#title' => t('Enable Debugging'),
				'#default_value' => $config->get('securepages_debug'),
				'#description' => t('Turn on debugging to allow easier testing of settings'),
		);
		return parent::buildForm($form, $form_state);
	}

	/**
	 * {@inheritdoc}
	 */
	public function submitForm(array &$form, FormStateInterface $form_state) {
		
		parent::submitForm($form, $form_state);
		
		$securepages_switch = $form_state->getValue('securepages_switch');
		$securepages_basepath = $form_state->getValue('securepages_basepath');
		$securepages_basepath_ssl = $form_state->getValue('securepages_basepath_ssl');
		$securepages_secure = $form_state->getValue('securepages_secure');
		$securepages_pages = $form_state->getValue('securepages_pages');
		$securepages_ignore = $form_state->getValue('securepages_ignore');
		$securepages_roles = $form_state->getValue('securepages_roles');
		$securepages_forms = $form_state->getValue('securepages_forms');
		$securepages_debug = $form_state->getValue('securepages_debug');
		
		// Get the config object.
		$config = $this->config('securepages.settings');
		
		// Set the values the user submitted in the form
		$config->set('securepages_switch', $securepages_switch)
		->set('securepages_basepath', $securepages_basepath)
		->set('securepages_basepath_ssl', $securepages_basepath_ssl)
		->set('securepages_secure', $securepages_secure)
		->set('securepages_pages', $securepages_pages)
		->set('securepages_ignore', $securepages_ignore)
		->set('securepages_roles', $securepages_roles)
		->set('securepages_forms', $securepages_forms)
		->set('securepages_debug', $securepages_debug)
		->save();
		}	
}
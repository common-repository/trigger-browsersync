<?php

/**
Plugin Name: Trigger Browsersync
Plugin URI: https://www.patabugen.co.uk/
Description: Integrate Browser-Sync with WordPress - for example to trigger a reload when saving a page.
Version: 0.8.6
License: GPL2
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Author: Sami Greenbury
Author URI: http://www.patabugen.co.uk/
Copyright: Sami Greenbury
Text Domain: browser-sync-triggers
 **/

/**
 * Class to handle functionality related to Integrating Browsersync with WordPress
 */
class TriggerBrowsersync
{
	protected $triggers_called = [];

	/**
	 * Setup the hooks required.
	 */
	public function __construct()
	{
		$this->add_actions();
		// Since errors are generated before a page load, we need to store
		// them in a session to be able to display them.
		// If we have a session, initialise the ability to show errors.
		if (session_id() !== '') {
			$_SESSION['TriggerBrowsersync_Errors'] = array();
			add_action('admin_notices', array($this, 'admin_notices'));
		}
	}

	private function log($message)
	{
		if (!apply_filters('trigger_browsersync_log_events', false)) {
			return;
		}
		error_log('Trigger-Browsersync: ' . $message);
	}

	private function getBrowsersyncUrl()
	{
		$url = apply_filters('trigger_browsersync_url', false);
		if ($url !== false) {
			return $url;
		}
		$protocol = apply_filters('trigger_browsersync_protocol', 'http');
		$host = apply_filters('trigger_browsersync_host', 'localhost');
		$port = apply_filters('trigger_browsersync_port', '3000');
		$url = $protocol . '://' . $host . ':' . $port;
		return $url;
	}

	public function add_actions()
	{
		$reload_on_actions = apply_filters(
			'trigger_browsersync_reload_actions',
			// The assoc keys let users disable hooks without
			// having to redefine the whle list.
			array(
				'save_post' => 'save_post', // Editing posts/pages/custom posts etc
				'added_option' => 'added_option', // Added settings
				'updated_option' => 'updated_option', // Changing settings
				'attachment_updated' => 'attachment_updated', // Editing media fields (caption etc)
				'updated_postmeta' => 'updated_postmeta', // Covers many things, particularly regenerating media thumbnails
				'activated_plugin' => 'activated_plugin',
				'deactivated_plugin' => 'deactivated_plugin',
				'delete_widget' => 'delete_widget',
			)
		);
		/**
		 * Go through each of the actions to trigger on and hook the most
		 * relevant method. There are generic methods for
		 */
		foreach ($reload_on_actions as $action_name) {
			switch ($action_name) {
				case 'updated_postmeta':
					add_action($action_name, array($this, 'trigger_reload_updated_postmeta'), 10, 4);
					break;
				case 'save_post':
					add_action($action_name, array($this, 'trigger_reload_save_post'), 10, 4);
					break;
				case 'updated_option':
				case 'added_option':
					add_action($action_name, array($this, 'trigger_reload_set_option'), 10, 4);
					break;
					// These have 1 argument
				case 'none-only-have-one-yet':
					add_action($action_name, function ($arg1) use ($action_name) {
						$this->log(
							"Triggered Reload because of: " . $action_name
								. ' (' . $arg1 . ' )'
						);
						$this->trigger_reload();
					}, 10, 1);
					break;
					// These have 2 arguments
				case 'deactivated_plugin':
				case 'activated_plugin':
					add_action($action_name, function ($arg1, $arg2) use ($action_name) {
						$this->log(
							"Triggered Reload because of: " . $action_name
								. ' (' . $arg1 . ', ' . $arg2 . ')'
						);
						$this->trigger_reload();
					}, 10, 2);
					break;
					// These have 0 argument
				default:
					add_action($action_name, function () use ($action_name) {
						$this->log("Triggered Reload because of: " . $action_name);
						$this->trigger_reload();
					}, 10, 0);
			}
		}
	}

	/**
	 * Tell Browser-Sync to reload any connected browsers.
	 */
	public function trigger_reload()
	{
		$this->trigger_api('reload');
	}

	public function trigger_reload_set_option($option_name, $new_value)
	{
		if (is_object($new_value)) {
			$new_value = maybe_serialize($new_value);
		}
		// Create a list of meta_keys which do not need to trigger a reload
		if ($this->is_irrelevant_meta_key($option_name)) {
			$this->log("Ignoring irrelevant option meta key: " . $option_name);
			return;
		}
		$this->log(
			'Triggered Reload because of updated option with key:'
				. $option_name . ' and value ' . json_encode($new_value)
		);
		$this->trigger_api('reload');
	}

	/**
	 * Reload handler for updated_postmeta. Lets us avoid triggering on
	 * meta_key updates which don't warrant an update (e.g. the edit_lock).
	 */
	public function trigger_reload_updated_postmeta($meta_id, $object_id, $meta_key, $meta_value)
	{
		// Create a list of meta_keys which do not need to trigger a reload
		if ($this->is_irrelevant_meta_key($meta_key)) {
			$this->log("Ignoring irrelevant updated_postmeta meta key: " . $meta_key);
			return;
		}
		$this->log(
			'Triggered Reload because of updated_postmeta with meta_key:'
				. $meta_key
		);
		$this->trigger_api('reload');
	}

	/**
	 * Reload handler for save_post. Lets us avoid triggering on
	 * saving of revisions.
	 */
	public function trigger_reload_save_post($post_id)
	{
		// Don't trigger for revisions
		if (wp_is_post_revision($post_id)) {
			$this->log(
				'Not reloading because this save_post is only a revision.'
					. ' Post ID: ' . $post_id
			);
			return;
		}
		$this->log(
			'Triggered Reload because of save_post on post'
				. $post_id
		);
		$this->trigger_api('reload');
	}

	/**
	 * Check whether a given meta_key is not relevant to Browsersync. This should
	 * return true for any meta_key which does not affect the functioning of the
	 * front-end of any page.
	 *
	 * @param string $meta_key
	 *
	 * @return boolean True if we don't need to reload/etc
	 */
	public function is_irrelevant_meta_key($meta_key)
	{
		$irrelevant_meta_keys = apply_filters(
			'trigger_browsersync_irrelevant_meta_keys',
			[
				'_edit_lock' => '_edit_lock',
				'_wr_post_view_count' => '_wr_post_view_count',
				'_yoast_wpseo_linkdex' => '_yoast_wpseo_linkdex',
				// wc_cp_maintenance_notices were triggering over and over causing
				// loops. So lets ignore them.
				'wc_cp_maintenance_notices' => 'wc_cp_maintenance_notices',
			]
		);
		if (in_array($meta_key, $irrelevant_meta_keys)) {
			$this->log('Is irrelevant meta key:' . $meta_key);
			return true;
		}

		$irrelevant_meta_key_regex = apply_filters(
			'trigger_browsersync_irrelevant_meta_key_regex',
			[
				'cron' => '/.*cron.*/',
				'cache' => '/.*cache.*/',
				'transient' => '/.*transient.*/',
			]
		);
		foreach ($irrelevant_meta_key_regex as $regex) {
			if (preg_match($regex, $meta_key) > 0) {
				$this->log('Is irrelevant meta key:' . $meta_key . ' against regex: ' . $regex);
				return true;
			}
		}
		return false;
	}

	public function trigger_api($action)
	{
		// Don't call the same action more than once per page-load
		if (in_array($action, $this->triggers_called)) {
			$this->log("Not triggering another " . $action);
			return;
		}
		$this->triggers_called[] = $action;

		$action = esc_attr($action);
		do_action('trigger_browsersync_before');
		do_action('trigger_browsersync_before_' . $action);
		$action_url = $this->getBrowsersyncUrl() . '/__browser_sync__?method=' . $action;
		$this->log("Calling: " . $action_url);
		$result = wp_remote_get(
			$action_url,
			array(
				'timeout' => 2	// Localhost requests shouldn't take long.
				// but don't waste too much time if Browsersync
				// isn't running
			)
		);
		$success_string = 'Called public API method';
		// if it's a WP_Error, then the connection failed.
		if (is_wp_error($result)) {
			if (isset($result->errors['http_request_failed'])) {
				// Catch errors which we know what are and give a friendly message.
				$this->add_error(
					'Error triggering Browsersync ' . $action . '. This is '
						. 'probably because Browsersync is not running '
						. 'or the port is incorrect. '
						. ' (I tried to load: ' . esc_url($action_url) . ')'
				);
			} else {
				// For other errors, let the user deal with the information.
				foreach ($result->errors as $result_code => $result_error) {
					$this->add_error(
						'Error triggering Browsersync ' . action . '. Message: '
							. $result_code . ' => ' . implode(', ', $result_error)
							. ' (I tried to load: ' . esc_url($action_url) . ')'
					);
				}
			}
		} else if (substr($result['body'], 0, strlen($success_string)) !== $success_string) {
			// If the connection was OK but the result does not look correct
			// report it to the user.
			$this->add_error(
				'Error triggering Browsersync ' . $action . ': ' . $result['body']
					. ' (I tried to load: ' . esc_url($action_url) . ')'
			);
		}
		do_action('trigger_browsersync_after');
		do_action('trigger_browsersync_after_' . $action);
	}

	public function add_error($message)
	{
		$this->log($message);
		if (session_id() === '') {
			// Without sessions, we can't pass errors between pages to
			// be able to dislpay them.
			return;
		}
		$_SESSION['TriggerBrowsersync_Errors'][] = $message;
	}

	public function admin_notices()
	{
		if (session_id() === '') {
			// Without sessions, we can't pass errors between pages.
			return;
		}
		foreach ($_SESSION['TriggerBrowsersync_Errors'] as $error) {
			echo '<div class="error"><p>' . wp_kses($error, array()) . '</p></div>';
		}
		// Reset the errors now that we've displayed them;
		$_SESSION['TriggerBrowsersync_Errors'] = array();
	}
}

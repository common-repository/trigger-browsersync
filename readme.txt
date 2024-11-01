=== Trigger Browsersync ===
Contributors: patabugen
Tags: browsersync, dev, automation, refresh
Donate link: https://paypal.me/patabugen/5
Requires at least: 3.0.1
Stable tag: trunk
Tested up to: 6
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Integrates WordPress with Browsersync to trigger events like Reload when you edit
pages and settings.

== Description ==

Integrates WordPress with Browsersync to trigger events like reload when you edit pages.

The plugin also only triggers a reload for now. If you have requests or ideas for other functionality, get in touch.

Since you (should) disable this plugin on production sites, the WordPress stats won't be reliable - if you find the plugin useful I'd really appreciate it if you'd let me know with a quick email or message!

= Triggers =
By default the plugin will trigger a reload on these actions:

 * save_post - Editing posts/pages/custom posts etc
 * added_option - Changing settings
 * attachment_updated - Editing media fields (caption etc)
 * updated_postmeta - Covers many things, particularly regenerating media thumbnails. Some meta_fields are ignored (see `trigger_browsersync_irrelevant_meta_keys`)
 * activated_plugin
 * deactivated_plugin
 * delete_widget

you can customise this as well as other Browsersync settings using filters:

    add_filter('trigger_browsersync_reload_actions', function($filters) {
    	// Remove a default. Bear in mind that one action you take (e.g. saving a page)
    	// may trigger more than one hook.
    	unset($filters['save_post']);
    	// Add your own. The key name lets other filters remove it (like the line above)
    	$filters['wp_logout'] = 'wp_logout';
    	return $filters;
    });

= Ignoring Meta Keys =
Not all meta_key updates (triggered by `updated_postmeta`) have any impact on the front-end functioning of your site. You can customise which `meta_key` values are ignored with the `trigger_browsersync_irrelevant_meta_keys` filter:

    add_filter('trigger_browsersync_irrelevant_meta_keys', function($filters) {
    	// Remove a default.
    	unset($filters['_edit_lock']);
    	// Add your own. The key name lets other filters remove it (like the line above)
    	$filters['_edit_lock'] = '_edit_lock';
    	return $filters;
    });

= Configuration with Filters =
The default Browsersync settings will be used (`http://localhost:3000`) but you can use filters to change them. The filters are used every time the trigger is activated so you don't need to set them before instanciating the class.

    add_filter('trigger_browsersync_protocol', function(){ return 'https'; } );
    add_filter('trigger_browsersync_host', function(){ return 'dev.server'; } );
    add_filter('trigger_browsersync_port', function(){ return '4321'; } );

Or you can specify the whole URL which will cause the others to be ignored, but don't include a trailing slash.

    add_filter('trigger_browsersync_url', function(){ return 'http://localhost:3000'; } );

= Environmental Configuration =
Since you probably only want Browsersync on your development or staging site, the plugin will do nothing once you activate it it in WordPress.

To make it work, you'll want to create a file to activate it. See Installation for instructions.

= Logging Activity =
Trigger Browsersync can log events to the WordPress log - this is especially useful for development on the plugin when you want to add or exclude a new event from triggering an action.

To enable logging add this filter to your `enable-trigger-browsersync.php` file (see Installation);

    add_filter('trigger_browsersync_log_events', '__return_true');

== Installation ==
This plugin sends signals to an existing and running BrowserSync setup. You need to install Browsersync and integrate it with your workflow first - how do to that is outside the scope of the plugin but you can get more information at [BrowserSync.io](https://www.browsersync.io/).

Install the plugin by uploading or via the plugin option in WordPress - the same as any other plugin.

The plugin will do nothing unless you create an instance of `TriggerBrowsersync`  somewhere.

You probably don't want the integration on your production server, which means you don't want the code to instanciate saved in your repo - I would recommend you create a file in mu-plugins with the code below, and then tell your version control to ignore the file.

    <?php
    add_action( 'plugins_loaded', function(){ // Trigger after the TriggerBrowsersync plugin has loaded
    	if ( class_exists( 'TriggerBrowsersync' ) ) { // Check the TriggerBrowsersync plugin loaded correctly
    		// Add any configuration filters you may need here.

    		// Activate the integration by creating an instance.
    		new TriggerBrowsersync();
    	}
    } );

= Step by Step Example Installation =

 * Create `wp-content/mu-plugins/enable-trigger-browsersync.php` (you may need to create the `mu-plugins` directory)
 * Paste the code above in
 * Edit .gitignore and add `wp-content/mu-plugins/enable-trigger-browsersync.php`

= All Hooks and Filters =

Use filters to customise settings:

 * trigger_browsersync_protocol - set the protocol for Gulp Watch (probably http or https). Defaults to http.
 * trigger_browsersync_host - set the host port for Gulp Watch. Defaults to localhost
 * trigger_browsersync_port - set the port for Gulp Watch. Defaults to 3000
 * trigger_browsersync_url - Set the whole URL instead of the parts above (e.g. http://localhost:3000)
 * trigger_browsersync_reload_actions - add/change the actions on which to trigger a reload. See source for defaults.
 * trigger_browsersync_log_events - Return true to enable log output (to the standard log)
 * trigger_browsersync_irrelevant_meta_keys - Lets you ignore particular meta_keys or options
 * trigger_browsersync_irrelevant_meta_key_regex - Lets you ignore particular meta_keys or options if they match preg_match

You can also hook on to some actions if you wish:

 * trigger_browsersync_before - Called just before we trigger anything
 * trigger_browsersync_after - Called just before we trigger anything
 * trigger_browsersync_before_reload - Called just before we trigger a reload
 * trigger_browsersync_after_reload - Called just after we trigger a reload - the reload won't yet be done

== Screenshots ==
There are no visible screens. Errors will be displayed as Admin Notices (but will
only work if there is an active session).

== Frequently Asked Questions ==
= I've installed the plugin, but it's not doing anything. =
 * Have you followed the installation instructions? The plugin will do nothing without them.
 * If you're not using the default Browser Sync settings, you'll need to add filters to override them.

== Changelog ==
= 0.8.6 =
 * Tested with WordPress 6

= 0.8.5 =
 * Tested with WordPress 5.3
 * Fixed typo bug.

= 0.8.4 =
 * Tested with WordPress 5.1

= 0.8.3 =
 * Don't trigger reload on cron updates.
 * Handle logging non-string value types better.

= 0.8 =
 * Fixed bug where non-string option values would throw a fatal error

= 0.7 =
 * Added `trigger_browsersync_irrelevant_meta_key_regex` filter to let us ignore meta keys or options based on a regex match. Initial value excludes any meta_key or option mame containing 'cache'
 * Fixed bug whereby options being changed were not logged properly
 * Added the `trigger_browsersync_irrelevant_meta_keys` to options names
 * Added `updated_option` to compliment existing `added_option`

= 0.6 =
 * Added `delete_widget` hook

= 0.5 =
 * Initial release with reload functionality, custom settings and customisable hook/trigger list.

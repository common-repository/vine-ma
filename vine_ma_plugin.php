<?php

/**
 * Plugin Name: Vine MA - Email Marketing, Forms, Interactive Bot Forms, Chatbot, Analytics
 * Version: 1.3.0
 * Description: Vine is a Marketing automation tool to generate more leads from your web site. Vine includes web forms, interactive bot forms, landing pages, AI chatbot, visitor tracking, and other functionality to help you to make your site more interesting and to know better what your visitors do there.
 * Author: Vine Oy
 * Author URI: https://vine.eu
 * Text Domain: vinema
 * License: GPLv2
 * License URI: http://www.gnu.org/licenses/gpl-2.0
 */

define("VINEHOST", "https://vine.eu");
define("VINECDNHOST", "https://cdn.vine.eu");


// add menu item to wordpress admin dashboard
function vine_ma_menu() {
    add_options_page( 'Vine', 'Vine', 'manage_options', 'vine_ma_plugin', 'vine_ma_plugin_options' );
}

add_action( 'admin_menu', 'vine_ma_menu' );
add_action( 'admin_init', 'vine_ma_register_plugin_settings' );
add_action( 'admin_enqueue_scripts', 'vine_ma_add_vine_script');

function vine_ma_add_vine_script($hook) {
    wp_enqueue_script('vine_script', plugin_dir_url(__FILE__) . '/vine.js');
}

function vine_ma_add_plugin_page_settings_link( $links ) {
	$links[] = '<a href="' .
		admin_url( 'options-general.php?page=vine_ma_plugin' ) .
		'">' . __('Settings') . '</a>';
	return $links;
}

// add settings link to the plugin item
add_filter('plugin_action_links_'.plugin_basename(__FILE__), 'vine_ma_add_plugin_page_settings_link');

function vine_ma_plugin_options() {
    ?>
    <h2>Vine MA</h2>
    <div style="padding-right: 20px">
        <?php 
        settings_fields('vine-plugin-options');
        do_settings_sections('vine_ma_plugin'); ?>
    </div>
    <?php
}

//register plugin settings
function vine_ma_register_plugin_settings() {
    register_setting( 'vine-plugin-options', 'vine-plugin-options');
    add_settings_section( 'vine_ma_general_settings', '', 'vine_ma_render_help_text', 'vine_ma_plugin' );
	add_settings_field( 'vine_ma_plugin_setting_username', 'Username:', 'vine_ma_plugin_setting_username', 'vine_ma_plugin', 'vine_ma_general_settings');
    add_settings_field( 'vine_ma_plugin_setting_organization_id', 'Organization ID:', 'vine_ma_plugin_setting_organization_id', 'vine_ma_plugin', 'vine_ma_general_settings');
	register_setting( 'vine-plugin-web-forms-cache', 'vine-plugin-web-forms-cache');
}

//render plugin page
function vine_ma_render_help_text() {
    echo "<p>Vine is a Marketing automation tool to generate more leads from your web site. Vine includes web forms, interactive bot forms, landing pages, AI chatbot, visitor tracking, and other functionality to help you to make your site more interesting and to know better what your visitors do there.</p>";
	
	$apikey = vine_ma_get_option('apikey');
	$token = null;
	if( $apikey != null )
	{
		echo "<p>You are currently connected to MA</p>";
		echo "<p><a href='javascript:logoutFromMA();'>Logout from MA</a></p>";
	}
	else {
		echo "<p>You need Vine account to use this plugin. If you do not have one, you can register trial account in <a target='_blank' href='https://vine.eu/en/try-for-free'>https://vine.eu/en/try-for-free</a></p>";
    	echo "<p><a id='vinemaloginbtn' href='javascript:openMaLoginWindow();'>Login to MA</a></p>";
		echo "<p><b>Please do not use partner account which can be moved between different customers.</b></p>";
	}
}
function vine_ma_plugin_setting_organization_id() {
	$orgid = vine_ma_get_option('organization_id');
	echo "<input id='vine_ma_plugin_setting_organization_id' name='vine-plugin-options[organization_id]' type='text' value='{$orgid}' readonly />";
}

function vine_ma_plugin_setting_username() {
	$username = vine_ma_get_option('username');
	if($username == null)
		$username == '';
    echo "<input id='vine_ma_plugin_setting_username' name='vine-plugin-options[username]' type='text' value='{$username}' readonly />";
}

//add 'Vine' script to WP pages by using organization id settings
function vine_ma_wpes_hook_add_script() {
	if ( is_user_logged_in() && isset($_GET['et_fb']) && isset($_GET['PageSpeed'])) return;
	$orgid = vine_ma_get_option('organization_id');
    $src = VINECDNHOST."/vscript/{$orgid}.js";
	$srcac = VINECDNHOST."/vscript/allowCookies.js";
	?>
		<script type="text/javascript" src="<?php echo $src ?>" data-cookieconsent="ignore"></script>
		<script type="text/plain" src="<?php echo $srcac ?>" data-cookieconsent="marketing" async></script>
	<?php
}
 
add_action( 'wp_head', 'vine_ma_wpes_hook_add_script', -99999);

add_action( 'wp_ajax_vine_ma_save_option', 'vine_ma_hook_save_option' );

//get vine temporary token
function vine_ma_get_authtoken($apikey) {
	if($apikey === '' || $apikey == null)
		return null;
	$url= VINEHOST.'/api/rest/2.0/login';
	$args = array(
	  'timeout' => 20,
      'headers' => array(
        'Content-Type' => 'application/json',
        'x-api-key' => $apikey,
		'user-agent'  =>  ''
	  )
	);
	$html = wp_remote_get($url, $args);
	$errorcode = wp_remote_retrieve_response_code($html); 
	$body = wp_remote_retrieve_body($html);
	if ( $errorcode === 200 || $errorcode === 304) {
		return array(
			'token' => $body,
			'error' => null
		);
	}
	else {
		return array(
			'token' => null,
			'error' => ($errorcode ? "{$errorcode}:{$body}" : "Timed out" )
		);
	}
}

//logout hook
add_action( 'wp_ajax_vine_ma_logout', 'vine_ma_hook_logout' );

//get user organization id
function vine_ma_get_organizationid_username($token) {
	$url=VINEHOST."/api/rest/2.0/user?\$authtoken={$token}";
	$args = array(
	  'timeout' => 20,
	  'user-agent'  =>  ''
	);
	$html = wp_remote_get($url, $args);
	$returncode = wp_remote_retrieve_response_code($html);
	if ( $returncode === 200 || $returncode === 304) {
		$body = wp_remote_retrieve_body($html);
		$xml = new SimpleXMLElement($body);
		$orgid = '';
		$username = '';
		foreach($xml->xpath('//m:properties') as $event) {
		  $orgid = (string)$event->xpath('d:ORGANIZATIONID')[0];
		  $firstname = (string)$event->xpath('d:FIRSTNAME')[0];
		  $lastname = (string)$event->xpath('d:LASTNAME')[0];
		  $email = (string)$event->xpath('d:EMAIL')[0];
		  $username = $firstname . ' ' . $lastname . ' (' . $email . ')';
        }
		return array(
			'orgid' => $orgid,
			'username' => $username
	  	);
	}
	else {
		return null;
	}
}

//save constant api key
function vine_ma_hook_save_option() {
	$apikey = $_POST['apikey'];
	$tokendata = vine_ma_get_authtoken($apikey);
	$token = $tokendata['token'];
	$userdata = vine_ma_get_organizationid_username($token);
	update_option( 'vine-plugin-options' ,array(
        'organization_id' => $userdata['orgid'],
		'username' => $userdata['username'],
        'apikey' => $apikey
	  ));
	//echo $organizationid;
	wp_die();
}

//logaout action, remove api key
function vine_ma_hook_logout() {
	update_option( 'vine-plugin-options' ,array(
        'organization_id' => '',
		'username' => '',
        'apikey' => ''
	  ));
	update_option( 'vine-plugin-web-forms-cache', array());
	//echo $organizationid;
	wp_die();
}

//banner hook
function vine_ma_admin_notice()
{
	$vineLogo = <<<EOD
		<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" version="1.1" width="24" height="9" viewBox='0 0 134 34' style="margin-right: 10px">
			<path fill='white' d='M 0.00 0.00 L 134.00 0.00 L 134.00 34.00 L 0.00 34.00 L 0.00 0.00 Z'/>
				<path fill='rgb(213,40,60)' d='M 42.07 0.99 C 46.17 2.04 50.16 3.47 54.14 4.88 C 54.13 13.39 54.19 21.90 54.10 30.40 C 51.01 30.52 47.91 30.61 44.82 30.64 C 44.64 23.83 44.84 17.02 44.66 10.22 C 43.13 9.76 41.60 9.29 40.08 8.79 C 40.68 6.17 41.33 3.57 42.07 0.99 Z'/>
				<path fill='rgb(213,40,60)' d='M 65.58 3.73 C 68.38 2.67 71.23 1.71 74.15 0.97 C 74.16 3.70 74.14 6.42 74.11 9.14 C 77.06 5.39 80.50 0.41 85.97 1.10 C 90.27 0.65 93.94 4.82 93.79 8.95 C 93.85 16.18 93.81 23.41 93.77 30.64 C 90.88 30.65 87.99 30.66 85.10 30.67 C 84.85 23.81 86.35 16.75 84.14 10.08 C 82.51 11.08 80.77 12.10 79.84 13.84 C 76.78 18.93 75.53 24.83 74.30 30.57 C 71.38 30.67 68.45 30.66 65.52 30.60 C 65.49 21.64 65.39 12.69 65.58 3.73 Z'/>
				<path fill='rgb(213,40,60)' d='M 107.37 6.40 C 112.93 -1.95 127.19 -0.22 131.70 8.36 C 133.11 10.69 133.02 13.49 133.28 16.11 C 127.17 18.05 120.86 19.33 114.77 21.37 C 116.45 22.64 118.11 24.22 120.29 24.48 C 123.20 24.86 125.96 23.67 128.73 23.01 C 128.77 25.78 128.76 28.56 128.64 31.33 C 123.48 32.33 117.89 33.23 112.88 31.10 C 108.20 29.06 105.38 24.01 105.22 19.02 C 105.04 14.77 104.78 10.05 107.37 6.40 Z'/>
				<path fill='rgb(213,40,60)' d='M 23.37 2.15 C 26.50 1.67 29.67 1.62 32.82 1.77 C 28.11 12.02 23.80 22.46 19.44 32.87 C 18.13 32.84 16.70 33.47 15.47 32.82 C 13.14 29.91 11.67 26.45 9.69 23.31 C 6.84 18.21 3.24 13.54 0.79 8.21 C 3.13 6.36 5.58 4.64 8.15 3.12 C 11.98 7.42 14.30 12.75 17.39 17.56 C 19.90 12.64 21.44 7.30 23.37 2.15 Z'/>
				<path fill='white' d='M 113.50 12.98 C 114.55 11.46 115.41 9.58 117.22 8.81 C 119.50 7.95 121.51 9.73 123.36 10.80 C 120.15 11.83 116.86 12.64 113.50 12.98 Z'/>
		</svg>
EOD;
	
	$apikey = vine_ma_get_option('apikey');
	if( $apikey == null)
	{
		?>
		<div class="notice notice-warning is-dismissible">
			<p>
				<?php echo $vineLogo ?>
				<?php _e('Warning! You are not connected to Vine MA. Vine services cannot work on your pages!', 'textdomain') ?>
			</p>
		</div>
		<?php
	}
	else {
		$orgid = vine_ma_get_option('organization_id');
		$username = vine_ma_get_option('username');
		if($orgid == null || $username == null )
		{
		?>
		<div class="notice notice-error is-dismissible">
			<p>
				<?php echo $vineLogo ?>
				<?php _e('An error occurred while communicating with Vine MA. Please try to relogin.', 'textdomain') ?>
			</p>
		</div>
		<?php
		}
	}
}

function vine_ma_admin_notice_multiorg_status()
{
	$vineLogo = <<<EOD
		<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" version="1.1" width="24" height="9" viewBox='0 0 134 34' style="margin-right: 10px">
			<path fill='white' d='M 0.00 0.00 L 134.00 0.00 L 134.00 34.00 L 0.00 34.00 L 0.00 0.00 Z'/>
				<path fill='rgb(213,40,60)' d='M 42.07 0.99 C 46.17 2.04 50.16 3.47 54.14 4.88 C 54.13 13.39 54.19 21.90 54.10 30.40 C 51.01 30.52 47.91 30.61 44.82 30.64 C 44.64 23.83 44.84 17.02 44.66 10.22 C 43.13 9.76 41.60 9.29 40.08 8.79 C 40.68 6.17 41.33 3.57 42.07 0.99 Z'/>
				<path fill='rgb(213,40,60)' d='M 65.58 3.73 C 68.38 2.67 71.23 1.71 74.15 0.97 C 74.16 3.70 74.14 6.42 74.11 9.14 C 77.06 5.39 80.50 0.41 85.97 1.10 C 90.27 0.65 93.94 4.82 93.79 8.95 C 93.85 16.18 93.81 23.41 93.77 30.64 C 90.88 30.65 87.99 30.66 85.10 30.67 C 84.85 23.81 86.35 16.75 84.14 10.08 C 82.51 11.08 80.77 12.10 79.84 13.84 C 76.78 18.93 75.53 24.83 74.30 30.57 C 71.38 30.67 68.45 30.66 65.52 30.60 C 65.49 21.64 65.39 12.69 65.58 3.73 Z'/>
				<path fill='rgb(213,40,60)' d='M 107.37 6.40 C 112.93 -1.95 127.19 -0.22 131.70 8.36 C 133.11 10.69 133.02 13.49 133.28 16.11 C 127.17 18.05 120.86 19.33 114.77 21.37 C 116.45 22.64 118.11 24.22 120.29 24.48 C 123.20 24.86 125.96 23.67 128.73 23.01 C 128.77 25.78 128.76 28.56 128.64 31.33 C 123.48 32.33 117.89 33.23 112.88 31.10 C 108.20 29.06 105.38 24.01 105.22 19.02 C 105.04 14.77 104.78 10.05 107.37 6.40 Z'/>
				<path fill='rgb(213,40,60)' d='M 23.37 2.15 C 26.50 1.67 29.67 1.62 32.82 1.77 C 28.11 12.02 23.80 22.46 19.44 32.87 C 18.13 32.84 16.70 33.47 15.47 32.82 C 13.14 29.91 11.67 26.45 9.69 23.31 C 6.84 18.21 3.24 13.54 0.79 8.21 C 3.13 6.36 5.58 4.64 8.15 3.12 C 11.98 7.42 14.30 12.75 17.39 17.56 C 19.90 12.64 21.44 7.30 23.37 2.15 Z'/>
				<path fill='white' d='M 113.50 12.98 C 114.55 11.46 115.41 9.58 117.22 8.81 C 119.50 7.95 121.51 9.73 123.36 10.80 C 120.15 11.83 116.86 12.64 113.50 12.98 Z'/>
		</svg>
EOD;
	
	$ismultiorg = vine_ma_get_multiorg_status();
	
	if( $ismultiorg == true)
	{
		?>
		<div class="notice notice-warning is-dismissible">
			<p>
				<?php echo $vineLogo ?>
				<?php _e('Warning! The account used should be account which cannot access multiple organizations. Please relogin with proper account.', 'textdomain') ?>
			</p>
		</div>
		<?php
	}
}

//subscribe to admin notices event
add_action( 'admin_notices', 'vine_ma_admin_notice' );
add_action( 'admin_notices', 'vine_ma_admin_notice_multiorg_status' );

//register vine web form block hook
add_action( 'init', 'vine_ma_gutenberg_register_web_form_block' );

//vine web block hook
function vine_ma_gutenberg_register_web_form_block() {
	global $pagenow;
	if ( $pagenow != 'post.php' )
		return;
	if ( ! function_exists( 'register_block_type' ) ) {
		// Gutenberg is not active.
		return;
	}
	
	wp_register_script(
		'vine-web-form-01',
		plugins_url( 'web_form_block.js', __FILE__ ),
		array( 'wp-blocks', 'wp-element' )
	);
	
	$webforms = vine_ma_get_web_forms();
	
	wp_localize_script('vine-web-form-01', 'VineFormsData', $webforms);
	
	register_block_type( 'vine-ma-plugin/vine-web-form', array(
		  'editor_script' => 'vine-web-form-01'
        )
	);
}

//get plugins options function
function vine_ma_get_option($name) {
	$options = get_option( 'vine-plugin-options' );
	if(!is_array( $options ) || $options[$name] == '')
		return null;
	return $options[$name];
}

//get vine web forms via rest api
function vine_ma_get_web_forms() {
	$cache = get_option( 'vine-plugin-web-forms-cache' );
	if(is_array( $cache ) && $cache['timestamp'] != null && (time() - $cache['timestamp']) < 120)
	{
		return array(
			'forms' => $cache['forms'],
			'error' => null
		);
	}
	$errorMessage = 'An error occurred while communicating with Vine MA. Please try to refresh page.';
	$apikey = vine_ma_get_option('apikey');
	if( $apikey != null) {
	  $tokendata = vine_ma_get_authtoken($apikey);
	  $token = $tokendata['token'];
	  $error = $tokendata['error'];
	  if( $token == null ) 
		  return array(
			'forms' => array(),
			'error' => "{$errorMessage} Token:{$error}"
		);
	} else {
		return array(
			'forms' => array(),
			'error' => "{$errorMessage} You are not logged in to Vine MA."
		);
	}
	$url=VINEHOST."/api/rest/2.0/VS_TRACK_FORM([FORMTYPE] <> 1 and [FORMTYPE] <> 2 and [FORMTYPE] <> 3 and [FORMTYPE] <> 4)?order=name&\$authtoken={$token}";
	$args = array(
	  'timeout' => 20,
	  'user-agent'  =>  ''
	);
	$html = wp_remote_get($url,$args);
	$returncode = wp_remote_retrieve_response_code($html);
 	if ( $returncode === 200 || $returncode === 304 ) {
		$body = wp_remote_retrieve_body($html);
		$xml = new SimpleXMLElement($body);
		$webforms = array();
        foreach($xml->xpath('//m:properties') as $event) {
          array_push($webforms, array(
            'id' => (string)$event->xpath('d:ID')[0],
			'name' => (string)$event->xpath('d:NAME')[0],
			'type' => (string)$event->xpath('d:FORMTYPE')[0],
          ));
        }
		$timestamp = time();
		update_option( 'vine-plugin-web-forms-cache', array(
			'forms' => $webforms,
			'timestamp' => $timestamp
		));
		return array(
			'forms' => $webforms,
			'error' => null
		);
	}
	else {
		return array(
			'forms' => array(),
			'error' => "{$errorMessage} Forms:" . ($errorcode ? "{$errorcode}:{$body}" : "Timed out" )
		);
	}
}

//check account multiorg status
function vine_ma_get_multiorg_status() {
	$cache = get_option( 'vine-plugin-account-multiorgstatus-cache' );
	if(is_array( $cache ) && $cache['timestamp'] != null && (time() - $cache['timestamp']) < 120)
	{
		return $cache['multiorgstatus'];
	}
	$errorMessage = 'An error occurred while communicating with Vine MA. Please try to refresh page.';
	$apikey = vine_ma_get_option('apikey');
	if( $apikey != null) {
	  $tokendata = vine_ma_get_authtoken($apikey);
	  $token = $tokendata['token'];
	  $error = $tokendata['error'];
	  if( $token == null ) 
		  return false;
	} else {
		return false;
	}
	$url=VINEHOST."/api/rest/2.0/VY_USERGROUP([STATUS]='B' and [GROUPROLE] in ('SWITCHORGANIZATION','SWITCHTOANYORG'))?\$authtoken={$token}";
	$args = array(
	  'timeout' => 20,
	  'user-agent'  =>  ''
	);
	$html = wp_remote_get($url,$args);
	$returncode = wp_remote_retrieve_response_code($html);
 	if ( $returncode === 200 || $returncode === 304 ) {
		$body = wp_remote_retrieve_body($html);
		$xml = new SimpleXMLElement($body);
		$status = $xml->xpath('//m:count')[0] == 0 ? false : true;
		$timestamp = time();
		update_option( 'vine-plugin-account-multiorgstatus-cache', array(
			'multiorgstatus' => $status,
			'timestamp' => $timestamp
		));
		return $status;
	}
	else {
		return false;
	}
}

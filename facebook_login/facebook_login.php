<?php

/*
Plugin Name: Sujeet Facebook Login
Plugin URI: https://github.com/wordpress/facebook_login
Description: Login with Facebook Account
Version: 1.0.1
Author: Sujeet Diwakar
Author URI: http://sujeet.cf
License: GPLv2 or later
Text Domain: sfbl
*/

// Make sure we don't expose any info if called directly
if ( !function_exists( 'add_action' ) ) {
  echo 'Hi there!  I\'m just a plugin, not much I can do when called directly.';
  exit;
  
}
/***************
WORDPRESS VERSION CHECK
***************/  
if(version_compare(get_bloginfo('version'),'4.0','<')){
	die('This plugin working grether than wordpress version 4.0');
}
  
/***************
Constants
***************/
define('SFBL_PATH', plugin_dir_path(__FILE__));
define('SFBL_URI', plugin_dir_url(__FILE__));

if(!class_exists('Sfbl')){
	
	class Sfbl{
		
		public function __construct(){
			add_action('admin_menu', [$this,'sfbl_settings_page']);
			add_action( 'admin_post_sfbl_facebook_login_action', [$this,'sfbl_facebook_login_action_setting_save'] );
			add_action('login_form',[$this,'sfbl_login_button']);
			add_action( 'wp_ajax_sfbl_action', [$this,'sfblCallback'] );
			add_action( 'wp_ajax_nopriv_sfbl_action', [$this,'sfblCallback'] );
			add_filter('get_avatar',[$this,'sfbl_filter_avatar'],10,5);
		}
		
		public function sfbl_settings_page(){
			add_submenu_page(
							'tools.php',
							__('Facebook Login', 'sfbl'), 
							__('Facebook Login', 'sfbl'),
							'manage_options', 
							'sfbl',
							array($this,'sfbl_page_call_back')
			);
		}
		
		public function sfbl_page_call_back(){
		   ?>
		   <div id="wpbody">
			<div id="wpbody-content">
				<div class="wrap">
			  <h1><?php _e('Facebook Login', 'sfbl'); ?></h1>
			  <?php
				if(isset($_GET['save_error'])){
					echo '<div style="background: #ff000061; padding: 11px 5px; border-radius: 6px; font-size: 15px;" class="sour_validation_msg">'.urldecode( $_GET['save_error'] ).'</div>';
				  }
				  if(isset($_GET['save_success'])){
					echo '<div style="background:#00800063; padding: 11px 5px; border-radius: 6px; font-size: 15px;" class="sour_validation_msg">'.urldecode( $_GET['save_success'] ).'</div>';
				  }
			  ?>
					<table class="form-table">
						<tbody>
				  <form method="post" action="admin-post.php">
					 <input type="hidden" name="action" value="sfbl_facebook_login_action" />
					<?php 
					wp_nonce_field('sfbl_facebook_login_verify'); 
					$sfbl_settings = get_option('sfbl_settings');
					?>
					<tr>
					  <th>
						<label for="app_id" ><?php _e('App ID', 'sfbl'); ?>
						</label>
					  </th>
					  <td>
						<input id="app_id"  name="app_id" value="<?php echo $sfbl_settings['app_id']; ?>" type="text" required >
					  </td>
					</tr>
					
					<tr>
					  <th>
						<label for="secret_key" ><?php _e('Secret Key', 'sfbl'); ?>
						</label>
					  </th>
					  <td>
						<input id="secret_key"  name="secret_key" value="<?php echo $sfbl_settings['secret_key']; ?>" type="password" required >
					  </td>
					</tr>
		
					 
					 <tr>
					  <td>
					  <p class="submit"><input type="submit" name="submit" id="submit" class="button button-primary" value="Save Changes"></p>
					  </td>
					 </tr>
				  </form>
				</tbody>
					</table>
				</div>
			</div>
		   </div>
		   <?php
		}
		public function sfbl_facebook_login_action_setting_save(){
		  /*
		  *	Check current user capability for edit settings
		  */
		  if(!current_user_can('manage_options')){
			wp_redirect(get_admin_url().'admin.php?page=sfbl&save_error='.urlencode('You are not allowed to edit this seetings') );
			exit();
		  }
		  
		  /*
		  *	Verify nonce field
		  */
		  check_admin_referer('sfbl_facebook_login_verify');
		  
		  if( isset($_POST['app_id']) ){
			$app_id = sanitize_text_field( $_POST['app_id']);
			$values['app_id'] = $app_id;
		  }
		  if( isset($_POST['secret_key']) ){
			$secret_key = sanitize_text_field($_POST['secret_key']);
			$values['secret_key'] = $secret_key;
		  }
		  
		  
			 /*
			  *	Add option for our form if not exist 
			  */ 
			  if(!get_option( 'sfbl_settings' )){

					add_option( 'sfbl_settings', array());
				}
				
			 /*
			  *	update settings 
			  */ 
			  update_option( 'sfbl_settings', $values);
			  wp_redirect(get_admin_url().'admin.php?page=sfbl&save_success='.urlencode('Setting saved successfully') );
			  exit();
		  
		}
		
		public function init_api(){
			if(!session_id()){
				session_start();
			}
			require(SFBL_PATH.'Facebook/autoload.php');
			$sfbl_settings = get_option('sfbl_settings');
			$fb = new Facebook\Facebook([
			
			  'app_id' => isset($sfbl_settings['app_id'])?$sfbl_settings['app_id']:'', // Replace {app-id} with your app id
			  'app_secret' => isset($sfbl_settings['secret_key'])?$sfbl_settings['secret_key']:'',
			  'default_graph_version' => 'v3.1',
			]);
			
			return $fb;
		}
		
		public function getLoginUrl(){
			if(is_user_logged_in()){
				return false;
			}
			$fb = $this->init_api();
			$helper = $fb->getRedirectLoginHelper();

			$permissions = ['email']; // Optional permissions
			$loginUrl = $helper->getLoginUrl(admin_url('admin-ajax.php').'/?action=sfbl_action', $permissions);
			return $loginUrl;
			
		}
		
		public function sfbl_login_button(){
			$loginurl = $this->getLoginUrl();
			if(!$loginurl){
				return;
			}
			?>
			<p style="margin-bottom: 20px; text-align:center;">
				<a class="loginBtn loginBtn--facebook" href="<?php echo $loginurl; ?>">Login with Facebook</a>
			</p>
			<style type="text/css">
				.loginBtn {
				  text-decoration: none;
				  padding:6px 8px  5px 44px;
				  box-sizing: border-box;
				  position: relative;				 
				  border: none;
				  text-align: left;
				  line-height: 34px;
				  white-space: nowrap;
				  border-radius: 0.2em;
				  font-size: 16px;
				  color: #FFF;
				}
				.loginBtn:before {
				  content: "";
				  box-sizing: border-box;
				  position: absolute;
				  top: 0;
				  left: 0;
				  width: 34px;
				  height: 100%;
				}
				.loginBtn:focus {
				  outline: none;
				}
				.loginBtn:active {
				  box-shadow: inset 0 0 0 32px rgba(0,0,0,0.1);
				}


				/* Facebook */
				.loginBtn--facebook {
				  background-color: #4C69BA;
				  background-image: linear-gradient(#4C69BA, #3B55A0);
				  /*font-family: "Helvetica neue", Helvetica Neue, Helvetica, Arial, sans-serif;*/
				  text-shadow: 0 -1px 0 #354C8C;
				}
				.loginBtn--facebook:before {
				  border-right: #364e92 1px solid;
				  background: url('https://s3-us-west-2.amazonaws.com/s.cdpn.io/14082/icon_facebook.png') 6px 6px no-repeat;
				}
				.loginBtn--facebook:hover,
				.loginBtn--facebook:focus {
				  background-color: #5B7BD5;
				  background-image: linear-gradient(#5B7BD5, #4864B1);
				}
			</style>
			<?php
		}
		
		public function sfblCallback(){
			if(is_user_logged_in()){
				die('User Allready LoggedIn');
			}
			
			$fb = $this->init_api();

			$helper = $fb->getRedirectLoginHelper();

			try {
			  $accessToken = $helper->getAccessToken();
			} catch(Facebook\Exceptions\FacebookResponseException $e) {
			  // When Graph returns an error
			  echo 'Graph returned an error: ' . $e->getMessage();
			  exit;
			} catch(Facebook\Exceptions\FacebookSDKException $e) {
			  // When validation fails or other local issues
			  echo 'Facebook SDK returned an error: ' . $e->getMessage();
			  exit;
			}

			if (! isset($accessToken)) {
			  if ($helper->getError()) {
				header('HTTP/1.0 401 Unauthorized');
				echo "Error: " . $helper->getError() . "\n";
				echo "Error Code: " . $helper->getErrorCode() . "\n";
				echo "Error Reason: " . $helper->getErrorReason() . "\n";
				echo "Error Description: " . $helper->getErrorDescription() . "\n";
			  } else {
				header('HTTP/1.0 400 Bad Request');
				echo 'Bad request';
			  }
			  exit;
			}
			
			// Logged in
			$accessToken = $accessToken->getValue();
			
			try {
			  // Returns a `Facebook\FacebookResponse` object
			  $response = $fb->get('/me?fields=id,name,first_name,last_name,email', $accessToken);
			  
			  $responseImage = $fb->get('/me/picture?redirect=false&width=250&height=250', $accessToken);
			  
			} catch(Facebook\Exceptions\FacebookResponseException $e) {
			  echo 'Graph returned an error: ' . $e->getMessage();
			  exit;
			} catch(Facebook\Exceptions\FacebookSDKException $e) {
			  echo 'Facebook SDK returned an error: ' . $e->getMessage();
			  exit;
			}

			$user = $response->getGraphUser();
			
			$name = sanitize_text_field($user['name']);
			$id = sanitize_text_field($user['id']);
			$first_name = sanitize_text_field($user['first_name']);
			$last_name = sanitize_text_field($user['last_name']);
			$email = sanitize_email($user['email']);
			
			 $profile_image = esc_url_raw($responseImage->getGraphUser()["url"]);
			
			$user_wp = get_user_by('email',$email);
			
			if(!$user_wp){
				$username = $id.'@facebook.com';
				$new_user = wp_create_user($username,wp_generate_password(),$email);
				
				update_user_meta($new_user,'first_name',$first_name);
				update_user_meta($new_user,'last_name',$last_name);
				add_user_meta($new_user,'facebook_picture',$profile_image);
				
				wp_update_user( array( 'ID' => $new_user, 'display_name' => $name ) );
				
				wp_set_auth_cookie($new_user,false,false);
				
				wp_redirect(admin_url());
				
				exit();
				
			}else{
				
				wp_set_auth_cookie($user_wp->ID,false,false);
				wp_redirect(admin_url());
				exit();
			}
			die();
		}
		public function sfbl_filter_avatar($avatar_html,$id_email,$size,$default,$alt){ 
			if(get_user_meta($id_email,'facebook_picture',true)){
				return '
				<img alt="" src="'.get_user_meta($id_email,'facebook_picture',true).'" srcset="'.get_user_meta($id_email,'facebook_picture',true).'" class="avatar avatar-32 photo" height="32" width="32">
				';
			}
			return $avatar_html;
		
		}
		
	}
	$sfbl = new Sfbl();
}

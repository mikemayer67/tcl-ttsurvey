<?php
namespace TLC\TTSurvey;


/**
 * TLC Time and Talent participant info and login
 */

if( ! defined('WPINC') ) { die; }

require_once plugin_path('include/const.php');
require_once plugin_path('include/logger.php');
require_once plugin_path('include/validation.php');

/**
 * The survey participant information is stored as wordpress posts 
 * using the custom post type of tlc-ttsurvey-id.  Each post corresponds
 * to a single participant userid or anonymous id (anonid)
 *
 * The post title contains the userid or anonid
 * The post content is a JSON string with the following fields:
 *
 *   Participant Entries:
 *   - username
 *   - email (optional)
 *   - access_token
 *   - pw_hash: password hash
 *
 *   Anonymous Entries:
 *   - n/a
 *
 * Survey responses from each participant is attached to either a
 *   user post or an anonymous post as meta data. The metadata key 
 *   indicates the survey status.
 *   - Submitted responses use the survey post_id as the metadata key
 *   - Working drafts use 'working' as the metadata key
 *
 * The following conventions are used in this module:
 *   userid:
 *     - unique ID associated with each survey participant
 *     - selected by the participant when they registered for the survey
 *     - stored in the title attribute of wp_post table
 *   username:
 *     - the participant's full name as it will appear on the survey
 *       summary report.
 *     - provided by the participant when they register for the survey
 *     - may be modified by the participant once they have logged in
 *     - stored as wordpress post metadata
 *   post id:
 *     - the index of the the user's entry in the wp_post table
 *     - used internal to wordpress and the plugin
 *   email:
 *     - the participant's email address (optional)
 *     - provided by the participant when they register for the survey
 *     - may be added/modified by the participant once they have logged in
 *     - may be removed by the participant once they have logged in
 *     - stored as wordpress post metadata
 *   password:
 *     - the participant's password for logging into the survey
 *     - provided by the participant when they register for the survey
 *     - may be modified by the participant once they have logged in
 *     - stored internally as a one-way hash of the password
 *   access token:
 *     - used to enable use of cookies to log the user in without a password
 *     - generated by the plugin when the participant registers for the survey
 *     - stored in a cookie along with the userid if cookies are enabled
 *     - is not provided to participant (unless they know how to view 
 *       cookie content in the browser)
 *     - may be regenerated by the participant once they have logged in
 *   anonid:
 *     - similar to the userid, but is associated with anonymous responses
 *     - assigned when the user is added upon registration
 *     - used internal to the plugin for associating the participant
 *       with their anonymous responses
 *     - association between userid/anonid is never included in any logs
 *       or wordpress internal tables other than as described below:
 *     - linkage between userid and anonid is obfuscated through a 
 *       bijection manipulation of the anonid. This obfuscated anonid is 
 *       stored in the metadata associated with the userid.  While this
 *       is not perfect security, it will require a degree of effort to 
 *       map the relationship.  It will not be available to a casual 
 *       viewer of the wordpress database. 
 *     - Users without an anonymous proxy are assigned a bogus 
 *       (obfuscated) anonid so that it is not easily discernable
 *       who has or has not provided anonymous responses.
 *
 **/

/**
 * Register the custom post type
 **/

const USERID_POST_TYPE = 'tlc-ttsurvey-id';
const ANON_USERID = '_anonymous';


function register_userid_post_type()
{
  switch( user_post_ui() )
  {
  case POST_UI_POSTS: $show_in_menu = 'edit.php';  break;
  case POST_UI_TOOLS: $show_in_menu = 'tools.php'; break;
  default:             $show_in_menu = false;      break;
  }
  register_post_type( USERID_POST_TYPE,
    array(
      'labels' => array(
        'name' => 'TLC TTSurvey Participants',
        'menu_name' => "Time & Talent Participants",
        'singular_name' => 'Participant',
        'add_new' => 'New Participant',
        'add_new_item' => 'Add New Participant',
        'edit_item' => 'Edit Participant',
        'new_item' => 'New Participant',
        'view_item' => 'View Participants',
        'search_items' => 'Search Participants',
        'not_found' =>  'No Participants Found',
        'not_found_in_trash' => 'No Participants found in Trash',
      ),
      'has_archive' => false,
      'supports' => array('title','editor'),
      'public' => false,
      'show_ui' => true,
      'show_in_rest' => false,
      'show_in_menu' => $show_in_menu,
    ),
  );
}

function users_init()
{
  register_userid_post_type();
}

function users_activate()
{
  log_info("Users Activate");
  register_userid_post_type();
  flush_rewrite_rules();
}

function users_deactivate()
{
  log_info("Users Deactivate");
  unregister_post_type(USERID_POST_TYPE);
}

function users_edit_form_top($post)
{
  $type = $post->post_type;
  if($post->post_type == USERID_POST_TYPE) {
    $content_url = admin_url() . "admin.php?page=" . SETTINGS_PAGE_SLUG;
    echo "<p class='tlc-post-warning'>";
    echo "Be very careful editing this data.<br>";
    echo "The JSON formatting, the userid, and any hashed value must be preserved to avoid messing up the user data.";
    echo "</p>";
  }
}

add_action('init',ns('users_init'));
add_action('edit_form_top',ns('users_edit_form_top'));

/**
 * User class
 **/

class User {
  private $_post_id = null;
  private $_userid = null;
  private $_data = null;

  private function __construct($post)
  {
    $this->_userid = $post->post_title;
    $this->_post_id = $post->ID;
    $this->_data = json_decode($post->post_content,true);
  }

  /**
   * User factory functions
   **/

  public static function from_post_id($post_id) 
  {
    $post = get_post($post_id);
    if(!$post) { return null; }
    return new User($post);
  }

  public static function from_userid($userid) 
  {
    $posts = get_posts(
      array(
        'post_type' => USERID_POST_TYPE,
        'numberposts' => -1,
        'title' => $userid,
      )
    );
    if(count($posts) > 1) {
      # log error both to the plugin log and to the php error log
      log_error("Multiple posts associated with userid $userid");
      die;
    }
    if(!$posts) { 
      return null; 
    }
    return new User($posts[0]);
  }

  public static function from_email($email) 
  {
    $posts = get_posts(
      array(
        'post_type' => USERID_POST_TYPE,
        'numberposts' => -1,
      )
    );
    $users = array();
    foreach($posts as $post) {
      $cand = new User($post);
      if($cand->email() == $email) { $users[] = $cand; }
    }
    return $users;
  }

  public static function create($userid,$password,$username,$email=null)
  {
    $content = array(
      'pw_hash' => password_hash($password,PASSWORD_DEFAULT),
      'username' => $username,
      'access_token' => gen_access_token(),
    );
    if($email) { $content['email'] = $email; }
    
    $post_id = wp_insert_post(
      array(
        'post_type' => USERID_POST_TYPE,
        'post_title' => $userid,
        'post_content' => json_encode($content),
        'post_status' => 'publish',
      ),
      true
    );

    // Don't set up the real anonyous proxy until needed, but to
    // help hide who has or has not submitted anonymous responses,
    // add the anonid metadata pointing back to user.
    update_post_meta($post_id,'anonid',User::_encode_anon($post_id));

    return User::from_post_id($post_id);
  }

  /**
   * Getters
   **/

  public function userid()       { return $this->_userid;                       }
  public function post_id()      { return $this->_post_id;                      }
  public function username()     { return $this->_data['username']     ?? null; }
  public function email()        { return $this->_data['email']        ?? null; }
  public function access_token() { return $this->_data['access_token'] ?? null; }
  
  /**
   * Validation
   **/

  public function verify_password($password)
  {
    $pw_hash = $this->_data['pw_hash'] ?? '';
    $rval = password_verify($password, $pw_hash);
    return $rval;
  }

  /**
   * Password
   **/

  public function set_password_reset_token($token,$expires)
  {
    update_post_meta($this->_post_id,'pw_reset_token',"$expires:$token");
  }

  public function update_password($token,$password,&$error=null) 
  {
    $reset_token = get_post_meta($this->_post_id,'pw_reset_token') ?? null;
    delete_post_meta($this->_post_id,'pw_reset_token');
    if(!$reset_token) { 
      $error = "No current password reset request";
      return false;
    }
    $reset_token = $reset_token[0];
    list($expires,$expected) = explode(':',$reset_token);
    if( $token !== $expected ) {
      $error = "Invalid reset request";
      return false;
    }
    $now = current_time('U',true);
    if($now > $expires) {
      $error = "Password reset request has expired";
      return false;
    }
    return $this->set_password($password);
  }

  /**
   * Setters
   **/

  public function set_username($username) 
  {
    if(!adjust_and_validate_user_input('username',$username)) {
      log_warning("Cannot update name for $this->_userid: invalid name ($username)");
      return false;
    }
    $this->_data['username'] = $username;
    $this->commit();
    return true;
  }

  public function set_email($email)
  {
    if(!adjust_and_validate_user_input('email',$email) ) {
      log_warning("Cannot update email for $this->_userid: invalid email ($email)");
      return false;
    }
    if($email) {
      $this->_data['email'] = $email;
    } else {
      unset($this->_data['email']);
    }
    $this->commit();
    return true;
  }

  public function clear_email()
  {
    $this->set_email(null);
  }

  public function set_password($password)
  {
    if(!adjust_and_validate_user_input('password',$password) ) {
      log_warning("Cannot update password for $this->_userid: invalid password");
      return false;
    }
    $this->_data['pw_hash'] = password_hash($password,PASSWORD_DEFAULT);
    $this->commit();
    return true;
  }

  public function regenerate_access_token()
  {
    $new_token = gen_access_token();
    $this->_data['access_token'] = $new_token;
    $this->commit();
    return $new_token;
  }

  private function commit()
  {
    wp_update_post(array(
      'ID' => $this->_post_id,
      'post_content' => wp_slash(json_encode($this->_data)),
    ));
  }

  /**
   * Anonymous Proxy
   **/

  private static function _encode_anon($anonid) { return ($anonid * 81) ^ 0xce67; }
  private static function _decode_anon($anonid) { return ($anonid ^ 0xce67) / 81; }

  public function anon_proxy()
  {
    if($this->_userid === ANON_USERID) {
      // Anonymous proxies cannot have their own anonymous proxies
      return null;
    }

    $anonids = get_post_meta($this->_post_id,'anonid');

    if(count($anonids) > 1) {
      // Oops... internal error resulted in multiple anon proxies
      log_error("Multiple anonymous proxies for userid $this->_userid");
      die;
    } 
    
    if(count($anonids) == 1) {
      // This is what we should expect... but doesn't mean that 
      // there's actually an anonymous proxy set up yet.
      // Verify that the anonid is really for an anonymous user.
      $anonid = User::_decode_anon($anonids[0]);
      $anon = User::from_post_id($anonid);
      if($anon && $anon->userid() === ANON_USERID) {
        return $anon;
      }
    } 

    // Looks like we need to create the anonymous proxy

    $anonid = wp_insert_post(
      array(
        'post_type' => USERID_POST_TYPE,
        'post_title' => ANON_USERID,
        'post_content' => '{}',
        'post_status' => 'publish',
      ),
      true
    );

    update_post_meta($this->_post_id,'anonid',User::_encode_anon($anonid));

    return User::from_post_id($anonid);
  }
}

/**
 * support functions
 **/

function is_userid_available($userid)
{
  $existing = User::from_userid($userid);
  return is_null($existing);
}

function gen_access_token($token_length=25)
{
  $access_token = '';
  $token_pool = '123456789123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
  for($i=0; $i<$token_length; $i++) {
    $index = rand(0,strlen($token_pool)-1);
    $access_token .= $token_pool[$index];
  }
  return $access_token;
}

/**
 * login validation
 **/

function validate_user_password($userid,$password)
{
  $user = User::from_userid($userid);
  if(!$user) { 
    log_info("Failed to validate password:: Invalid userid $userid");
    return false;
  }
  if(!$user->verify_password($password)) {
    log_info("Failed to validate password:: Incorrect password for $userid");
    return false;
  }
  return true;
}

function validate_user_access_token($userid,$token)
{
  $user = User::from_userid($userid);
  if(!$user) { return false; }
  return $token == $user->access_token();
}


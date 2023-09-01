<?php
namespace TLC\TTSurvey;

/**
 * TLC Time and Talent plugin login cookie handling
 */

if( ! defined('WPINC') ) { die; }

require_once plugin_path('logger.php');
require_once plugin_path('users.php');
require_once plugin_path('database.php');

const ACTIVE_USER_COOKIE = 'tlc-ttsurvey-active';
const USERIDS_COOKIE = 'tlc-ttsurvey-userids';

class LoginCookie
{
  /**
   * singleton setup
   **/
  private static $_instance = null;

  static function instance() {
    if( is_null(self::$_instance) ) {
      self::$_instance = new self;
    }
    return self::$_instance;
  }

  /**
   * instance setup
   **/
  private $_active_userid = null;
  private $_userids = array();

  private function __construct() {
    $this->_active_userid = $_COOKIE[ACTIVE_USER_COOKIE] ?? null;

    $userids = $_COOKIE[USERIDS_COOKIE] ?? "{}";
    $userids = json_decode($userids,true);
    $userids = array_filter(
      $userids,
      function ($key) {
        return Users::instance()->is_valid_userid($key);
      },
      ARRAY_FILTER_USE_KEY
    );
    $this->_userids = $userids;
  }

  /**
   * resets the timeout on the current cookie value to 1 year from now
   **/
  static function reset_timeout() {
    setcookie(
      USERIDS_COOKIE,
      $_COOKIE[USERIDS_COOKIE],
      time() + 86400*365,
    );
  }

  function active_userid()
  {
    return $this->_active_userid;
  }

  function all_userids()
  {
    return array_keys($this->_userids);
  }

  function active_anonid()
  {
    return $this->anonid($this->_active_userid);
  }

  function anonid($userid)
  {
    return $this->_userids[$userid] ?? null;
  }

  function add($userid,$anonid,$active=true)
  {
    if($active)
    {
      $this->_active_userid = $userid;
    }
    if($anonid || !array_key_exists($userid,$this->_userids))
    {
      $this->_userids[$userid] = $anonid;
    }
    $this->_save();
  }

  function logout()
  {
    setcookie(ACTIVE_USER_COOKIE, "", 0);
    $this->_active_userid = null;
  }

  function resume($userid,$anonid,$case)
  {
    /*
    TODO: Verify userid
    TODO: Verify anonid if specified
    TODO: if anonid was blank, create one now and notify user of new anonid
     */
    $this->add($userid,$anonid,true);
  }

  function remove($userid)
  {
    if($this->_active_user == $userid) {
      $this->_active_user = null;
    }
    unset($this->_userids[$userid]);
    $this->_save();
  }

  private function _save()
  {
    $userids = json_encode($this->_userids);
    log_info("save cookie for userids: '$userids'");
    setcookie( ACTIVE_USER_COOKIE, $this->_active_userid, 0 );
    setcookie( USERIDS_COOKIE, $userids,time() + 86400*365);
  }
}

$login_cookie = LoginCookie::instance();
$login_cookie->reset_timeout();

function login_init()
{
  $nonce = $_POST['_wpnonce'] ?? '';

  if( wp_verify_nonce($nonce,LOGIN_FORM_NONCE) )
  {
    require_once plugin_path('users.php');
    $users = Users::instance();


    $action = $_POST['action'] ?? null;
    log_dev("action=$action");
    if( $action == 'resume' ) {
      LoginCookie::instance()->resume(
        $_POST['userid'],
        $_POST['anonid']??"",
        $_POST['case']
      );
    }
    elseif( $action == 'new_user' ) {
      $first = $_POST['firstname'] ?? null;
      $last = $_POST['lastname'] ?? null;
      $email = $_POST['email'] ?? null;
      // @@@ TODO: handle empty names
      log_dev("Registering new user: $first, $last, $email");
      [$userid,$anonid] = $users->add_user($first,$last,$email);
      log_dev("New user assigned ids: $userid, $anonid");
      LoginCookie::instance()->add($userid,$anonid,true);
    }
    elseif( $action == 'resend_userid') {
      require_once plugin_path('sendmail.php');
      sendmail_userid_reminder($_POST['email']);
    }
    elseif( $action == 'logout') {
      LoginCookie::instance()->logout();
    }
  }
}

add_action('init',ns('login_init'));

//[$userid,$anonid] = create_unique_ids('QQ');
//$login_cookie->add($userid,$anonid,false);
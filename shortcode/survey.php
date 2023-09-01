<?php
namespace TLC\TTSurvey;

/**
 * Handle the actual survey
 */

if( ! defined('WPINC') ) { die; }

require_once plugin_path('users.php');

$user_name = Users::instance()->full_name($userid);

if( $anonid == null ) {
  echo "Logged in as $user_name ($userid) with no anonymous id";
} else {
  echo "Logged in as $user_name ($userid) and anonymous id $anonid";
}

$form_uri=$_SERVER['REQUEST_URI'];
$nonce = wp_nonce_field(LOGIN_FORM_NONCE);

?>
<form class=tlc-logout method='post' action='<?=$form_uri?>'>
  <?=$nonce?>
  <input type=hidden name=action value=logout>
  <input type=submit value="Log Out">
</form>


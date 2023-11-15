<?php
namespace TLC\TTSurvey;

if( ! defined('WPINC') ) { die; }

require_once plugin_path('include/login.php');

$userid = $_POST['userid'];

// Let CookieJar know this is an ajax call.
//   This modifies how login_with_password handles cookies 
$jar = CookieJar::instance(true);

$cookie = forget_user_token($userid);
$result = array(
  'success'=>true,
  'cookies'=>array($cookie),
);

echo json_encode($result);
wp_die();
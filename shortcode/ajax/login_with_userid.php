<?php
namespace TLC\TTSurvey;

if( ! defined('WPINC') ) { die; }

require_once plugin_path('include/login.php');

$userid = adjust_user_input('userid',$_POST['userid']);
$password = adjust_user_input('password',$_POST['password']);
$remember = filter_var($_POST['remember']??false, FILTER_VALIDATE_BOOLEAN);

// Let CookieJar know this is an ajax call.
//   This modifies how login_with_userid handles cookies 
$jar = CookieJar::instance(true);

$result = login_with_userid($userid,$password,$remember);

echo json_encode($result);
wp_die();

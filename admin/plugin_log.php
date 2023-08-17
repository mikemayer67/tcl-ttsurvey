<?php
namespace TLC\TTSurvey;

if( !current_user_can('manage_options') ) { wp_die('Unauthorized user'); }

require_once plugin_path('logger.php');
require_once plugin_path('settings.php');

$nonce = wp_nonce_field(SETTINGS_NONCE);
$action = $_SERVER['REQUEST_URI'];
?>

<div class='log-table'>
<?php Logger::instance()->dump_html(); ?>
</div>

<form id='tlc-clear-log' class='tlc' action='<?=$action?>' method="POST">
  <input type='hidden' name='action' value='clear-log'>
  <?=$nonce?>
  <input type='submit' value='Clear Log' class='submit button button-primary button-large'>
</form>

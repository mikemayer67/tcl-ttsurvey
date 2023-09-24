<?php
namespace TLC\TTSurvey;

if( !current_user_can('manage_options') ) { wp_die('Unauthorized user'); }

require_once plugin_path('include/settings.php');
require_once plugin_path('include/surveys.php');
require_once plugin_path('include/logger.php');

$current_year = date('Y');

$survey_years = survey_years();
$current_status = $survey_years[$current_year] ?? 'not started';
unset($survey_years[$current_year]);
$years = array_keys($survey_years);
arsort($years);

$log_level = survey_log_level();

$caps = survey_capabilities();
$pdf_uri = survey_pdf_uri();

?>

<div class=tlc-overview>
<h2>Survey Settings</h2>
<table class='tlc-overview'>
  <tr>
    <td class=label>Current Year</td>
    <td class=value>
      <table class=years>
        <tr>
          <td class=year><?=$current_year?></td>
          <td class=status><?=$current_status?></td>
        </tr> 
      </table>
    </td>
  </tr>
  <tr>
    <td class=label>Past Years</td>
    <td class=value>
      <table class=years>
<?php
if(!$survey_years) {
  echo "<tr><td class=year>n/a</td></tr>";
}
foreach($years as $year) {
  $status = $survey_years[$year];
  echo "<tr><td class=year>$year</td><td class=status>$status</td></tr>";
}
?>
      </table>
    </td>
  </tr>
  <tr>
    <td class=label>Admins</td>
    <td class=value>
      <table class=admins>

<?php
foreach(get_users() as $user) {
  $id = $user->id;
  $name = $user->display_name;
  $responses = $caps['responses'][$id];
  $content = $caps['content'][$id];
  $user_caps = array();
  if( $caps['responses'][$id] ) {
    $user_caps[] = "Responses";
  }
  if( $caps['content'][$id] ) {
    $user_caps[] = " Content";
  }
  if( !empty($user_caps) ) {
    $user_caps = implode(", ",$user_caps);
    echo "<tr><td class=username>$name</td><td class=usercaps>$user_caps</td></tr>";
  }
}
?>
      </table>
    </td>
  </tr>

  <tr>
    <td class=label>Survey URL</td>
    <td class=value><?=$pdf_uri?></td>
  </tr>

  <tr>
    <td class=label>Log Level</td>
    <td class=value><?=$log_level?></td>
  </tr>

</table>

<h2>Usage</h2>

<div class=tlc-shortcode-info>
Simply add the shortcode <code>[tlc-ttsurvey]</code> to your pages or posts to embed
the Time & Talent survey
</div>

<div class=tlc-shortcode-note>
Only the first occurance of this shortcode on any given page will be rendered.  All others will be quietly ignored.
</div>

<div class=tlc-shortcode-info>
The following <b>optional</b> arguments are currently recognized:
</div>
<div class=tlc-shortcode-note>
Any unspecified argument defaults to the value defined in the plugin settings
</div>

<div class=tlc-shortcode-args>
<div class=tlc-shortcode-arg>year</div>
<div class=tlc-shortcode-arg-info>Must match one of the survey years.</div>
<div class=tlc-shortcode-arg>plugin-css</div>
<div class=tlc-shortcode-arg-info>Overrides the current theme's css when rendering
the survey.  This should <b>not</b> affect styling outside the survey.
</div>

<div class=tlc-shortcode-info>Example</div>
<div class=tlc-shortcode-example><span>
[tlc-ttsurvey year=2023 survey-css]
</span></div>

<h2>Theme Compatibility</h2>
<div>
<ul>
  <li>The survey does not render well when its width is too narrow.  If your theme 
  provides wide page templates, you may want to make sure the page that contains 
  the survey uses that template.  Similarly, you probably do not want to use 
  multi-column templates or templates with side bars for the survey page.</li>
  <li>If the does survey does not render well for your theme, try adding
  <code>survey-css</code> to the survey shortcode (<i>see above</i>).  
  This corrected the formatting issues for all of the tested themes. But note this 
  means that the look and feel of the survey will probably not match that of the 
  theme.</li>
</ul>
</div>

</div>

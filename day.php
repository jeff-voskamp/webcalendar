<?php
/* $Id$ */
include_once 'includes/init.php';

if ( ( $user != $login ) && $is_nonuser_admin )
  load_user_layers ( $user );
else
  load_user_layers ();

load_user_categories ();

$wday = strftime ( '%w', mktime ( 0, 0, 0, $thismonth, $thisday, $thisyear ) );

$now = mktime ( 0, 0, 0, $thismonth, $thisday, $thisyear );
$nowYmd = date ( 'Ymd', $now);
$next = mktime ( 0, 0, 0, $thismonth, $thisday + 1, $thisyear );
$nextday = date ( 'd', $next );
$nextmonth = date ( 'm', $next );
$nextyear = date ( 'Y', $next );
$nextYmd = date ( 'Ymd', $next );

$prev = mktime ( 0, 0, 0, $thismonth, $thisday - 1, $thisyear );
$prevday = date ( 'd', $prev );
$prevmonth = date ( 'm', $prev );
$prevyear = date ( 'Y', $prev );
$prevYmd = date ( 'Ymd', $prev );

if ( empty ( $TIME_SLOTS ) )
  $TIME_SLOTS = 24;

$boldDays = ( $BOLD_DAYS_IN_YEAR == 'Y' );

$startdate = mktime ( 0, 0, 0, $thismonth, 0, $thisyear );
$enddate = mktime ( 23, 59, 59, $thismonth + 1, 0, $thisyear );

$printerStr = $smallTasks = $unapprovedStr = '';

/* Pre-Load the repeated events for quckier access */
$repeated_events = read_repeated_events ( empty ( $user )
  ? $login : $user, $startdate, $enddate, $cat_id );

/* Pre-load the non-repeating events for quicker access */
$events = read_events ( empty ( $user )
  ? $login : $user, $startdate, $enddate, $cat_id );

if ( empty ( $DISPLAY_TASKS_IN_GRID ) || $DISPLAY_TASKS_IN_GRID == 'Y' )
  /* Pre-load tasks for quicker access */
  $tasks = read_tasks ( ( ! empty ( $user ) && strlen ( $user ) )
    ? $user : $login, $now, $cat_id );

if ( $DISPLAY_TASKS == 'Y' )
  $smallTasks = '<div id="minitask">' .display_small_tasks ( $cat_id ) . '</div>';

$dayStr = print_day_at_a_glance ( $nowYmd, ( empty ( $user )
  ? $login : $user ), $can_add );
$navStr = display_navigation ( 'day' );
$smallMonthStr = display_small_month ( $thismonth, $thisyear, true );
if ( empty ( $friendly ) ) {
  $unapprovedStr = display_unapproved_events (
    ( $is_assistant || $is_nonuser_admin ? $user : $login ) );
  $printerStr = generate_printer_friendly ( 'day.php' );
}
$eventinfo = ( ! empty ( $eventinfo ) ? $eventinfo : '' );
$trailerStr = print_trailer ();
$HeadX = generate_refresh_meta ();
print_header ( array ( 'js/popups.php/true' ), $HeadX,'', false, false, false, false  );

echo <<<EOT
    <table width="100%" cellpadding="1">
      <tr>
        <td class="aligntop" width="80%">
          {$navStr}
        </td>
        <td>&nbsp;</td>
      </tr>
      <tr>
        <td>
          {$dayStr}
        </td>
        <td class="aligntop" rowspan="2">
          <!-- START MINICAL -->
          <div class="minicalcontainer aligncenter">
            {$smallMonthStr}
          </div><br />
          {$smallTasks}
        </td>
      </tr>
    </table>
    {$eventinfo}
    {$unapprovedStr}
    {$printerStr}
    {$trailerStr}
EOT;

?>

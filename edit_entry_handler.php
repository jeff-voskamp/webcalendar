<?php
require_once 'includes/init.php';
require_once 'includes/classes/WebCalMailer.php';
$mail = new WebCalMailer;

load_user_categories();

$do_override = false;
$error = '';
$old_id = -1;

$dateStr   = translate( 'Date XXX' );
$descStr   = translate( 'Description XXX' );
$helloStr  = translate( 'Hello, XXX.' );
$newAppStr = translate( 'XXX has made a new appointment.' );
$subjStr   = translate( 'Subject XXX' );
$timeStr   = translate( 'Time XXX' );
$updAppStr = translate( 'XXX has updated an appointment.' );

/**
 * Put byday values in logical sequence.
 */
function sort_byday( $a, $b ) {
  global $byday_values;

  $len_a = strlen( $a );
  $len_b = strlen( $b );
  $val_a = $byday_values[substr( $a, -2 )];
  $val_b = $byday_values[substr( $b, -2 )];

  if( $len_a != $len_b )
    return ( $len_a < $len_b ? -1 : 1 );
  elseif( $len_a == 2 )
    return strcmp( $val_a, $val_b );
  else { // They start with numeric offsets.
    $offset_a = substr( $a, 0, $len_a - 2 );
    $offset_b = substr( $b, 0, $len_b - 2 );

    if( $offset_a == $offset_b )
      return strcmp( $val_a, $val_b );
    else // Add weight to weekday value to help sort.
      return strcmp( abs( $offset_a ) + $val_a * 10,
        abs( $offset_b ) + $val_b * 10 );
  }
}

$confirm_conflicts = getPostValue( 'confirm_conflicts' );
$id                = getPostValue( 'cal_id' );
$override          = getPostValue( 'override' );
$override_date     = getPostValue( 'override_date' );

if( ! empty( $override ) && ! empty( $override_date ) ) {
  // Override date specified.
  // User is going to create an exception to a repeating event.
  $do_override = true;
  $old_id = $id;
}

$old_status = array();

// Pass all string values through getPostValue.
$access   = getPostValue( 'access' );
$location = getPostValue( 'location', '', 'XSS' );
$name     = getPostValue( 'name', '', 'XSS' );
$priority = getPostValue( 'priority' );
$user     = getPostValue( 'user' );

// Remember previous cal_group_id if present.
$parent = getPostValue( 'parent' );
$old_id = ( empty( $parent ) ? $old_id : $parent );

// Not sure which of these to keep.
$participants = getPostValue( 'participants' );
$participants = getPostValue( 'selectedPart' );

$byday          = getPostValue( 'byday' );
$bydayAll       = getPostValue( 'bydayAll' );
$bydayList      = getPostValue( 'bydayList' );
$bymonth        = getPostValue( 'bymonth' );
$bymonthday     = getPostValue( 'bymonthday' );
$bymonthdayList = getPostValue( 'bymonthdayList' );
$bysetpos       = getPostValue( 'bysetpos' );
$bysetposList   = getPostValue( 'bysetposList' );
$byweekno       = getPostValue( 'byweekno' );
$byyearday      = getPostValue( 'byyearday' );

$cat_id           = getValue ( 'cat_id', '-?[0-9,\-]*', true );
$completed_hour   = getPostValue( 'completed_hour' );
$completed_minute = getPostValue( 'completed_minute' );
$ymd = getPostValue('completed_YMD');
$parsed = date_parse($ymd);
$completed_day   = $parsed['day'];
$completed_month = $parsed['month'];
$completed_year  = $parsed['year'];
$description      = getPostValue( 'description', '', 'XSS' );

$due_ampm   = getPostValue( 'due_ampm' );
$due_hour   = getPostValue( 'due_hour' );
$due_minute = getPostValue( 'due_minute' );
$ymd = getPostValue('due__YMD');
$parsed = date_parse($ymd);
$due_day   = $parsed['day'];
$due_month = $parsed['month'];
$due_year  = $parsed['year'];

$eType = getPostValue( 'eType' );
if (!in_array($eType, ['event', 'task', 'journal']))
  $eType = 'event';

// entry_changed is calculated client-side with javascript.
$entry_changed = getPostValue( 'entry_changed' );
$entry_url     = getPostValue( 'entry_url' );
$exceptions    = getPostValue( 'exceptions' );

$rem_action      = getPostValue( 'rem_action' );
$rem_before      = getPostValue( 'rem_before' );
$rem_days        = getPostValue( 'rem_days' );
$rem_hours       = getPostValue( 'rem_hours' );
$rem_last_sent   = getPostValue( 'rem_last_sent' );
$rem_minutes     = getPostValue( 'rem_minutes' );
$rem_related     = getPostValue( 'rem_related' );
$rem_rep_count   = getPostValue( 'rem_rep_count' );
$rem_rep_days    = getPostValue( 'rem_rep_days' );
$rem_rep_hours   = getPostValue( 'rem_rep_hours' );
$rem_rep_minutes = getPostValue( 'rem_rep_minutes' );
$rem_times_sent  = getPostValue( 'rem_times_sent' );
$rem_when        = getPostValue( 'rem_when' );

$reminder        = getPostValue( 'reminder' );
$reminder_ampm   = getPostValue( 'reminder_ampm' );
$reminder_hour   = getPostValue( 'reminder_hour' );
$reminder_minute = getPostValue( 'reminder_minute' );
$reminder_type   = getPostValue( 'reminder_type' );
$ymd = getPostValue('reminder__YMD');
$parsed = date_parse($ymd);
$reminder_day   = $parsed['day'];
$reminder_month = $parsed['month'];
$reminder_year  = $parsed['year'];

$rpt_ampm    = getPostValue( 'rpt_ampm' );
$rpt_count   = getPostValue( 'rpt_count' );
$rpt_end_use = getPostValue( 'rpt_end_use' );
$rpt_freq    = getPostValue( 'rpt_freq' );
$rpt_hour    = getPostValue( 'rpt_hour' );
$rpt_minute  = getPostValue( 'rpt_minute' );
$rpt_type    = getPostValue( 'rpt_type' );
$rptmode     = getPostValue( 'rptmode' );
$ymd = getPostValue('rpt__YMD');
$parsed = date_parse($ymd);
$rpt_day   = $parsed['day'];
$rpt_month = $parsed['month'];
$rpt_year  = $parsed['year'];

$timetype      = getPostValue( 'timetype' );
$weekdays_only = getPostValue( 'weekdays_only' );
$wkst          = getPostValue( 'wkst' );

$description = ( strlen( $description ) == 0 || $description == '<br />'
  ? $name : $description );

// For public events, we don't EVER allow HTML tags.  There is just too
// many bad things a malicious user can do.
// See this for an example of how someone could create an admin account in
// webcalendar:
//   https://www.upsploit.com/index.php/advisories/download/UPS-2010-0011
// This same technique could be used to delete all events and other bad stuff.
if ( $login == '__public__' ) {
  $name = strip_tags ( $name );
  $description = strip_tags ( $description );
  $location = strip_tags ( $location );
}

// Don't allow certain HTML tags in description.
// Malicious users could use meta refresh to redirect users to another
// site (possibly a malware site). This could be from a public submission
// on an event calendar, and the admin gets sent to the malware site when
// viewing the event to approve/reject it.
foreach( array(
    'APPLET',
    'BODY',
    'HEAD',
    'HTML',
    'LINK',
    'META',
    'OBJECT',
    'SCRIPT',
    'TITLE' ) as $i ) {
  if( preg_match( "/<\s*$i/i", $description ) ) {
    $error = translate( 'Security violation!' );
    activity_log( 0, $login, $login, SECURITY_VIOLATION, 'Hijack attempt:edit_entry' );
  }
}

// Pass all numeric values through getPostValue.
$entry_ampm   = getPostValue( 'entry_ampm' );
$entry_hour   = getPostValue( 'entry_hour' );
$entry_minute = getPostValue( 'entry_minute' );

$end_ampm   = getPostValue( 'end_ampm' );
$end_hour   = getPostValue( 'end_hour' );
$end_minute = getPostValue( 'end_minute' );

$ymd = getPostValue('_YMD');
$parsed = date_parse($ymd);
$day   = $parsed['day'];
$month = $parsed['month'];
$year  = $parsed['year'];

$percent = getPostValue( 'percent' );

// Ensure variables are not empty.
if( empty( $eType ) )
  $eType = 'event';

if( empty( $percent ) )
  $percent = 0;

if( empty( $timetype ) )
  $timetype = 'T';

$duration_h = getValue( 'duration_h' );
$duration_m = getValue( 'duration_m' );

if( empty( $duration_h ) || $duration_h < 0 )
  $duration_h = 0;

if( empty( $duration_m ) || $duration_m < 0 )
  $duration_m = 0;

// Reminder values could be valid as 0.
if( empty( $rem_days ) )
  $rem_days = 0;

if( empty( $rem_hours ) )
  $rem_hours = 0;

if( empty( $rem_minutes ) )
  $rem_minutes = 0;

if( empty( $rem_rep_days ) )
  $rem_rep_days = 0;

if( empty( $rem_rep_hours ) )
  $rem_rep_hours = 0;

if( empty( $rem_rep_minutes ) )
  $rem_rep_minutes = 0;

if( empty( $reminder_hour ) )
  $reminder_hour = 0;

if( empty( $reminder_minute ) )
  $reminder_minute = 0;

// Timed event.
if( $timetype == 'T' ) {
  $entry_hour += $entry_ampm;

  if( $eType == 'task' )
    $due_hour += $due_ampm;
}

// Use end times
if( $TIMED_EVT_LEN == 'E' && $eType != 'task' )
  $end_hour += $end_ampm;
else
  $end_hour   =
  $end_minute = 0;

// If "all day event" was selected, then we set the event time to be 12AM with a
// duration of 24 hours. We don't actually store the "all day event" flag per se.
// This method makes conflict checking much simpler. We just need to make sure
// that we don't screw up the day view (which normally starts with the first
// timed event). Note that if someone actually wants to create an event that
// starts at midnight and lasts exactly 24 hours, it will be treated in the
// same manner.

// All Day Event.
if( $timetype == 'A' )
  $duration_h = 24;

// Untimed Event
if( $timetype == 'U' )
  $duration_h = 0;

if( strpos( 'AU', $timetype ) !== false ) {
  $duration_m   =
  $end_hour     =
  $end_minute   =
  $entry_hour   =
  $entry_minute = 0;
}
// Combine all values to create event start date/time.
$eventstart = ( $timetype != 'T'
  ? gmmktime( $entry_hour, $entry_minute, 0, $month, $day, $year )
  : mktime( $entry_hour, $entry_minute, 0, $month, $day, $year ) );

if( $eType == 'task' ) {
  // Combine all values to create event due date/time - User Time.
  $eventdue =
    mktime( $due_hour, $due_minute, 0, $due_month, $due_day, $due_year );

  // Combine all values to create completed date
  if( ! empty( $completed_year ) && ! empty( $completed_month )
      && ! empty( $completed_day ) )
    $eventcomplete = sprintf( "%04d%02d%02d", $completed_year,
      $completed_month, $completed_day );
}

// Create event stop from event duration/end values.
// Note: for any given event, either end times or durations are 0
if( $TIMED_EVT_LEN == 'E' ) {
  // User might have entered midnight as an end time
  // if so, we need to jump to next day
  if( $end_hour === 0 && $end_ampm == 0 )
    $day++;

  $eventstophour = $end_hour + $duration_h;
  $eventstopmin  = $end_minute + $duration_m;
} else {
  $eventstophour = $entry_hour + $duration_h;
  $eventstopmin  = $entry_minute + $duration_m;
}

$duration = 0;

if( $eType != 'task' ) {
  $eventstop = ( $timetype != 'T'
    ? gmmktime( $eventstophour, $eventstopmin, 0, $month, $day, $year )
    : mktime( $eventstophour, $eventstopmin, 0, $month, $day, $year ) );

  // Calculate event duration.
  if( $timetype == 'A' )
    $duration = 1440;
  elseif( $timetype == 'T' ) {
    $duration = ( $eventstop - $eventstart ) / 60;

    if( $duration < 0 )
      $duration = 0;
  }
}

/* Make sure this user is really allowed to edit this event.
 * Otherwise, someone could hand type in the URL to edit someone else's event.
 * Can edit if:
 *   - new event
 *   - user is admin
 *   - user created event
 *   - user is participant
 */
$can_doall =
$can_edit  = false;
// Value may be needed later for recreating event.
$old_create_by = ( empty( $user ) ? '' : $user );

if( empty( $id ) ) {
  // New event...
  $can_edit = (!empty($readonly) && $readonly != 'Y');

  if (access_is_enabled())
    $can_edit = access_can_access_function(ACCESS_EVENT_EDIT, $user);

  if ($login == '__public__')
    $can_edit = access_is_enabled()? $can_edit: $PUBLIC_ACCESS_CAN_ADD == 'Y';

  if (!$is_admin && !$is_assistant && !$is_nonuser_admin) {
    if ($is_nonuser)
      $can_edit = false;
    else if (!empty($user) && $user != $login && $user != '__public__')
      $can_edit = false;
  }
} else {
  // Event owner or assistant?
  $res = dbi_execute( 'SELECT cal_create_by FROM webcal_entry WHERE cal_id = ?',
    array( $id ) );

  if( $res ) {
    $row = dbi_fetch_row( $res );
    // Value may be needed later for recreating event.
    $old_create_by = $row[0];

    if( ( $row[0] == $login ) || ( ( $user == $row[0] )
        && ( $is_assistant || $is_nonuser_admin ) ) )
      $can_edit = true;
    dbi_free_result( $res );
  } else
    $error = $dberror . dbi_error();
}

if( $is_admin )
  $can_edit = true;
elseif( access_is_enabled() && ! empty( $old_create_by ) )
  $can_edit = access_user_calendar( 'edit', $old_create_by, $login );

if( empty( $error ) && ! $can_edit ) {
  // Is user a participant of the event?
  $res = dbi_execute( 'SELECT cal_id FROM webcal_entry_user WHERE cal_id = ?
    AND cal_login = ? AND cal_status IN( \'W\', \'A\' )',
    array( $id, $login ) );

  if( $res ) {
    $row = dbi_fetch_row( $res );

    if( ! empty( $row[0] ) )
      $can_edit = true; // Is participant.

    dbi_free_result( $res );
  } else
    $error = $dberror . dbi_error();
}

if( ! $can_edit && empty( $error ) )
  $error = print_not_auth();

// CAPTCHA
if( file_exists( 'includes/classes/captcha/captcha.php' )
    && $login == '__public__'
    && ! empty( $ENABLE_CAPTCHA ) && $ENABLE_CAPTCHA == 'Y' ) {
  if( function_exists( 'imagecreatetruecolor' ) ) {
    require_once 'includes/classes/captcha/captcha.php';
    $res = captcha::check();

    if( ! $res )
      $error =
      translate( 'You must enter the anti-spam text on the previous page.' );
  } else {
    // Should have seen warning on edit_entry.php, so no warning here...
  }
}

// If display of participants is disabled, set the participant list to the event
// creator. This also works for single-user mode. Basically, if no participants
// were selected (because there was no selection list available in the form or
// because the user refused to select any participant from the list), then we
// will assume the only participant is the current user.
if( empty( $participants[0] ) ) {
  $participants[0] = $login;
  // There might be a better way to do this,
  // but if Admin sets this value, WebCalendar should respect it.
  if( ! empty( $PUBLIC_ACCESS_DEFAULT_SELECTED )
      && $PUBLIC_ACCESS_DEFAULT_SELECTED == 'Y' )
    $participants[1] = '__public__';
}

if( empty( $DISABLE_REPEATING_FIELD ) || $DISABLE_REPEATING_FIELD == 'N' ) {
  // Process only if Expert Mode or Weekly.
  if( $rpt_type == 'weekly' || ! empty( $rptmode ) ) {
    $bydayAr = explode( ',', $bydayList );

    if( ! empty( $bydayAr ) ) {
      foreach( $bydayAr as $bydayElement ) {
        if( strlen( $bydayElement ) > 2 )
          $bydayAll[] = $bydayElement;
      }
    }

    if( ! empty( $bydayAll ) ) {
      $bydayAll = array_unique( $bydayAll );
      // Call special sort algorithm.
      usort( $bydayAll, 'sort_byday' );
      $byday = implode( ',', $bydayAll );

      // Strip off leading comma if present.
      if( substr( $byday, 0, 1 ) == ',' )
        $byday = substr( $byday, 1 );
    }
  }

  // This allows users to select on weekdays if daily.
  if( $rpt_type == 'daily' && ! empty( $weekdays_only ) )
    $byday = 'MO,TU,WE,TH,FR';

  // Process only if expert mode and MonthbyDate or Yearly.
  if( ( $rpt_type == 'monthlyByDate' || $rpt_type == 'yearly' )
      && ! empty( $rptmode ) ) {
    $bymonthdayAr = explode( ',', $bymonthdayList );

    if( ! empty( $bymonthdayAr ) ) {
      sort( $bymonthdayAr );
      $bymonthdayAr = array_unique( $bymonthdayAr );
      $bymonthday   = implode( ',', $bymonthdayAr );
    }
    // Strip off leading comma if present.
    if( substr( $bymonthday, 0, 1 ) == ',' )
      $bymonthday = substr( $bymonthday, 1 );
  }

  if( $rpt_type == 'monthlyBySetPos' ) {
    $bysetposAr = explode( ',', $bysetposList );

    if( ! empty( $bysetposAr ) ) {
      sort( $bysetposAr );
      $bysetposAr = array_unique( $bysetposAr );
      $bysetpos   = implode( ',', $bysetposAr );
    }
    // Strip off leading comma if present.
    if( substr( $bysetpos, 0, 1 ) == ',' )
      $bysetpos = substr( $bysetpos, 1 );
  }

  // If expert mode not selected,
  // we need to set the basic value for monthlyByDay events.
  if( $rpt_type == 'monthlyByDay' && empty( $rptmode ) && empty( $byday ) )
    $byday = ceil( $day / 7 ) . $byday_names[ date( 'w', $eventstart ) ];

  $bymonth = ( empty( $bymonth ) ? '' : implode( ',', $bymonth ) );

  if( ! empty( $rpt_end_use ) ) {
    $rpt_hour += $rpt_ampm;
    $rpt_until =
      mktime( $rpt_hour, $rpt_minute, 0, $rpt_month, $rpt_day, $rpt_year );
  }

  $exception_list =
  $inclusion_list = array();

  if( empty( $exceptions ) )
    $exceptions = array();
  else {
    foreach( $exceptions as $i ) {
      if( substr( $i, 0, 1 ) == '+' )
        $inclusion_list[] = substr( $i, 1, 8 );
      else
        $exception_list[] = substr( $i, 1, 8 );
    }
  }
} // end test for $DISABLE_REPEATING_FIELD

// Make sure we initialize these variables.
if( empty( $byday ) )
  $byday = '';

if( empty( $bymonth ) )
  $bymonth = '';

if( empty( $bymonthday ) )
  $bymonthday = '';

if( empty( $bysetpos ) )
  $bysetpos = '';

if( empty( $byweekno ) )
  $byweekno = '';

if( empty( $byyearday ) )
  $byyearday = '';

if( empty( $count ) )
  $count = '';

if( empty( $rpt_freq ) )
  $rpt_freq = 1;

if( empty( $rpt_type ) )
  $rpt_type = '';

if( empty( $wkst ) )
  $wkst = 'MO';

// First check for any schedule conflicts.
if( empty( $ALLOW_CONFLICT_OVERRIDE ) || $ALLOW_CONFLICT_OVERRIDE != 'Y' )
  $confirm_conflicts = ''; // Security precaution.

if( $ALLOW_CONFLICTS != 'Y' && empty( $confirm_conflicts )
    && strlen( $entry_hour ) > 0 && $timetype != 'U' && $eType != 'task' ) {
  $conf_until = ( empty( $rpt_until ) ? '' : $rpt_until );
  $conf_count = ( empty( $rpt_count ) ? 999 : $rpt_count );
  $dates = get_all_dates( $eventstart, $rpt_type, $rpt_freq,
    $bymonth, $byweekno, $byyearday, $bymonthday, $byday, $bysetpos,
    $conf_count, $conf_until, $wkst, $exception_list, $inclusion_list );

  // Make sure at least start date is in array.
  if( empty( $dates ) )
    $dates[0] = $eventstart;

  // Make sure $thismonth and $thisyear are set for use in query_events().
  $thismonth = $month;
  $thisyear  = $year;
  $conflicts = check_for_conflicts( $dates, $duration, $eventstart,
    $participants, $login, empty( $id ) ? 0 : $id );
} //end check for any schedule conflicts

if( empty( $error ) && ! empty( $conflicts ) )
  $error = translate( 'The following conflicts with the suggested time' )
   . ': <ul>$conflicts</ul>';

$msg = '';

if( empty( $error ) ) {
  $newevent = true;

  // Now add the entries.
  if( empty( $id ) || $do_override ) {
    $res = dbi_execute( 'SELECT MAX(cal_id) FROM webcal_entry' );

    if( $res ) {
      $row = dbi_fetch_row( $res );
      $id  = $row[0] + 1;
      dbi_free_result( $res );
    } else
      $id = 1;
  } else {
    $newevent = false;
    // Save old values of participants.
    $res = dbi_execute( 'SELECT cal_login, cal_status, cal_percent
      FROM webcal_entry_user WHERE cal_id = ? ', array( $id ) );

    if( $res ) {
      for( $i = 0; $tmprow = dbi_fetch_row( $res ); $i++ ) {
        $old_status[$tmprow[0]]  = $tmprow[1];
        $old_percent[$tmprow[0]] = $tmprow[2];
      }
      dbi_free_result( $res );
    } else
      $error = $dberror . dbi_error();

    if( empty( $error ) ) {
      foreach( array(
          'entry',
          'entry_ext_user',
          'entry_repeats',
          'entry_user',
          'site_extras' ) as $d ) {
        dbi_execute( 'DELETE FROM webcal_' . $d . ' WHERE cal_id = ?',
          array( $id ) );
      }
    }
    $newevent = false;
  }

  if( $do_override ) {
    if( ! dbi_execute( 'INSERT INTO webcal_entry_repeats_not
      ( cal_id, cal_date, cal_exdate ) VALUES ( ?, ?, ? )',
        array( $old_id, $override_date, 1 ) ) )
      $error = $dberror . dbi_error();
  }

  $query_params = array( $id );

  if( $old_id > 0 )
    $query_params[] = $old_id;

  $query_params[] = ( empty( $old_create_by ) ? $login : $old_create_by );
  $query_params[] = gmdate( 'Ymd', $eventstart );
  $query_params[] = ( ( strlen( $entry_hour ) > 0 && $timetype != 'U' )
    ? gmdate( 'His', $eventstart ) : '-1' );

  if( ! empty( $eventcomplete ) )
    $query_params[] = $eventcomplete;

  // Just set $eventdue to something.
  $eventdue = ( empty( $eventdue ) ? $eventstart : $eventdue );

  $query_params[] = gmdate( 'Ymd', $eventdue );
  $query_params[] = gmdate( 'His', $eventdue );
  $query_params[] = gmdate( 'Ymd' );
  $query_params[] = gmdate( 'Gis' );
  $query_params[] = sprintf( "%d", $duration );
  $query_params[] = ( empty( $priority ) ? '5' : sprintf( "%d", $priority ) );
  $query_params[] = ( empty( $access ) ? 'P' : $access );

  $tmpRpt = ( ! empty( $rpt_type ) && $rpt_type != 'none' );

  if( $eType == 'event' )
    $query_params[] = ( $tmpRpt ? 'M' : 'E' );
  elseif( $eType == 'journal' )
    $query_params[] = ( $tmpRpt ? 'O' : 'J' );
  elseif( $eType == 'task' )
    $query_params[] = ( $tmpRpt ? 'N' : 'T' );

  $query_params[] = ( strlen( $name ) == 0 ? 'Unnamed Event' : $name );
  $query_params[] = $description;

  if( ! empty( $location ) )
    $query_params[] = $location;

  if( ! empty( $entry_url ) )
    $query_params[] = $entry_url;

  if( empty( $error ) && ! dbi_execute( 'INSERT INTO webcal_entry ( cal_id,'
      . ( $old_id > 0 ? ' cal_group_id,' : '' )
      . ' cal_create_by, cal_date, cal_time,'
      . ( empty( $eventcomplete ) ? '' : ' cal_completed,' )
      . ' cal_due_date, cal_due_time, cal_mod_date, cal_mod_time, cal_duration,
      cal_priority, cal_access, cal_type, cal_name, cal_description'
      . ( empty( $location ) ? '' : ', cal_location ' )
      . ( empty( $entry_url ) ? '' : ', cal_url ' ) . ' ) VALUES ( ?'
      . str_repeat( ',?', count( $query_params ) - 1 ) . ' )', $query_params ) )
    $error = $dberror . dbi_error();

  // Log add/update.
  if( $eType == 'task' ) {
    $log_c = LOG_CREATE_T;
    $log_u = LOG_UPDATE_T;
  } elseif( $eType == 'journal' ) {
    $log_c = LOG_CREATE_J;
    $log_u = LOG_UPDATE_J;
  } else {
    $log_c = LOG_CREATE;
    $log_u = LOG_UPDATE;
  }
  activity_log( $id, $login, ( $is_assistant || $is_nonuser_admin
    ? $user : $login ), $newevent ? $log_c : $log_u, '' );

  if( $single_user == 'Y' )
    $participants[0] = $single_user_login;

  // Add categories.
  $cat_owner = ( ( ! empty( $user ) && strlen( $user ) )
      && ( $is_assistant || $is_admin ) ? $user : $login );
  dbi_execute( 'DELETE FROM webcal_entry_categories WHERE cal_id = ?
    AND ( cat_owner = ? OR cat_owner IS NULL )', array( $id, $cat_owner ) );

  if( ! empty( $cat_id ) ) {
    $categories = explode( ',', $cat_id );
    $j = 0;
    foreach( $categories as $i ) {
      $j++;

      $names  = array( 'cal_id', 'cat_id' );
      $values = array( $id, abs( $i ) );

      // We set cat_id negative in form if global.
      if( $i > 0 ) {
        $names[]  = 'cat_owner';
        $values[] = $cat_owner;

        $values[] = $j;
      } else
        $values[] = 99; // Force global categories to the end of lists.

      $names[] = 'cat_order';

      if( ! dbi_execute( 'INSERT INTO webcal_entry_categories ( '
          . implode( ',', $names ) . ' ) VALUES ( ?'
        // Build the variable placeholders - comma-separated question marks.
          . str_repeat( ',?', count( $values ) - 1 ) . ' )', $values ) ) {
        $error = $dberror . dbi_error();
        break;
      }
    }
  }

  // Add site extras.
  $extra_email_data = '';
  foreach( $site_extras as $i ) {
    if( ! empty( $error ) )
      break;

    if( $i == 'FIELDSET' )
      continue;

    $extra_name = $i[0];
    $extra_type = $i[2];
    $extra_arg1 = $i[3];
    $extra_arg2 = $i[4];

    if( ! empty( $i[5] ) )
      $extra_email = $i[5] & EXTRA_DISPLAY_EMAIL;

    //$value = $$extra_name;
    $value = getPostValue( $extra_name );
    $sql = '';
    $query_params = array();

    if( strlen( $extra_name ) || $extra_type == EXTRA_DATE ) {
      if( $extra_type == EXTRA_CHECKBOX
          || $extra_type == EXTRA_EMAIL
          || $extra_type == EXTRA_MULTILINETEXT
          || $extra_type == EXTRA_RADIO
          || $extra_type == EXTRA_SELECTLIST
          || $extra_type == EXTRA_TEXT
          || $extra_type == EXTRA_URL
          || $extra_type == EXTRA_USER ) {
        // We were passed an array instead of a string.
        if( $extra_type == EXTRA_SELECTLIST && $extra_arg2 > 0 )
          $value = implode( ',', $value );

        $sql = 'INSERT INTO webcal_site_extras ( cal_id, cal_name, cal_type,
          cal_data ) VALUES ( ?, ?, ?, ? )';
        $query_params = array( $id, $extra_name, $extra_type, $value );

        if( ! empty( $extra_email ) ) {
          $value =
            ( $extra_type == EXTRA_RADIO ? $extra_arg1[$value] : $value );
          $extra_email_data .= $extra_name . ': ' . $value . "\n";
        }
      } elseif( $extra_type == EXTRA_DATE ) {
        $edate = sprintf( "%04d%02d%02d",
          getPostValue( $extra_name . 'year' ),
          getPostValue( $extra_name . 'month' ),
          getPostValue( $extra_name . 'day' ) );
        $sql = 'INSERT INTO webcal_site_extras ( cal_id, cal_name, cal_type,
          cal_date ) VALUES ( ?, ?, ?, ? )';
        $query_params = array( $id, $extra_name, $extra_type, $edate );

        if( ! empty( $extra_email ) )
          $extra_email_data .= $extra_name . ': ' . $edate . "\n";
      }
    }
    if( strlen( $sql ) && empty( $error ) ) {
      if( ! dbi_execute( $sql, $query_params ) )
        $error = $dberror . dbi_error();
    }
  } //end for site_extras loop

  // Process reminder.
  if( ! dbi_execute( 'DELETE FROM webcal_reminders WHERE cal_id = ?',
      array( $id ) ) )
    $error = $dberror . dbi_error();

  if( $DISABLE_REMINDER_FIELD != 'Y' && $reminder == true ) {
    if( empty( $rem_before ) )
      $rem_before = 'Y';

    // If entry has changed, reset the reminder.
    if( $entry_changed ) {
      $rem_last_sent  =
      $rem_times_sent = '0';
    }

    if( empty( $rem_last_sent ) )
      $rem_last_sent = '0';

    if( empty( $rem_related ) )
      $rem_related = 'S';

    $reminder_date     =
    $reminder_duration =
    $reminder_offset   =
    $reminder_repeats  = 0;

    if( $rem_when == 'Y' ) { // Use date.
      $reminder_hour += $reminder_ampm;
      $reminder_date = mktime( $reminder_hour, $reminder_minute, 0,
        $reminder_month, $reminder_day, $reminder_year );
    } else // Use offset.
      $reminder_offset =
        ( $rem_days * 1440 ) + ( $rem_hours * 60 ) + $rem_minutes;

    if( $rem_rep_count > 0 ) {
      $reminder_repeats = $rem_rep_count;
      $reminder_duration = ( $rem_rep_days * 1440 )
        + ( $rem_rep_hours * 60 ) + $rem_rep_minutes;
    }
    if( ! dbi_execute( 'INSERT INTO webcal_reminders ( cal_id, cal_date,
      cal_offset, cal_related, cal_before, cal_repeats, cal_duration, cal_action,
      cal_last_sent, cal_times_sent ) VALUES ( ?, ?, ?, ?, ?, ?, ?, ?, ?, ? )',
        array( $id, $reminder_date, $reminder_offset, $rem_related, $rem_before,
          $reminder_repeats, $reminder_duration, $rem_action, $rem_last_sent,
          $rem_times_sent ) ) )
      $error = $dberror . dbi_error();
  }

  if( empty( $error ) ) {
    // Clearly, we want to delete the old repeats, before inserting new...
    foreach( array(
        'repeats',
        'repeats_not' ) as $d ) {
      if( ! dbi_execute( 'DELETE FROM webcal_entry_' . $d
       . ' WHERE cal_id = ?', array( $id ) ) )
        $error .= $dberror . dbi_error();
    }
    // Add repeating info.
    if( ! empty( $rpt_type ) && strlen( $rpt_type ) && $rpt_type != 'none' ) {
      $names  = array( 'cal_id', 'cal_type', 'cal_frequency' );
      $values = array( $id, $rpt_type, ( $rpt_freq ? $rpt_freq : 1 ) );

      if( ! empty( $bymonth ) ) {
        $names[]  = 'cal_bymonth';
        $values[] = $bymonth;
      }
      if( ! empty( $bymonthday ) ) {
        $names[]  = 'cal_bymonthday';
        $values[] = $bymonthday;
      }
      if( ! empty( $byday ) ) {
        $names[]  = 'cal_byday';
        $values[] = $byday;
      }
      if( ! empty( $bysetpos ) ) {
        $names[]  = 'cal_bysetpos';
        $values[] = $bysetpos;
      }
      if( ! empty( $byweekno ) ) {
        $names[]  = 'cal_byweekno';
        $values[] = $byweekno;
      }
      if( ! empty( $byyearday ) ) {
        $names[]  = 'cal_byyearday';
        $values[] = $byyearday;
      }
      if( ! empty( $wkst ) ) {
        $names[]  = 'cal_wkst';
        $values[] = $wkst;
      }
      if( ! empty( $rpt_count ) && is_numeric( $rpt_count ) ) {
        $names[]  = 'cal_count';
        $values[] = $rpt_count;
      }
      if( ! empty ( $rpt_end_use ) && $rpt_end_use == 'u' && ! empty( $rpt_until ) ) {
        $names[] = 'cal_end';
        $values[] = gmdate( 'Ymd', $rpt_until );
        $names[] = 'cal_endtime';
        $values[] = gmdate( 'His', $rpt_until );
      }
      $sql = 'INSERT INTO webcal_entry_repeats ( '
       . implode( ',', $names ) . ' ) VALUES ( ?'
       . str_repeat( ',?', count( $values ) - 1 ) . ' )';
      dbi_execute( $sql, $values );
      $msg .= '<span class="bold">SQL:</span> ' . $sql . '<br /><br />';
    } //end add repeating info

    // We manually created exceptions. This can be done without repeats.
    if( ! empty( $exceptions ) ) {
      foreach( $exceptions as $i ) {
        if( ! dbi_execute( 'INSERT INTO webcal_entry_repeats_not
            ( cal_id, cal_date, cal_exdate ) VALUES ( ?, ?, ? )',
            array( $id, substr( $i, 1, 8 ),
              ( ( substr( $i, 0, 1 ) == '+' ) ? 0 : 1 ) ) ) )
          $error = $dberror . dbi_error();
      }
    } //end exceptions
  }
  // EMAIL PROCESSING
  $from = $login_email;

  if( empty( $from ) && ! empty( $EMAIL_FALLBACK_FROM ) )
    $from = $EMAIL_FALLBACK_FROM;

  // Check if participants have been removed and send out emails.
  if( ! $newevent && count( $old_status ) > 0 ) {
    foreach ($old_status as $old_participant => $dummy) {
      $found_flag = false;
      foreach( $participants as $i ) {
        if( $i == $old_participant ) {
          $found_flag = true;
          break;
        }
      }

      // Check UAC.
      $can_email = 'Y';

      if( access_is_enabled() )
        $can_email = access_user_calendar( 'email', $old_participant, $login );

      $is_nonuser_admin = user_is_nonuser_admin( $login, $old_participant );

      // Don't send mail if editing a non-user calendar and we are the admin.
      if( ! $found_flag && ! $is_nonuser_admin && $can_email == 'Y' ) {
        // Only send mail if their email address is filled in.
        $do_send =
          get_pref_setting( $old_participant, 'EMAIL_EVENT_DELETED' );
        $htmlmail      = get_pref_setting( $old_participant, 'EMAIL_HTML' );
        $t_format      = get_pref_setting( $old_participant, 'TIME_FORMAT' );
        $user_language = get_pref_setting( $old_participant, 'LANGUAGE' );
        $user_TIMEZONE = get_pref_setting( $old_participant, 'TIMEZONE' );

        set_env( 'TZ', $user_TIMEZONE );
        user_load_variables( $old_participant, 'temp' );

        if( $old_participant != $login && ! empty( $tempemail )
            && $do_send == 'Y' && $SEND_EMAIL != 'N' ) {
          reset_language( empty( $user_language ) || $user_language == 'none'
            ? $LANGUAGE : $user_language );

          $fmtdate = ( $timetype == 'T'
            ? date( 'Ymd', $eventstart ) : gmdate( 'Ymd', $eventstart ) );
          $msg = str_replace( 'XXX', $tempfullname, $helloStr ) . "\n\n"
           . str_replace( 'XXX', $login_fullname,
             translate( 'XXX has canceled an appointment.' ) ) . "\n"
           . str_replace( 'XXX', $name, $subjStr ) . "\n\n"
           . str_replace( 'XXX', $description, $descStr ) . "\n"
           . str_replace( 'XXX', date_to_str( $fmtdate ), $dateStr ) . "\n"
           // Apply user's GMT offset and display their TZID.
           . ( $timetype != 'T' ? '' : str_replace( 'XXX',
             display_time( '', 2, $eventstart, $t_format ),
             $timeStr . "\n\n\n" ) );

          // Add URL to event, if we can figure it out.
          if( ! empty( $SERVER_URL ) ) {
            // DON'T change & to &amp; here. Email will handle it.
            $url = $SERVER_URL . 'view_entry.php?id=' . $id . '&em=1';

            if( $htmlmail == 'Y' )
              $url = activate_urls( $url );

            $msg .= $url . "\n\n";
          }
          $mail->WC_Send( $login_fullname, $tempemail,
            $tempfullname, $name, $msg, $htmlmail, $from,
            ( get_pref_setting( $old_participant,
              'EMAIL_ATTACH_ICS', 'N' ) == 'Y' ? $id : '' ) );
          activity_log( $id, $login, $old_participant, LOG_NOTIFICATION,
            translate( 'User removed from participants list.' ) );
        }
      }
    }
  }
  $send_own = get_pref_setting( $login, 'EMAIL_EVENT_CREATE' );
  // Now add participants and send out notifications.
  foreach( $participants as $i ) {
    // Is the person adding the nonuser calendar admin?
    $is_nonuser_admin = user_is_nonuser_admin( $login, $i );

    // If public access, require approval unless
    // $PUBLIC_ACCESS_ADD_NEEDS_APPROVAL is set to 'N'
    if( $login == '__public__' ) {
      $status = ( ! empty( $PUBLIC_ACCESS_ADD_NEEDS_APPROVAL )
          && $PUBLIC_ACCESS_ADD_NEEDS_APPROVAL == 'N'
        ? 'A' // No approval needed.
        : 'W' // Approval required.
        );
      // Percent will always be 0 for Public
      $new_percent = 0;
    } elseif( ! $newevent ) {
      // Keep the old status if no email will be sent.
      $send_user_mail = ( empty( $old_status[$i] ) || $entry_changed );
      $tmp_status = ( ! empty( $old_status[$i] ) && ! $send_user_mail
        ? $old_status[$i] : 'W' );
      $status = ( $i != $login && boss_must_approve_event( $login, $i )
        && $REQUIRE_APPROVALS == 'Y'
        && ! $is_nonuser_admin ? $tmp_status : 'A' );

      // Set percentage to old_percent if not owner.
      $tmp_percent = ( empty( $old_percent[$i] ) ? 0 : $old_percent[$i] );

      // TODO: This logic needs work.
      $new_percent = ( $i != $login ? $tmp_percent : $percent );
      // If user is admin and this event was previously approved for public,
      // keep it as approved even though date/time may have changed.
      // This goes against stricter security, but it confuses users to have
      // to re-approve events they already approved.
      if( $i == '__public__' && $is_admin
          && ( empty( $old_status['__public__'] )
            || $old_status['__public__'] == 'A' ) )
        $status = 'A';
    } else { // New Event.
      $send_user_mail = true;
      $status = ( $i != $login
        && boss_must_approve_event( $login, $i ) && $REQUIRE_APPROVALS == 'Y'
        && ! $is_nonuser_admin ? 'W' : 'A' );
      $new_percent = ( $i != $login ? 0 : $percent );

      // If admin, no need to approve Public Access Events.
      if( $i == '__public__' && $is_admin )
        $status = 'A';
    } //end new/old event

    // Some users report that they get an error on duplicate keys
    // on the following add... As a safety measure, delete any
    // existing entry with the id. Ignore the result.
    dbi_execute( 'DELETE FROM webcal_entry_user
      WHERE cal_id = ? AND cal_login = ?', array( $id, $i ) );

    if( ! dbi_execute( 'INSERT INTO webcal_entry_user ( cal_id, cal_login,
        cal_status, cal_percent ) VALUES ( ?, ?, ?, ? )',
        array( $id, $i, $status, $new_percent ) ) ) {
      $error = $dberror . dbi_error();
      break;
    } else {
      // Check UAC.
      $can_email = 'Y';

      if( access_is_enabled() )
        $can_email = access_user_calendar( 'email', $i, $login );

      // Don't send mail if we are editing a non-user calendar and are the admin.
      if( ! $is_nonuser_admin && $can_email == 'Y' ) {
        // Only send mail if their email address is filled in.
        $do_send = get_pref_setting( $i, $newevent
          ? 'EMAIL_EVENT_ADDED' : 'EMAIL_EVENT_UPDATED' );
        $htmlmail      = get_pref_setting( $i, 'EMAIL_HTML' );
        $t_format      = get_pref_setting( $i, 'TIME_FORMAT' );
        $user_language = get_pref_setting( $i, 'LANGUAGE' );
        $user_TIMEZONE = get_pref_setting( $i, 'TIMEZONE' );

        set_env( 'TZ', $user_TIMEZONE );
        user_load_variables( $i, 'temp' );

        if( boss_must_be_notified( $login, $i ) && ! empty( $tempemail )
            && $do_send == 'Y' && $send_user_mail && $SEND_EMAIL != 'N' ) {
          // We send to creator if they want it.
          if( $send_own != 'Y' && ( $i == $login ) )
            continue;

          reset_language( empty( $user_language ) || $user_language == 'none'
            ? $LANGUAGE : $user_language );
          $fmtdate = ( $timetype == 'T'
            ? date( 'Ymd', $eventstart ) : gmdate( 'Ymd', $eventstart ) );
          $msg = str_replace( 'XXX', $tempfullname, $helloStr ) . "\n\n"
           . str_replace( 'XXX', $login_fullname,
            ( $newevent || ( empty( $old_status[$i] ) )
              ? $newAppStr : $updAppStr ) ) . "\n"
           . str_replace( 'XXX', $name, $subjStr ) . "\n\n"
           . str_replace( 'XXX', $description, $descStr ) . "\n"
           . str_replace( 'XXX', date_to_str( $fmtdate ), $dateStr ) . "\n"
           // Apply user's GMT offset and display their TZID.
           . ( $timetype != 'T' ? ''
            : str_replace( 'XXX', display_time( '', 2, $eventstart, $t_format ),
              $timeStr ) . "\n" )
          // Add Site Extra Date if permitted.
          . $extra_email_data . str_replace( 'XXX', generate_application_name(),
            ( $REQUIRE_APPROVALS == 'Y'
              ? translate( 'Please look on XXX to accept or reject this appointment.' )
              : translate( 'Please look on XXX to view this appointment.' ) ) );

              // Add URL to event, if we can figure it out.
          if( ! empty( $SERVER_URL ) ) {
            // DON'T change & to &amp; here. Email will handle it.
            $url = $SERVER_URL . 'view_entry.php?id=' . $id . '&em=1';

            if( $htmlmail == 'Y' )
              $url = activate_urls( $url );

            $msg .= "\n\n" . $url;
          }
          // Use WebCalMailer class.
          $mail->WC_Send( $login_fullname, $tempemail,
            $tempfullname, $name, $msg, $htmlmail, $from,
            ( get_pref_setting( $i, 'EMAIL_ATTACH_ICS', 'N' ) == 'Y'
              ? $id : '' ) );
          activity_log( $id, $login, $i, LOG_NOTIFICATION, '' );
        }
      }
    }
  } //end for loop participants

  // Add external participants.
  $ext_emails =
  $ext_names  =
  $matches    = array();
  $ext_count = 0;
  $externalparticipants = getPostValue( 'externalparticipants' );

  if( $single_user == 'N' && ! empty( $ALLOW_EXTERNAL_USERS )
      && $ALLOW_EXTERNAL_USERS == 'Y'
      && ! empty( $externalparticipants ) ) {
    $lines = explode( "\n", $externalparticipants );

    if( ! is_array( $lines ) )
      $lines = array( $externalparticipants );

    if( is_array( $lines ) ) {
      $linecnt = count( $lines );
      for( $i = 0; $i < $linecnt; $i++ ) {
        $ext_words = explode( ' ', $lines[$i] );

        if( ! is_array( $ext_words ) )
          $ext_words = array( $lines[$i] );

        if( is_array( $ext_words ) ) {
          $ext_emails[$ext_count] =
          $ext_names[$ext_count]  = '';
          foreach( $ext_words as $j ) {
            // Use regexp matching to pull email address out.
            $j = chop( $j ); // Remove any \r.

            if( preg_match( '/<?\\S+@\\S+\\.\\S+>?/', $j, $matches ) ) {
              $ext_emails[$ext_count] = $matches[0];
              $ext_emails[$ext_count] =
                preg_replace( '/[<>]/', '',  $ext_emails[$ext_count] );
            } else {
              if( strlen( $ext_names[$ext_count] ) )
                $ext_names[$ext_count] .= ' ';

              $ext_names[$ext_count] .= $j;
            }
          }

          // Test for duplicate Names.
          if( $i > 0 ) {
            for( $k = $i - 1; $k > 0; $k-- ) {
              if( $ext_names[$i] == $ext_names[$k] )
                $ext_names[$i] .= "[$k]";
            }
          }
          if( strlen( $ext_emails[$ext_count] )
              && empty( $ext_names[$ext_count] ) )
            $ext_names[$ext_count] = $ext_emails[$ext_count];

          $ext_count++;
        }
      }
    }
  }

  // Send notification if enabled.
  if( is_array( $ext_names ) && is_array( $ext_emails ) ) {
    $ext_namescnt = count( $ext_names );
    for( $i = 0; $i < $ext_namescnt; $i++ ) {
      if( strlen( $ext_names[$i] ) ) {
        if( ! dbi_execute( 'INSERT INTO webcal_entry_ext_user
          ( cal_id, cal_fullname, cal_email ) VALUES ( ?, ?, ? )',
          array( $id, $ext_names[$i],
            ( strlen( $ext_emails[$i] ) ? $ext_emails[$i] : null ) ) ) )
          $error = $dberror . dbi_error();

        // Send mail notification if enabled.
        // TODO: Move this code into a function...
        if( $EXTERNAL_NOTIFICATIONS == 'Y' && $SEND_EMAIL != 'N'
            && strlen( $ext_emails[$i] ) > 0 ) {
          if( ( ! $newevent && isset( $EXTERNAL_UPDATES )
              && $EXTERNAL_UPDATES == 'Y' ) || $newevent ) {
            $fmtdate = ( $timetype == 'T'
              ? date( 'Ymd', $eventstart ) : gmdate( 'Ymd', $eventstart ) );
            // Strip [\d] from duplicate Names before emailing.
            $ext_names[$i] =
              trim( preg_replace( '/\[[\d]]/', '', $ext_names[$i] ) );
            $msg = str_replace( 'XXX', $ext_names[$i], $helloStr ) . "\n\n"
             . str_replace( 'XXX', $login_fullname,
              ( $newevent ? $newAppStr : $updAppStr ) ) . "\n"
             . str_replace( 'XXX', $name, $subjStr ) . "\n\n"
             . str_replace( 'XXX', $description, $descStr ) . "\n\n"
             . str_replace( 'XXX', date_to_str( $fmtdate ), $dateStr ) . "\n"
             . ( $timetype == 'T' ? str_replace( 'XXX',
                display_time( '',
                  ( ! empty( $GENERAL_USE_GMT ) && $GENERAL_USE_GMT == 'Y'
                    ? 3 // Do not apply TZ offset & display TZID, which is GMT.
                    : 6 // Display time in server's timezone.
                    ), $eventstart ), $timeStr ) : '' )
            // Add Site Extra Date if permitted.
            . $extra_email_data;
            // Don't send HTML to external adresses.
            // Always attach iCalendar file to external users
            $mail->WC_Send( $login_fullname, $ext_emails[$i],
              $ext_names[$i], $name, $msg, 'N', $from, $id );
          }
        }
      }
    }
  } //end external mail
} //end empty error

// If we were editing this event,
// then go back to the last view (day, month, week).
// If this is a new event,
// then go to the preferred view for the date range where this event was added.
if( empty( $error ) && empty( $mailerError ) ) {
  $return_view = get_last_view();

  if( ! empty( $return_view ) )
    do_redirect( $return_view );
  else {
    $xdate = sprintf( "%04d%02d%02d", $year, $month, $day );
    $user_args = ( empty( $user ) ? '' : 'user=' . $user );
    send_to_preferred_view( $xdate, $user_args );
  }
}

if( ! empty( $conflicts ) ) {
  print_header();
  echo '
    <h2>' . translate( 'Scheduling Conflict' ) . '</h2>
    ' . translate( 'Your suggested time of' ) . '
    <span class="bold">';

  if( $timetype == 'A' )
    echo translate( 'All day event' );
  else {
    $time = sprintf( "%d%02d00", $entry_hour, $entry_minute );
    // Pass the adjusted timestamp in case the date changed due to GMT offset.
    echo display_time( '', 0, $eventstart );

    if( $duration > 0 )
      echo '-' . display_time( '', 0, $eventstart + ( $duration * 60 ) );
  }

  echo '</span>
    ' . translate( 'conflicts with the following existing calendar entries' ) . ':
    <ul>' . $conflicts . '
    </ul>
    ' // User can confirm conflicts.
  . '<form name="confirm" method="post">';
  print_form_key();
  foreach ($_POST as $xkey => $xval) {
    if (is_array($xval)) {
      $xkey .= "[]";
      foreach ($xval as $ykey => $yval) {
        echo '
      <input type="hidden" name="' . $xkey . '" value="' . $yval . '" />';
      }
    } else {
      echo '
      <input type="hidden" name="' . $xkey . '" value="' . $xval . '" />';
    }
  }

  echo
  // Allow them to override a conflict if server settings allow it.
   ( ! empty( $ALLOW_CONFLICT_OVERRIDE ) && $ALLOW_CONFLICT_OVERRIDE == 'Y' ? '
      <input type="submit" name="confirm_conflicts" value="'
     . translate( 'Save' ) . '" />' : '' ) . '
      <input type="button" value="' . translate( 'Cancel' )
   . '" onclick="history.back()" />
    </form>';

} else
  // Process errors.
  $mail->MailError( $mailerError, $error );

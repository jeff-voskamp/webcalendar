<?php
/**
 * Authentication functions.
 *
 * This file contains all the functions for getting information about users.
 * So, if you want to use an authentication scheme other than the webcal_user
 * table, you can just create a new version of each function found below.
 *
 * <b>Note:</b> this application assumes that usernames (logins) are unique.
 *
 * <b>Note #2:</b> If you are using HTTP-based authentication, then you still
 * need these functions and you will still need to add users to webcal_user.
 *
 * @author Craig Knudsen <cknudsen@cknudsen.com>
 * @copyright Craig Knudsen, <cknudsen@cknudsen.com>, http://www.k5n.us/cknudsen
 * @license http://www.gnu.org/licenses/gpl.html GNU GPL
 * @package WebCalendar
 * @subpackage Authentication
 */
defined ( '_ISVALID' ) or die ( 'You cannot access this file directly!' );

require_once 'auth-settings.php';

// Set some global config variables about your system.
$admin_can_disable_user = true;

/**
 * Check to see if a given login/password is valid.
 *
 * If invalid, the error message will be placed in $error.
 *
 * @param string $login    User login
 * @param string $password User password
 * @param bool $#silent  if truem do not return any $error
 *
 * @return bool True on success
 *
 * @global string Error message
 */
function user_valid_login ( $login, $password, $silent=false ) {
  global $error;
  $ret = $enabled = false;

  $sql = 'SELECT cal_login, cal_enabled, cal_passwd FROM webcal_user WHERE cal_login = ?';
  $res = dbi_execute ( $sql, [$login] );
  if ( $res ) {
    $row = dbi_fetch_row ( $res );
    if ( $row && $row[0] != '' ) {
      // Check the password
      $expected_hash = $row[2];
      if ( strlen ( $expected_hash ) == 32 && ctype_xdigit ( $expected_hash ) ) {
        // Old-Style MD5 password
        $supplied_hash = md5 ( $password );
        $okay = hash_equals ( $supplied_hash, $expected_hash );
        $rehash = true;
      } else {
        // New-Style Secure Password
        $okay = password_verify ( $password, $expected_hash );
        $rehash = password_needs_rehash ( $expected_hash, PASSWORD_DEFAULT );
      }
      // Upgrade insecurely stored passwords
      if ( $okay && $rehash ){
        $new_hash = password_hash ( $password, PASSWORD_DEFAULT );
        $sql = 'UPDATE webcal_user SET cal_passwd = ? WHERE cal_login = ?';
        dbi_execute ( $sql, [$new_hash, $login] );
      }
      $enabled = ( $row[1] == 'Y' ? true : false );
      // MySQL seems to do case insensitive matching, so double-check the login.
      if ( $okay && $row[0] == $login )
        $ret = true; // found login/password
      else if ( ! $silent )
        $error = translate ( 'Invalid login', true ) . ': ' .
          translate ( 'incorrect password', true );
    } else if ( ! $silent ) {
      $error = translate ( 'Invalid login', true );
      // Could be no such user or bad password
      // Check if user exists, so we can tell.
      $res2 = dbi_execute ( 'SELECT cal_login FROM webcal_user
        WHERE cal_login = ?', [$login] );
      if ( $res2 ) {
        $row = dbi_fetch_row ( $res2 );
        if ( $row && ! empty ( $row[0] ) ) {
          // got a valid username, but wrong password
          $error = translate ( 'Invalid login', true ) . ': ' .
            translate ( 'incorrect password', true );
        } else {
          // No such user.
          $error = translate ( 'Invalid login', true) . ': ' .
            translate ( 'no such user', true );
        }
        dbi_free_result ( $res2 );
      }
    }
    dbi_free_result ( $res );
    if ( ! $enabled && $error == '' ) {
      $ret = false;
      $error = ( ! $silent ? translate('Account disabled', true) : '' );
    }
  } else if ( ! $silent ) {
    $error = db_error();
  }

  return $ret;
}

/**
 * Check to see if a given login/crypted password is valid.
 *
 * If invalid, the error message will be placed in $error.
 *
 * @param string $login          User login
 * @param string $crypt_password Encrypted user password
 *
 * @return bool True on success
 *
 * @global string Error message
 */
function user_valid_crypt ( $login, $crypt_password ) {
  global $error;
  $ret = false;

  $sql = 'SELECT cal_login, cal_passwd FROM webcal_user WHERE cal_login = ?';
  $res = dbi_execute ( $sql, [$login] );
  if ( $res ) {
    $row = dbi_fetch_row ( $res );
    if ( $row && $row[0] != '' ) {
      // MySQL seems to do case insensitive matching, so double-check
      // the login.
      // also check if password matches
      if ( ($row[0] == $login) && ( (crypt($row[1], $crypt_password) == $crypt_password) ) )
        $ret = true; // found login/password
      else
        $error = 'Invalid login';
    } else {
      $error = 'Invalid login';
    }
    dbi_free_result ( $res );
  } else {
    $error = 'Database error: ' . dbi_error();
  }

  return $ret;
}

/**
 * Load info about a user (first name, last name, admin) and set globally.
 *
 * @param string $user User login
 * @param string $prefix Variable prefix to use
 *
 * @return bool True on success
 */
function user_load_variables ( $login, $prefix ) {
  global $PUBLIC_ACCESS_FULLNAME, $NONUSER_PREFIX, $cached_user_var, $SCRIPT;
  $ret = false;

  if ( ! empty ( $cached_user_var[$login][$prefix] ) )
    return  $cached_user_var[$login][$prefix];
  $cached_user_var = [];

  //help prevent spoofed username attempts from disclosing fullpath
  $GLOBALS[$prefix . 'fullname'] = '';
  if ($NONUSER_PREFIX && substr ($login, 0, strlen ($NONUSER_PREFIX) ) == $NONUSER_PREFIX) {
    nonuser_load_variables ( $login, $prefix );
    return true;
  }
  if ( $login == '__public__' || $login == '__default__' ) {
    $GLOBALS[$prefix . 'login'] = $login;
    $GLOBALS[$prefix . 'firstname'] = '';
    $GLOBALS[$prefix . 'lastname'] = '';
    $GLOBALS[$prefix . 'is_admin'] = 'N';
    $GLOBALS[$prefix . 'email'] = '';
    $GLOBALS[$prefix . 'fullname'] = ( $login == '__public__'?
      $PUBLIC_ACCESS_FULLNAME : translate ( 'DEFAULT CONFIGURATION' ) );
    $GLOBALS[$prefix . 'password'] = '';
    $GLOBALS[$prefix . 'enabled'] = 'Y';
    return true;
  }
  $sql =
    'SELECT cal_firstname, cal_lastname, cal_is_admin, cal_email, cal_passwd, ' .
    'cal_enabled FROM webcal_user WHERE cal_login = ?';
  $rows = dbi_get_cached_rows ( $sql, [$login] );
  if ( $rows ) {
    $row = $rows[0];
    $GLOBALS[$prefix . 'login'] = $login;
    $GLOBALS[$prefix . 'firstname'] = $row[0];
    $GLOBALS[$prefix . 'lastname'] = $row[1];
    $GLOBALS[$prefix . 'is_admin'] = $row[2];
    $GLOBALS[$prefix . 'email'] = empty ( $row[3] ) ? '' : $row[3];
    if ( strlen ( $row[0] ) && strlen ( $row[1] ) )
      $GLOBALS[$prefix . 'fullname'] = "$row[0] $row[1]";
    else
      $GLOBALS[$prefix . 'fullname'] = $login;
    $GLOBALS[$prefix . 'password'] = $row[4];
    $GLOBALS[$prefix . 'enabled'] = $row[5];
    $ret = true;
  } else {
    return false;
  }
  //save these results
  $cached_user_var[$login][$prefix] = $ret;
  return $ret;
}

/**
 * Add a new user.
 *
 * @param string $user      User login
 * @param string $password  User password
 * @param string $firstname User first name
 * @param string $lastname  User last name
 * @param string $email     User email address
 * @param string $admin     Is the user an administrator? ('Y' or 'N')
 * @param string $enabled   Is the user account enabled? ('Y' or 'N')
 *
 * @return bool True on success
 *
 * @global string Error message
 */
function user_add_user ( $user, $password, $firstname,
  $lastname, $email, $admin, $enabled='Y' ) {
  global $error;

  if ( $user == '__public__' ) {
    $error = translate ( 'Invalid user login', true);
    return false;
  }

  if ( strlen ( $email ) )
    $uemail = $email;
  else
    $uemail = NULL;
  if ( strlen ( $firstname ) )
    $ufirstname = $firstname;
  else
    $ufirstname = NULL;
  if ( strlen ( $lastname ) )
    $ulastname = $lastname;
  else
    $ulastname = NULL;
  if ( strlen ( $password ) )
    $upassword = password_hash ( $password, PASSWORD_DEFAULT );
  else
    $upassword = NULL;
  if ( $admin != 'Y' )
    $admin = 'N';
  $sql = 'INSERT INTO webcal_user ' .
    '( cal_login, cal_lastname, cal_firstname, ' .
    'cal_is_admin, cal_passwd, cal_email, cal_enabled ) ' .
    'VALUES ( ?, ?, ?, ?, ?, ?, ? )';
  if ( ! dbi_execute ( $sql, [$user, $ulastname,
    $ufirstname, $admin, $upassword, $uemail, $enabled] ) ) {
    $error = db_error();
    return false;
  }
  return true;
}

/**
 * Update a user.
 *
 * @param string $user      User login
 * @param string $firstname User first name
 * @param string $lastname  User last name
 * @param string $mail      User email address
 * @param string $admin     Is the user an administrator? ('Y' or 'N')
 * @param string $enabled   Is the user account enabled? ('Y' or 'N')
 *
 * @return bool True on success
 *
 * @global string Error message
 */
function user_update_user ( $user, $firstname, $lastname, $email, $admin, $enabled='Y' ) {
  global $error;

  if ( $user == '__public__' ) {
    $error = translate ( 'Invalid user login' );
    return false;
  }
  if ( strlen ( $email ) )
    $uemail = $email;
  else
    $uemail = NULL;
  if ( strlen ( $firstname ) )
    $ufirstname = $firstname;
  else
    $ufirstname = NULL;
  if ( strlen ( $lastname ) )
    $ulastname = $lastname;
  else
    $ulastname = NULL;
  if ( $admin != 'Y' )
    $admin = 'N';

  if ( $enabled != 'Y' )
    $enabled = 'N';

  $sql = 'UPDATE webcal_user SET cal_lastname = ?, ' .
    'cal_firstname = ?, cal_email = ?,' .
    'cal_is_admin = ?, cal_enabled = ? WHERE cal_login = ?';
  if ( ! dbi_execute ( $sql,
    [$ulastname, $ufirstname, $uemail, $admin, $enabled, $user] ) ) {
    $error = db_error();
    return false;
  }
  return true;
}

/**
 * Update user password.
 *
 * @param string $user     User login
 * @param string $password User password
 *
 * @return bool True on success
 *
 * @global string Error message
 */
function user_update_user_password ( $user, $password ) {
  global $error;

  $sql = 'UPDATE webcal_user SET cal_passwd = ? WHERE cal_login = ?';
  if ( ! dbi_execute ( $sql, [password_hash ( $password , PASSWORD_DEFAULT ), $user] ) ) {
    $error = db_error();
    return false;
  }
  return true;
}

/**
 * Delete a user from the system.
 *
 * This will also delete any of the user's events in the system that have
 * no other participants. Any layers that point to this user
 * will be deleted. Any views that include this user will be updated.
 *
 * @param string $user User to delete
 */
function user_delete_user ( $user ) {
  // Get event ids for all events this user is a participant
  $events = get_users_event_ids ( $user );

  // Now count number of participants in each event...
  // If just 1, then save id to be deleted
  $delete_em = [];
  $evcnt = count ( $events );
  for ( $i = 0; $i < $evcnt; $i++ ) {
    $res = dbi_execute ( 'SELECT COUNT( * )
  FROM webcal_entry_user
  WHERE cal_id = ?', [$events[$i]] );
    if ( $res ) {
      if ( $row = dbi_fetch_row ( $res ) ) {
        if ( $row[0] == 1 )
          $delete_em[] = $events[$i];
      }
      dbi_free_result ( $res );
    }
  }
  $delete_emcnt = count ( $delete_em );
  // Now delete events that were just for this user
  for ( $i = 0; $i < $delete_emcnt; $i++ ) {
    dbi_execute ( 'DELETE FROM webcal_entry_repeats WHERE cal_id = ?',
      [$delete_em[$i]] );
    dbi_execute ( 'DELETE FROM webcal_entry_repeats_not WHERE cal_id = ?',
      [$delete_em[$i]] );
    dbi_execute ( 'DELETE FROM webcal_entry_log WHERE cal_entry_id = ?',
      [$delete_em[$i]] );
    dbi_execute ( 'DELETE FROM webcal_import_data WHERE cal_id = ?',
      [$delete_em[$i]] );
    dbi_execute ( 'DELETE FROM webcal_site_extras WHERE cal_id = ?',
      [$delete_em[$i]] );
    dbi_execute ( 'DELETE FROM webcal_entry_ext_user WHERE cal_id = ?',
      [$delete_em[$i]] );
    dbi_execute ( 'DELETE FROM webcal_reminders WHERE cal_id = ?',
      [$delete_em[$i]] );
    dbi_execute ( 'DELETE FROM webcal_blob WHERE cal_id = ?',
      [$delete_em[$i]] );
    dbi_execute ( 'DELETE FROM webcal_entry WHERE cal_id = ?',
      [$delete_em[$i]] );
  }

  // Delete user participation from events
  dbi_execute ( 'DELETE FROM webcal_entry_user WHERE cal_login = ?',
    [$user] );
  // Delete preferences
  dbi_execute ( 'DELETE FROM webcal_user_pref WHERE cal_login = ?',
    [$user] );
  // Delete from groups
  dbi_execute ( 'DELETE FROM webcal_group_user WHERE cal_login = ?',
    [$user] );
  // Delete bosses & assistants
  dbi_execute ( 'DELETE FROM webcal_asst WHERE cal_boss = ?',
    [$user] );
  dbi_execute ( 'DELETE FROM webcal_asst WHERE cal_assistant = ?',
    [$user] );
  // Delete user's views
  $delete_em = [];
  $res = dbi_execute ( 'SELECT cal_view_id FROM webcal_view WHERE cal_owner = ?',
    [$user] );
  if ( $res ) {
    while ( $row = dbi_fetch_row ( $res ) ) {
      $delete_em[] = $row[0];
    }
    dbi_free_result ( $res );
  }
  $delete_emcnt = count ( $delete_em );
  for ( $i = 0; $i < $delete_emcnt; $i++ ) {
    dbi_execute ( 'DELETE FROM webcal_view_user WHERE cal_view_id = ?',
      [$delete_em[$i]] );
  }
  dbi_execute ( 'DELETE FROM webcal_view WHERE cal_owner = ?',
    [$user] );
  //Delete them from any other user's views
  dbi_execute ( 'DELETE FROM webcal_view_user WHERE cal_login = ?',
    [$user] );
  // Delete layers
  dbi_execute ( 'DELETE FROM webcal_user_layers WHERE cal_login = ?',
    [$user] );
  // Delete any layers other users may have that point to this user.
  dbi_execute ( 'DELETE FROM webcal_user_layers WHERE cal_layeruser = ?',
    [$user] );
  // Delete user
  dbi_execute ( 'DELETE FROM webcal_user WHERE cal_login = ?',
    [$user] );
  // Delete function access
  dbi_execute ( 'DELETE FROM webcal_access_function WHERE cal_login = ?',
    [$user] );
  // Delete user access
  dbi_execute ( 'DELETE FROM webcal_access_user WHERE cal_login = ?',
    [$user] );
  dbi_execute ( 'DELETE FROM webcal_access_user WHERE cal_other_user = ?',
    [$user] );
  // Delete user's categories
  dbi_execute ( 'DELETE FROM webcal_categories WHERE cat_owner = ?',
    [$user] );
  dbi_execute ( 'DELETE FROM webcal_entry_categories WHERE cat_owner = ?',
    [$user] );
  // Delete user's reports
  $delete_em = [];
  $res = dbi_execute ( 'SELECT cal_report_id FROM webcal_report WHERE cal_login = ?',
    [$user] );
  if ( $res ) {
    while ( $row = dbi_fetch_row ( $res ) ) {
      $delete_em[] = $row[0];
    }
    dbi_free_result ( $res );
  }
  $delete_emcnt = count ( $delete_em );
  for ( $i = 0; $i < $delete_emcnt; $i++ ) {
    dbi_execute ( 'DELETE FROM webcal_report_template WHERE cal_report_id = ?',
      [$delete_em[$i]] );
  }
  dbi_execute ( 'DELETE FROM webcal_report WHERE cal_login = ?',
    [$user] );
    //not sure about this one???
  dbi_execute ( 'DELETE FROM webcal_report WHERE cal_user = ?',
    [$user] );
  // Delete user templates
  dbi_execute ( 'DELETE FROM webcal_user_template WHERE cal_login = ?',
    [$user] );
}

/**
 * Get a list of users and return info in an array.
 *
 * @param bool  $publicOnly  return only public data
 * @return array Array of user info
 */
function user_get_users ( $publicOnly=false ) {
  global $PUBLIC_ACCESS, $PUBLIC_ACCESS_FULLNAME,
  $USER_SORT_ORDER;

  $count = 0;
  $ret = [];
  if ( $PUBLIC_ACCESS == 'Y' )
    $ret[$count++] = [
       'cal_login' => '__public__',
       'cal_lastname' => '',
       'cal_firstname' => '',
       'cal_is_admin' => 'N',
       'cal_email' => '',
       'cal_password' => '',
       'cal_fullname' => $PUBLIC_ACCESS_FULLNAME];
  if ( $publicOnly ) return $ret;

  $order1 = empty ( $USER_SORT_ORDER ) ?
    'cal_lastname, cal_firstname,' : "$USER_SORT_ORDER,";
  $res = dbi_execute ( 'SELECT cal_login, cal_lastname, cal_firstname, ' .
    'cal_is_admin, cal_email, cal_passwd FROM webcal_user ' .
    "ORDER BY $order1 cal_login" );
  if ( $res ) {
    while ( $row = dbi_fetch_row ( $res ) ) {
      if ( strlen ( $row[1] ) && strlen ( $row[2] ) )
        $fullname = "$row[2] $row[1]";
      else
        $fullname = $row[0];
      $ret[$count++] = [
        'cal_login' => $row[0],
        'cal_lastname' => $row[1],
        'cal_firstname' => $row[2],
        'cal_is_admin' => $row[3],
        'cal_email' => empty ( $row[4] ) ? '' : $row[4],
        'cal_password' => $row[5],
        'cal_fullname' => $fullname];
    }
    dbi_free_result ( $res );
  }
  // No need to call sort_users() as the SQL can sort for us.
  return $ret;
}

function user_delete_events($username)
{
  // Get event ids for all events this user is a participant.
  $events = get_users_event_ids($username);

  // Now count number of participants in each event...
  // If just 1, then save id to be deleted.
  $delete_em = [];
  for ($i = 0, $cnt = count($events); $i < $cnt; $i++) {
    $res = dbi_execute('SELECT COUNT( * ) FROM webcal_entry_user ' .
      'WHERE cal_id = ?', [$events[$i]]);
    if ($res) {
      $row = dbi_fetch_row($res);
      if (!empty($row) && $row[0] == 1)
        $delete_em[] = $events[$i];
      dbi_free_result($res);
    }
  }
  // Now delete events that were just for this user.
  for ($i = 0, $cnt = count($delete_em); $i < $cnt; $i++) {
    dbi_execute(
      'DELETE FROM webcal_entry_repeats WHERE cal_id = ?',
      [$delete_em[$i]]
    );
    dbi_execute(
      'DELETE FROM webcal_entry_repeats_not WHERE cal_id = ?',
      [$delete_em[$i]]
    );
    dbi_execute(
      'DELETE FROM webcal_entry_log WHERE cal_entry_id = ?',
      [$delete_em[$i]]
    );
    dbi_execute(
      'DELETE FROM webcal_import_data WHERE cal_id = ?',
      [$delete_em[$i]]
    );
    dbi_execute(
      'DELETE FROM webcal_site_extras WHERE cal_id = ?',
      [$delete_em[$i]]
    );
    dbi_execute(
      'DELETE FROM webcal_entry_ext_user WHERE cal_id = ?',
      [$delete_em[$i]]
    );
    dbi_execute(
      'DELETE FROM webcal_reminders WHERE cal_id =? ',
      [$delete_em[$i]]
    );
    dbi_execute(
      'DELETE FROM webcal_blob WHERE cal_id = ?',
      [$delete_em[$i]]
    );
    dbi_execute(
      'DELETE FROM webcal_entry WHERE cal_id = ?',
      [$delete_em[$i]]
    );
  }
  // Delete user participation from events.
  dbi_execute(
    'DELETE FROM webcal_entry_user WHERE cal_login = ?',
    [$username]
  );

  return count($delete_em);
}
?>

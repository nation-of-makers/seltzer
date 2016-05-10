<?php
/*
    Copyright 2014 Edward L. Platt <ed@elplatt.com>
    
    This file is part of the Seltzer CRM Project
    template.inc.php - Template for contributed modules

    Seltzer is free software: you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation, either version 3 of the License, or
    any later version.

    Seltzer is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with Seltzer.  If not, see <http://www.gnu.org/licenses/>.
*/

// Installation functions //////////////////////////////////////////////////////

/**
 * @return This module's revision number.  Each new release should increment
 * this number.
 */
function service_slack_revision () {
    return 1;
}

// Utility functions ///////////////////////////////////////////////////////////

function service_slack_getSlackID ($email, $username) {
   // users.list to get a full dump of users since we cannot search by email
    
    // var_dump_pre('crm username',$username);
    $slackUsersList = 'https://i3detroit.slack.com/api/users.list';
    $data = array('token' => variable_get('slack_token',''));
    // use key 'http' even if you send the request to https://...
    $http_options = array(
        'http' => array(
            'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
            'method'  => 'POST',
            'content' => http_build_query($data),
        ),
    );
    $context  = stream_context_create($http_options);
    $http_result = file_get_contents($slackUsersList, false, $context);
    $slackUsers = json_decode($http_result,true);
    // extract user from array if they exist
    $slackID = '';
    $slackName = '';
    $slackStatus = '';
    $arr_length = count($slackUsers['members']);
    for( $i = 0; $i < $arr_length; $i++) {
        $checkStatus = false;
        if (array_key_exists('email', $slackUsers['members'][$i]['profile']) &&
         $slackUsers['members'][$i]['profile']['email'] == $email) {
            $slackID = $slackUsers['members'][$i]['id'];
            $slackName = $slackUsers['members'][$i]['name'];
            $checkStatus = true;
        } elseif (array_key_exists('name', $slackUsers['members'][$i]) &&
         $slackUsers['members'][$i]['name'] == null) {
            $slackID = $slackUsers['members'][$i]['id'];
            $slackName = $slackUsers['members'][$i]['name'];
            $checkStatus = true;
        }

        if ($checkStatus) {
            // var_dump_pre("slackUsers['members'][$i]['deleted']",$slackUsers['members'][$i]['deleted']);
            if ($slackUsers['members'][$i]['deleted'] == true) {
                $slackStatus = 'Disabled';
            } else {
                $slackStatus = 'Active';
            }
        }
    }
    return [$slackID,$slackName,$slackStatus];
}


// DB to Object mapping ////////////////////////////////////////////////////////


// Tables //////////////////////////////////////////////////////////////////////
// Put table generators here
/**
 * Return a table structure for a table of service links.
 *
 * @param $opts The options to pass to service_data().
 * @return The table structure.
*/
function service_slack_addrow ($opts) {
    // var_dump_pre('addrow_opts', $opts);
    // Get contact info
    $contacts = crm_get_one('contact', array('cid'=> $opts['cid']));
    // var_dump_pre("contacts['user']['username']:",$contacts['user']['username']);
    list($slackID, $slackName, $slackStatus) = service_slack_getSlackID ($contacts['email'], $contacts['user']['username']);
    // var_dump_pre('$slackID, $slackName, $slackStatus',$slackID,$slackName, $slackStatus);
    if (user_access('services_view') || user_access('services_edit') || $opts['cid'] == user_id()) {
        // Service Name
        $row['serviceName'] = 'Slack';
        // Slack username
        $row['userName'] = $slackName;
        // Slack ID
        $row['userID'] = $slackID;
        // Service Status
        $row['userStatus'] = $slackStatus;
        // Service functions
        if ((user_access('services_edit') || user_access('services_delete')) || $opts['cid'] == user_id()) {
            // Construct ops array
            $ops = array();
            // Add edit op based on status
            switch ($slackStatus) {
                case 'Active':
                    if ((user_access('services_edit') || $opts['cid'] == user_id())) {
                        $ops[] = '<form action="https://i3detroit.slack.com/api/users.admin.setInactive" method="post">
                         <input type="hidden" name="token" value=' . variable_get('slack_token','') . '>
                         <input type="hidden" name="user" value='.$slackID.'>
                         <input type="hidden" name="set_active" value="true">
                         <input type="submit" value="Disable">';
                    }
                    break;
                case 'Disabled':
                    if ((user_access('services_delete') || $opts['cid'] == user_id())) {
                        $ops[] = '<form action="https://i3detroit.slack.com/api/users.admin.setRegular" method="post">
                         <input type="hidden" name="token" value=' . variable_get('slack_token','') . '>
                         <input type="hidden" name="user" value='.$slackID.'>
                         <input type="hidden" name="set_active" value="true">
                         <input type="submit" value="Enable">';
                    }
                    break;
                default:
                    if ((user_access('services_edit') || $opts['cid'] == user_id())) {
                        $ops[] = '<form action="https://i3detroit.slack.com/api/users.admin.invite" method="post">
                         <input type="hidden" name="token" value=' . variable_get('slack_token','') . '>
                         <input type="hidden" name="email" value="' . $contacts['email'] . '">
                         <input type="hidden" name="first_name" value="' . $contacts['firstName'] . '">
                         <input type="hidden" name="last_name" value="' . $contacts['lastName'] . '">
                         <input type="hidden" name="set_active" value="true">
                         <input type="submit" value="Invite">';
                    }
            }
            // Add ops row
            $row['ops'] = join(' ', $ops);
        }
    }
    return $row;
}

// Forms ///////////////////////////////////////////////////////////////////////
// Put form generators here

// Themeing ////////////////////////////////////////////////////////////////////

/**
 * Return themed html for a nickname.
 */
// function theme_services_nickname ($cid) {
//     $contact = crm_get_one('contact', array('cid'=>$cid));
//     return '<h3>Nickname</h3><p>' . services_nickname($contact) . '</p>';
// }

// Pages ///////////////////////////////////////////////////////////////////////

/**
 * @return An array of pages provided by this module.
 */

/**
 * Page hook.  Adds module content to a page before it is rendered.
 *
 * @param &$page_data Reference to data about the page being rendered.
 * @param $page_name The name of the page being rendered.
 * @param $options The array of options passed to theme('page').
*/

// Request Handlers ////////////////////////////////////////////////////////////

?>
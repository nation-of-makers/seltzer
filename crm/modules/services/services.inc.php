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
function services_revision () {
    return 1;
}

/**
 * @return An array of the permissions provided by this module.
 */
function services_permissions () {
    return array(
        'services_view'
        , 'services_edit'
        , 'services_delete'
    );
}

/**
 * Install or upgrade this module.
 * @param $old_revision The last installed revision of this module, or 0 if the
 *   module has never been installed.
 */
function services_install($old_revision = 0) {
    if ($old_revision < 1) {
        // Create databases here
        $roles = array(
            '1' => 'authenticated'
            , '2' => 'member'
            , '3' => 'director'
            , '4' => 'president'
            , '5' => 'vp'
            , '6' => 'secretary'
            , '7' => 'treasurer'
            , '8' => 'webAdmin'
        );
        $default_perms = array(
            'director' => array('services_view', 'services_edit', 'services_delete')
        );
        foreach ($roles as $rid => $role) {
            $esc_rid = mysql_real_escape_string($rid);
            if (array_key_exists($role, $default_perms)) {
                foreach ($default_perms[$role] as $perm) {
                    $esc_perm = mysql_real_escape_string($perm);
                    $sql = "INSERT INTO `role_permission` (`rid`, `permission`) VALUES ('$esc_rid', '$esc_perm')";
                    $res = mysql_query($sql);
                    if (!$res) die(mysql_error());
                }
            }
        }
    }
}

// Utility functions ///////////////////////////////////////////////////////////

function services_getSlackID ($email) {
    // users.list to get a full dump of users since we cannot search by email
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
    //var_dump_pre($slackUsers);
    $slackID = '';
    $arr_length = count($slackUsers['members']);
    for( $i = 0; $i < $arr_length; $i++) {
        if (array_key_exists('email', $slackUsers['members'][$i]['profile']) &&
         $slackUsers['members'][$i]['profile']['email'] == $email) {
            $slackID = $slackUsers['members'][$i]['id'];
        }
    }
    return $slackID;
}

function var_dump_pre($mixed = null) {
    echo '<pre>';
    var_dump($mixed);
    echo '</pre>';
    return true;
}



// DB to Object mapping ////////////////////////////////////////////////////////

/**
 * Implementation of hook_data_alter().
 * @param $type The type of the data being altered.
 * @param $data An array of structures of the given $type.
 * @param $opts An associative array of options.
 * @return An array of modified structures.
 */
// function services_data_alter ($type, $data = array(), $opts = array()) {
//     switch ($type) {
//         case 'contact':
//             foreach ($data as $i => $contact) {
//                 $data[$i]['nickname'] = services_nickname($data[$i]);
//             }
//             break;
//     }
//     return $data;
// }

// Tables //////////////////////////////////////////////////////////////////////
// Put table generators here
/**
 * Return a table structure for a table of service links.
 *
 * @param $opts The options to pass to service_data().
 * @return The table structure.
*/
function services_table ($opts) {

    // Determine settings
    $export = false;
    foreach ($opts as $option => $value) {
        switch ($option) {
            case 'export':
                $export = $value;
                break;
        }
    }
    // Get contact info
    $contacts = crm_get_one('contact', $opts);
    
    // Initialize table
    $table = array(
        "id" => '',
        "class" => '',
        "rows" => array(),
        "columns" => array()
    );

    // Add columns
    if (user_access('services_view') || $opts['cid'] == user_id()) {
        if ($export) {
            $table['columns'][] = array("title"=>'cid', 'class'=>'', 'id'=>'');
        }
        $table['columns'][] = array("title"=>'Service', 'class'=>'', 'id'=>'');
        $table['columns'][] = array("title"=>'ServiceID', 'class'=>'', 'id'=>'');
    }

    // Add ops column
    if (!$export && (user_access('services_edit') || user_access('services_delete') || $opts['cid'] == user_id())) {
        $table['columns'][] = array('title'=>'Ops','class'=>'');
    }

    // Add rows
    // Slack info
    $row = array();
    $slackID = services_getSlackID($contacts['email']);
    if (user_access('services_view') || user_access('services_edit') || $opts['cid'] == user_id()) {
        // Add cells
        if ($export) {
            $row[] = $contacts['cid'];
        }
        $row[] = 'Slack';
        $row[] = $slackID;
        if (!$export && (user_access('services_edit') || user_access('services_delete')) || $opts['cid'] == user_id()) {
            // Construct ops array
            $ops = array();
            // Add edit op
            if ((user_access('services_edit') || $opts['cid'] == user_id())) {
                $ops[] = '<form action="https://i3detroit.slack.com/api/users.admin.invite" method="post">
                 <input type="hidden" name="token" value=' . variable_get('slack_token','') . '>
                 <input type="hidden" name="email" value="' . $contacts['email'] . '">
                 <input type="hidden" name="first_name" value="' . $contacts['firstName'] . '">
                 <input type="hidden" name="last_name" value="' . $contacts['lastName'] . '">
                 <input type="hidden" name="set_active" value="true">
                 <input type="submit" value="Invite">';
            }
            // There's no delete function in API yet, so can't show a delete button
            // Add ops row
            $row[] = join(' ', $ops);
        }
        $table['rows'][] = $row;
    }
    // End Slack
    
    // Wiki Account
    // End Wiki
    return $table;
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
function services_page_list () {
    $pages = array();
//    if (user_access('key_view')) { // add access controls?:w
        $pages[] = 'services';
    return $pages;
}

/**
 * Page hook.  Adds module content to a page before it is rendered.
 *
 * @param &$page_data Reference to data about the page being rendered.
 * @param $page_name The name of the page being rendered.
 * @param $options The array of options passed to theme('page').
*/
function services_page (&$page_data, $page_name, $options) {
    switch ($page_name) {
        case 'contact':
            // Capture contact cid
            $cid = $options['cid'];
            if (empty($cid)) {
                return;
            }
            // // Add nickname tab
            // $nickname = theme('services_nickname', $cid);
            // page_add_content_bottom($page_data, "blah!", 'View');
            // break;
            // Add keys tab
            if (user_access('services_view') || user_access('services_edit') || user_access('services_delete') || $cid == user_id()) {
                $services = theme('table', 'services', array('cid' => $cid));
                $services .= theme('services_form', $cid);
                page_add_content_bottom($page_data, $services, 'Services');
            }
    }
}

// Request Handlers ////////////////////////////////////////////////////////////

?>

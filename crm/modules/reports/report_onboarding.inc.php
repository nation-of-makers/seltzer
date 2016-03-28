<?php

/*
    Copyright 2009-2014 Edward L. Platt <ed@elplatt.com>
    
    This file is part of the Seltzer CRM Project
    report_onboarding.inc.php - Show members in the "onboarding" plan
    Part of the Reports module

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
// No install as this is called by reports module

// Utility functions ///////////////////////////////////////////////////////////
/*
 * Set the page content based on report name. Used for autoinclude
 */
$report_onboarding_theme = 'table';
$report_onboarding_theme_opts = 'onboarding';
$report_onboarding_name = "Contact onboarding";
$report_onboarding_desc = "List of contacts in onboarding process";

/**
 * @return A comma-separated list of user emails.
 * @param $opts - Options to pass to member_data().
 */
function get_cids_onboarding () {
    $result = array();

    // Query contacts who are on the Onboarding plan
    $sql = "
        SELECT cid FROM contact
        WHERE EXISTS (SELECT * FROM membership 
            WHERE membership.pid = '13' 
            AND membership.end IS NULL 
            AND contact.cid=membership.cid
        );";
    
    $res = mysql_query($sql);
    if (!$res) { crm_error(mysql_error($res)); }
   
    $cids=array();
    while ($row = mysql_fetch_row($res)) $cids[]=$row[0];
    mysql_free_result($res);

    return $cids;
}

// Tables ///////////////////////////////////////////////////////////////////////
function onboarding_table () {
    // Determine settings
    $export = false;
    // foreach ($opts as $option => $value) {
    //     switch ($option) {
    //         case 'export':
    //             $export = $value;
    //             break;
    //     }
    // }
    
    $onboarding_cids = get_cids_onboarding();

     // Initialize table
    $table = array(
        'columns' => array(
            array('title' => 'Name')
        )
        , 'rows' => array()
    );

    // Add rows
    foreach ($onboarding_cids as $cid) {
        // Add secrets data
        $row = array();
        // Get info on member
        $data = member_data(array('cid'=>$cid));
        $member = $data[0];
        $contact = $member['contact'];
        $name = theme('contact_name', $contact['cid'], true);
       // $name_link = theme('contact_name', $member, true);
   
        $row[] = $name;

        $table['rows'][] = $row;  

    }   
    // Return table
    return $table;
}


// Themeing ////////////////////////////////////////////////////////////////////

// Pages ///////////////////////////////////////////////////////////////////////
// No pages

<?php

/*
    Copyright 2009-2014 Edward L. Platt <ed@elplatt.com>
    Copyright 2013-2014 Chris Murray <chris.f.murray@hotmail.co.uk>

    This file is part of the Seltzer CRM Project
    ldap.inc.php - LDAP interface module

    This module will allow the creation, editing & deletion of user accounts
    on a hackerspace's LDAP server when the accounts are added, edited or
    deleted on seltzer

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

/**
 * @return This module's revision number.  Each new release should increment
 * this number.
 */
function ldap_revision () {
    return 1;
}

/**
 * Install or upgrade this module.
 * @param $old_revision The last installed revision of this module, or 0 if the
 *   module has never been installed.
 */
function ldap_install($old_revision = 0) {
    if ($old_revision < 1) {
        // There is nothing to install. Do nothing
    }
}

/**
 * LDAP Base DN
 */
function ldapbasedn() {
    global $ldapbasedn;
    return $ldapbasedn;
}

/**
 * connect to LDAP server
 */
function ldapconn() {
    global $ldaphost;
    global $ldapport;
    ldap_set_option($ldapconn, LDAP_OPT_PROTOCOL_VERSION, 3);
    $ldapconn = ldap_connect($ldaphost, $ldapport)
          or die(error_register("Could not connect to LDAP server " . ldaphost() . "."));
    return $ldapconn;
}

/**
 * binding to LDAP server
 */
function ldapbind() {
    global $ldapuser;
    global $ldappass;
    $ldapbind = ldap_bind(ldapconn(), $ldapuser, $ldappass);
    return $ldapbind;
}

/**
 * Update ldap data when a contact is updated.
 * @param $contact The contact data array.
 * @param $op The operation being performed.
 */
function ldap_contact_api ($contact, $op) {
    switch ($op) {
        case 'create':
            //ldap_user_save ($contact);
            break;
        case 'update':
            //ldap_user_save ($contact);
            break;
        case 'delete':
            //ldap_user_delete ($esc_cid);
            break;
    }
    return $contact;
}

/**
 * Update ldap data when a plan is updated.
 * @param $plan The plan data array.
 * @param $op The operation being performed.
 */
function ldap_plan_api ($plan, $op) {
    switch ($op) {
        case 'create':
            //ldap_group_save ($plan);
            break;
        case 'update':
            //ldap_group_save ($plan);
            break;
        case 'delete':
            //ldap_group_delete ($plan);
            break;
    }
    return $plan;
}

/**
 * Update ldap data when a membership is updated.
 * @param $membership The membership data array.
 * @param $op The operation being performed.
 */
function ldap_membership_api ($membership, $op) {
    switch ($op) {
        case 'add':
            //ldap_group_user_add ($membership);
            break;
        case 'update':
            //ldap_group_user_add ($membership);
            break;
        case 'delete':
            //ldap_group_user_remove ($membership);
            break;
    }
    return $membership;
}

/**
 * save a user to the LDAP directory
 */
function ldap_user_save ($contact) {
    
    if (ldapbind()) {
        message_register('LDAP bind successful...');
        
        // prepare data
        $username = $contact['user']['username'];
        $info["objectClass"][0] = "inetOrgPerson";
        $info["objectClass"][1] = "posixAccount";
        //$info["objectClass"][2] = "radiusprofile";
        //$info["dialupAccess"] = "access_attr";
        $info["dn"] = "cn=$username,OU=Users," . ldapbasedn();
        $info["cn"] = $username;
        $info["givenName"] = $contact['firstName'];
        $info["sn"] = $contact['lastName'];
        $info["mail"] = $contact'email'];
        $info["mobile"] = $contact['phone'];
        
        // add data to directory
        ldap_add(ldapconn(), $info["dn"], $info);
        message_register('LDAP add successful...');
        
        ldap_close(ldapconn());
        } else {
            error_register('LDAP bind failed...');
    }
}

/**
 * save a group to the LDAP directory
 */
function ldap_group_save ($plan) {
    
    if (ldapbind()) {
        message_register('LDAP bind successful...');
        
        // prepare data
        
        $planName = $plan['name'];
        //$gid = "1000" . $plan['pid'];
        //$info["objectClass"][0] = "posixGroup";
        $info["objectClass"][0] = "groupOfNames";
        $info["dn"] = "cn=$planName,OU=Groups," . ldapbasedn();
        $info["cn"] = $planName;
        //$info["gidNumber"] = $gid;
        $info["description"] = $planName;
        
        //print_r($info);
        //die();
        
        // add data to directory
        ldap_add(ldapconn(), $info["dn"], $info);
        message_register('LDAP add successful...');
        
        ldap_close(ldapconn());
        } else {
            error_register('LDAP bind failed...');
    }
}

/**
 * add a user to a group in the LDAP directory
 */
function ldap_group_user_add ($membership){
    
    if (ldapbind()) {
        message_register('LDAP bind successful...');
        
        $contact_data = crm_get_data('contact', array('cid'=>$membership['cid']));
        $contact = $contact_data[0];
        $username = $contact['user']['username'];
        $ldapUser = "CN=$username,OU=Users," . ldapbasedn();
        
        $plan_data = crm_get_data('member_plan', array('pid'=>$membership['pid']));
        $plan = $plan_data[0];
        $planName = $plan['name'];
        $group_name = "CN=$planName,OU=Groups," . ldapbasedn();
        
        $group_info['member'] = $ldapUser; // User's DN is added to group's 'member' array
        
        ldap_mod_add($ldapconn,$group_name,$group_info);
        message_register('LDAP add successful...');
        
        ldap_close(ldapconn());
        } else {
            error_register('LDAP bind failed...');
    }
}

/**
 * delete a user from the LDAP directory
 */
function ldap_user_delete ($cid){
    
    if (ldapbind()) {
        message_register('LDAP bind successful...');
        $contact_data = crm_get_data('contact', array('cid'=>$cid));
        $contact = $contact_data[0];
        $username = $contact['user']['username'];
        $ldapUser = "CN=$username,OU=Users," . ldapbasedn();
        ldap_delete($ldapconn,$ldapUser);
        message_register('LDAP user remove successful...');
        ldap_close(ldapconn());
        } else {
            error_register('LDAP bind failed...');
    }
}

/**
 * delete a group from the LDAP directory
 */
function ldap_group_delete ($plan){
    
    if (ldapbind()) {
        message_register('LDAP bind successful...');
        $planName = $plan['name'];
        $group_name = "CN=$planName,OU=Groups," . ldapbasedn();
        ldap_delete($ldapconn,$group_name);
        message_register('LDAP group remove successful...');
        
        ldap_close(ldapconn());
        } else {
            error_register('LDAP bind failed...');
    }
}

/**
 * delete a user from a group in the LDAP directory
 */
function ldap_group_user_delete ($membership){
    
    if (ldapbind()) {
        message_register('LDAP bind successful...');
        
        $contact_data = crm_get_data('contact', array('cid'=>$membership['cid']));
        $contact = $contact_data[0];
        $username = $contact['user']['username'];
        $ldapUser = "CN=$username,OU=Users," . ldapbasedn();
        
        $plan_data = crm_get_data('member_plan', array('pid'=>$membership['pid']));
        $plan = $plan_data[0];
        $planName = $plan['name'];
        $group_name = "CN=$planName,OU=Groups," . ldapbasedn();
        
        $group_info['member'] = $ldapUser; // User's DN is added to group's 'member' array
        
        ldap_mod_del($ldapconn, $group_name, $group_info);
        message_register('LDAP user remove from group successful...');
        
        ldap_close(ldapconn());
        } else {
            error_register('LDAP bind failed...');
    }
}


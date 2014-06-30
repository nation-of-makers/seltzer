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

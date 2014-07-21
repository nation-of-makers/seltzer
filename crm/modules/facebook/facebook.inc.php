<?php

/*
    Copyright 2009-2014 Edward L. Platt <ed@elplatt.com>
    Copyright 2014 Chris Murray <chris.f.murray@hotmail.co.uk>

    This file is part of the Seltzer CRM Project
    facebook.inc.php - Facebook interface module

    This module will allow users to login to Seltzer with Facebook & automagically create a user account for them

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
function facebook_revision () {
    return 1;
}

/**
 * Install or upgrade this module.
 * @param $old_revision The last installed revision of this module, or 0 if the
 *   module has never been installed.
 */
function facebook_install($old_revision = 0) {
    if ($old_revision < 1) {
        // There is nothing to install. Do nothing
    }
}

/**
 * Retrieves facebook app credentials from config.inc.php
 */
function facebook_app_id () {
    global $facebook_app_id;
    return $facebook_app_id;
}

function facebook_app_secret () {
    global $facebook_app_secret;
    return $facebook_app_secret;
}

/**
 * Page hook.  Adds module content to a page before it is rendered.
 *
 * @param &$page_data Reference to data about the page being rendered.
 * @param $page_name The name of the page being rendered.
 * @param $options The array of options passed to theme('page').
*/
function facebook_page (&$page_data, $page_name, $options) {
    switch ($page_name) {
        case 'login':
            page_add_content_top($page_data, theme('form', crm_get_form('facebook_login')));
            break;
    }
}

/**
 * @return The themed html string for a facebook login form.
*/
function theme_facebook_login_form () {
    return theme('form', crm_get_form('facebook_login'));
}

/**
 * @return facebook login form structure.
*/
function facebook_login_form () {
    $form = array(
        'type' => 'form',
        'method' => 'post',
        'command' => 'facebook_login',
        'fields' => array(
            array(
                'type' => 'fieldset',
                'label' => 'Log in with Facebook',
                'fields' => array(
                    array(
                        'type' => 'submit',
                        'name' => 'submitted',
                        'value' => 'Log in with Facebook'
                    )
                )
            )
        )
    );
    return $form;
}

/**
 * Handle login request.
 *
 * @return the url to display when complete.
 */
function command_facebook_login () {
    
}
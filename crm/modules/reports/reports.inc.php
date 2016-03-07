<?php

/*
    Copyright 2009-2014 Edward L. Platt <ed@elplatt.com>
    
    This file is part of the Seltzer CRM Project
    reports.inc.php - Independent reports module

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
function reports_revision () {
    return 1;
}

/**
 * @return An array of the permissions provided by this module.
 */
function reports_permissions () {
    return array(
        'reports_view'
        , 'reports_edit'
        , 'reports_delete'
    );
}

/**
 * Install or upgrade this module.
 * @param $old_revision The last installed revision of this module, or 0 if the
 *   module has never been installed.
 */
function reports_install($old_revision = 0) {
    if ($old_revision < 1) {
        // No unique databases needed
                // Set default permissions
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
            'director' => array('reports_view', 'reports_edit', 'reports_delete')
        );
        foreach ($roles as $rid => $role) {
            $esc_rid = mysql_real_escape_string($rid);
            if (array_key_exists($role, $default_perms)) {
                foreach ($default_perms[$role] as $perm) {
                    $esc_perm = mysql_real_escape_string($perm);
                    $sql = "INSERT INTO `role_permission` (`rid`, `permission`) VALUES ('$esc_rid', '$esc_perm')";
                    $sql .= " ON DUPLICATE KEY UPDATE rid=rid";
                    $res = mysql_query($sql);
                    if (!$res) die(mysql_error());
                }
            }
        }
    }
}

// Utility functions ///////////////////////////////////////////////////////////
/**
 * @return A comma-separated list of user emails.
 * @param $opts - Options to pass to member_data().
 */
function member_email_report ($opts) {
    $result = array();
    $data = member_data($opts);
    foreach ($data as $row) {
        $email = trim($row['contact']['email']);
        if (!empty($email)) {
            $result[] = $email;
        }
    }
    return join($result, ', ');
}

/**
 * @return the earliest date of any membership.
 */
function member_membership_earliest_date () {
    $sql = "SELECT `start` FROM `membership` ORDER BY `start` ASC LIMIT 1";
    $res = mysql_query($sql);
    $row = mysql_fetch_assoc($res);
    if (!$row) {
        return '';
    }
    return $row['start'];
}

/**
 * @return json structure containing membership statistics.
 */
function member_statistics () {
    // Get plans and earliest date
    $plans = crm_map(member_plan_data(), 'pid');
    $results = array();
    foreach ($plans as $pid => $plan) {
        $results[$pid] = array();
    }
    $earliest = member_membership_earliest_date();
    if (empty($earliest)) {
        message_register('No membership data available.');
        return '[]';
    }
    // Generate list of months
    $start = 12*(int)date('Y', strtotime($earliest)) + (int)date('m', strtotime($earliest)) - 1;
    $now = 12*(int)date('Y') + (int)date('m') - 1;
    $dates = array();
    for ($months = $start; $months <= $now; $months++) {
        $year = floor($months/12);
        $month = $months % 12 + 1;
        $dates[] = "('$year-$month-01')";
    }
    // Create temporary table with dates
    $sql = "DROP TEMPORARY TABLE IF EXISTS `temp_months`";
    $res = mysql_query($sql);
    if (!$res) { crm_error(mysql_error($res)); }
    $sql = "CREATE TEMPORARY TABLE `temp_months` (`month` date NOT NULL);";
    $res = mysql_query($sql);
    if (!$res) { crm_error(mysql_error($res)); }
    $sql = "INSERT INTO `temp_months` (`month`) VALUES " . implode(',', $dates) . ";";
    $res = mysql_query($sql);
    if (!$res) { crm_error(mysql_error($res)); }
    // Query number of active memberships for each month
    $sql = "
        SELECT
            `plan`.`pid`
            , `plan`.`name`
            , `temp_months`.`month`
            , UNIX_TIMESTAMP(`temp_months`.`month`) AS `month_timestamp`
            , count(`membership`.`sid`) AS `member_count`
        FROM `temp_months`
        JOIN `plan`
        LEFT JOIN `membership`
        ON `membership`.`pid`=`plan`.`pid`
        AND `membership`.`start` <= `month`
        AND (`membership`.`end` IS NULL OR `membership`.`end` > `month`)
        GROUP BY `plan`.`pid`, `month`;
    ";
    $res = mysql_query($sql);
    if (!$res) { crm_error(mysql_error($res)); }
    // Build results
    while ($row = mysql_fetch_assoc($res)) {
        $results[$row['pid']][] = array(
            'x' => (int)$row['month_timestamp']
            , 'label' => $row['month']
            , 'y' => (int)$row['member_count']
        );
    }
    // Convert from associative to indexed
    $indexed = array();
    foreach ($results as $pid => $v) {
        $indexed[] = array(
            'name' => $plans[$pid]['name'] . " ($pid)"
            , 'values' => $v
        );
    }
    return json_encode($indexed);
}

// Forms ///////////////////////////////////////////////////////////////////////
// Put form generators here


// Themeing ////////////////////////////////////////////////////////////////////
/**
 * @return The themed html for an active member email report.
*/
function theme_reports_email ($opts) {
    $output = '<div class="member-email-report">';
    $title = '';
    if (isset($opts['filter']) && isset($opts['filter']['active'])) {
        $title = $opts['filter']['active'] ? 'Active ' : 'Lapsed ';
    }
    $title .= 'Email Report';
    $output .= "<h2>$title</h2>";
    $output .= '<textarea rows="10" cols="80">';
    $output .= member_email_report($opts);
    $output .= '</textarea>';
    $output .= '</div>';
    return $output;
}

/**
 * @return The themed html for a membership report.
 */
function theme_reports_membership () {
    $json = member_statistics();
    $output = <<<EOF
<h2>Membership Report</h2>
<svg id="membership-report" width="960" height="500">
</svg>
<script type="text/javascript">
// Calculate stacking for data
var layers = $json;
var stack = d3.layout.stack()
    .values(function (d) { return d.values; });
layers = stack(layers);
var n = layers.length;
var m = layers[0].values.length;

// Calculate geometry
var chartWidth = 960,
    chartHeight = 500;
var padding = { left:50, bottom:100, top:25, right:50 };
var width = chartWidth - padding.left - padding.right;
var height = chartHeight - padding.bottom - padding.top;

// Create scales
var x = d3.scale.linear()
    .domain([d3.min(layers, function(layer) { return d3.min(layer.values, function(d) { return d.x; }); }), d3.max(layers, function(layer) { return d3.max(layer.values, function(d) { return d.x; }); })])
    .range([0, width]);
var y = d3.scale.linear()
    .domain([0, d3.max(layers, function(layer) { return d3.max(layer.values, function(d) { return d.y0 + d.y; }); })])
    .range([height, 0]);
var color = d3.scale.linear()
    .range(["#aa3", "#aaf"])
    .domain([0, 1]);

var colors = [];
//var roff = Math.round(Math.random()*15);
//var goff = Math.round(Math.random()*15);
//var boff = Math.round(Math.random()*15);
var roff = 12, goff = 7, boff = 8;
console.log([roff, goff, boff]);
for (var i = 0; i < layers.length; i++) {
    var r = ((i+roff) * 3) % 16;
    var g = ((i+goff) * 5) % 16;
    var b = ((i+boff) * 7) % 16;
    colors[i] = '#' + r.toString(16) + g.toString(16) + b.toString(16);
}

// Set up the svg element
var svg = d3.select("#membership-report")
    .attr("width", chartWidth)
    .attr("height", chartHeight);

// Define axes
var yaxis = d3.svg.axis().orient('left').scale(y);
var xlabel = d3.scale.ordinal()
    .domain(layers[0].values.map(function (d) { return d.label; }))
    .rangePoints([0, width]);
var xaxis = d3.svg.axis().orient('bottom').scale(xlabel);

// Draw lines
var chart = svg.append('g')
    .attr('transform', 'translate(' + padding.left + ',' + padding.top + ')');
chart.selectAll('.rule')
    .data(y.ticks(yaxis.ticks()))
    .enter()
    .append('line')
        .attr('class', 'rule')
        .attr('x1', '0').attr('x2', width)
        .attr('y1', '0').attr('y2', '0')
        .attr('transform', function(d) { return 'translate(0,' + y(d) + ')'; })
        .style('stroke', '#eee');
    
// Draw the data
var area = d3.svg.area()
    .x(function(d) { return x(d.x); })
    .y0(function(d) { return y(d.y0); })
    .y1(function(d) { return y(d.y0 + d.y); });
chart.selectAll("path")
    .data(layers)
  .enter().append("path")
    .attr('width', width)
    .attr("d", function (d) { return area(d.values); })
    .style("fill", function(d,i) { return colors[i]; });

// Draw the axes
chart.append('g').attr('id', 'yaxis').attr('class', 'axis').call(yaxis).attr('transform', 'translate(-0.5, 0.5)');
yaxis.orient('right');
chart.append('g').attr('id', 'yaxis').attr('class', 'axis').call(yaxis).attr('transform', 'translate(' + (width-0.5) + ',0.5)');
chart.append('g').attr('id', 'xaxis').attr('class', 'axis').call(xaxis)
    .attr('transform', 'translate(-0.5,' + (height+0.5) + ')')
    .selectAll('text')
        .style('text-anchor', 'end')
        .attr('dy', '-.35em')
        .attr('dx', '-9')
        .attr('transform', 'rotate(-90)');
d3.selectAll('.axis path').attr('fill', 'none').attr('stroke', 'black');

// Draw a legend
var legend = chart.append('g').attr('id', 'legend')
    .selectAll('g').data(layers)
    .enter()
    .append('g')
        .attr('transform', function(d,i) { return 'translate(10,' + ((layers.length - i - 1)*22) + ')'; });
legend.append('rect')
    .attr('width', '20').attr('height', '20')
    .style("fill", function(d,i) { return colors[i]; });
legend.append('text')
    .text(function (d) { return d.name; })
    .attr('transform', 'translate(25, 15)');
</script>
EOF;
    return $output;
}


// Pages ///////////////////////////////////////////////////////////////////////

/**
 * @return An array of pages provided by this module.
 */
function reports_page_list () {
    $pages = array();
    if (user_access('reports_view')) {
        $pages[] = 'reports';
    }
    return $pages;
}

/**
 * Page hook.  Adds module content to a page before it is rendered.
 *
 * @param &$page_data Reference to data about the page being rendered.
 * @param $page_name The name of the page being rendered.
 * @param $options The array of options passed to theme('page').
*/
function reports_page (&$page_data, $page_name, $options) {
   // Set page title
    page_set_title($page_data, 'Reports');
    
    // Add view, add and import tabs
    if (user_access('reports_view')) {
        page_add_content_top($page_data, theme('reports_membership', 'membership'), 'Membership');
        page_add_content_top($page_data, theme('reports_email', 'email'), 'Email');
     }
    break;
}

<?php

/*
    Copyright 2009-2014 Edward L. Platt <ed@elplatt.com>
    
    This file is part of the Seltzer CRM Project
    report_membership.inc.php - Membership graph report
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
$report_membership_theme = 'report_membership';
$report_membership_theme_opts = '';
$report_membership_name = "Membership Status";
$report_membership_desc = "Graphical history of memberships";

/**
 * @return the earliest date of any membership.
 */
function report_membership_earliest_date () {
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
    $earliest = report_membership_earliest_date();
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
    // reorder so that plans are in the order we want
    // this is some fuck-ugly code, but it works
    $p1 = array_splice($indexed, 5, 1);
    $p2 = array_splice($indexed, 0, 7);
    $indexed = array_merge($p2,$p1,$indexed);

    return json_encode($indexed);
}

// Forms ///////////////////////////////////////////////////////////////////////
// Put form generators here


// Themeing ////////////////////////////////////////////////////////////////////
/**
 * @return The themed html for a membership report.
 */
function theme_report_membership () {
    $json = member_statistics();
    $output = <<<EOF
<h2>Membership Report</h2>
<svg id="membership-report" width="960" height="500">
</svg>
<script type="text/javascript">
// Calculate stacking for data
var layers = $json;
var stack = d3.layout.stack()
    .values(function (d) { return d.values; })
    .order("reverse");
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
// No pages

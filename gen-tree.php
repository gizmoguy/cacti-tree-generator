#!/usr/bin/php -q
<?php

/**
 @Author:	Brad Cowie <brad@gizmoguy.net.nz>
 @Description: 	Generates a subtree in cacti for a WAND server

 We want to generate a new tree for the provided host that looks like:

    Hostname
      - Network
      - Disk
      - System
      - Sensors
*/

include('order_key.class.php');

if($argc != 3)
{
	echo "Usage: {$argv[0]} parent host

parent - The parent node you want the graphs to appear under. Usually you would create a node on the cacti tree for the host and then use this.
host   - The name of the host according to cacti.\n";
	die();
}

// Setup data structures
// These are arrays of local_graph_ids
$subtrees['network'] = array();
$subtrees['disk']    = array();
$subtrees['system']  = array();
$subtrees['sensors'] = array();

mysql_connect('localhost', USER, PASS);
mysql_select_db('cacti');

// Work out the host id
$result = mysql_query("select id from host where description = \"{$argv[2]}\" limit 1");

if(mysql_num_rows($result) == 0)
{
	die("Error: Couldn't find a host by the name {$argv[2]}");
}

$row = mysql_fetch_row($result);
$host_id = $row[0];

// This query will fetch all graphs for a given host
$result = mysql_query("
SELECT graph_templates_graph.local_graph_id, graph_templates_graph.title_cache
FROM (graph_templates_graph,graph_local)
LEFT JOIN host on (host.id=graph_local.host_id)
LEFT JOIN graph_templates on (graph_templates.id=graph_local.graph_template_id)
WHERE graph_templates_graph.local_graph_id > 0
 AND graph_templates_graph.local_graph_id=graph_local.id
 AND graph_templates_graph.title_cache like '%%%%'
 AND graph_local.host_id=$host_id");

while ($row = mysql_fetch_assoc($result)) {
	$row['title_cache'] = strtolower($row['title_cache']);

	if(preg_match('/errors.*(eth|lo).*/i', $row['title_cache'])
	   || preg_match('/traffic.*(eth|lo).*/i', $row['title_cache']))
	{
		// Network related graphs, add id to the array
		$subtrees['network'][] = $row['local_graph_id'];
	}
	else if(strpos($row['title_cache'], 'iostat') !== false
		|| strpos($row['title_cache'], 'smart') !== false)
	{
		$tmpname = explode(" - ", $row['title_cache']);
		$dev = $tmpname[count($tmpname)-1];

		// Disk related graphs, add id to the array
		$subtrees['disk'][$dev][] = $row['local_graph_id'];
	}
	else if (strpos($row['title_cache'], 'used space') !== false
		|| strpos($row['title_cache'], 'disk space') !== false)
	{
		$subtrees['disk']['Disk Usage'][] = $row['local_graph_id'];
	}
	else if(strpos($row['title_cache'], 'cpu usage')
		|| strpos($row['title_cache'], 'load average')
		|| strpos($row['title_cache'], 'logged in users')
		|| strpos($row['title_cache'], 'memory usage')
		|| strpos($row['title_cache'], 'processes')
		|| strpos($row['title_cache'], 'uptime'))
	{
		// System related graphs, add id to the array
		$subtrees['system'][] = $row['local_graph_id'];

	}
	else if(strpos($row['title_cache'], 'sensor'))
	{
		// System related graphs, add id to the array
		$subtrees['sensors'][] = $row['local_graph_id'];
	}
}

// Work out what root tree we want to add to
// Lets just assume we will be adding to the WAND node
$result = mysql_query("select id from graph_tree where name = \"WAND\"");

if(mysql_num_rows($result) == 0)
{
	die("Error: Couldn't find parent node");
}

$row = mysql_fetch_row($result);
$graph_tree_id = $row[0];

// Work out the subtree id
$result = mysql_query("select id from graph_tree_items where title = \"{$argv[1]}\"");

if(mysql_num_rows($result) == 0)
{
	die("Error: Couldn't find subtree to add new tree to, have you created it in Cacti? (remember I want a heading not a host)");
}

$row = mysql_fetch_row($result);
$header_id = $row[0];

// Fetch the order key
$result = mysql_query("select order_key from graph_tree_items where graph_tree_id = $graph_tree_id and id = $header_id");

if(mysql_num_rows($result) == 0)
{
	die("Error: Oops I couldn't find the order_key for the subtree provided");
}

$row = mysql_fetch_row($result);
$order_key = $row[0];

// order_key appears to be what cacti uses to generate a tree, pretty silly if you ask me
$ok = new OrderKey($order_key);


// Generate our subtrees for network, disk, system and sensors

$network_subtree = $ok->getChild();
$disk_subtree    = $ok->getSibling();
$system_subtree  = $ok->getSibling();
$sensors_subtree = $ok->getSibling();

mysql_query("insert into graph_tree_items values ('', $graph_tree_id, 0, 0, 'Network', 0, '$network_subtree', 1, 1)");

$nok    = new OrderKey($network_subtree);
$sib_id = $nok->getChild();

foreach($subtrees['network'] as $local_graph_id)
{
	mysql_query("insert into graph_tree_items values ('', $graph_tree_id, $local_graph_id, 5, '', $host_id, '$sib_id', 1, 1)");
	$sib_id = $nok->getSibling();
}

mysql_query("insert into graph_tree_items values ('', $graph_tree_id, 0, 0, 'Disk', 0, '$disk_subtree', 1, 1)");

$dok         = new OrderKey($disk_subtree);
$dev_subtree = $dok->getChild();

foreach($subtrees['disk'] as $dev => $dev_graphs)
{
	mysql_query("insert into graph_tree_items values ('', $graph_tree_id, 0, 0, '$dev', 0, '$dev_subtree', 1, 2)");

	$devok   = new OrderKey($dev_subtree);
	$sib_id = $devok->getChild();

	foreach($dev_graphs as $local_graph_id) {
		mysql_query("insert into graph_tree_items values ('', $graph_tree_id, $local_graph_id, 5, '', $host_id, '$sib_id', 1, 1)");
		$sib_id = $devok->getSibling();
	}

	$dev_subtree = $dok->getSibling();
}

mysql_query("insert into graph_tree_items values ('', $graph_tree_id, 0, 0, 'System', 0, '$system_subtree', 1, 1)");

$syok    = new OrderKey($system_subtree);
$sib_id = $syok->getChild();

foreach($subtrees['system'] as $local_graph_id)
{
	mysql_query("insert into graph_tree_items values ('', $graph_tree_id, $local_graph_id, 5, '', $host_id, '$sib_id', 1, 1)");
	$sib_id = $syok->getSibling();
}

mysql_query("insert into graph_tree_items values ('', $graph_tree_id, 0, 0, 'Sensors', 0, '$sensors_subtree', 1, 1)");

$seok    = new OrderKey($sensors_subtree);
$sib_id = $seok->getChild();

foreach($subtrees['sensors'] as $local_graph_id)
{
	mysql_query("insert into graph_tree_items values ('', $graph_tree_id, $local_graph_id, 5, '', $host_id, '$sib_id', 1, 1)");
	$sib_id = $seok->getSibling();
}

?>

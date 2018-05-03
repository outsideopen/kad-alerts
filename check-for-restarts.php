#!/usr/bin/env php
<?php

require_once __DIR__ . '/vendor/autoload.php';

$envLoader = new \josegonzalez\Dotenv\Loader(__DIR__ . '/.env');
$envLoader->parse();
$env = $envLoader->toArray();

// grab a list of all agents needing a restart
$client = new \Kaseya\Client($env['kaseya_host'], $env['kaseya_user'], $env['kaseya_pass']);
$asset  = new \Kaseya\Service\Asset($client);
$groups = [];
$agents = $asset->agents->all();
$count  = $agents->TotalRecords;
$j      = 0;
do {
	foreach ($agents as $agent) {
		$patch = $asset->patches->status($agent->AgentId);
		if ($patch->Reset == 2) {
			$key            = explode('.', $agent->AgentName);
			$key            = array_reverse($key);
			$machine        = array_pop($key);
			$key            = join('.', $key);
			$groups[$key][] = $machine;
		}
		$j++;
	}

	$agents = $asset->agents->all(['$skip' => $j]);
} while ($count > $j);

// send a list of
$settings = [
	'channel' => $env['slack_channel'],
];
$slack    = new Maknz\Slack\Client($env['slack_hook_url'], $settings);

ksort($machines);
foreach ($groups as $group => $machines) {
	$slack->send("Machines needing restart for *{$group}*: " . join(', ', $machines));
}
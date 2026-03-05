<?php

// simple curl function
function omedaCurl($url, $payload = false) {
	global $c;

	$response = false;

	$ch = curl_init($url);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
	curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
	curl_setopt($ch, CURLOPT_POST, ($payload ? true : false));
	curl_setopt($ch, CURLOPT_HTTPHEADER, array("x-omeda-appid: {$c('API_KEY')}", "x-omeda-inputid: {$c('INPUT_ID')}", 'Content-Type:application/json'));
	
	($payload ? curl_setopt($ch, CURLOPT_POSTFIELDS, $payload) : null);
	
	$fp = fopen(plugin_dir_path( __FILE__ ) . '/errorlog.txt', 'w');
	curl_setopt($ch, CURLOPT_VERBOSE, 1);
	
	curl_setopt($ch, CURLOPT_STDERR, $fp);
	//curl_setopt($ch, CURLINFO_HEADER_OUT, true);

	$response = curl_exec($ch);

	$status_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
	
	curl_close($ch);

	return [$response, $status_code];
}
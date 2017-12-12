<?php

require_once('class.quickhash.php');

define('NL', '<br/>');

$path = '/path/to/my/large_file.mp4';  	// this files should be > 1GB for best illustration of results

$time_start = microtime_float();

$qh = new quickHash();
// must set path to file
$qh->set_path($path);

// set any public variable if you want
//$qh->debug = TRUE;
//$qh->hash_type = 'sha512';	// defaults to 'md5'

// call 'get_quickhash' to hash the file.
$approx_hash = $qh->get_quickhash();

$time_end = microtime_float();
echo "elapsed quickHash time: " . ((float)$time_end - (float)$time_start) . NL;
// display the hash.
echo $approx_hash . NL . NL;

// compare to a complete hash
$time_start = microtime_float();
$true_hash 	= hash_file($qh->hash_type, $path);
$time_end 	= microtime_float();
echo "elapsed normal ".$qh->hash_type." hash time: " . ((float)$time_end - (float)$time_start) . NL;
echo "Normal ".$qh->hash_type.": $true_hash" . NL;

function microtime_float() {
    list($usec, $sec) = explode(" ", microtime());
    return ((float)$usec + (float)$sec);
}
?>
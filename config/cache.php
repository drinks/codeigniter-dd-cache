<?php if (!defined('BASEPATH')) exit('No direct script access allowed');

// "memcache" or "apc"
$config['cache_type'] = "memcache";

$config['memcache_servers'][] = array(
  'host' => 'localhost',
  'port' => 11211,
  );
  
// add more memcache servers with:
/*
$config['memcache_servers'][] = array(
  'host' => 'localhost',
  'port' => 11211,
  );
*/

// default expiration for disk cache (same as memcached default, 10 mins)
$config['cache_default_expires'] = 600;

?>
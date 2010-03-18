CodeIgniter-DD-Cache
=================

CodeIgniter-DD-Cache is a partial caching library for CodeIgniter. It allows you to write and get chunks
of data to and from a connected memcache or APC instance.

This work is based entirely on a combination of Phil Sturgeon's CodeIgniter-Cache (http://github.com/philsturgeon/codeigniter-cache),
and Elliot Haughin's CodeIgniter-multicache (http://www.haughin.com/code/multicache/)


Usage
-----

	// Uncached model call
	$this->blog_m->getPosts($category_id, 'live');

	// cached model call
	$this->cache->model('blog_m', 'getPosts', array($category_id, 'live'), 120); // keep for 2 minutes 
	
	
	$this->cache->library('some_library', 'calculate_something', array($foo, $bar, $bla)); // keep for default time (0 = unlimited)
	
	$this->cache->write($data, 'cached-name');
	$data = $this->cache->get('cached-name');
	
	$this->cache->delete('cached-name');


Installation
------------

Make sure you have memcached or APC set up for PHP, and set your config appropriately.


Requirements
------------

CodeIgniter, APC or memcached


Extra
-----

If you'd like to request changes, report bug fixes, or contact
the developer of this library, email <dan.drinkard@gmail.com>
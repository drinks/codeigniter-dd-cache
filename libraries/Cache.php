<?php if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Cache Class
 *
 * Partial Caching library for CodeIgniter
 *
 * @category  Libraries
 * @author    Dan Drinkard, based on Cache by Phil Sturgeon
 *            and Multicache by Elliot Haughin
 * @link      http://github.com/dndrnkrd/Cache
 * @license   MIT
 * @version   0.1
 */

class Cache
{
  private $ci;
  private $key;
  private $contents;
  private $expires;
  private $default_expires;
  private $created;
  private $memcache; // the memcache handle
  private $connected_servers; // array of server instances

  
  /**
   * Constructor - Initializes and references CI
   */
  function __construct()
  {
    log_message('debug', "Cache Class Initialized.");

    $this->ci =& get_instance();
    $this->_reset();

    $this->ci->load->config('cache');
    
    $this->default_expires = $this->ci->config->item('cache_default_expires');
    $this->_connect();
  }
  
  /**
   * Initialize Cache object to empty
   *
   * @access  private
   * @return  void
   */
  private function _reset()
  {
    $this->contents = NULL;
    $this->key = NULL;
    $this->expires = NULL;
    $this->created = NULL;
    $this->dependencies = array();
  }
  
  private function _connect()
  {
    switch($this->ci->config->item('cache_type'))
    {
      case 'memcache':
        if(function_exists('memcache_connect'))
        {
          $this->memcache = new Memcache;
          $this->_connect_memcache();
        }
      break;
      case 'apc':
        // nothing needed
      break;
    }
  }
  
  private function _connect_memcache()
  {
    $servers = $this->ci->config->item('memcache_servers');
    if(!empty($servers))
    {
      // must turn off error reporting.
      // so memcache can die silently if
      // it can't connect to a server.

      $error_display = ini_get('display_errors');
      $error_reporting = ini_get('error_reporting');

      ini_set('display_errors', "Off");
      ini_set('error_reporting', 0);

      foreach ( $servers as $server )
      {
        if ( $this->memcache->addServer($server['host'], $server['port']) )
        {
          $this->connected_servers[] = $server;
        }
      }

      // back on again!

      ini_set('display_errors', $error_display);
      ini_set('error_reporting', $error_reporting);
    }
  }
  
  /**
   * Call a library's cached result or create new cache
   *
   * @access  public
   * @param string
   * @return  array
   */
  public function library($library, $method, $arguments = array(), $expires = NULL)
  {
    if(!in_array(ucfirst($library), $this->ci->load->_ci_classes))
    {
      $this->ci->load->library($library);
    }
    
    return $this->_call($library, $method, $arguments, $expires);
  }
  
  /**
   * Call a model's cached result or create new cache
   *
   * @access  public
   * @return  array
   */
  public function model($model, $method, $arguments = array(), $expires = NULL)
  {
    if(!in_array(ucfirst($model), $this->ci->load->_ci_classes))
    {
      $this->ci->load->model($model);
    }
    
    return $this->_call($model, $method, $arguments, $expires);
  }
  
  // Deprecated, use model() or library()
  private function _call($property, $method, $arguments = array(), $expires = NULL)
  {
    $this->ci->load->helper('security');

    if(!is_array($arguments))
    {
      $arguments = (array) $arguments;
    }
    
    // Clean given arguments to a 0-index array
    $arguments = array_values($arguments);
    
    $key = $property.$method.serialize($arguments);
    $key = md5($key);
    
    // See if we have this cached
    $cached_response = $this->get($key);

    // Not FALSE? Return it
    if($cached_response)
    {
      return $cached_response;
    }
    
    else
    {
      // Call the model or library with the method provided and the same arguments
      $new_response = call_user_func_array(array($this->ci->$property, $method), $arguments);
      $this->write($new_response, $key, $expires);
      
      return $new_response;
    }
  }
  
  
  /**
   * Helper function to get the cache creation date
   */
  function get_created($created) { return $this->created; }
  
  
  /**
   * Retrieve Cache File
   *
   * @access public
   * @param string
   * @param boolean
   * @return  mixed
   */
  function get($key = NULL)
  {
    
    // Check if cache was requested with the function or uses this object
    if ($key !== NULL)
    {
      $this->_reset();
      $this->key = $key;
    }
    
    switch( $this->ci->config->item('cache_type') )
    {
      case 'memcache':
      
        // Check for servers
        if (empty($this->connected_servers))
        {
          return FALSE;
        }
        
        $this->contents = $this->memcache->get($key);
        
      break;
      case 'apc':
      
        $this->contents = apc_fetch($key);
        
      break;
    }
    
    // if cache returned false we're done here
    if($this->contents === FALSE) return FALSE;
    
    // if not we have a serialized array
    $this->contents = @unserialize($this->contents);
    
    // Instantiate the object variables
    $this->expires    = @$this->contents['__cache_expires'];
    $this->created    = @$this->contents['__cache_created'];
    
    // Cleanup the meta variables from the contents
    $this->contents = @$this->contents['__cache_contents'];
    
    // Return the cache
    log_message('debug', "Cache retrieved: ".$key);
    return $this->contents;
  }
  
  /**
   * Write Cache File
   *
   * @access  public
   * @param mixed
   * @param string
   * @param int
   * @param array
   * @return  void
   */
  function write($contents = NULL, $key = NULL, $expires = NULL)
  {
    // Check if cache was passed with the function or uses this object
    if ($contents !== NULL)
    {
      $this->_reset();
      $this->contents = $contents;
      $this->key = $key;
      $this->expires = $expires;
    }
    
    // Put the contents in an array so additional meta variables
    // can be easily removed from the output
    $this->contents = array('__cache_contents' => $this->contents);
    
    // Meta variables
    $this->contents['__cache_created'] = time();
    
    // Add expires variable if its set...
    if (! empty($this->expires))
    {
      $this->contents['__cache_expires'] = $this->expires + time();
    }
    // ...or add default expiration if its set
    elseif (! empty($this->default_expires) )
    {
      $this->contents['__cache_expires'] = $this->default_expires + time();
    }
    
    $data = @serialize($this->contents);
    
    // Write the cache
    switch ( $this->ci->config->item('cache_type') )
    {
      case 'memcache':
        if ( empty($this->connected_servers) )
        {
          return false;
        }

        $success = $this->memcache->set($key, $data, 0, $this->contents['__cache_expires']);
      break;
      
      case 'apc':
        $success = apc_store($key, $data, $this->contents['__cache_expires']); 
      break;
    }
    
    // Log success
    log_message('debug', "Cache written: ".$key);
    
    // Reset values
    $this->_reset();
    return $success;
  }
  
  /**
   * Delete Cache File
   *
   * @access  public
   * @param string
   * @return  void
   */ 
  function delete($key = NULL, $when = 0)
  {
    if ($key !== NULL) $this->key = $key;
    
    switch ( $this->ci->config->item('cache_type') )
    {
      case 'memcache':
        if ( empty($this->connected_servers) )
        {
          return false;
        }

        $success = $this->memcache->delete($key, $when);
      break;

      case 'apc':
        $success = apc_delete($key); 
      break;
    }
    
    // Reset values
    $this->_reset();
    return $success;
  }
  
}
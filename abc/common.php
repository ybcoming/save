<?php 

/*
******** common variable	
*/
 $reverse_proxy_addresses=array();
 $own=array(
	'item'=>1,
	'list'=>1,
	'search'=>1
 );
 $base_url='';
 $base_path='';
 $base_root='';
 
 
 	$dir			= explode(':', rtrim($_SERVER['HTTP_HOST'],'.'));
	$dir			= explode('.',$dir[0],2);

	if($dir[1] == $base_site && isset($sites[$dir[0]]) && $sites[$dir[0]] === 1){	
		$third		= FALSE;
		$setfile 	= DRUPAL_ROOT.'/../sites/i/settings.php';
		$base_url	= $dir[1];
		$_GET['g']  = $dir[0];
	}elseif($dir[1] == $base_site && empty($sites[$dir[0]])){
		$third		= TRUE;	
		$setfile 	= DRUPAL_ROOT.'/../sites/c/'.$dir[0].'/settings.php';
		$base_url	= $dir[0].'.'.$dir[1];
		$_GET['g']  = 'www';
	}elseif($dir[1] != $base_site )	{
		$third		= TRUE;	
		$tmp		= explode('.',$dir[1]，2);		
		$setfile 	= DRUPAL_ROOT.'/../sites/c/'.$tmp[0].'/settings.php';
		unset($tmp);
		$base_url	= $dir[1];	
		$_GET['g']  = $dir[0];		
	}

 
/*
********* common function
*/

function conf_path() {
  Global $own;
  $dir			= explode( '.',rtrim($_SERVER['HTTP_HOST'],'.'));
  if(isset($own[$dir[0]])){
  $setfile  = DRUPAL_ROOT.'/../sites/i/'.$dir[0].'/settings.php';
  }else{
   $front 		= substr($dir[0],0,2);
   $setfile  = DRUPAL_ROOT.'/../sites/o/'.$front.'/'.$dir[0].'/settings.php';
  }

  if (file_exists( $setfile) ) {
        include $setfile;
      }else{
        request_not_found();  	
      }
    
}

/**
 * Returns the IP address of the client machine.
 *
 * If Drupal is behind a reverse proxy, we use the X-Forwarded-For header
 * instead of $_SERVER['REMOTE_ADDR'], which would be the IP address of
 * the proxy server, and not the client's. The actual header name can be
 * configured by the reverse_proxy_header variable.
 *
 * @return
 *   IP address of client machine, adjusted for reverse proxy and/or cluster
 *   environments.
 */
function ip_address() {
  static  $ip_address = '';
  Global $reverse_proxy_addresses;

  if (!isset($ip_address)) {
    $ip_address = $_SERVER['REMOTE_ADDR'];

    if ( $conf['reverse_proxy']==1 ) {
       
      if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        // If an array of known reverse proxy IPs is provided, then trust
        // the XFF header if request really comes from one of them.
       // $reverse_proxy_addresses = variable_get('reverse_proxy_addresses', array());

        // Turn XFF header into an array.
        $forwarded = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);

        // Trim the forwarded IPs; they may have been delimited by commas and spaces.
        $forwarded = array_map('trim', $forwarded);

        // Tack direct client IP onto end of forwarded array.
        $forwarded[] = $ip_address;

        // Eliminate all trusted IPs.
        $untrusted = array_diff($forwarded, $reverse_proxy_addresses);

        // The right-most IP is the most specific we can trust.
        $ip_address = array_pop($untrusted);
      }
    }
  }

  return $ip_address;
}

/**
 * Returns the equivalent of Apache's $_SERVER['REQUEST_URI'] variable.
 *
 * Because $_SERVER['REQUEST_URI'] is only available on Apache, we generate an
 * equivalent using other environment variables.
 */
function request_uri() {
  if (isset($_SERVER['REQUEST_URI'])) {
    $uri = $_SERVER['REQUEST_URI'];
  }
  else {
    if (isset($_SERVER['argv'])) {
      $uri = $_SERVER['SCRIPT_NAME'] . '?' . $_SERVER['argv'][0];
    }
    elseif (isset($_SERVER['QUERY_STRING'])) {
      $uri = $_SERVER['SCRIPT_NAME'] . '?' . $_SERVER['QUERY_STRING'];
    }
    else {
      $uri = $_SERVER['SCRIPT_NAME'];
    }
  }
  // Prevent multiple slashes to avoid cross site requests via the Form API.
  $uri = '/' . ltrim($uri, '/');

  return $uri;
}


/**
 * Validates that a hostname (for example $_SERVER['HTTP_HOST']) is safe.
 *
 * @return
 *  TRUE if only containing valid characters, or FALSE otherwise.
 */
function drupal_valid_http_host($host) {
  return preg_match('/^\[?(?:[a-zA-Z0-9-:\]_]+\.?)+$/', $host);
}

function request_path() {
  static $path;

  if (isset($path)) {
    return $path;
  }

  if (isset($_GET['q']) && is_string($_GET['q'])) {
    // This is a request with a ?q=foo/bar query string. $_GET['q'] is
    // overwritten in drupal_path_initialize(), but request_path() is called
    // very early in the bootstrap process, so the original value is saved in
    // $path and returned in later calls.
    $path = $_GET['q'];
  }
  elseif (isset($_SERVER['REQUEST_URI'])) {
    // This request is either a clean URL, or 'index.php', or nonsense.
    // Extract the path from REQUEST_URI.
    $request_path = strtok($_SERVER['REQUEST_URI'], '?');
    $base_path_len = strlen(rtrim(dirname($_SERVER['SCRIPT_NAME']), '\/'));
    // Unescape and strip $base_path prefix, leaving q without a leading slash.
    $path = substr(urldecode($request_path), $base_path_len + 1);
    // If the path equals the script filename, either because 'index.php' was
    // explicitly provided in the URL, or because the server added it to
    // $_SERVER['REQUEST_URI'] even when it wasn't provided in the URL (some
    // versions of Microsoft IIS do this), the front page should be served.
    if ($path == basename($_SERVER['PHP_SELF'])) {
      $path = '';
    }
  }
  else {
    // This is the front page.
    $path = '';
  }

  // Under certain conditions Apache's RewriteRule directive prepends the value
  // assigned to $_GET['q'] with a slash. Moreover we can always have a trailing
  // slash in place, hence we need to normalize $_GET['q'].
  $path = trim($path, '/');

  return $path;
}

/**
 * Initializes the PHP environment.
 */
function drupal_environment_initialize() {
  if (!isset($_SERVER['HTTP_REFERER'])) {
    $_SERVER['HTTP_REFERER'] = '';
  }
  if (!isset($_SERVER['SERVER_PROTOCOL']) || ($_SERVER['SERVER_PROTOCOL'] != 'HTTP/1.0' && $_SERVER['SERVER_PROTOCOL'] != 'HTTP/1.1')) {
    $_SERVER['SERVER_PROTOCOL'] = 'HTTP/1.0';
  }

  if (isset($_SERVER['HTTP_HOST'])) {
    // As HTTP_HOST is user input, ensure it only contains characters allowed
    // in hostnames. See RFC 952 (and RFC 2181).
    // $_SERVER['HTTP_HOST'] is lowercased here per specifications.
    $_SERVER['HTTP_HOST'] = strtolower($_SERVER['HTTP_HOST']);
    if (!drupal_valid_http_host($_SERVER['HTTP_HOST'])) {
      // HTTP_HOST is invalid, e.g. if containing slashes it may be an attack.
      header($_SERVER['SERVER_PROTOCOL'] . ' 400 Bad Request');
      exit;
    }
  }
  else {
    // Some pre-HTTP/1.1 clients will not send a Host header. Ensure the key is
    // defined for E_ALL compliance.
    $_SERVER['HTTP_HOST'] = '';
  }

  // When clean URLs are enabled, emulate ?q=foo/bar using REQUEST_URI. It is
  // not possible to append the query string using mod_rewrite without the B
  // flag (this was added in Apache 2.2.8), because mod_rewrite unescapes the
  // path before passing it on to PHP. This is a problem when the path contains
  // e.g. "&" or "%" that have special meanings in URLs and must be encoded.
  $_GET['q'] = request_path();


  // Use session cookies, not transparent sessions that puts the session id in
  // the query string.
  ini_set('session.use_cookies', '1');
  ini_set('session.use_only_cookies', '1');
  ini_set('session.use_trans_sid', '0');
  // Don't send HTTP headers using PHP's session handler.
  // An empty string is used here to disable the cache limiter.
  ini_set('session.cache_limiter', '');
  // Use httponly session cookies.
  ini_set('session.cookie_httponly', '1');

}



/**
 * Sets the base URL, cookie domain, and session name from configuration.
 */
function drupal_settings_initialize() {
  global $base_url, $base_path, $base_root;

  // Export these settings.php variables to the global namespace.
  global $databases, $cookie_domain, $conf, $installed_profile, $update_free_access, $db_url, $db_prefix, $drupal_hash_salt, $is_https, $base_secure_url, $base_insecure_url;
  $conf = array();

 conf_path();
 
  $is_https = isset($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) == 'on';

  if (isset($base_url)) {
    // Parse fixed base URL from settings.php.
    $parts = parse_url($base_url);
    if (!isset($parts['path'])) {
      $parts['path'] = '';
    }
    $base_path = $parts['path'] . '/';
    // Build $base_root (everything until first slash after "scheme://").
    $base_root = substr($base_url, 0, strlen($base_url) - strlen($parts['path']));
  }
  else {
    // Create base URL.
    $http_protocol = $is_https ? 'https' : 'http';
    $base_root = $http_protocol . '://' . $_SERVER['HTTP_HOST'];

    $base_url = $base_root;

    // $_SERVER['SCRIPT_NAME'] can, in contrast to $_SERVER['PHP_SELF'], not
    // be modified by a visitor.
    if ($dir = rtrim(dirname($_SERVER['SCRIPT_NAME']), '\/')) {
      $base_path = $dir;
      $base_url .= $base_path;
      $base_path .= '/';
    }
    else {
      $base_path = '/';
    }
  }
  $base_secure_url = str_replace('http://', 'https://', $base_url);
  $base_insecure_url = str_replace('https://', 'http://', $base_url);

  if ($cookie_domain) {
    // If the user specifies the cookie domain, also use it for session name.
    $session_name = $cookie_domain;
  }
  else {
    // Otherwise use $base_url as session name, without the protocol
    // to use the same session identifiers across HTTP and HTTPS.
    list( , $session_name) = explode('://', $base_url, 2);
    // HTTP_HOST can be modified by a visitor, but we already sanitized it
    // in drupal_settings_initialize().
    if (!empty($_SERVER['HTTP_HOST'])) {
      $cookie_domain = $_SERVER['HTTP_HOST'];
      // Strip leading periods, www., and port numbers from cookie domain.
      $cookie_domain = ltrim($cookie_domain, '.');
      if (strpos($cookie_domain, 'www.') === 0) {
        $cookie_domain = substr($cookie_domain, 4);
      }
      $cookie_domain = explode(':', $cookie_domain);
      $cookie_domain = '.' . $cookie_domain[0];
    }
  }
  // Per RFC 2109, cookie domains must contain at least one dot other than the
  // first. For hosts such as 'localhost' or IP Addresses we don't set a cookie domain.
  if (count(explode('.', $cookie_domain)) > 2 && !is_numeric(str_replace('.', '', $cookie_domain))) {
    ini_set('session.cookie_domain', $cookie_domain);
  }
  // To prevent session cookies from being hijacked, a user can configure the
  // SSL version of their website to only transfer session cookies via SSL by
  // using PHP's session.cookie_secure setting. The browser will then use two
  // separate session cookies for the HTTPS and HTTP versions of the site. So we
  // must use different session identifiers for HTTPS and HTTP to prevent a
  // cookie collision.
  if ($is_https) {
    ini_set('session.cookie_secure', TRUE);
  }
  $prefix = ini_get('session.cookie_secure') ? 'SSESS' : 'SESS';
  session_name($prefix . substr(hash('sha256', $session_name), 0, 32));
}


function route_request() {
		Global $controller_path;
		$requri = request_uri();
		$p = $requri ? explode('/',$requri) : array();
		$params = array();
		if (isset($p[0]) && $p[0])
			$controller=$p[0];
		if (isset($p[1]) && $p[1])
			$function=$p[1];
		if (isset($p[2]))
			$params=array_slice($p,2);

		$controllerfile=$controller_path.$controller.'/'.$function.'.php';
		if (!preg_match('#^[A-Za-z0-9_-]+$#',$controller) || !file_exists($controllerfile))
			$this->request_not_found();

		$function='_'.$function;
		if (!preg_match('#^[A-Za-z_][A-Za-z0-9_-]*$#',$function) || function_exists($function))
			$this->request_not_found();
		require($controllerfile);
		if (!function_exists($function))
			$this->request_not_found();

		call_user_func_array($function,$params);
		return $this;
	}

	//Override this function for your own custom 404 page
	function request_not_found() {
		header("HTTP/1.0 404 Not Found");
		die('<html><head><title>404 Not Found</title></head><body><h1>Not Found</h1><p>The requested URL was not found on this server.</p><p>Please go <a href="javascript: history.back(1)">back</a> and try again.</p><hr /><p>Powered By: <a href="http://kissmvc.com">KISSMVC</a></p></body></html>');
	}
 
drupal_environment_initialize();
drupal_settings_initialize();



//////////////////////////////////////////////



/*
******** common variable	
*/
	$reverse_proxy_addresses=array();

  	//ini_set('session.cookie_secure', TRUE);
 	ini_set('session.use_cookies', '1');
  	ini_set('session.use_only_cookies', '1');
  	ini_set('session.use_trans_sid', '0');
  	// Don't send HTTP headers using PHP's session handler.
  	// An empty string is used here to disable the cache limiter.
  	ini_set('session.cache_limiter', '');
  	ini_set('session.cookie_httponly', '1');
	ini_set('session.cookie_domain', '.'.$base_url);
	
	/*
		$controllers=array(
		'news'=>1,
		'product'=>1,
		'picture'=>1
		);
		.........
		.........
		.........
	*/
	
	$is_https = isset($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) == 'on';
	$session_name = $is_https ? 's_session_name' : 'session_name';
	
	if( empty($_COOKIE[$session_name])){
			if( $controller != 'login'){
				header('https://secure.'.$base_site.'/login');
				exit;
			}else[
				$setfile 	= DRUPAL_ROOT.'/../sites/default/settings.php';
			}
		}elseif{
			session_name($session_name);
			session_start();
			$setfile 	= DRUPAL_ROOT.'/../sites/'.$_SESSION['site'].'/settings.php';
		}

   if (!empty($setfile) && file_exists($setfile)) {
       require $setfile;
    }else{
  		request_not_found();
    }  
	

	$pathinfo	= rtrim($_SERVER['PATH_INFO'],'/ '); 
	if(empty($pathinfo)) {
		$controller =	$action = 'index';
	}else{
		$p 	= implode('/',$pathinfo);			
		isset($controllers[$p[0]]) ? $controller=array_shift($p) : request_not_found();
		$actions	= $$controller && isset($actions[$p[0]]) ? $action=array_shift($p) : $action='index';
		$params		= $p;
	}

	
	
	
	




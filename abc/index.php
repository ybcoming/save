index.php

define('DRUPAL_ROOT', __DIR__);
define('DRUPAL_SITE','ybcoming.com');


// https 链接 ： /login, /password/reset

if( $_SERVER['HTTP_HOST'] == 'secure.'.DRUPAL_SITE ){

	require DRUPAL_ROOT.'/../includes/scommon.inc';

 }else{
 
	require DRUPAL_ROOT.'/../includes/common.inc';
	
 }

?><?php
/*
=====================================================
 DataLife Engine - by SoftNews Media Group 
-----------------------------------------------------
 https://dle-news.ru/
-----------------------------------------------------
 Copyright (c) 2004-2024 SoftNews Media Group
=====================================================
 This code is protected by copyright
=====================================================
 File: opensearch.php
-----------------------------------------------------
 Use: OpenSearch
=====================================================
*/

if( !defined( 'DATALIFEENGINE' ) ) {
	header( "HTTP/1.1 403 Forbidden" );
	header ( 'Location: ../' );
	die( "Hacking attempt!" );
}

if (isset ( $config["lang_" . $config['skin']] ) AND $config["lang_" . $config['skin']] != '' AND file_exists( DLEPlugins::Check(ROOT_DIR . '/language/' . $config["lang_" . $config['skin']] . '/website.lng') ) ) {

	include_once (DLEPlugins::Check(ROOT_DIR . '/language/' . $config["lang_" . $config['skin']] . '/website.lng'));
	
} else {

	include_once (DLEPlugins::Check(ROOT_DIR . '/language/' . $config['langs'] . '/website.lng'));

}

$member_id = array ();
$member_id['user_group'] = 5;

$tpl = new dle_template();
$tpl->dir = ROOT_DIR . '/templates';
define( 'TEMPLATE_DIR', $tpl->dir );

$tpl->load_template( 'opensearch.tpl' );

$tpl->set( '{path}', $config['http_home_url'] );

$tpl->compile( 'main' );

header( 'Content-type: application/xml' );

echo $tpl->result['main'];

die();

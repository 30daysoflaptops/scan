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
 File: quote.php
-----------------------------------------------------
 Use: comments quote
=====================================================
*/

if(!defined('DATALIFEENGINE')) {
	header( "HTTP/1.1 403 Forbidden" );
	header ( 'Location: ../../' );
	die( "Hacking attempt!" );
}

if( !isset($_REQUEST['user_hash']) OR !$_REQUEST['user_hash'] OR $_REQUEST['user_hash'] != $dle_login_hash ) {
	die ("error");
}

$id = intval( $_GET['id'] );
$area = $_GET['area'];

if(!$id) die( "error" );

if( $config['allow_comments_wysiwyg'] > 0) {

	$allowed_tags = array('div[align|style|class|data-commenttime|data-commentuser|contenteditable]', 'span[style|class|data-userurl|data-username|contenteditable]', 'p[align|style|class]', 'pre[class]', 'code', 'br', 'strong', 'em', 'ul', 'li', 'ol', 'b', 'u', 'i', 's', 'hr');
	
	if( $user_group[$member_id['user_group']]['allow_url'] ) $allowed_tags[] = 'a[href|target|style|class|title]';
	if( $user_group[$member_id['user_group']]['allow_image'] ) $allowed_tags[] = 'img[style|class|src|alt|width|height]';
	
	$parse = new ParseFilter( $allowed_tags );
	$parse->wysiwyg = true;
	
} else {
	$parse = new ParseFilter();
}

$parse->safe_mode = true;
$parse->remove_html = false;

$row = $db->super_query( "SELECT post_id, autor, text, date FROM " . PREFIX . "_comments WHERE id = '{$id}'" );

if (!$row['text']) die( "error" );

$row_news = $db->super_query( "SELECT allow_comm, approve, access FROM " . PREFIX . "_post LEFT JOIN " . PREFIX . "_post_extras ON (" . PREFIX . "_post.id=" . PREFIX . "_post_extras.news_id) WHERE id='{$row['post_id']}'" );
$options = news_permission( $row_news['access'] );

if( (!$user_group[$member_id['user_group']]['allow_addc'] and $options[$member_id['user_group']] != 2) or $options[$member_id['user_group']] == 1 ) die( "error" );

if( !$row_news['allow_comm'] OR !$row_news['approve'] ) {
	die( "error" );
}

if( $config['allow_comments_wysiwyg'] < 1 ) {
	
	$text = $parse->decodeBBCodes( $row['text'], false );
	$text = str_replace( "&#58;", ":", $text );
	$text = str_replace( "&#91;", "[", $text );
	$text = str_replace( "&#93;", "]", $text );
	$text = str_replace( "&#123;", "{", $text );
	$text = str_replace( "&#39;", "'", $text );
	$text = "[quote={$row['autor']}]{$text}[/quote]";

} else {
	
	$parse->wysiwyg = true;
	$text = $parse->decodeBBCodes( $row['text'], TRUE, $config['allow_comments_wysiwyg'] );
	$text = preg_replace('/<p[^>]*>/', '', $text); 
	$text = str_replace("</p>", "<br>", $text);	
	$text = preg_replace('/<div[^>]*>/', '', $text); 
	$text = str_replace("</div>", "<br>", $text);
	$text = str_replace( "\r", "", $text );
	$text = str_replace( "\n", "", $text );
	
	$count_start = substr_count ($text, "[quote");
	$count_end = substr_count ($text, "[/quote]");

	if ($count_start AND $count_start == $count_end) {
		$text = str_ireplace( "[quote]", "<div class=\"quote\">", $text );
		$text = preg_replace( "#\[quote=(.*?)\]#i", "<div class=\"title_quote\">{$lang['i_quote']} \\1</div><div class=\"quote\">", $text );
		$text = str_ireplace( "[/quote]", "</div>", $text );
	}

	$text = trim($text);

	$row['date'] = strtotime($row['date']);

	if ($config['allow_comments_wysiwyg'] == '1') {
		$contenteditable = ' contenteditable="false"';
	} else $contenteditable = '';

	$text = "<div class=\"quote_block noncontenteditable\"><div class=\"title_quote\" data-commenttime=\"{$row['date']}\" data-commentuser=\"{$row['autor']}\"{$contenteditable}>". difflangdate($config['timestamp_comment'], $row['date']).",  {$row['autor']} {$lang['user_says']}</div><div class=\"quote\"><div class=\"quote_body contenteditable\">{$text}</div></div></div>";
	
}


if (stripos ( $text, "[hide" ) !== false ) {
	
	$text = preg_replace_callback ( "#\[hide(.*?)\](.+?)\[/hide\]#is", 
		function ($matches) use ($member_id, $user_group, $lang) {
			
			$matches[1] = str_replace(array("=", " "), "", $matches[1]);
			$matches[2] = $matches[2];

			if( $matches[1] ) {
				
				$groups = explode( ',', $matches[1] );

				if( in_array( $member_id['user_group'], $groups ) OR $member_id['user_group'] == "1") {
					return $matches[0];
				} else return "";
				
			} else {
				
				if( $user_group[$member_id['user_group']]['allow_hide'] ) return $matches[0]; else return "";
				
			}

	}, $text );
	
}

echo $text;

?>
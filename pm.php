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
 File: pm.php
-----------------------------------------------------
 Use: PM
=====================================================
*/

if(!defined('DATALIFEENGINE')) {
	header( "HTTP/1.1 403 Forbidden" );
	header ( 'Location: ../../' );
	die( "Hacking attempt!" );
}

if( !$is_logged ) {
	die ( "Hacking attempt!" );
}

if( !isset($_REQUEST['user_hash']) OR !$_REQUEST['user_hash'] OR $_REQUEST['user_hash'] != $dle_login_hash ) {

	die ("error");
	
}

if( $config['allow_comments_wysiwyg'] < 1) {
	
	$parse = new ParseFilter();
	
} else {

	$allowed_tags = array('div[align|style|class|data-commenttime|data-commentuser|contenteditable]', 'span[style|class|data-userurl|data-username|contenteditable]', 'p[align|style|class]', 'pre[class]', 'code', 'br', 'strong', 'em', 'ul', 'li', 'ol', 'b', 'u', 'i', 's', 'hr');
	
	if( $user_group[$member_id['user_group']]['allow_url'] ) $allowed_tags[] = 'a[href|target|style|class]';
	if( $user_group[$member_id['user_group']]['allow_image'] ) $allowed_tags[] = 'img[style|class|src]';
	
	$parse = new ParseFilter($allowed_tags);
	
}
	
$parse->safe_mode = true;
$parse->remove_html = false;
$parse->allow_video = false;
$parse->allow_media = false;
$parse->disable_leech = true;
$parse->allow_url = $user_group[$member_id['user_group']]['allow_url'];
$parse->allow_image = $user_group[$member_id['user_group']]['allow_image'];

if ($_POST['action'] == "send_pm") {

	if(!$user_group[$member_id['user_group']]['allow_pm'] ) {
		echo "{\"error\":\" {$lang['pm_err_1']}\"}";
		die();
	}
	
	if( $user_group[$member_id['user_group']]['max_pm_day'] ) {
	
		$this_time = time() - 86400;
		$db->query( "DELETE FROM " . PREFIX . "_sendlog WHERE date < '$this_time' AND flag='1'" );
	
		$row = $db->super_query("SELECT COUNT(*) as count FROM " . PREFIX . "_sendlog WHERE user = '{$member_id['name']}' AND flag='1'");
	
		if( $row['count'] >=  $user_group[$member_id['user_group']]['max_pm_day'] ) {
			$lang['pm_err_10'] = str_replace('{max}', $user_group[$member_id['user_group']]['max_pm_day'], $lang['pm_err_10']);
			echo "{\"error\":\" {$lang['pm_err_10']}\"}";
			die();
		}
	}
	
	$name = $db->safesql( htmlspecialchars(strip_tags( trim( $_POST['name'] ) ), ENT_QUOTES, $config['charset'] ) );
	$subj = $db->safesql( htmlspecialchars(strip_tags( trim( $_POST['subj'] ) ), ENT_QUOTES, $config['charset'] ) );

	if( dle_strlen( $_POST['comments'], $config['charset'] ) > 65000 ) $_POST['comments'] = "";
	
	$stop = "";
	
	if( $config['allow_comments_wysiwyg'] > 0 ) {
			
		if( strlen( $_POST['comments'] ) < 8 ) $_POST['comments'] = "";
		
		$parse->wysiwyg = true;
			
		$comments = $db->safesql( $parse->BB_Parse( $parse->process( trim( $_POST['comments'] ) ) ) );
	
	} else {
		
		if ($config['allow_comments_wysiwyg'] == "-1") $parse->allowbbcodes = false;
		
		$comments = $db->safesql( $parse->BB_Parse( $parse->process( trim( $_POST['comments'] ) ), false ) );
	}
	
	if(!$name OR !$subj OR !$comments) $stop .= $lang['pm_err_2'];
	
	if( dle_strlen( $subj, $config['charset'] ) > 250 ) {
		$stop .= $lang['pm_err_3'];
	}
	
	if( dle_strlen( $name, $config['charset'] ) > 40 ) {
		$stop .= $lang['reg_err_3'];
	}
	
	if( $parse->not_allowed_tags ) {
		
		$stop .= "<li>" .$lang['news_err_33']. "</li>";
	}

	if( $parse->not_allowed_text ) {
		
		$stop .= "<li>" . $lang['news_err_37']. "</li>";
	}
	
	if( $user_group[$member_id['user_group']]['captcha_pm'] ) {

		if ($config['allow_recaptcha']) {

			$sec_code = 1;
			$sec_code_session = false;

			if ( $_POST['g_recaptcha_response'] ) {
			
					$reCaptcha = new ReCaptcha($config['recaptcha_private_key']);

					$resp = $reCaptcha->verifyResponse(get_ip(), $_POST['g_recaptcha_response'] );
			
			        if ($resp === null OR !$resp->success) {

						$stop .= "<li>" . $lang['recaptcha_fail'] . "</li>";

			        }

			} else $stop .= "<li>" . $lang['recaptcha_fail'] . "</li>";

		} elseif( $_REQUEST['sec_code'] != $_SESSION['sec_code_session'] OR !$_SESSION['sec_code_session'] ) $stop .= "<li>" . $lang['news_err_30'] . "</li>";
	
	}

	if( $user_group[$member_id['user_group']]['pm_question'] ) {
	
		if ( intval($_SESSION['question']) ) {
	
			$answer = $db->super_query("SELECT id, answer FROM " . PREFIX . "_question WHERE id='".intval($_SESSION['question'])."'");
	
			$answers = explode( "\n", $answer['answer'] );
	
			$pass_answer = false;
	
			if( function_exists('mb_strtolower') ) {
				$question_answer = trim(mb_strtolower($_POST['question_answer'], $config['charset']));
			} else {
				$question_answer = trim(strtolower($_POST['question_answer']));
			}
	
			if( count($answers) AND $question_answer ) {
				foreach( $answers as $answer ){

					if( function_exists('mb_strtolower') ) {
						$answer = trim(mb_strtolower($answer, $config['charset']));
					} else {
						$answer = trim(strtolower($answer));
					}

					if( $answer AND $answer == $question_answer ) {
						$pass_answer	= true;
						break;
					}
				}
			}

			if( !$pass_answer ) $stop .= "<li>".$lang['reg_err_24']."</li>";

		} else $stop .= "<li>".$lang['reg_err_24']."</li>";
	
	}
	
	if( !$stop AND $user_group[$member_id['user_group']]['spampmfilter'] ) {
		
		$row = $db->super_query( "SELECT * FROM " . PREFIX . "_spam_log WHERE ip = '{$_IP}'" );
		$member_id['email'] = $db->safesql($member_id['email']);

		if ( !$row['id'] OR !$row['email'] ) {
	
			$sfs = new StopSpam($config['spam_api_key'], $user_group[$member_id['user_group']]['spampmfilter'] );
			$args = array('ip' => $_IP, 'email' => $member_id['email']);
	
			if ($sfs->is_spammer( $args )) {
	
				if ( !$row['id'] ) {
					$db->query( "INSERT INTO " . PREFIX . "_spam_log (ip, is_spammer, email, date) VALUES ('{$_IP}','1', '{$member_id['email']}', '{$_TIME}')" );
				} else {
					$db->query( "UPDATE " . PREFIX . "_spam_log SET is_spammer='1', email='{$member_id['email']}' WHERE id='{$row['id']}'" );
				}
	
				$stop .= $lang['reg_err_34'];
	
			} else {
				
				if ( !$row['id'] ) {
					$db->query( "INSERT INTO " . PREFIX . "_spam_log (ip, is_spammer, email, date) VALUES ('{$_IP}','0', '{$member_id['email']}', '{$_TIME}')" );
				} else {
					$db->query( "UPDATE " . PREFIX . "_spam_log SET email='{$member_id['email']}' WHERE id='{$row['id']}'" );
				}
				
			}
		
		} else {
	
			if ($row['is_spammer']) {
	
				$stop .= $lang['reg_err_34'];
			
			}
	
		}
	
	}
	
	if( !$stop ) {
		
		$db->query( "SELECT email, name, user_id, pm_all, user_group FROM " . USERPREFIX . "_users WHERE name = '{$name}'" );
		
		if( !$db->num_rows() ) $stop .= $lang['pm_err_4'];
		
		$row = $db->get_row();
		$db->free();
		
		if(!$user_group[$row['user_group']]['allow_pm'] ) {
			echo "{\"error\":\" {$lang['pm_err_11']}\"}";
			die();
		}
	
	}
	
	if( !$stop ) {

		$db->query( "SELECT id FROM " . USERPREFIX . "_ignore_list WHERE user='{$row['user_id']}' AND user_from='{$member_id['name']}'" );
		if( $db->num_rows() ) $stop .= $lang['pm_ignored'];
		$db->free();

	}
	
	if( !$stop AND ($user_group[$row['user_group']]['max_pm'] AND $row['pm_all'] >= $user_group[$row['user_group']]['max_pm']) and $member_id['user_group'] != 1 ) {
		$stop .= $lang['pm_err_8'];
	}
	
	if( !$stop ) {
		
		unset($_SESSION['question']);
		unset($_SESSION['sec_code_session']);
		
		$time = time();
		$member_id['name'] = $db->safesql($member_id['name']);

		if( isset($_REQUEST['outboxcopy']) AND intval($_REQUEST['outboxcopy']) ) {
			
			$db->query( "INSERT INTO " . USERPREFIX . "_pm (subj, text, user, user_from, date, pm_read, folder) values ('$subj', '$comments', '{$row['user_id']}', '{$member_id['name']}', '{$time}', '0', 'outbox')" );
			$send_id = $db->insert_id();

			$db->query( "UPDATE " . USERPREFIX . "_users SET pm_all=pm_all+1 WHERE user_id='{$member_id['user_id']}'" );
		
		} else $send_id = 0;
		
		$db->query( "INSERT INTO " . USERPREFIX . "_pm (subj, text, user, user_from, date, pm_read, folder, sendid) values ('{$subj}', '{$comments}', '{$row['user_id']}', '{$member_id['name']}', '{$time}', '0', 'inbox', '{$send_id}')" );
		$newpmid = $db->insert_id();
		
		$db->query( "UPDATE " . USERPREFIX . "_users SET pm_all=pm_all+1, pm_unread=pm_unread+1  WHERE user_id='{$row['user_id']}'" );
		
		if( isset( $_GET['replyid'] ) ) $replyid = intval( $_GET['replyid'] ); else $replyid = false;
		
		if( $replyid ) {
			
			$db->query( "UPDATE " . USERPREFIX . "_pm SET reply=1 WHERE id= '{$replyid}'" );
		
		}

		if( $user_group[$member_id['user_group']]['max_pm_day'] ) { 

			$db->query( "INSERT INTO " . PREFIX . "_sendlog (user, date, flag) values ('{$member_id['name']}', '{$time}', '1')" );

		}
		
		if( $config['mail_pm'] ) {
			
			$mail_template = $db->super_query( "SELECT * FROM " . PREFIX . "_email WHERE name='pm' LIMIT 0,1" );
			$mail = new dle_mail( $config, $mail_template['use_html'] );
			
			if (strpos($config['http_home_url'], "//") === 0) $slink = "https:".$config['http_home_url'];
			elseif (strpos($config['http_home_url'], "/") === 0) $slink = "https://".$_SERVER['HTTP_HOST'].$config['http_home_url'];
			else $slink = $config['http_home_url'];
			
			$slink = $slink . "index.php?do=pm&doaction=readpm&pmid=" . $newpmid;
			
			$mail_template['template'] = stripslashes( $mail_template['template'] );
			$mail_template['template'] = str_replace( "{%username%}", $row['name'], $mail_template['template'] );
			$mail_template['template'] = str_replace( "{%date%}", langdate( "j F Y H:i", $_TIME ), $mail_template['template'] );
			$mail_template['template'] = str_replace( "{%fromusername%}", $member_id['name'], $mail_template['template'] );
			$mail_template['template'] = str_replace( "{%title%}", strip_tags( stripslashes( $subj ) ), $mail_template['template'] );
			$mail_template['template'] = str_replace( "{%url%}", $slink, $mail_template['template'] );
			
			$body = str_replace( '\n', "", $comments );
			$body = str_replace( '\r', "", $body );
			
			$body = stripslashes( stripslashes( $body ) );
			$body = str_replace( "<br />", "\n", $body );
			$body = str_replace( "<br>", "\n", $body );
			$body = strip_tags( $body );
			
			if( $mail_template['use_html'] ) {
				$body = str_replace("\n", "<br>", $body );
			}
			
			$mail_template['template'] = str_replace( "{%text%}", $body, $mail_template['template'] );
			
			$mail->send( $row['email'], $lang['mail_pm'], $mail_template['template'] );
		
		}
		
		echo "{\"success\": \"{$lang['pm_sendok']}\"}";
		die();
			
	} else {
		echo "{\"error\": \"<ul>{$stop}</ul>\"}";
		die();
	}
	

} elseif ($_GET['action'] == "show_send") {

	$name = htmlspecialchars(strip_tags( trim( urldecode($_GET['name'] ) ) ), ENT_QUOTES, $config['charset'] );
	
	if(!$user_group[$member_id['user_group']]['allow_pm'] ) {
		echo "<div id='dlesendpmpopup' title='{$lang['send_pm']} {$name}' style='display:none'><script>DLEPush.error ( '{$lang['pm_err_1']}' );$('#dlesendpmpopup').remove();</script></div>";
		die();
	}
	
	if( $user_group[$member_id['user_group']]['max_pm_day'] ) {
	
		$this_time = time() - 86400;
		$db->query( "DELETE FROM " . PREFIX . "_sendlog WHERE date < '$this_time' AND flag='1'" );
	
		$row = $db->super_query("SELECT COUNT(*) as count FROM " . PREFIX . "_sendlog WHERE user = '{$member_id['name']}' AND flag='1'");
	
		if( $row['count'] >=  $user_group[$member_id['user_group']]['max_pm_day'] ) {
			$lang['pm_err_10'] = str_replace('{max}', $user_group[$member_id['user_group']]['max_pm_day'], $lang['pm_err_10']);
			echo "<div id='dlesendpmpopup' title='{$lang['send_pm']} {$name}' style='display:none'><script>DLEPush.error ( '{$lang['pm_err_10']}' );$('#dlesendpmpopup').remove();</script></div>";
			die();
		}
	}

	$user_group[$member_id['user_group']]['allow_up_image'] = false;
	$user_group[$member_id['user_group']]['video_comments'] = false;
	$user_group[$member_id['user_group']]['media_comments'] = false;
	$text = "";
	$comments_image_uploader_loaded = false;
	
	$id = 0;

	$response = "<input type=\"hidden\" name=\"pm_name\" id=\"pm_name\" value=\"{$name}\">";
	$response .= "<div style=\"padding-bottom:5px;\"><input type=\"text\" name=\"pm_subj\" id=\"pm_subj\" class=\"quick-edit-text\" placeholder=\"{$lang['send_pm_1']}\" /></div>";
	
	if( $config['allow_comments_wysiwyg'] < 1) {

		include_once (DLEPlugins::Check(ENGINE_DIR . '/ajax/bbcode.php'));

		if ( $config['allow_comments_wysiwyg'] == 0 ) $params = "onfocus=\"setNewField(this.name, document.getElementById( 'dle-send-pm' ) )\"";
		else $params = "";
		
		$box_class = "bb-editor";


	} else {
		
		$params = "class=\"ajaxwysiwygeditor\"";
		$box_class = "wseditor dlecomments-editor";

		if ($config['allow_comments_wysiwyg'] == "1") {	

			if( $user_group[$member_id['user_group']]['allow_url'] ) $link_icon = "'insertLink', 'dleleech',"; else $link_icon = "";
			
			if ($user_group[$member_id['user_group']]['allow_image']) {
				if($config['bbimages_in_wysiwyg']) $link_icon .= "'dleimg',"; else $link_icon .= "'insertImage',";
			}
			
		$bb_code = <<<HTML
<script>

      $('.ajaxwysiwygeditor').froalaEditor({
        dle_root: dle_root,
        width: '100%',
        height: '220',
        zIndex: 9990,
        language: '{$lang['language_code']}',
		direction: '{$lang['direction']}',

		htmlAllowedTags: ['div', 'span', 'p', 'br', 'strong', 'em', 'ul', 'li', 'ol', 'b', 'u', 'i', 's', 'a', 'img'],
		htmlAllowedAttrs: ['class', 'href', 'alt', 'src', 'style', 'target', 'data-username', 'data-userurl', 'data-commenttime', 'data-commentuser', 'contenteditable'],
		pastePlain: true,
        imagePaste: false,
        imageUpload: false,
		quickInsertEnabled: false,
		videoInsertButtons: ['videoBack', '|', 'videoByURL'],
		
        toolbarButtonsXS: ['bold', 'italic', 'underline', 'strikeThrough', '|', 'align', 'formatOL', 'formatUL', '|', {$link_icon} 'emoticons', '|', 'dlehide', 'dlequote', 'dlespoiler'],

        toolbarButtonsSM: ['bold', 'italic', 'underline', 'strikeThrough', '|', 'align', 'formatOL', 'formatUL', '|', {$link_icon} 'emoticons', '|', 'dlehide', 'dlequote', 'dlespoiler'],

        toolbarButtonsMD: ['bold', 'italic', 'underline', 'strikeThrough', '|', 'align', 'formatOL', 'formatUL', '|', {$link_icon} 'emoticons', '|', 'dlehide', 'dlequote', 'dlespoiler'],

        toolbarButtons: ['bold', 'italic', 'underline', 'strikeThrough', '|', 'align', 'formatOL', 'formatUL', '|', {$link_icon} 'emoticons', '|', 'dlehide', 'dlequote', 'dlespoiler']

      });
	  
</script>
HTML;

		} else {

			if( $user_group[$member_id['user_group']]['allow_url'] ) $link_icon = "link dleleech "; else $link_icon = "";
			
			$mobile_link_icon = $link_icon;
			
			if ($user_group[$member_id['user_group']]['allow_image']) {
				if($config['bbimages_in_wysiwyg']) $link_icon .= "| dleimage "; else $link_icon .= "| dleimage ";
			}
			
			if( @file_exists( ROOT_DIR . '/templates/'. $config['skin'].'/editor.css' ) ) {
				
				$editor_css = "templates/{$config['skin']}/editor.css?v={$config['cache_id']}";
					
			} else $editor_css = "engine/editor/css/content.css?v={$config['cache_id']}";
			
		$bb_code = <<<HTML

<script>

setTimeout(function() {

	tinymce.remove('textarea.ajaxwysiwygeditor');

	tinyMCE.baseURL = dle_root + 'engine/editor/jscripts/tiny_mce';
	tinyMCE.suffix = '.min';

	tinymce.init({
		selector: 'textarea.ajaxwysiwygeditor',
		language : "{$lang['language_code']}",
		directionality: '{$lang['direction']}',
		element_format : 'html',
		width : "100%",
		height : 240,

		deprecation_warnings: false,
		promotion: false,
		cache_suffix: '?v={$config['cache_id']}',
		
		plugins: "link image lists quickbars dlebutton codesample",
		
		draggable_modal: true,
		toolbar_mode: 'floating',
		contextmenu: false,
		relative_urls : false,
		convert_urls : false,
		remove_script_host : false,
		browser_spellcheck: true,
		extended_valid_elements : "div[align|style|class|data-commenttime|data-commentuser|contenteditable],span[id|data-username|data-userurl|align|style|class|contenteditable],b/strong,i/em,u,s,p[align|style|class|contenteditable],pre[class],code",
		quickbars_insert_toolbar: '',
		quickbars_selection_toolbar: 'bold italic underline | dlequote dlespoiler dlehide',
		
	    formats: {
	      bold: {inline: 'b'},
	      italic: {inline: 'i'},
	      underline: {inline: 'u', exact : true},
	      strikethrough: {inline: 's', exact : true}
	    },

		elementpath: false,
		paste_as_text: true,
		paste_data_images: false,
		statusbar : false,
		branding: false,

		dle_root : dle_root,
		
		menubar: false,
		link_default_target: '_blank',
		editable_class: 'contenteditable',
		noneditable_class: 'noncontenteditable',
		image_dimensions: true,
		
		toolbar: "bold italic underline | alignleft aligncenter alignright | bullist numlist | dleemo {$link_icon} | dlequote codesample dlespoiler dlehide",
		
		mobile: {
			toolbar_mode: "sliding",
			toolbar: "bold italic underline | alignleft aligncenter alignright | bullist numlist | {$mobile_link_icon} dlequote dlespoiler dlehide",
			
		},

		setup: (editor) => {

			const onCompeteAction = (autocompleteApi, rng, value) => {
				editor.selection.setRng(rng);
				editor.insertContent(value);
				autocompleteApi.hide();
			};

			editor.ui.registry.addAutocompleter('getusers', {
			ch: '@',
			minChars: 1,
			columns: 1,
			onAction: onCompeteAction,
			fetch: (pattern) => {

				return new Promise((resolve) => {

					$.get(dle_root + "engine/ajax/controller.php?mod=find_tags", { mode: 'users', term: pattern, skin: dle_skin, user_hash: dle_login_hash }, function(data){
						if ( data.found ) {
							resolve(data.items);
						}
					}, "json");

				});
			}
			});
		},

		content_css : dle_root + "{$editor_css}"

	});

	$('#dlesendpmpopup').dialog( "option", "position", { my: "center", at: "center", of: window } );
	
}, 100);

</script>
HTML;


		}
	}

	$response .= <<<HTML
	<div class="{$box_class}">
		{$bb_code}
		<textarea name="pm_text" id="pm_text" style="width:100%;height:250px;" {$params}></textarea>
	</div>
	<div style="padding-top:5px;">
		<label class="pm_outbox_copy"><input type="checkbox" name="outboxcopy" id="outboxcopy" value="1">{$lang['send_pm_2']}</label>
	</div>
HTML;

	if( $user_group[$member_id['user_group']]['pm_question'] ) {
		$question = $db->super_query("SELECT id, question FROM " . PREFIX . "_question ORDER BY RAND() LIMIT 1");
	
		$_SESSION['question'] = $question['id'];
	
		$question = htmlspecialchars( stripslashes( $question['question'] ), ENT_QUOTES, $config['charset'] );
		
		$response .= <<<HTML
	<div id="dle-question" style="padding-top:5px;">{$question}</div>
	<div><input type="text" name="pm_question_answer" id="pm_question_answer" placeholder="{$lang['question_hint']}" class="quick-edit-text"></div>
HTML;
	
	}

	if( $user_group[$member_id['user_group']]['captcha_pm'] ) {
	
		if ( $config['allow_recaptcha'] ) {

			if( $config['allow_recaptcha'] == 2) {
				
				$response .= <<<HTML
		<input type="hidden" name="pm-recaptcha-response" id="pm-recaptcha-response" data-key="{$config['recaptcha_public_key']}" value="">
		<script>
		if ( typeof grecaptcha === "undefined"  ) {
		
			$.getScript( "https://www.google.com/recaptcha/api.js?render={$config['recaptcha_public_key']}");
	
		}
		</script>
HTML;

			} elseif($config['allow_recaptcha'] == 3 )  {
				
				$response .= <<<HTML
		<div id="dle_pm_recaptcha" style="padding-top:5px;height:78px;"></div>
		<script>
		<!--
		var recaptcha_widget;
		
		if ( typeof hcaptcha === "undefined"  ) {
		
			$.getScript( "https://js.hcaptcha.com/1/api.js?hl={$lang['language_code']}&render=explicit").done(function () {
			
				var setIntervalID = setInterval(function () {
					if (window.hcaptcha) {
						clearInterval(setIntervalID);
						recaptcha_widget = hcaptcha.render('dle_pm_recaptcha', {'sitekey' : '{$config['recaptcha_public_key']}', 'theme':'{$config['recaptcha_theme']}'});
					};
				}, 300);
			});
	
		} else {
			recaptcha_widget = hcaptcha.render('dle_pm_recaptcha', {'sitekey' : '{$config['recaptcha_public_key']}', 'theme':'{$config['recaptcha_theme']}'});
		}
		//-->
		</script>
HTML;
			} elseif ($config['allow_recaptcha'] == 4) {

				$response .= <<<HTML
		<div id="dle_pm_recaptcha" style="padding-top:5px;height:78px;"></div>
		<script>
		<!--
		var recaptcha_widget;
		
		if ( typeof turnstile === "undefined"  ) {
		
			$.getScript( "https://challenges.cloudflare.com/turnstile/v0/api.js?compat=recaptcha&render=explicit").done(function () {
			
				var setIntervalID = setInterval(function () {
					if (window.turnstile) {
						clearInterval(setIntervalID);
						recaptcha_widget = turnstile.render('#dle_pm_recaptcha', {'sitekey' : '{$config['recaptcha_public_key']}', 'theme':'{$config['recaptcha_theme']}', 'language':'{$lang['language_code']}'});
					};
				}, 300);
			});
	
		} else {
			recaptcha_widget = turnstile.render('#dle_pm_recaptcha', {'sitekey' : '{$config['recaptcha_public_key']}', 'theme':'{$config['recaptcha_theme']}', 'language':'{$lang['language_code']}'});
		}
		//-->
		</script>
HTML;
			} else {
	
				$response .= <<<HTML
		<div id="dle_pm_recaptcha" style="padding-top:5px;height:78px;"></div>
		<script>
		<!--
		var recaptcha_widget;
		
		if ( typeof grecaptcha === "undefined"  ) {
		
			$.getScript( "https://www.google.com/recaptcha/api.js?hl={$lang['language_code']}&render=explicit").done(function () {
			
				var setIntervalID = setInterval(function () {
					if (window.grecaptcha) {
						clearInterval(setIntervalID);
						recaptcha_widget = grecaptcha.render('dle_pm_recaptcha', {'sitekey' : '{$config['recaptcha_public_key']}', 'theme':'{$config['recaptcha_theme']}'});
					};
				}, 300);
			});
	
		} else {
			recaptcha_widget = grecaptcha.render('dle_pm_recaptcha', {'sitekey' : '{$config['recaptcha_public_key']}', 'theme':'{$config['recaptcha_theme']}'});
		}
		//-->
		</script>
HTML;
	
			}
			
		} else {
	
			$response .= <<<HTML
	<div style="padding-top:5px;" class="dle-captcha"><a onclick="reload_pm(); return false;" title="{$lang['reload_code']}" href="#"><span id="dle-captcha_pm"><img src="{$config['http_home_url']}engine/modules/antibot/antibot.php" alt="{$lang['reload_code']}" width="160" height="80" /></span></a>
	<input class="ui-widget-content ui-corner-all sec-code" type="text" name="sec_code" id="sec_code_pm" placeholder="{$lang['captcha_hint']}">
	</div>
	<script>
	<!--
	function reload_pm () {
	
		var rndval = new Date().getTime(); 
	
		document.getElementById('dle-captcha_pm').innerHTML = '<img src="{$config['http_home_url']}engine/modules/antibot/antibot.php?rndval=' + rndval + '" width="160" height="80" alt="" />';
		document.getElementById('sec_code_pm').value = '';
	};
	//-->
	</script>
HTML;
	
		}
	}	
	

	echo "<div id=\"dlesendpmpopup\" title=\"{$lang['send_pm']} {$name}\" style=\"display:none\"><form  method=\"post\" name=\"dle-send-pm\" id=\"dle-send-pm\">{$response}</form></div>";
	die();

} else {

	function del_tpl( $matches=array() ) {
		global $tpl;

		$tpl->copy_template = $matches[1];
	}
	
	$tpl = new dle_template( );
	$tpl->dir = ROOT_DIR . '/templates/' . $config['skin'];
	define( 'TEMPLATE_DIR', $tpl->dir );
	
	$name = htmlspecialchars(strip_tags( trim( $_POST['name'] ) ), ENT_QUOTES, $config['charset'] );
	$subj = htmlspecialchars(strip_tags( trim( $_POST['subj'] ) ), ENT_QUOTES, $config['charset'] );
	
	if( $config['allow_comments_wysiwyg'] < 1) {
		
		if ($config['allow_comments_wysiwyg'] == "-1") $parse->allowbbcodes = false;
		
		$text = $parse->BB_Parse( $parse->process( $_POST['text'] ), false );

	} else {
		
		$parse->wysiwyg = true;

		$text = $parse->BB_Parse( $parse->process( $_POST['text'] ) );
	}
	
	$tpl->load_template( 'pm.tpl' );
	
	preg_replace_callback( "'\\[readpm\\](.*?)\\[/readpm\\]'is", "del_tpl", $tpl->copy_template );
	
			if( strpos( $tpl->copy_template, "[xfvalue_" ) !== false ) $xfound = true;
			else $xfound = false;
			
			if( $xfound ) { 
	
				$xfields = xfieldsload( true );
	
				$xfieldsdata = xfieldsdataload( $member_id['xfields'] );
					
				foreach ( $xfields as $value ) {
					$preg_safe_name = preg_quote( $value[0], "'" );
						
					if( $value[5] != 1 OR $member_id['user_group'] == 1 OR ($is_logged AND $member_id['name'] == $row['user_from']) ) {
						if( empty( $xfieldsdata[$value[0]] ) ) {
							$tpl->copy_template = preg_replace( "'\\[xfgiven_{$preg_safe_name}\\](.*?)\\[/xfgiven_{$preg_safe_name}\\]'is", "", $tpl->copy_template );
						} else {
							$tpl->copy_template = preg_replace( "'\\[xfgiven_{$preg_safe_name}\\](.*?)\\[/xfgiven_{$preg_safe_name}\\]'is", "\\1", $tpl->copy_template );
						}
						$tpl->set( "[xfvalue_{$value[0]}]", stripslashes( $xfieldsdata[$value[0]] ) );
					} else {
						$tpl->copy_template = preg_replace( "'\\[xfgiven_{$preg_safe_name}\\](.*?)\\[/xfgiven_{$preg_safe_name}\\]'is", "", $tpl->copy_template );
						$tpl->copy_template = preg_replace( "'\\[xfvalue_{$preg_safe_name}\\]'i", "", $tpl->copy_template );
					}
				}
			}
	
			$tpl->set( '{author}', $member_id['name'] );
			$tpl->set( '[reply]', "<a href=\"#\">" );
			$tpl->set( '[/reply]', "</a>" );
			$tpl->set( '[del]', "<a href=\"#\">" );
			$tpl->set( '[/del]', "</a>" );
			$tpl->set( '[ignore]', "<a href=\"#\">" );
			$tpl->set( '[/ignore]', "</a>" );
			$tpl->set( '[complaint]', "<a href=\"#\">" );
			$tpl->set( '[/complaint]', "</a>" );

			$tpl->set( '[online]', "" );
			$tpl->set( '[/online]', "" );
			$tpl->set_block( "'\\[offline\\](.*?)\\[/offline\\]'si", "" );
	
			if( $member_id['signature'] and $user_group[$member_id['user_group']]['allow_signature'] ) {
					
				$tpl->set_block( "'\\[signature\\](.*?)\\[/signature\\]'si", "\\1" );
				$tpl->set( '{signature}', stripslashes( $member_id['signature'] ) );
				
			} else {
				$tpl->set_block( "'\\[signature\\](.*?)\\[/signature\\]'si", "" );
			}
	
			if( $user_group[$member_id['user_group']]['icon'] ) $tpl->set( '{group-icon}', "<img src=\"" . $user_group[$member_id['user_group']]['icon'] . "\" border=\"0\" alt=\"\" />" );
			else $tpl->set( '{group-icon}', "" );
	
			$tpl->set( '{group-name}', $user_group[$member_id['user_group']]['group_prefix'].$user_group[$member_id['user_group']]['group_name'].$user_group[$member_id['user_group']]['group_suffix'] );
			$tpl->set( '{news-num}', intval( $member_id['news_num'] ) );
			$tpl->set( '{comm-num}', intval( $member_id['comm_num'] ) );

			if ( count(explode("@", $member_id['foto'])) == 2 ) {
				$tpl->set( '{foto}', 'https://www.gravatar.com/avatar/' . md5(trim($member_id['foto'])) . '?s=' . intval($user_group[$member_id['user_group']]['max_foto']) );
			
			} else {
			
				if( $member_id['foto'] ) {
					
					if (strpos($member_id['foto'], "//") === 0) $avatar = "http:".$member_id['foto']; else $avatar = $member_id['foto'];
		
					$avatar = @parse_url ( $avatar );

					if( $avatar['host'] ) {
						
						$tpl->set( '{foto}', $member_id['foto'] );
						
					} else $tpl->set( '{foto}', $config['http_home_url'] . "uploads/fotos/" . $member_id['foto'] );
					
				} else $tpl->set( '{foto}', "{THEME}/dleimages/noavatar.png" );
		
			}
	
			$tpl->set( '{date}', "--" );
	
			if($member_id['reg_date'] ) $tpl->set( '{registration}', langdate( "j.m.Y", $member_id['reg_date'] ) );
			else $tpl->set( '{registration}', '--' );

			$tpl->set( '{subj}', $subj );
			$tpl->set( '{text}', stripslashes($text) );
	
	$tpl->compile( 'content' );
	$tpl->clear();
	
	$tpl->result['content'] = preg_replace ( "#\[hide(.*?)\]#i", "", $tpl->result['content'] );
	$tpl->result['content'] = str_ireplace( "[/hide]", "", $tpl->result['content']);
	$tpl->result['content'] = str_replace( '{THEME}', $config['http_home_url'] . 'templates/' . $config['skin'], $tpl->result['content'] );

	$tpl->result['content'] = "<div id=\"blind-animation\" style=\"display:none\">".$tpl->result['content']."<div>";
	
	echo $tpl->result['content'];
}

?>
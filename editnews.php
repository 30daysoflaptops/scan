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
 File: editnews.php
-----------------------------------------------------
 Use: AJAX news edit
=====================================================
*/

if(!defined('DATALIFEENGINE')) {
	header( "HTTP/1.1 403 Forbidden" );
	header ( 'Location: ../../' );
	die( "Hacking attempt!" );
}

$parse = new ParseFilter();

if( !$is_logged ) die( "error" );

$id = intval( $_REQUEST['id'] );

if( !$id ) die( "error" );

if( $_REQUEST['action'] == "edit" ) {

	$dark_theme = "";

	if (defined('TEMPLATE_DIR')) {
		$template_dir = TEMPLATE_DIR;
	} else $template_dir = ROOT_DIR . "/templates/" . $config['skin'];

	if (is_file($template_dir . "/info.json")) {

		$data = json_decode(trim(file_get_contents($template_dir . "/info.json")), true);

		if (isset($data['type']) and $data['type'] == "dark") {
			$dark_theme = " dle_theme_dark";
		}
	}

	$row = $db->super_query( "SELECT p.id, p.autor, p.date, p.short_story, p.full_story, p.xfields, p.title, p.category, p.approve, p.allow_br, e.reason FROM " . PREFIX . "_post p LEFT JOIN " . PREFIX . "_post_extras e ON (p.id=e.news_id) WHERE p.id = '$id'" );
	
	if( $id != $row['id'] ) die( "error" );
	
	$cat_list = explode( ',', $row['category'] );
	
	$have_perm = 0;

	if( $user_group[$member_id['user_group']]['allow_edit'] and $row['autor'] == $member_id['name'] ) {
		$have_perm = 1;
	}
	
	if( $user_group[$member_id['user_group']]['allow_all_edit'] ) {
		$have_perm = 1;
		
		$allow_list = explode( ',', $user_group[$member_id['user_group']]['cat_add'] );
		
		foreach ( $cat_list as $selected ) {
			if( $allow_list[0] != "all" AND !in_array( $selected, $allow_list ) ) $have_perm = 0;
		}
	}
	
	if( $user_group[$member_id['user_group']]['max_edit_days'] ) {
		$newstime = strtotime( $row['date'] );
		$maxedittime = $_TIME - ($user_group[$member_id['user_group']]['max_edit_days'] * 3600 * 24);
		if( $maxedittime > $newstime ) $have_perm = 0;
	}
	
	if( ($member_id['user_group'] == 1) ) {
		$have_perm = 1;
	}

	
	if( !$have_perm ) die( $lang['editnews_error'] );

	if( !$user_group[$member_id['user_group']]['allow_html'] ) $config['allow_quick_wysiwyg'] = false;
	
	$news_txt = $row['short_story'];
	$full_txt = $row['full_story'];
	$author = urlencode($row['autor']);

	if( $row['allow_br'] AND !$config['allow_quick_wysiwyg'] ) {
		
		$news_txt = $parse->decodeBBCodes( $news_txt, false );
		$full_txt = $parse->decodeBBCodes( $full_txt, false );
		$fix_br = "checked";
	
	} else {
		
		if( $config['allow_quick_wysiwyg'] ) {
			$news_txt = $parse->decodeBBCodes( $news_txt, true, $config['allow_quick_wysiwyg'] );
			$full_txt = $parse->decodeBBCodes( $full_txt, true, $config['allow_quick_wysiwyg'] );
		} else { 
			$news_txt = $parse->decodeBBCodes( $news_txt, true, false );
			$full_txt = $parse->decodeBBCodes( $full_txt, true, false );

		}
		
		$fix_br = "";
	
	}

	if( $row['approve'] ) {
		$fix_approve = "checked";
	} else $fix_approve = "";
	
	$row['title'] = $parse->decodeBBCodes( $row['title'], false );

	$xfields = xfieldsload();
	$xfieldsdata = xfieldsdataload ($row['xfields']);
	$xfbuffer = "";

	$config['file_chunk_size'] =  number_format(floatval($config['file_chunk_size']), 1, '.', '');
	if ($config['file_chunk_size'] < 1) $config['file_chunk_size'] = '1.5';

	foreach ($xfields as $name => $value) {
		$fieldname = $value[0];
		$fieldcount = md5($fieldname);

		if ( isset($xfieldsdata[$value[0]]) OR $config['quick_edit_mode'] ) {
			
			if( isset($xfieldsdata[$value[0]]) ) $fieldvalue = $xfieldsdata[$value[0]];
			else $fieldvalue = '';

		} else continue;

		if( $value[2] ) {

			$cats_allowed = false;

			foreach ($cat_list as $cats_explode) {
				if (in_array($cats_explode, explode(",", $value[2]))) {
					$cats_allowed = true;
				}
			}

			if(!$cats_allowed) continue;

		}

		if( $value[19] ) {
			
			$value[19] = explode( ',', $value[19] );
			
			if( $value[19][0] AND !in_array( $member_id['user_group'], $value[19] ) ) {
				continue;
			}
			
		}
		
		$value[1] = htmlspecialchars($value[1], ENT_QUOTES, $config['charset'] );
		 
		$fieldvalue = str_ireplace( "&#123;title", "{title", $fieldvalue );
		$fieldvalue = str_ireplace( "&#123;short-story", "{short-story", $fieldvalue );
		$fieldvalue = str_ireplace( "&#123;full-story", "{full-story", $fieldvalue );

		if ($value[8] OR $value[6] OR $value[3] == "image" OR $value[3] == "imagegalery" OR $value[3] == "file" OR $value[3] == "datetime") {
			
			$fieldvalue = html_entity_decode(stripslashes($fieldvalue), ENT_QUOTES, $config['charset']);
			$fieldvalue = htmlspecialchars($fieldvalue, ENT_QUOTES, $config['charset'] );
			
		} elseif($value[3] == "htmljs") {
			
			 $fieldvalue = htmlspecialchars($fieldvalue, ENT_QUOTES, $config['charset'] );
			 
		} else {
			
			if( $row['allow_br'] AND !$config['allow_quick_wysiwyg'] ) {
				
				$fieldvalue = $parse->decodeBBCodes( $fieldvalue, false );
			
			} else {
				
				if( $config['allow_quick_wysiwyg'] ) $fieldvalue = $parse->decodeBBCodes( $fieldvalue, true, $config['allow_quick_wysiwyg'] );
				else $fieldvalue = $parse->decodeBBCodes( $fieldvalue, true, false );
			
			}

		}
		

		if ($value[3] == "textarea") {
			
			if ( $value[7] ) {
				
				if ( !$config['allow_quick_wysiwyg'] ) {
	
					$params = "onfocus=\"setNewField(this.id, document.ajaxnews{$id})\" class=\"quick-edit-textarea\" "; 
					$class_name = "bb-editor";
					$panel="<!--panel-->";
					
				} else {
	
					$params = "class=\"wysiwygeditor\" ";
					$class_name = "wseditor dlefastedit-editor";
					$panel="";
				}
				
			} else {
				$params = "class=\"quick-edit-textarea\" ";
				$class_name = "";
				$panel="";
			}
		
			 $xfbuffer .= "<div class=\"xfieldsrow\">{$value[1]}:<br /><div class=\"{$class_name}{$dark_theme}\">{$panel}<textarea name=\"xfield[{$fieldname}]\" id=\"xf_$fieldname\" {$params}>{$fieldvalue}</textarea></div></div>";

		} elseif ($value[3] == "htmljs") {
			
			 $xfbuffer .= "<div class=\"xfieldsrow\">{$value[1]}:<br /><textarea name=\"xfield[{$fieldname}]\" id=\"xf_$fieldname\" class=\"quick-edit-textarea\">{$fieldvalue}</textarea></div>";

		} elseif ($value[3] == "text") {

			$fieldvalue = str_replace('&amp;', '&', $fieldvalue);

			$xfbuffer .= "<div class=\"xfieldsrow\"><div class=\"xfieldscolleft\">{$value[1]}:</div><div class=\"xfieldscolright\"><input type=\"text\" name=\"xfield[{$fieldname}]\" id=\"xfield[{$fieldname}]\" value=\"{$fieldvalue}\" class=\"quick-edit-text\" /></div></div>";

		} elseif ($value[3] == "datetime") {

			if ($value[23] == 1) {
				$e_params = "data-rel=\"calendardate\" ";
			} elseif ($value[23] == 2) {
				$e_params = "data-rel=\"calendartime\" ";
			} else {
				$e_params = "data-rel=\"calendardatetime\" ";
			}

			$xfbuffer .= "<div class=\"xfieldsrow\"><div class=\"xfieldscolleft\">{$value[1]}:</div><div class=\"xfieldscolright\"><input type=\"text\" name=\"xfield[{$fieldname}]\" id=\"xfield[{$fieldname}]\" value=\"{$fieldvalue}\" class=\"quick-edit-datetime\" {$e_params}></div></div>";

		} elseif ($value[3] == "select") {
			
			if ($value[34]) {
				$sel_multiple = "data-placeholder=\" \" multiple";
			} else {
				$sel_multiple = "";
			}

			$fieldvalue = str_replace('&amp;', '&', $fieldvalue);
			$fieldvalue = explode(',', $fieldvalue);
			
			$xfbuffer .= "<div class=\"xfieldsrow\"><div class=\"xfieldscolleft\">{$value[1]}:</div><div class=\"xfieldscolright\"><select name=\"xfield[{$fieldname}][]\" class=\"quick-edit-select\" {$sel_multiple}>";

	        foreach (explode("\r\n", htmlspecialchars($value[4], ENT_QUOTES, $config['charset'] )) as $index => $value) {
			  
			  $value = explode("|", $value);
			  if( count($value) < 2) $value[1] = $value[0];
			  
	          $xfbuffer .= "<option value=\"$index\"" . (in_array($value[0], $fieldvalue) ? " selected" : "") . ">$value[1]</option>\r\n";
	        }

			$xfbuffer .= "</select></div></div>";

		} elseif ($value[3] == "yesorno") {
			
			$fieldvalue = intval($fieldvalue);
			
			$xfbuffer .= "<div class=\"xfieldsrow\"><div class=\"xfieldscolleft\">{$value[1]}:</div><div class=\"xfieldscolright\"><input type=\"checkbox\" name=\"xfield[{$fieldname}]\" value=\"1\"" . ($fieldvalue ? "checked " : "") . "></div></div>";

		} elseif( $value[3] == "image" ) {
			
			$max_file_size = (int)$value[10] * 1024;
			
			if( $fieldvalue ) {
				
				$temp_array = explode('|', $fieldvalue);
					
				if (count($temp_array) == 1 OR count($temp_array) == 5 ){
						
					$temp_alt = '';
					$temp_value = implode('|', $temp_array );
						
				} else {
						
					$temp_alt = $temp_array[0];
					unset($temp_array[0]);
					$temp_value =  implode('|', $temp_array );
						
				}
			
				$dataimage = get_uploaded_image_info($temp_value);
	
				if( $value[12] AND $dataimage->thumb ) {
					$img_url = 	$dataimage->thumb;
				} else {
					$img_url = 	$dataimage->url;
				}
				
				$filename = explode("_", $dataimage->name);
				if( count($filename) > 1 ) unset($filename[0]);
				$filename = implode("_", $filename);
				
				$xf_id = md5($temp_value);
				$up_image = "<div class=\"file-preview-card uploadedfile\" id=\"xf_{$xf_id}\" data-id=\"{$temp_value}\" data-alt=\"{$temp_alt}\"><div class=\"active-ribbon\"><span><i class=\"mediaupload-icon mediaupload-icon-ok\"></i></span></div><div class=\"file-content select-disable\"><img src=\"{$img_url}\" class=\"file-preview-image\"></div><div class=\"file-footer\"><div class=\"file-footer-caption\"><div class=\"file-caption-info\" rel=\"tooltip\" title=\"{$filename}\">{$filename}</div><div class=\"file-size-info\">{$dataimage->dimension} ({$dataimage->size})</div></div><div class=\"file-footer-bottom\"><div class=\"file-preview\"><a onclick=\"xfaddalt(\\'".$xf_id."\\', \\'".$fieldname."\\');return false;\" href=\"#\" rel=\"tooltip\" title=\"{$lang['xf_img_descr']}\"><i class=\"mediaupload-icon mediaupload-icon-edit\"></i></a></div><div class=\"file-delete\"><a href=\"#\" onclick=\"xfimagedelete(\\'".$fieldname."\\',\\'".$temp_value."\\');return false;\" href=\"#\"><i class=\"mediaupload-icon mediaupload-icon-trash\"></i></a></div></div></div></div>";
				
			} else $up_image = "";

$max_file_size = number_format($max_file_size, 0, '', '');

$uploadscript = <<<HTML

$('#xfupload_{$fieldname}').html('<div class="qq-uploader"><div id="uploadedfile_{$fieldname}">{$up_image}</div><div style="position: relative;"><div id="upload_button_{$fieldname}" class="qq-upload-button btn btn-green bg-teal btn-sm btn-raised" style="width: auto;">{$lang['xfield_xfim']}</div></div></div>');

file_uploaders['{$fieldname}'] = new plupload.Uploader({

    runtimes : 'html5',
    file_data_name: "qqfile",
    browse_button: 'upload_button_{$fieldname}',
    container: document.getElementById('xfupload_{$fieldname}'),
	drop_element: document.getElementById('xfupload_{$fieldname}'),
    url: dle_root + "engine/ajax/controller.php?mod=upload",
	multipart_params: {"subaction" : "upload", "news_id" : "{$row['id']}", "area" : "xfieldsimage", "author" : "{$author}", "xfname" : "{$fieldname}", "user_hash" : "{$dle_login_hash}"},
	multi_selection: false,
	chunk_size: '{$config['file_chunk_size']}mb',
     
    filters : {
        max_file_size : '{$max_file_size}',
        mime_types: [
            {title : "Image files", extensions : "gif,jpg,jpeg,png,bmp,webp,avif"}
        ]
    },
     
 
    init: {
 
        FilesAdded: function(up, files) {
		
            plupload.each(files, function(file) {
				$('<div id="uploadfile-'+file.id+'" class="file-box"><span class="qq-upload-file-status">{$lang['media_upload_st6']}</span><span class="qq-upload-file">&nbsp;'+file.name+'</span>&nbsp;<span class="qq-status" ><span class="qq-upload-spinner"></span> <span class="qq-upload-size"></span></span><div class="progress"><div class="progress-bar progress-blue" style="width: 0%"><span>0%</span></div></div></div>').appendTo('#xfupload_{$fieldname}');
            });
			
			up.start();
			up.refresh();
        },
 
        UploadProgress: function(up, file) {
		
			  $('#uploadfile-'+file.id+' .qq-upload-size').text(plupload.formatSize(file.loaded) + ' {$lang['media_upload_st8']} ' + plupload.formatSize(file.origSize));
			  $('#uploadfile-'+file.id+' .progress-bar').css( "width", file.percent + '%' );
			  $('#uploadfile-'+file.id+' .qq-upload-spinner').css( "display", "inline-block");

        },
		
		FileUploaded: function(up, file, result) {
		
				try {
				   var response = JSON.parse(result.response);
				} catch (e) {
					var response = '';
				}
				
				if( result.status == 200 ) {
				
					if ( response.success ) {
					
						var returnbox = response.returnbox;
						var returnval = response.xfvalue;

						returnbox = returnbox.replace(/&lt;/g, "<");
						returnbox = returnbox.replace(/&gt;/g, ">");
						returnbox = returnbox.replace(/&amp;/g, "&");

						$('#uploadfile-'+file.id+' .qq-status').html('{$lang['media_upload_st9']}');
						$('#uploadedfile_{$fieldname}').html( returnbox );
						$('#xf_{$fieldname}').val(returnval);

						$('#upload_button_{$fieldname}').attr("disabled","disabled");
						
						up.disableBrowse(true);
						
						setTimeout(function() {
						
							$('#uploadfile-'+file.id).fadeOut('slow', function() { $(this).remove(); up.refresh();});
							
						}, 1000);
						
						$('#mediaupload').remove();

					} else {
					
						$('#uploadfile-'+file.id+' .qq-status').html('{$lang['media_upload_st10']}');

						if( response.error ) $('#uploadfile-'+file.id+' .qq-status').append( '<br><span style="color:red">' + response.error + '</span>' );

						setTimeout(function() {
							$('#uploadfile-'+file.id).fadeOut('slow', function() { $(this).remove(); up.refresh();});
						}, 10000);
					}
						
				} else {
				
					$('#uploadfile-'+file.id+' .qq-status').append( '<br><span style="color:red">HTTP Error:' + result.status + '</span>' );
					
					setTimeout(function() {
						$('#uploadfile-'+file.id).fadeOut('slow', function() { $(this).remove(); up.refresh();});
					}, 10000);
				}

				up.refresh();
				
        },
		
        Error: function(up, err) {
			var type_err = '{$lang['media_upload_st11']}';
			var size_err = '{$lang['media_upload_st12']}';
			
			type_err = type_err.replace('{file}', err.file.name);
			type_err = type_err.replace('{extensions}', up.settings.filters.mime_types[0].extensions);
			size_err = size_err.replace('{file}', err.file.name);
			size_err = size_err.replace('{sizeLimit}', plupload.formatSize(up.settings.filters.max_file_size));
			
			if(err.code == '-600') {
			
				DLEPush.error(size_err);
				
			} else if(err.code == '-601') {
			
				DLEPush.error(type_err);
				
			} else {
			
				DLEPush.error(err.message);
				
			}
		
        }
    }
});

setTimeout(function() {
	
	file_uploaders['{$fieldname}'].init();

	if($('#xf_{$fieldname}').val() != "" ) {
		$('#upload_button_{$fieldname}').attr("disabled","disabled");

		setTimeout(function() {
			file_uploaders['{$fieldname}'].disableBrowse(true);
		}, 100);

	}
}, 300);
	
	if ( typeof Sortable != "undefined"  ) {
	
		var sortable_{$fieldcount} = Sortable.create(document.getElementById('uploadedfile_{$fieldname}'), {
		  group: {
			name: 'xfuploadedimages',
			put: function (to, from) {

				if(from.options.group.name != to.options.group.name ){
					return false;
				}

				return to.el.children.length < 1;
			}
		  },
		  handle: '.file-content',
		  draggable: '.uploadedfile',
		  onSort: function (evt) {
				
				if( sortable_{$fieldcount}.el.children.length ) {
					$('#xfupload_{$fieldname} .qq-upload-button').attr("disabled","disabled");
					file_uploaders['{$fieldname}'].disableBrowse(true);
				} else {
					$('#xfupload_{$fieldname} .qq-upload-button').removeAttr('disabled');
					file_uploaders['{$fieldname}'].disableBrowse(false);
				}
				
				xfsinc('{$fieldname}');
				file_uploaders['{$fieldname}'].refresh();
		  },
		  animation: 150
		});
		
	}
	
HTML;
			
			$xfbuffer .= "<div class=\"xfieldsrow\"><div class=\"xfieldscolleft\">{$value[1]}:</div><div class=\"xfieldscolright\"><div id=\"xfupload_{$fieldname}\"></div><input type=\"hidden\" name=\"xfield[$fieldname]\" id=\"xf_$fieldname\" value=\"{$fieldvalue}\" /><script>{$uploadscript}</script></div></div>";

		} elseif( $value[3] == "imagegalery" ) {

	    $max_file_size = (int)$value[10] * 1024;

		if( $fieldvalue ) {
			$fieldvalue_arr = explode(',', $fieldvalue);
			$up_image = array();
			
			foreach ($fieldvalue_arr as $temp_value) {
				
				$temp_value = trim($temp_value);
				
				if($temp_value == "") continue;
				
				$temp_array = explode('|', $temp_value);
					
				if (count($temp_array) == 1 OR count($temp_array) == 5 ){
						
					$temp_alt = '';
					$temp_value = implode('|', $temp_array );
						
				} else {
						
					$temp_alt = $temp_array[0];
					unset($temp_array[0]);
					$temp_value =  implode('|', $temp_array );
						
				}
			
				$dataimage = get_uploaded_image_info($temp_value);
				
				if( $value[12] AND $dataimage->thumb ) {
					$img_url = 	$dataimage->thumb;
				} else {
					$img_url = 	$dataimage->url;
				}
				
				$filename = explode("_", $dataimage->name);
				if( count($filename) > 1 ) unset($filename[0]);
				$filename = implode("_", $filename);
				
				$xf_id = md5($temp_value);
				$up_image[] = "<div class=\"file-preview-card uploadedfile\" id=\"xf_{$xf_id}\" data-id=\"{$temp_value}\" data-alt=\"{$temp_alt}\"><div class=\"active-ribbon\"><span><i class=\"mediaupload-icon mediaupload-icon-ok\"></i></span></div><div class=\"file-content select-disable\"><img src=\"{$img_url}\" class=\"file-preview-image\"></div><div class=\"file-footer\"><div class=\"file-footer-caption\"><div class=\"file-caption-info\" rel=\"tooltip\" title=\"{$filename}\">{$filename}</div><div class=\"file-size-info\">{$dataimage->dimension} ({$dataimage->size})</div></div><div class=\"file-footer-bottom\"><div class=\"file-preview\"><a onclick=\"xfaddalt(\\'".$xf_id."\\', \\'".$fieldname."\\');return false;\" href=\"#\" rel=\"tooltip\" title=\"{$lang['xf_img_descr']}\"><i class=\"mediaupload-icon mediaupload-icon-edit\"></i></a></div><div class=\"file-delete\"><a onclick=\"xfimagegalerydelete_{$fieldcount}(\\'".$fieldname."\\',\\'".$temp_value."\\', \\'".$xf_id."\\');return false;\" href=\"#\"><i class=\"mediaupload-icon mediaupload-icon-trash\"></i></a></div></div></div></div>";

			}
			
			$totaluploadedfiles = count($up_image);
			$up_image = implode($up_image);

			
		} else { $up_image = ""; $totaluploadedfiles = 0; }
		
		if (!$value[5]) { 
			$params = "rel=\"essential\" "; 
			$uid = "uid=\"essential\" "; 

		} else { 

			$params = ""; 
			$uid = "";

		}
		
$max_file_size = number_format($max_file_size, 0, '', '');

$uploadscript = <<<HTML
	var maxallowfiles_{$fieldcount} = {$value[16]};
	var totaluploaded_{$fieldcount} = {$totaluploadedfiles};
	var totalqueue_{$fieldcount} = 0;
	
	function xfimagegalerydelete_{$fieldcount} ( xfname, xfvalue, id )
	{
		DLEconfirm( '{$lang['image_delete']}', '{$lang['p_info']}', function () {
		
			ShowLoading('');

			$.post(dle_root + 'engine/ajax/controller.php?mod=upload', { subaction: 'deluploads', user_hash: '{$dle_login_hash}', news_id: '{$row['id']}', author: '{$author}', 'images[]' : xfvalue }, function(data){
	
				HideLoading('');

				$('#xf_'+id).remove();
				totaluploaded_{$fieldcount} --;
				xfsinc('{$fieldname}');
				
				$('#xfupload_' + xfname + ' .qq-upload-button').removeAttr('disabled');
				file_uploaders[xfname].disableBrowse(false);
				file_uploaders[xfname].refresh();

				$('#mediaupload').remove();
				
			});
			
		} );
		
		return false;

	};
	
$('#xfupload_{$fieldname}').html('<div class="qq-uploader"><div id="uploadedfile_{$fieldname}">{$up_image}</div><div style="position: relative;"><div id="upload_button_{$fieldname}" class="qq-upload-button btn btn-green bg-teal btn-sm btn-raised" style="width: auto;">{$lang['xfield_xfimg']}</div></div></div>');

file_uploaders['{$fieldname}'] = new plupload.Uploader({

    runtimes : 'html5',
    file_data_name: "qqfile",
    browse_button: 'upload_button_{$fieldname}',
    container: document.getElementById('xfupload_{$fieldname}'),
	drop_element: document.getElementById('xfupload_{$fieldname}'),
    url: dle_root + "engine/ajax/controller.php?mod=upload",
	multipart_params: {"subaction" : "upload", "news_id" : "{$row['id']}", "area" : "xfieldsimagegalery", "author" : "{$author}", "xfname" : "{$fieldname}", "user_hash" : "{$dle_login_hash}"},

	chunk_size: '{$config['file_chunk_size']}mb',
     
    filters : {
        max_file_size : '{$max_file_size}',
        mime_types: [
            {title : "Image files", extensions : "gif,jpg,jpeg,png,bmp,webp,avif"}
        ]
    },
     
 
    init: {
 
        FilesAdded: function(up, files) {
		
            plupload.each(files, function(file) {
			
				totalqueue_{$fieldcount} ++;
				
				if(maxallowfiles_{$fieldcount} && (totaluploaded_{$fieldcount} + totalqueue_{$fieldcount} ) > maxallowfiles_{$fieldcount} ) {
					totalqueue_{$fieldcount} --;
				
					$('#xfupload_{$fieldname} .qq-upload-button').attr("disabled","disabled");
					
					up.disableBrowse(true);
					up.removeFile(file);

				} else {
					$('<div id="uploadfile-'+file.id+'" class="file-box"><span class="qq-upload-file-status">{$lang['media_upload_st6']}</span><span class="qq-upload-file">&nbsp;'+file.name+'</span>&nbsp;<span class="qq-status"> <span class="qq-upload-spinner"></span> <span class="qq-upload-size"></span></span><div class="progress"><div class="progress-bar progress-blue" style="width: 0%"><span>0%</span></div></div></div>').appendTo('#xfupload_{$fieldname}');
				}
					
            });
			
			up.start();
			up.refresh();
        },
 
        UploadProgress: function(up, file) {
		
			  $('#uploadfile-'+file.id+' .qq-upload-size').text(plupload.formatSize(file.loaded) + ' {$lang['media_upload_st8']} ' + plupload.formatSize(file.origSize));
			  $('#uploadfile-'+file.id+' .progress-bar').css( "width", file.percent + '%' );
			  $('#uploadfile-'+file.id+' .qq-upload-spinner').css( "display", "inline-block");

        },
		
		FileUploaded: function(up, file, result) {
		
				try {
				   var response = JSON.parse(result.response);
				} catch (e) {
					var response = '';
				}
				
				totalqueue_{$fieldcount} --;
				
				if( result.status == 200 ) {
				
					if ( response.success ) {
					
						totaluploaded_{$fieldcount} ++;

						var fieldvalue = $('#xf_{$fieldname}').val();
					
						var returnbox = response.returnbox;
						var returnval = response.xfvalue;

						returnbox = returnbox.replace(/&lt;/g, "<");
						returnbox = returnbox.replace(/&gt;/g, ">");
						returnbox = returnbox.replace(/&amp;/g, "&");

						$('#uploadfile-'+file.id+' .qq-status').html('{$lang['media_upload_st9']}');
						$('#uploadedfile_{$fieldname}').append( returnbox );
						
						if (fieldvalue == "") {
							$('#xf_{$fieldname}').val(returnval);
						} else {
							fieldvalue += ',' +returnval;
							$('#xf_{$fieldname}').val(fieldvalue);
						}

						if(maxallowfiles_{$fieldcount} && totaluploaded_{$fieldcount} == maxallowfiles_{$fieldcount} ) {
								$('#xfupload_{$fieldname} .qq-upload-button').attr("disabled","disabled");
								up.disableBrowse(true);
						}

						setTimeout(function() {
							$('#uploadfile-'+file.id).fadeOut('slow', function() { $(this).remove(); up.refresh();});
						}, 1000);
						
						$('#mediaupload').remove();

					} else {
					
						$('#uploadfile-'+file.id+' .qq-status').html('{$lang['media_upload_st10']}');

						if( response.error ) $('#uploadfile-'+file.id+' .qq-status').append( '<br><span style="color:red">' + response.error + '</span>' );

						setTimeout(function() {
							$('#uploadfile-'+file.id).fadeOut('slow', function() { $(this).remove(); up.refresh();});
						}, 10000);
					}
						
				} else {
				
					$('#uploadfile-'+file.id+' .qq-status').append( '<br><span style="color:red">HTTP Error:' + result.status + '</span>' );
					
					setTimeout(function() {
						$('#uploadfile-'+file.id).fadeOut('slow', function() { $(this).remove(); up.refresh(); });
					}, 10000);
				}

				up.refresh();
				
        },
		
        Error: function(up, err) {
			var type_err = '{$lang['media_upload_st11']}';
			var size_err = '{$lang['media_upload_st12']}';
			
			type_err = type_err.replace('{file}', err.file.name);
			type_err = type_err.replace('{extensions}', up.settings.filters.mime_types[0].extensions);
			size_err = size_err.replace('{file}', err.file.name);
			size_err = size_err.replace('{sizeLimit}', plupload.formatSize(up.settings.filters.max_file_size));
			
			if(err.code == '-600') {
			
				DLEPush.error(size_err);
				
			} else if(err.code == '-601') {
			
				DLEPush.error(type_err);
				
			} else {
			
				DLEPush.error(err.message);
				
			}
		
        }
    }
});
	
if ( typeof Sortable != "undefined"  ) {

	var sortable_{$fieldcount} = Sortable.create(document.getElementById('uploadedfile_{$fieldname}'), {
		group: {
		name: 'xfuploadedimages',
		put: function (to, from) {

			if(from.options.group.name != to.options.group.name ){
				return false;
			}

			if(maxallowfiles_{$fieldcount} && totaluploaded_{$fieldcount} >= maxallowfiles_{$fieldcount} ) {
				return false;
			} else {return true;}
		}
		},
		handle: '.file-content',
		draggable: '.uploadedfile',
		onSort: function (evt) {

			totaluploaded_{$fieldcount} = sortable_{$fieldcount}.el.children.length;
			
			if(maxallowfiles_{$fieldcount} && totaluploaded_{$fieldcount} >= maxallowfiles_{$fieldcount} ) {
				$('#xfupload_{$fieldname} .qq-upload-button').attr("disabled","disabled");
				file_uploaders['{$fieldname}'].disableBrowse(true);
			} else {
				$('#xfupload_{$fieldname} .qq-upload-button').removeAttr('disabled');
				file_uploaders['{$fieldname}'].disableBrowse(false);
			}
			
			xfsinc('{$fieldname}');
			file_uploaders['{$fieldname}'].refresh();
		},
		animation: 150
	});
	
}

setTimeout(function() {
	file_uploaders['{$fieldname}'].init();

	if(maxallowfiles_{$fieldcount} && totaluploaded_{$fieldcount} >=  maxallowfiles_{$fieldcount} ) {
		$('#xfupload_{$fieldname} .qq-upload-button').attr("disabled","disabled");
		setTimeout(function() {
			file_uploaders['{$fieldname}'].disableBrowse(true);
			file_uploaders['{$fieldname}'].refresh();
		}, 100);
	}

}, 300);

HTML;

			$xfbuffer .= "<div class=\"xfieldsrow\"><div class=\"xfieldscolleft\">{$value[1]}:</div><div class=\"xfieldscolright\"><div id=\"xfupload_{$fieldname}\"></div><input type=\"hidden\" name=\"xfield[$fieldname]\" id=\"xf_$fieldname\" value=\"{$fieldvalue}\" /><script>{$uploadscript}</script></div></div>";

		} elseif ($value[3] == "video" OR $value[3] == "audio") {

			$max_file_size = (int)$value[32] * 1024;

			if ($fieldvalue) {
				$fieldvalue_arr = explode(',', $fieldvalue);
				$up_files = array();

				foreach ($fieldvalue_arr as $temp_value) {

					$temp_value = trim($temp_value);

					if (!$temp_value) continue;

					$temp_array = explode('|', $temp_value);

					if (count($temp_array) < 4) {

						$temp_alt = '';
						$temp_id = $temp_array[1];
						$temp_size = $temp_array[2];
						$temp_url = $temp_array[0];
						$temp_value = implode('|', $temp_array);
					} else {

						$temp_alt = $temp_array[0];
						$temp_id = $temp_array[2];
						$temp_size = $temp_array[3];
						$temp_url = $temp_array[1];
						unset($temp_array[0]);
						$temp_value =  implode('|', $temp_array);
					}

					$filename = pathinfo($temp_url, PATHINFO_BASENAME);
					$filename = explode("_", $filename);
					if (count($filename) > 1) unset($filename[0]);
					$filename = implode("_", $filename);

					$file_type = explode(".", $filename);
					$file_type = totranslit(end($file_type));

					if ($file_type == "mp3") {
						$img_url = $config['http_home_url'] . "engine/skins/images/mp3_file.png";
					} else {
						$img_url = $config['http_home_url'] . "engine/skins/images/video_file.png";
					}

					$xf_id = md5($temp_value);

					$up_files[] = "<div class=\"file-preview-card uploadedfile\" id=\"xf_{$xf_id}\" data-id=\"{$temp_value}\" data-alt=\"{$temp_alt}\"><div class=\"active-ribbon\"><span><i class=\"mediaupload-icon mediaupload-icon-ok\"></i></span></div><div class=\"file-content select-disable\"><img src=\"{$img_url}\" class=\"file-preview-image\"></div><div class=\"file-footer\"><div class=\"file-footer-caption\"><div class=\"file-caption-info\" rel=\"tooltip\" title=\"{$filename}\">{$filename}</div><div class=\"file-size-info\">({$temp_size})</div></div><div class=\"file-footer-bottom\"><div class=\"file-preview\"><a onclick=\"xfaddalt(\\'" . $xf_id . "\\', \\'" . $fieldname . "\\');return false;\" href=\"#\" rel=\"tooltip\" title=\"{$lang['xf_img_descr']}\"><i class=\"mediaupload-icon mediaupload-icon-edit\"></i></a></div><div class=\"file-delete\"><a onclick=\"xfplaylistdelete_{$fieldcount}(\\'" . $fieldname . "\\',\\'" . $temp_id . "\\', \\'" . $xf_id . "\\');return false;\" href=\"#\"><i class=\"mediaupload-icon mediaupload-icon-trash\"></i></a></div></div></div></div>";
				}

				$totaluploadedfiles = count($up_files);
				$up_files = implode($up_files);

			} else {
				$up_files = "";
				$totaluploadedfiles = 0;
			}

			if (!$value[5]) {
				$params = "rel=\"essential\" ";
				$uid = "uid=\"essential\" ";
			} else {
				$params = "";
				$uid = "";
			}

			$max_file_size = number_format($max_file_size, 0, '', '');

			if ($value[3] == "audio") {
				$allowed_files = "mp3";
				$lang['xfield_xfvideo'] = $lang['xfield_xfaudio'];
			} else $allowed_files = "mp4,m4v,m4a,mov,webm,m3u8,mkv";

			$uploadscript = <<<HTML
	var maxallowfiles_{$fieldcount} = {$value[31]};
	var totaluploaded_{$fieldcount} = {$totaluploadedfiles};
	var totalqueue_{$fieldcount} = 0;
	
	function xfplaylistdelete_{$fieldcount} ( xfname, xfvalue, id )
	{
		DLEconfirm( '{$lang['file_delete']}', '{$lang['p_info']}', function () {
		
			ShowLoading('');
	
			$.post(dle_root +'engine/ajax/controller.php?mod=upload', { subaction: 'deluploads', user_hash: '{$dle_login_hash}', news_id: '{$row['id']}', author: '{$author}', 'files[]' : xfvalue }, function(data){
	
				HideLoading('');

				$('#xf_'+id).remove();
				totaluploaded_{$fieldcount} --;
				xfsinc('{$fieldname}');
				
				$('#xfupload_' + xfname + ' .qq-upload-button').removeAttr('disabled');
				
				if (typeof file_uploaders[xfname] !== 'undefined') {
					file_uploaders[xfname].disableBrowse(false);
					file_uploaders[xfname].refresh();
				}
				
				$('#mediaupload').remove();
				
			});
			
		} );
		
		return false;

	};
	
$('#xfupload_{$fieldname}').html('<div class="qq-uploader"><div id="uploadedfile_{$fieldname}">{$up_files}</div><div style="position: relative;"><div id="upload_button_{$fieldname}" class="qq-upload-button btn btn-green bg-teal btn-sm btn-raised" style="width: auto;">{$lang['xfield_xfimg']}</div></div></div>');

file_uploaders['{$fieldname}'] = new plupload.Uploader({

    runtimes : 'html5',
    file_data_name: "qqfile",
    browse_button: 'upload_button_{$fieldname}',
    container: document.getElementById('xfupload_{$fieldname}'),
	drop_element: document.getElementById('xfupload_{$fieldname}'),
    url: dle_root + "engine/ajax/controller.php?mod=upload",
	multipart_params: {"subaction" : "upload", "news_id" : "{$row['id']}", "area" : "xfields{$value[3]}", "author" : "{$author}", "xfname" : "{$fieldname}", "user_hash" : "{$dle_login_hash}"},

	chunk_size: '{$config['file_chunk_size']}mb',
     
    filters : {
        max_file_size : '{$max_file_size}',
        mime_types: [
            {title : "Files", extensions : "{$allowed_files}"}
        ]
    },

    init: {
 
        FilesAdded: function(up, files) {
		
            plupload.each(files, function(file) {
			
				totalqueue_{$fieldcount} ++;
				
				if(maxallowfiles_{$fieldcount} && (totaluploaded_{$fieldcount} + totalqueue_{$fieldcount} ) > maxallowfiles_{$fieldcount} ) {
					totalqueue_{$fieldcount} --;
				
					$('#xfupload_{$fieldname} .qq-upload-button').attr("disabled","disabled");
					
					up.disableBrowse(true);
					up.removeFile(file);

				} else {
					$('<div id="uploadfile-'+file.id+'" class="file-box"><span class="qq-upload-file-status">{$lang['media_upload_st6']}</span><span class="qq-upload-file">&nbsp;'+file.name+'</span>&nbsp;<span class="qq-status"> <span class="qq-upload-spinner"></span> <span class="qq-upload-size"></span></span><div class="progress"><div class="progress-bar progress-blue" style="width: 0%"><span>0%</span></div></div></div>').appendTo('#xfupload_{$fieldname}');
				}
					
            });
			
			up.start();
			up.refresh();
        },
 
        UploadProgress: function(up, file) {
		
			  $('#uploadfile-'+file.id+' .qq-upload-size').text(plupload.formatSize(file.loaded) + ' {$lang['media_upload_st8']} ' + plupload.formatSize(file.origSize));
			  $('#uploadfile-'+file.id+' .progress-bar').css( "width", file.percent + '%' );
			  $('#uploadfile-'+file.id+' .qq-upload-spinner').css( "display", "inline-block");

        },
		
		FileUploaded: function(up, file, result) {
		
				try {
				   var response = JSON.parse(result.response);
				} catch (e) {
					var response = '';
				}
				
				totalqueue_{$fieldcount} --;
				
				if( result.status == 200 ) {
				
					if ( response.success ) {
					
						totaluploaded_{$fieldcount} ++;

						var fieldvalue = $('#xf_{$fieldname}').val();
					
						var returnbox = response.returnbox;
						var returnval = response.xfvalue;

						returnbox = returnbox.replace(/&lt;/g, "<");
						returnbox = returnbox.replace(/&gt;/g, ">");
						returnbox = returnbox.replace(/&amp;/g, "&");

						$('#uploadfile-'+file.id+' .qq-status').html('{$lang['media_upload_st9']}');
						$('#uploadedfile_{$fieldname}').append( returnbox );
						
						if (fieldvalue == "") {
							$('#xf_{$fieldname}').val(returnval);
						} else {
							fieldvalue += ',' +returnval;
							$('#xf_{$fieldname}').val(fieldvalue);
						}

						if(maxallowfiles_{$fieldcount} && totaluploaded_{$fieldcount} == maxallowfiles_{$fieldcount} ) {
								$('#xfupload_{$fieldname} .qq-upload-button').attr("disabled","disabled");
								up.disableBrowse(true);
						}

						setTimeout(function() {
							$('#uploadfile-'+file.id).fadeOut('slow', function() { $(this).remove(); up.refresh();});
						}, 1000);
						
						$('#mediaupload').remove();

					} else {
					
						$('#uploadfile-'+file.id+' .qq-status').html('{$lang['media_upload_st10']}');

						if( response.error ) $('#uploadfile-'+file.id+' .qq-status').append( '<br><span style="color:red">' + response.error + '</span>' );

						setTimeout(function() {
							$('#uploadfile-'+file.id).fadeOut('slow', function() { $(this).remove(); up.refresh();});
						}, 10000);
					}
						
				} else {
				
					$('#uploadfile-'+file.id+' .qq-status').append( '<br><span style="color:red">HTTP Error:' + result.status + '</span>' );
					
					setTimeout(function() {
						$('#uploadfile-'+file.id).fadeOut('slow', function() { $(this).remove(); up.refresh(); });
					}, 10000);
				}

				up.refresh();
				
        },
		
        Error: function(up, err) {
			var type_err = '{$lang['media_upload_st11']}';
			var size_err = '{$lang['media_upload_st12']}';
			
			type_err = type_err.replace('{file}', err.file.name);
			type_err = type_err.replace('{extensions}', up.settings.filters.mime_types[0].extensions);
			size_err = size_err.replace('{file}', err.file.name);
			size_err = size_err.replace('{sizeLimit}', plupload.formatSize(up.settings.filters.max_file_size));
			
			if(err.code == '-600') {
			
				DLEPush.error(size_err);
				
			} else if(err.code == '-601') {
			
				DLEPush.error(type_err');
				
			} else {
			
				DLEPush.error(err.message);
				
			}
		
        }
    }
});
	
if ( typeof Sortable != "undefined"  ) {

	var sortable_{$fieldcount} = Sortable.create(document.getElementById('uploadedfile_{$fieldname}'), {
		group: {
		name: 'xfuploaded{$value[3]}',
		put: function (to, from) {

			if(from.options.group.name != to.options.group.name ){
				return false;
			}

			if(maxallowfiles_{$fieldcount} && totaluploaded_{$fieldcount} >= maxallowfiles_{$fieldcount} ) {
				return false;
			} else {return true;}
		}
		},
		handle: '.file-content',
		draggable: '.uploadedfile',
		onSort: function (evt) {

			totaluploaded_{$fieldcount} = sortable_{$fieldcount}.el.children.length;
			
			if(maxallowfiles_{$fieldcount} && totaluploaded_{$fieldcount} >= maxallowfiles_{$fieldcount} ) {
				$('#xfupload_{$fieldname} .qq-upload-button').attr("disabled","disabled");
				file_uploaders['{$fieldname}'].disableBrowse(true);
			} else {
				$('#xfupload_{$fieldname} .qq-upload-button').removeAttr('disabled');
				file_uploaders['{$fieldname}'].disableBrowse(false);
			}
			
			xfsinc('{$fieldname}');
			file_uploaders['{$fieldname}'].refresh();
		},
		animation: 150
	});
	
}

setTimeout(function() {
	file_uploaders['{$fieldname}'].init();

	if(maxallowfiles_{$fieldcount} && totaluploaded_{$fieldcount} >=  maxallowfiles_{$fieldcount} ) {
		$('#xfupload_{$fieldname} .qq-upload-button').attr("disabled","disabled");
		setTimeout(function() {
			file_uploaders['{$fieldname}'].disableBrowse(true);
			file_uploaders['{$fieldname}'].refresh();
		}, 100);
	}

}, 300);

HTML;

			$xfbuffer .= "<div class=\"xfieldsrow\"><div class=\"xfieldscolleft\">{$value[1]}:</div><div class=\"xfieldscolright\"><div id=\"xfupload_{$fieldname}\"></div><input type=\"hidden\" name=\"xfield[$fieldname]\" id=\"xf_$fieldname\" value=\"{$fieldvalue}\" /><script>{$uploadscript}</script></div></div>";


		} elseif( $value[3] == "file" ) {
			
			$max_file_size = (int)$value[15] * 1024;
			$allowed_files = strtolower( $value[14] );
	
			$fieldvalue = str_replace('&amp;', '&', $fieldvalue);
			
			if( $fieldvalue ) {
				
				$fileid = intval(preg_replace( "'\[attachment=(.*?):(.*?)\]'si", "\\1", $fieldvalue ));
				
				$fileid = "&nbsp;<button class=\"qq-upload-button btn btn-sm btn-red\" onclick=\"xffiledelete('".$fieldname."','".$fileid."');return false;\">{$lang['xfield_xfid']}</button>";
	
				$show="display:inline-block;";
				
			} else { $show="display:none;"; $fileid="";}

			$max_file_size = number_format($max_file_size, 0, '', '');
			
$uploadscript = <<<HTML
$('#xfupload_{$fieldname}').html('<div class="qq-uploader"><div style="position: relative;"><div id="upload_button_{$fieldname}" class="qq-upload-button btn btn-green bg-teal btn-sm btn-raised" style="width: auto;">{$lang['xfield_xfif']}</div></div></div>');

file_uploaders['{$fieldname}'] = new plupload.Uploader({

    runtimes : 'html5',
    file_data_name: "qqfile",
    browse_button: 'upload_button_{$fieldname}',
    container: document.getElementById('xfupload_{$fieldname}'),
	drop_element: document.getElementById('xfupload_{$fieldname}'),
    url: dle_root + "engine/ajax/controller.php?mod=upload",
	multipart_params: {"subaction" : "upload", "news_id" : "{$row['id']}", "area" : "xfieldsfile", "author" : "{$author}", "xfname" : "{$fieldname}", "user_hash" : "{$dle_login_hash}"},
	multi_selection: false,
	chunk_size: '{$config['file_chunk_size']}mb',
     
    filters : {
        max_file_size : '{$max_file_size}',
        mime_types: [
            {title : "Files", extensions : "{$allowed_files}"}
        ]
    },
     
 
    init: {
 
        FilesAdded: function(up, files) {
		
            plupload.each(files, function(file) {
				$('<div id="uploadfile-'+file.id+'" class="file-box"><span class="qq-upload-file-status">{$lang['media_upload_st6']}</span><span class="qq-upload-file">&nbsp;'+file.name+'</span>&nbsp;<span class="qq-status"> <span class="qq-upload-spinner"></span> <span class="qq-upload-size"></span></span><div class="progress"><div class="progress-bar progress-blue" style="width: 0%"><span>0%</span></div></div></div>').appendTo('#xfupload_{$fieldname}');
            });
			
			up.start();
        },
 
        UploadProgress: function(up, file) {
		
			  $('#uploadfile-'+file.id+' .qq-upload-size').text(plupload.formatSize(file.loaded) + ' {$lang['media_upload_st8']} ' + plupload.formatSize(file.origSize));
			  $('#uploadfile-'+file.id+' .progress-bar').css( "width", file.percent + '%' );
			  $('#uploadfile-'+file.id+' .qq-upload-spinner').css( "display", "inline-block");

        },
		
		FileUploaded: function(up, file, result) {
		
				try {
				   var response = JSON.parse(result.response);
				} catch (e) {
					var response = '';
				}
				
				if( result.status == 200 ) {
				
					if ( response.success ) {
					
						var returnbox = response.returnbox;
						var returnval = response.xfvalue;

						returnbox = returnbox.replace(/&lt;/g, "<");
						returnbox = returnbox.replace(/&gt;/g, ">");
						returnbox = returnbox.replace(/&amp;/g, "&");

						$('#uploadfile-'+file.id+' .qq-status').html('{$lang['media_upload_st9']}');
						$('#xf_{$fieldname}').show();
						$('#uploadedfile_{$fieldname}').html( returnbox );
						$('#xf_{$fieldname}').val(returnval);
						$('#xfupload_{$fieldname} .qq-upload-button').attr("disabled","disabled");
						
						up.disableBrowse(true);
						
						setTimeout(function() {
							$('#uploadfile-'+file.id).fadeOut('slow', function() { $(this).remove(); });
						}, 1000);
						
						$('#mediaupload').remove();

					} else {
					
						$('#uploadfile-'+file.id+' .qq-status').html('{$lang['media_upload_st10']}');

						if( response.error ) $('#uploadfile-'+file.id+' .qq-status').append( '<br><span style="color:red">' + response.error + '</span>' );

						setTimeout(function() {
							$('#uploadfile-'+file.id).fadeOut('slow', function() { $(this).remove(); });
						}, 10000);
					}
						
				} else {
				
					$('#uploadfile-'+file.id+' .qq-status').append( '<br><span style="color:red">HTTP Error:' + result.status + '</span>' );
					
					setTimeout(function() {
						$('#uploadfile-'+file.id).fadeOut('slow', function() { $(this).remove(); });
					}, 10000);
				}
				
        },
		
        Error: function(up, err) {
			var type_err = '{$lang['media_upload_st11']}';
			var size_err = '{$lang['media_upload_st12']}';
			
			type_err = type_err.replace('{file}', err.file.name);
			type_err = type_err.replace('{extensions}', up.settings.filters.mime_types[0].extensions);
			size_err = size_err.replace('{file}', err.file.name);
			size_err = size_err.replace('{sizeLimit}', plupload.formatSize(up.settings.filters.max_file_size));
			
			if(err.code == '-600') {
			
				DLEPush.error(size_err);
				
			} else if(err.code == '-601') {
			
				DLEPush.error(type_err);
				
			} else {
			
				DLEPush.error(err.message);
				
			}
		
        }
    }
});

setTimeout(function() {

	file_uploaders['{$fieldname}'].init();

	if($('#xf_{$fieldname}').val() != "" ) {

		$('#xfupload_{$fieldname} .qq-upload-button').attr("disabled","disabled");

		setTimeout(function() {
			file_uploaders['{$fieldname}'].disableBrowse(true);
		}, 100);
		
	}

}, 300);
HTML;

			$xfbuffer .= "<div class=\"xfieldsrow\"><div class=\"xfieldscolleft\">{$value[1]}:</div><div class=\"xfieldscolright\"><input style=\"{$show}\" class=\"quick-edit-text\" type=\"text\" name=\"xfield[$fieldname]\" id=\"xf_$fieldname\" value=\"{$fieldvalue}\" /><span id=\"uploadedfile_{$fieldname}\">{$fileid}</span><div id=\"xfupload_{$fieldname}\"></div><script>{$uploadscript}</script></div></div>";
		
		}
	
	}
	
	$addtype = "addnews";
	
	if( !$config['allow_quick_wysiwyg'] ) {
		
		include_once (DLEPlugins::Check(ENGINE_DIR . '/ajax/bbcode.php'));
		$xfbuffer = str_replace ("<!--panel-->", $code, $xfbuffer);
	
	} else {

		$p_name = urlencode($row['autor']);

		if ( $config['allow_quick_wysiwyg'] == "2") {

			if($config['bbimages_in_wysiwyg']) {
				$implugin = 'dleimage';
			} else $implugin = 'image';
	
			$image_upload = array();
			
			if ( $user_group[$member_id['user_group']]['allow_image_upload'] ) {
		
				$image_upload[0] = "dleupload ";
		
				$image_upload[1] = <<<HTML
var dle_image_upload_handler = (blobInfo, progress) => new Promise((resolve, reject) => {
  var xhr, formData;

  xhr = new XMLHttpRequest();
  xhr.withCredentials = false;
  xhr.open('POST', dle_root + 'engine/ajax/controller.php?mod=upload');
  
  xhr.upload.onprogress = (e) => {
    progress(e.loaded / e.total * 100);
  };

  xhr.onload = function() {
    var json;

    if (xhr.status === 403) {
      reject('HTTP Error: ' + xhr.status, { remove: true });
      return;
    }

    if (xhr.status < 200 || xhr.status >= 300) {
      reject('HTTP Error: ' + xhr.status);
      return;
    }

    json = JSON.parse(xhr.responseText);

    if (!json || typeof json.link != 'string') {

		if(typeof json.error == 'string') {
			reject(json.error);
		} else {
			reject('Invalid JSON: ' + xhr.responseText);	
		}
		
		var editor = tinymce.activeEditor;
		var node = editor.selection.getEnd();
		editor.selection.select(node);
		editor.selection.setContent('');
		
      return;
    }

	if( json.flink ) {
		
		var editor = tinymce.activeEditor;
		var node = editor.selection.getEnd();

		var object = $(node);
		object.removeAttr('width');
		object.removeAttr('height');
		object.attr('src', json.link );
		object.attr('style', 'display: block; margin-left: auto; margin-right: auto;' );
		
		editor.selection.select(node);
		editor.selection.setContent('<a href="'+json.flink+'" class="highslide">'+object.prop('outerHTML')+'</a>&nbsp;');
		editor.notificationManager.close();
		$('#mediaupload').remove();

	} else {
		resolve(json.link);
		$('#mediaupload').remove();
	}
	
  };

  xhr.onerror = function () {
    reject('Image upload failed due to a XHR Transport error. Code: ' + xhr.status);
  };

  formData = new FormData();
  formData.append('qqfile', blobInfo.blob(), blobInfo.filename());
  formData.append("subaction", "upload");
  formData.append("news_id", "{$row['id']}");
  formData.append("area", "short_story");
  formData.append("author", "{$p_name}");
  formData.append("mode", "quickload");
  formData.append("editor_mode", "tinymce");
  formData.append("user_hash", "{$dle_login_hash}");    
  
  xhr.send(formData);
});
HTML;

		$image_upload[2] = <<<HTML
paste_data_images: true,
automatic_uploads: true,
images_upload_handler: dle_image_upload_handler,
images_reuse_filename: true,
image_uploadtab: false,
images_file_types: 'gif,jpg,png,jpeg,bmp,webp,avif',
file_picker_types: 'image',

file_picker_callback: function (cb, value, meta) {
    const input = document.createElement('input');
    input.setAttribute('type', 'file');
    input.setAttribute('accept', 'image/*');

    input.addEventListener('change', (e) => {
      const file = e.target.files[0];

		var filename = file.name;
		filename = filename.split('.').slice(0, -1).join('.');
	
      const reader = new FileReader();
      reader.addEventListener('load', () => {

        const id = filename;
        const blobCache =  tinymce.activeEditor.editorUpload.blobCache;
        const base64 = reader.result.split(',')[1];
        const blobInfo = blobCache.create(id, file, base64);
        blobCache.add(blobInfo);

        cb(blobInfo.blobUri());

      });
      reader.readAsDataURL(file);
    });

    input.click();
},
HTML;
		
			} else {
				
				$image_upload[0] = "";
				$image_upload[1] = "";
				$image_upload[2] = "";
				
			}	
			
			if( $user_group[$member_id['user_group']]['allow_file_upload'] ) {
				$image_upload[0] = "dleupload ";
			}
			
			if( @file_exists( ROOT_DIR . '/templates/'. $config['skin'].'/editor.css' ) ) {
				
					$editor_css = "templates/{$config['skin']}/editor.css?v={$config['cache_id']}";
					
			} else $editor_css = "engine/editor/css/content.css?v={$config['cache_id']}";
			
			$js_code = <<<HTML
<script>
var text_upload = "{$lang['bb_t_up']}";

setTimeout(function() {

	tinymce.remove('textarea.wysiwygeditor');
	
	tinyMCE.baseURL = dle_root + 'engine/editor/jscripts/tiny_mce';
	tinyMCE.suffix = '.min';

	var dle_theme = '{$dark_theme}';

	if(dle_theme != '') {
		$('body').addClass( dle_theme );
	}

	{$image_upload[1]}
	
	tinymce.init({
		selector: 'textarea.wysiwygeditor',
		
		language : "{$lang['language_code']}",
		directionality: '{$lang['direction']}',
		element_format : 'html',
		
		body_class: dle_theme,
		skin: dle_theme == 'dle_theme_dark' ? 'oxide-dark' : 'oxide',

		width : "100%",
		height : 350,

		deprecation_warnings: false,
		promotion: false,
		cache_suffix: '?v={$config['cache_id']}',

		plugins: "accordion fullscreen advlist autolink lists link image charmap anchor searchreplace visualblocks visualchars nonbreaking table codemirror dlebutton codesample quickbars autosave wordcount pagebreak toc",
		
		setup: function(editor) {
			editor.on('PreInit', function() {
				var shortEndedElements = editor.schema.getVoidElements();
				shortEndedElements['path'] = {};
				shortEndedElements['source'] = {};
				shortEndedElements['use'] = {};
			});
		},

		relative_urls : false,
		convert_urls : false,
		remove_script_host : false,
		verify_html: false,
		nonbreaking_force_tab: true,
		branding: false,
		link_default_target: '_blank',
		browser_spellcheck: true,
		pagebreak_separator: '{PAGEBREAK}',
		pagebreak_split_block: true,
		editable_class: 'contenteditable',
		noneditable_class: 'noncontenteditable',
		paste_as_text: true,

		image_advtab: true,
		image_caption: true,
		image_dimensions: true,
		{$image_upload[2]}
		
		draggable_modal: true,
		menubar: false,
		toolbar: 'bold italic underline strikethrough | align | bullist numlist | link dleleech | {$implugin} {$image_upload[0]} dlemp dlaudio dletube dleemo | dle table fontformatting forecolor backcolor dletypo removeformat toc | fullscreen code',
		toolbar_mode: 'floating',
		toolbar_groups: {
		  
		  fontformatting: {
			icon: 'change-case',
			tooltip: 'Formatting',
			items: 'blocks styles fontfamily fontsizeinput lineheight'
		  },
		  
		  align: {
			icon: 'align-center',
			tooltip: 'Formatting',
			items: 'alignleft aligncenter alignright alignjustify'
		  },
		  
		  dle: {
			icon: 'icon-dle',
			tooltip: 'DLE Tags',
			items: 'dlequote dlespoiler accordion dlehide codesample | pagebreak dlepage'
		  }
		  
		},
		
		statusbar: false,
		contextmenu: 'image table lists',

		block_formats: 'Tag (p)=p;Tag (div)=div;Header 1=h1;Header 2=h2;Header 3=h3;Header 4=h4;Header 5=h5;Header 6=h6;',
		style_formats: [
			{ title: 'Information Block', block: 'div', wrapper: true, styles: { 'color': '#333333', 'border': 'solid 1px #00897B', 'padding': '0.625rem', 'background-color': '#E0F2F1', 'box-shadow': 'rgb(0 0 0 / 24%) 0px 1px 2px' } },
			{ title: 'Warning Block', block: 'div', wrapper: true, styles: { 'border': 'solid 1px #FF9800', 'padding': '0.625rem', 'background-color': '#FFF3E0', 'color': '#aa3510', 'box-shadow': 'rgb(0 0 0 / 24%) 0px 1px 2px' } },
			{ title: 'Error Block', block: 'div', wrapper: true, styles: { 'border': 'solid 1px #FF5722', 'padding': '0.625rem', 'background-color': '#FBE9E7', 'color': '#9c1f1f', 'box-shadow': 'rgb(0 0 0 / 24%) 0px 1px 2px' } },
			{ title: 'Borders', block: 'div', wrapper: true, styles: { 'border': 'solid 1px #ccc', 'padding': '0.625rem' } },
			{ title: 'Borders top and bottom', block: 'div', wrapper: true, styles: { 'border-top': 'solid 1px #ccc', 'border-bottom': 'solid 1px #ccc', 'padding': '10px 0' } },
			{ title: 'Use a shadow', block: 'div', styles: { 'box-shadow': '0 5px 12px rgba(126,142,177,0.2)' } },
			{ title: 'Increased letter spacing', inline: 'span', styles: { 'letter-spacing': '1px' } },
			{ title: 'Сapital letters', inline: 'span', styles: { 'text-transform': 'uppercase' } },
			{ title: 'Gray background', block: 'div', wrapper: false, styles: { 'color': '#fff', 'background-color': '#607D8B', 'padding': '0.625rem' } },
			{ title: 'Brown background', block: 'div', wrapper: false, styles: { 'color': '#fff', 'background-color': '#795548', 'padding': '0.625rem' } },
			{ title: 'Blue background', block: 'div', wrapper: false, styles: { 'color': '#104d92', 'background-color': '#E3F2FD', 'padding': '0.625rem' } },
			{ title: 'Green background', block: 'div', wrapper: false, styles: { 'color': '#fff', 'background-color': '#009688', 'padding': '0.625rem' } },
		],

		image_class_list: [
			{ title: 'None', value: '' },
			{ title: 'Image Border', value: 'image-bordered' },
			{ title: 'Image Shadow', value: 'image-shadows' },
			{ title: 'Image Padding', value: 'image-padded' },
			{ title: 'Borders Padding', value: 'image-bordered image-padded' },
			{ title: 'Shadow Padding', value: 'image-shadows image-padded' },
		],

		quickbars_insert_toolbar: false,
		quickbars_selection_toolbar: 'bold italic underline quicklink | dlequote dlespoiler dlehide | forecolor backcolor styles blocks fontsizeinput lineheight',
		quickbars_image_toolbar: 'alignleft aligncenter alignright | image link',
  
		formats: {
		  bold: {inline: 'b'},  
		  italic: {inline: 'i'},
		  underline: {inline: 'u', exact : true},  
		  strikethrough: {inline: 's', exact : true}
		},
		
		toc_depth : 4,
		
		dle_root : dle_root,
		dle_upload_area : "short_story",
		dle_upload_user : "{$p_name}",
		dle_upload_news : "{$row['id']}",
		
		content_css : dle_root + "{$editor_css}"
	});

}, 100);

</script>
HTML;

		
		} else {


			if ( $user_group[$member_id['user_group']]['allow_image_upload'] OR $user_group[$member_id['user_group']]['allow_file_upload'] ) {
				
				$image_upload = "'dleupload',";
				$image_q_upload = ", 'imageUpload'";
				
			} else { $image_upload = ""; $image_q_upload = ""; }
			
			if($config['bbimages_in_wysiwyg']) {
				$implugin = 'dleimg';
			} else $implugin = 'insertImage';
	
			$js_code = <<<HTML
<script>
var text_upload = "{$lang['bb_t_up']}";

      $('.wysiwygeditor').froalaEditor({
        dle_root: dle_root,
        dle_upload_area : "short_story",
        dle_upload_user : "{$p_name}",
        dle_upload_news : "{$row['id']}",
        width: '100%',
        height: '280',
        zIndex: 9990,
        language: '{$lang['language_code']}',
		direction: '{$lang['direction']}',

        imageAllowedTypes: ['jpeg', 'jpg', 'png', 'gif', 'bmp', 'webp','avif'],
        imageDefaultWidth: 0,
        imageInsertButtons: ['imageBack', '|', 'imageByURL'{$image_q_upload}],
		imageUploadURL: dle_root + 'engine/ajax/controller.php?mod=upload',
		imageUploadParam: 'qqfile',
		imageUploadParams: { "subaction" : "upload", "news_id" : "{$row['id']}", "area" : "short_story", "author" : "{$p_name}", "mode" : "quickload", "user_hash" : "{$dle_login_hash}"},
        imageMaxSize: {$config['max_up_size']} * 1024,
        imagePaste: false,
		
        toolbarButtonsXS: ['bold', 'italic', 'underline', 'strikeThrough', '|', 'align', 'indent', 'outdent', '|', 'subscript', 'superscript', '|', 'insertTable', 'formatOL', 'formatUL', 'insertHR', '|', 'clearFormatting', 'dlecode', '|', 'html', '-', 
                         'fontFamily', 'fontSize', '|', 'color', 'paragraphFormat', 'paragraphStyle', '|', 'insertLink', 'dleleech', '|', 'emoticons', '{$implugin}',{$image_upload}'|', 'insertVideo', 'dleaudio', 'dlemedia','|', 'dlehide', 'dlequote', 'dlespoiler'],

						 
        toolbarButtonsSM: ['bold', 'italic', 'underline', 'strikeThrough', '|', 'align', 'indent', 'outdent', '|', 'subscript', 'superscript', '|', 'insertTable', 'formatOL', 'formatUL', 'insertHR', '|', 'clearFormatting', 'dlecode', '|', 'html', '-', 
                         'fontFamily', 'fontSize', '|', 'color', 'paragraphFormat', 'paragraphStyle', '|', 'insertLink', 'dleleech', '|', 'emoticons', '{$implugin}',{$image_upload}'|', 'insertVideo', 'dleaudio', 'dlemedia','|', 'dlehide', 'dlequote', 'dlespoiler'],

        toolbarButtonsMD: ['bold', 'italic', 'underline', 'strikeThrough', '|', 'align', 'indent', 'outdent', '|', 'subscript', 'superscript', '|', 'insertTable', 'formatOL', 'formatUL', 'insertHR', '|', 'clearFormatting', 'dlecode', '|', 'html', '-', 
                         'fontFamily', 'fontSize', '|', 'color', 'paragraphFormat', 'paragraphStyle', '|', 'insertLink', 'dleleech', '|', 'emoticons', '{$implugin}',{$image_upload}'|', 'insertVideo', 'dleaudio', 'dlemedia','|', 'dlehide', 'dlequote', 'dlespoiler'],

        toolbarButtons: ['bold', 'italic', 'underline', 'strikeThrough', '|', 'align', 'indent', 'outdent', '|', 'subscript', 'superscript', '|', 'insertTable', 'formatOL', 'formatUL', 'insertHR', '|', 'clearFormatting', 'dlecode', '|', 'html', '-', 
                         'fontFamily', 'fontSize', '|', 'color', 'paragraphFormat', 'paragraphStyle', '|', 'insertLink', 'dleleech', '|', 'emoticons', '{$implugin}',{$image_upload}'|', 'insertVideo', 'dleaudio', 'dlemedia','|', 'dlehide', 'dlequote', 'dlespoiler']

      }).on('froalaEditor.image.inserted froalaEditor.image.replaced', function (e, editor, \$img, response) {
	  
			if( response ) {
			
			    response = JSON.parse(response);
			  
			    \$img.removeAttr("data-returnbox").removeAttr("data-success").removeAttr("data-xfvalue").removeAttr("data-flink");

				if(response.flink) {
				  if(\$img.parent().hasClass("highslide")) {
		
					\$img.parent().attr('href', response.flink);
		
				  } else {
		
					\$img.wrap( '<a href="'+response.flink+'" class="highslide"></a>' );
					
				  }
				}
			  
			}
			
		});
		
</script>
HTML;
		}

		$code = "";	
	}

	if ( !$config['allow_quick_wysiwyg'] ) {
		
		$params = "onfocus=\"setNewField(this.name, document.ajaxnews{$id})\" class=\"quick-edit-textarea\"";
		$box_class = "bb-editor";
		
	} else {
		
		$params = "class=\"wysiwygeditor\"";
		$box_class = "wseditor dlefastedit-editor";
		
	}

	if( $news_txt OR ($config['quick_edit_mode'] AND !$config['disable_short']) ) {
	
		$short_area = <<<HTML
<div class="xfieldsrow"><b>{$lang['s_fshort']}</b>
<div class="{$box_class}{$dark_theme}">
{$code}
<textarea id="news_txt" name="news_txt" {$params}>{$news_txt}</textarea>
</div>
</div>
HTML;

	}

	if($full_txt OR ($config['quick_edit_mode'] AND !$config['disable_short']) ) {
	
		$full_area = <<<HTML
<div class="xfieldsrow"><b>{$lang['s_ffull']}</b>
<div class="{$box_class}{$dark_theme}">
{$code}
<textarea id="full_txt" name="full_txt" {$params}>{$full_txt}</textarea>
</div>
</div>
HTML;

	}

	if( !$config['allow_quick_wysiwyg'] ) {
		
		$fix_br = "&nbsp;&nbsp;<label><input type=\"checkbox\" name=\"allow_br\" value=\"1\" {$fix_br}>{$lang['aj_allowbr']}</label>";
		
	} else $fix_br ="";

	if($lang['direction'] == 'rtl') $rtl_prefix ='_rtl'; else $rtl_prefix = '';

	$buffer = <<<HTML
<script src="{$config['http_home_url']}engine/classes/js/sortable.js"></script>
<script src="{$config['http_home_url']}engine/classes/uploads/html5/plupload/plupload.full.min.js"></script>
<script src="{$config['http_home_url']}engine/classes/uploads/html5/plupload/i18n/{$lang['language_code']}.js"></script>
<script src="{$config['http_home_url']}engine/classes/calendar/calendar.js"></script>
<link href="{$config['http_home_url']}engine/classes/calendar/calendar.css" rel="stylesheet" type="text/css">
<form name="ajaxnews{$id}" id="ajaxnews{$id}" metod="post" action="">
<div><input type="text" name="title" class="quick-edit-text" value="{$row['title']}"></div>
{$short_area}
{$full_area}
{$xfbuffer}
<div class="xfieldsrow"><div class="xfieldscolleft">{$lang['reason']}</div><div class="xfieldscolright"><input type="text" name="reason" class="quick-edit-text" value="{$row['reason']}"></div></div>
<div class="xfieldsrow"><label><input type="checkbox" name="approve" value="1" {$fix_approve}>{$lang['add_al_ap']}</label>{$fix_br}</div>
</form>
{$js_code}
<script>

    var elemfont = document.createElement('i');
    elemfont.className = 'mediaupload-icon';
	elemfont.style.position = 'absolute';
	elemfont.style.left = '-9999px';
	document.body.appendChild(elemfont);

	if ($( elemfont ).css('font-family') !== 'mediauploadicons') {
		$('head').append('<link rel="stylesheet" type="text/css" href="' + dle_root +'engine/classes/uploads/html5/fileuploader{$rtl_prefix}.css">');
	}

    document.body.removeChild(elemfont);
	
	function xfimagedelete( xfname, xfvalue ) {
		
		DLEconfirm( '{$lang['image_delete']}', '{$lang['p_info']}', function () {
		
			ShowLoading('');
			
			$.post(dle_root + 'engine/ajax/controller.php?mod=upload', { subaction: 'deluploads', user_hash: '{$dle_login_hash}', news_id: '{$row['id']}', author: '{$author}', 'images[]' : xfvalue }, function(data){
	
				HideLoading('');
				
				$('#uploadedfile_'+xfname).html('');
				$('#xf_'+xfname).val('');
				$('#xfupload_' + xfname + ' .qq-upload-button').removeAttr('disabled');
				
				if (typeof file_uploaders[xfname] !== 'undefined') {
					file_uploaders[xfname].disableBrowse(false);
					file_uploaders[xfname].refresh();
				}
				
				$('#mediaupload').remove();
				
			});
			
		} );

		return false;

	};
	function xffiledelete( xfname, xfvalue ) {
		
		DLEconfirm( '{$lang['file_delete']}', '{$lang['p_info']}', function () {
		
			ShowLoading('');
			
			$.post(dle_root + 'engine/ajax/controller.php?mod=upload', { subaction: 'deluploads', user_hash: '{$dle_login_hash}', news_id: '{$row['id']}', author: '{$author}', 'files[]' : xfvalue }, function(data){
	
				HideLoading('');
				
				$('#uploadedfile_'+xfname).html('');
				$('#xf_'+xfname).val('');
				$('#xf_'+xfname).hide('');
				$('#xfupload_' + xfname + ' .qq-upload-button').removeAttr('disabled');
				
				if (typeof file_uploaders[xfname] !== 'undefined') {
					file_uploaders[xfname].disableBrowse(false);
					file_uploaders[xfname].refresh();
				}
				
				$('#mediaupload').remove();
			});
			
		} );

		return false;

	};
	
	function xfaddalt( id, xfname ) {
	
		var sel_alt = $('#xf_'+id).data('alt').toString().trim();
		sel_alt = sel_alt.replace(/"/g, '&quot;');
		sel_alt = sel_alt.replace(/'/g, '&#039;');

		DLEprompt('{$lang['bb_descr']}', sel_alt, '{$lang['p_prompt']}', function (r) {
			r = r.replace(/</g, '');
			r = r.replace(/>/g, '');
			r = r.replaceAll(',', '&#44;');
			r = r.replaceAll('|', '&#124;');
			
			$('#xf_'+id).data('alt', r);
			xfsinc(xfname);
		
		}, true);
		
	};
	
	function xfsinc(xfname) {
	
		var order = [];
		
		$( '#uploadedfile_' + xfname + ' .uploadedfile' ).each(function() {
			var xfurl = $(this).data('id').toString().trim();
			var xfalt = $(this).data('alt').toString().trim();
			
			if(xfalt) {
				order.push(xfalt + '|'+ xfurl);
			} else {
				order.push(xfurl);
			}

		});
	
		$('#xf_' + xfname).val(order.join(','));
	};
</script>	
HTML;

} elseif( $_REQUEST['action'] == "save" ) {
	
	if( !isset($_REQUEST['user_hash']) OR !$_REQUEST['user_hash'] OR $_REQUEST['user_hash'] != $dle_login_hash ) {
		
		die ("error");
	
	}
	
	$row = $db->super_query( "SELECT id, date, xfields, title, category, approve, short_story, full_story, autor, alt_name FROM " . PREFIX . "_post where id = '$id'" );
	
	if( $id != $row['id'] ) die( "News Not Found" );
	
	if( $config['allow_alt_url'] ) {
		if( $config['seo_type'] == 1 OR $config['seo_type'] == 2 ) {
			if( intval( $row['category'] ) and $config['seo_type'] == 2 ) {
				$full_link = $config['http_home_url'] . get_url( intval( $row['category'] ) ) . "/" . $row['id'] . "-" . $row['alt_name'] . ".html";
			} else {
				$full_link = $config['http_home_url'] . $row['id'] . "-" . $row['alt_name'] . ".html";
			}
		} else {
			$full_link = $config['http_home_url'] . date( 'Y/m/d/', strtotime( $row['date'] ) ) . $row['alt_name'] . ".html";
		}
	} else {
		$full_link = $config['http_home_url'] . "index.php?newsid=" . $row['id'];
	}

	$cat_list = explode( ',', $row['category'] );
	
	$have_perm = 0;
	
	if( $user_group[$member_id['user_group']]['allow_all_edit'] ) {
		$have_perm = 1;
		
		$allow_list = explode( ',', $user_group[$member_id['user_group']]['cat_add'] );
		
		foreach ( $cat_list as $selected ) {
			if( $allow_list[0] != "all" and ! in_array( $selected, $allow_list ) ) $have_perm = 0;
		}
	}
	
	if( $user_group[$member_id['user_group']]['allow_edit'] and $row['autor'] == $member_id['name'] ) {
		$have_perm = 1;
	}
	
	if( $user_group[$member_id['user_group']]['max_edit_days'] ) {
		$newstime = strtotime( $row['date'] );
		$maxedittime = $_TIME - ($user_group[$member_id['user_group']]['max_edit_days'] * 3600 * 24);
		if( $maxedittime > $newstime ) $have_perm = 0;
	}
	
	if( ($member_id['user_group'] == 1) ) {
		$have_perm = 1;
	}
	
	if( !$have_perm ) die( "Access it is refused" );
	
	$allow_br = isset( $_REQUEST['allow_br'] ) ? intval( $_REQUEST['allow_br'] ) : 0;
	$approve = isset(  $_REQUEST['approve'] ) ? intval(  $_REQUEST['approve'] ) : 0;

	if( !$user_group[$member_id['user_group']]['moderation'] ) $approve = 0;
	
	if( !$config['allow_quick_wysiwyg'] AND $allow_br ) $use_html = false;
	else $use_html = true;

	$_POST['title'] = $db->safesql( $parse->process( trim( strip_tags ($_POST['title'] ) ) ) );

	if ( $config['allow_quick_wysiwyg'] ) $parse->allow_code = false;

	if ( !$user_group[$member_id['user_group']]['allow_html'] ) {

		$_POST['news_txt'] = strip_tags ($_POST['news_txt']);
		$_POST['full_txt'] = strip_tags ($_POST['full_txt']);

	}

	$news_txt = $db->safesql($parse->BB_Parse( $parse->process( $_POST['news_txt'] ), $use_html ));
	$full_txt = $db->safesql($parse->BB_Parse( $parse->process( $_POST['full_txt'] ), $use_html ));

	$add_module = "yes";
	$ajax_edit = "yes";
	$stop = "";
	$category = $cat_list;
	$xf_existing = xfieldsdataload($row['xfields']);
	$xfieldsaction = "init";
	include (DLEPlugins::Check(ENGINE_DIR . '/inc/xfields.php'));

	$editreason = $db->safesql( htmlspecialchars( strip_tags( stripslashes( trim( $_POST['reason'] ) ) ), ENT_QUOTES, $config['charset'] ) );
	
	if( $editreason != "" ) $view_edit = 1;
	else $view_edit = 0;
	$added_time = time();
	
	if( !trim($_POST['title']) ) die( $lang['add_err_7'] );

	if ($parse->not_allowed_text ) die( $lang['news_err_39'] );

	$db->query( "UPDATE " . PREFIX . "_post SET title='{$_POST['title']}', short_story='$news_txt', full_story='$full_txt', xfields='$filecontents', approve='$approve', allow_br='$allow_br' WHERE id = '$id'" );
	$db->query( "UPDATE " . PREFIX . "_post_extras SET editdate='$added_time', editor='{$member_id['name']}', reason='$editreason', view_edit='$view_edit' WHERE news_id = '$id'" );

	$db->query( "DELETE FROM " . PREFIX . "_xfsearch WHERE news_id = '{$id}'" );

	if ( count($xf_search_words) AND $approve ) {
					
		$temp_array = array();
					
		foreach ( $xf_search_words as $value ) {
						
			$temp_array[] = "('" . $id . "', '" . $value[0] . "', '" . $value[1] . "')";
		}
					
		$xf_search_words = implode( ", ", $temp_array );
		$db->query( "INSERT INTO " . PREFIX . "_xfsearch (news_id, tagname, tagvalue) VALUES " . $xf_search_words );
	}

	if( $row['category'] AND $approve != $row['approve'] ) {
		
		$db->query( "DELETE FROM " . PREFIX . "_post_extras_cats WHERE news_id = '{$id}'" );

		if($approve) {

			$cat_ids = array ();
	
			$cat_ids_arr = explode( ",", $row['category'] );
	
			foreach ( $cat_ids_arr as $value ) {
	
				$cat_ids[] = "('" . $id . "', '" . trim( $value ) . "')";
	
			}
	
			$cat_ids = implode( ", ", $cat_ids );
			$db->query( "INSERT INTO " . PREFIX . "_post_extras_cats (news_id, cat_id) VALUES " . $cat_ids );

		}

	}
	
	$db->query( "INSERT INTO " . USERPREFIX . "_admin_logs (name, date, ip, action, extras) values ('".$db->safesql($member_id['name'])."', '{$_TIME}', '{$_IP}', '25', '{$_POST['title']}')" );

	if ( $config['allow_alt_url'] AND !$config['seo_type'] ) $cprefix = "full_"; else $cprefix = "full_".$id;	

	clear_cache( array( 'news_', 'rss', $cprefix ) );

	if( $config['news_indexnow'] AND ($approve OR (!$approve AND $approve != $row['approve'] ) ) ) {

		DLESEO::IndexNow( $full_link );

	}

	$buffer = "ok";

} else die( "error" );

$db->close();

echo $buffer;
?>
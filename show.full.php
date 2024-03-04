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
 File: show.full.php
-----------------------------------------------------
 Use: View full news and comments
=====================================================
*/

if( !defined('DATALIFEENGINE') ) {
	header( "HTTP/1.1 403 Forbidden" );
	header ( 'Location: ../../' );
	die( "Hacking attempt!" );
}

	$allow_list = explode( ',', $user_group[$member_id['user_group']]['allow_cats'] );
	$not_allow_cats = explode ( ',', $user_group[$member_id['user_group']]['not_allow_cats'] );
	
	$perm = 1;
	$i = 0;
	$news_found = false;
	$allow_full_cache = false;

	if ( $config['allow_alt_url'] AND !$config['seo_type'] ) $cprefix = "full"; else $cprefix = "full_".$newsid;

	$row = dle_cache ( $cprefix, $sql_news );

	if( $row ) {

		$row = json_decode($row, true);

	}
	
	if ( is_array($row) ) {

		$full_cache = true;
		
	} else {
		
		$row = $db->super_query( $sql_news );
		$full_cache = false;
	}
	
	if ( isset($row['id']) AND $row['id'] ) {
		
		$options = news_permission( $row['access'] );
		
		if( isset($options[$member_id['user_group']]) AND $options[$member_id['user_group']] AND $options[$member_id['user_group']] != 3 ) $perm = 1;
		if( isset($options[$member_id['user_group']]) AND $options[$member_id['user_group']] == 3 ) $perm = 0;
				
		if( isset($options[$member_id['user_group']]) AND $options[$member_id['user_group']] == 1 ) $user_group[$member_id['user_group']]['allow_addc'] = 0;
		if( isset($options[$member_id['user_group']]) AND $options[$member_id['user_group']] == 2 ) $user_group[$member_id['user_group']]['allow_addc'] = 1;
				
		if( $row['id'] AND !$row['approve'] AND $member_id['name'] != $row['autor'] AND !$user_group[$member_id['user_group']]['allow_all_edit'] ) $perm = 0;
	
		if ($row['id'] AND $config['no_date'] AND !$config['news_future'] AND !$user_group[$member_id['user_group']]['allow_all_edit']) {
	
			if( strtotime($row['date']) > $_TIME ) {
				$perm = 0;		
			}
	
		}
	
		$need_pass = $row['need_pass'];
		
		if ($row['id'] AND $need_pass AND $member_id['user_group'] > 2 ) {
	
			if( trim($_POST['news_password']) ) {
				$pass = $db->super_query( "SELECT password FROM " . PREFIX . "_post_pass WHERE news_id='{$row['id']}' " );
				$pass = explode("\n", str_replace("\r", "", $pass['password']));
				$n_passwords = array();
				
				foreach ($pass as $value) {
					$value = trim( $value );
					if($value) $n_passwords[] = $value;
				}
				
				unset($value);unset($pass);
				
				if (in_array(trim($_POST['news_password']), $n_passwords)) {
					$_SESSION['news_pass_'.$row['id'].''] = 1;
				}
	
				unset($n_passwords);
			}
		
			if( !$_SESSION['news_pass_'.$row['id'].''] ) {
				
				$perm = 0;
				
			} else $need_pass = false;
	
		}
	
		if( !$row['category'] ) {
			
			$my_cat = "---";
			$my_cat_link = "---";
			
			$tpl->set( '[not-has-category]', "" );
			$tpl->set( '[/not-has-category]', "" );
			$tpl->set_block( "'\\[has-category\\](.*?)\\[/has-category\\]'si", "" );
			
		} else {
				
			$my_cat = array ();
			$my_cat_link = array ();
			$cat_list = $row['cats'] = explode( ',', $row['category'] );
			
			$tpl->set( '[has-category]', "" );
			$tpl->set( '[/has-category]', "" );
			$tpl->set_block( "'\\[not-has-category\\](.*?)\\[/not-has-category\\]'si", "" );
		
			if( count( $cat_list ) == 1 ) {
					
				if( $allow_list[0] != "all" AND !in_array( $cat_list[0], $allow_list ) ) $perm = 0;
	
				if( $not_allow_cats[0] != "" AND in_array( $cat_list[0], $not_allow_cats ) ) $perm = 0;
					
				if( $cat_info[$cat_list[0]]['id'] ) {
					$my_cat[] = $cat_info[$cat_list[0]]['name'];
					$my_cat_link = get_categories( $cat_list[0], $config['category_separator']);
				} else {
					$my_cat_link = "---";
				}
				
			} else {
					
				foreach ( $cat_list as $element ) {
						
					if( $allow_list[0] != "all" AND !in_array( $element, $allow_list ) ) $perm = 0;
					
					if( $not_allow_cats[0] != "" AND in_array( $element, $not_allow_cats ) ) $perm = 0;
						
					if( $element AND $cat_info[$element]['id'] ) {
						$my_cat[] = $cat_info[$element]['name'];
						if( $config['allow_alt_url'] ) $my_cat_link[] = "<a href=\"" . $config['http_home_url'] . get_url( $element ) . "/\">{$cat_info[$element]['name']}</a>";
						else $my_cat_link[] = "<a href=\"$PHP_SELF?do=cat&amp;category={$cat_info[$element]['alt_name']}\">{$cat_info[$element]['name']}</a>";
					}
				}
					
				if( count( $my_cat_link ) ) {
					$my_cat_link = implode( $config['category_separator'], $my_cat_link );
				} else $my_cat_link = "---";
			}
				
			if( count( $my_cat ) ) {
				$my_cat = implode( $config['category_separator'], $my_cat );
			} else $my_cat = "---";
			
		}
	}

	if ( isset($row['id']) AND $row['id'] AND  $perm ) {

		$config['fullcache_days'] = intval($config['fullcache_days']);
		
		if( $config['fullcache_days'] < 1 ) $config['fullcache_days'] = 30;

		if( strtotime($row['date']) >= ($_TIME - ($config['fullcache_days'] * 86400)) ) {
				
			$allow_full_cache = true;
			
		}

		define( 'NEWS_ID', $row['id'] );
		$category_id = intval( $row['category'] );
		$tpl->news_mode = true;

		if ( isset($cat_info[$category_id]['schema_org']) AND $cat_info[$category_id]['schema_org'] != '1' ) {
			$config['schema_org'] = $cat_info[$category_id]['schema_org'];
		}
			
		if( $config['schema_org'] ) {
			
			$schema = DLESEO::Thing($config['schema_org']);
			if( $config['site_type'] == 'Person') {
				$schema->publisher =  DLESEO::Thing($config['site_type'],  array('name' => $config['pub_name'] ), false );
			} else {
				$schema->publisher =  DLESEO::Thing($config['site_type'],  array('name' => $config['pub_name'], 'logo' => array('@type' => "ImageObject", 'url' => $config['site_icon'] ) ), false );	
			}
			
		}
		
		$disable_index = $row['disable_index'];
		$news_author = $row['user_id'];
	
		$xfields = xfieldsload();

		if($config['last_viewed']) {
			$onload_scripts[] = "save_last_viewed('{$row['id']}');";
		}
		
		if( $row['votes'] AND $view_template != "print" ) include_once (DLEPlugins::Check(ENGINE_DIR . '/modules/poll.php'));
		
		if( $view_template == "print" ) $tpl->load_template( 'print.tpl' );
		elseif( $category_id and $cat_info[$category_id]['full_tpl'] != '' ) $tpl->load_template( $cat_info[$category_id]['full_tpl'] . '.tpl' );
		else $tpl->load_template( 'fullstory.tpl' );

		if( stripos( $tpl->copy_template, "{next-" ) !== false OR stripos( $tpl->copy_template, "{prev-" ) !== false) {
			$link = "";
			$prev_next = false;
			
			if( $allow_full_cache ) {
				$prev_next = dle_cache ( "news", "next_prev_l_".$row['id'] );
				if( $prev_next ) $prev_next = json_decode($prev_next, true);
			}

			if( !is_array($prev_next) ) {
				
				$prev_next = array();
				
				$row_link = $db->super_query( "SELECT id, date, title, category, alt_name FROM " . PREFIX . "_post WHERE category = '{$row['category']}' AND date >= '{$row['date']}'{$where_date} AND id != '{$row['id']}' AND approve = '1' ORDER BY date ASC LIMIT 1" );
				
				if( isset($row_link['id']) AND $row_link['id'] ) {
					if( $config['allow_alt_url'] ) {
						if( $config['seo_type'] == 1 OR $config['seo_type'] == 2 ) {
							if( intval( $row_link['category'] ) and $config['seo_type'] == 2 ) {
								
								$cats_url = get_url( $row_link['category'] );
								if( $cats_url ) $cats_url .= "/";
								
								$link = $config['http_home_url'] . $cats_url . $row_link['id'] . "-" . $row_link['alt_name'] . ".html";
							} else {
								$link = $config['http_home_url'] . $row_link['id'] . "-" . $row_link['alt_name'] . ".html";
							}
						} else {
							$link = $config['http_home_url'] . date( 'Y/m/d/', strtotime( $row_link['date'] ) ) . $row_link['alt_name'] . ".html";
						}
					} else {
						$link = $config['http_home_url'] . "index.php?newsid=" . $row_link['id'];
					}
					
					$prev_next['next_title'] = str_replace("&amp;amp;", "&amp;", htmlspecialchars( strip_tags( stripslashes( $row_link['title'] ) ), ENT_QUOTES, $config['charset'] ) );
					
				} else $prev_next['next_title'] = "";
				
				$prev_next['next_link'] = $link;
				$link = "";
					
				$row_link = $db->super_query( "SELECT id, date, title, category, alt_name FROM " . PREFIX . "_post WHERE category = '{$row['category']}' AND date <= '{$row['date']}'{$where_date} AND id != '{$row['id']}' AND approve = '1' ORDER BY date DESC LIMIT 1" );
				
				if( isset( $row_link['id'] ) AND $row_link['id'] ) {
					if( $config['allow_alt_url'] ) {
						if( $config['seo_type'] == 1 OR $config['seo_type'] == 2 ) {
							if( intval( $row_link['category'] ) and $config['seo_type'] == 2 ) {
								
								$cats_url = get_url( $row_link['category'] );
								if( $cats_url ) $cats_url .= "/";
								
								$link = $config['http_home_url'] .$cats_url . $row_link['id'] . "-" . $row_link['alt_name'] . ".html";
							} else {
								$link = $config['http_home_url'] . $row_link['id'] . "-" . $row_link['alt_name'] . ".html";
							}
						} else {
							$link = $config['http_home_url'] . date( 'Y/m/d/', strtotime( $row_link['date'] ) ) . $row_link['alt_name'] . ".html";
						}
					} else {
						$link = $config['http_home_url'] . "index.php?newsid=" . $row_link['id'];
					}
					
					$prev_next['prev_title'] = str_replace("&amp;amp;", "&amp;", htmlspecialchars( strip_tags( stripslashes( $row_link['title'] ) ), ENT_QUOTES, $config['charset'] ) );

				} else $prev_next['prev_title'] = "";
				
				$prev_next['prev_link'] = $link;
				
				if ($allow_full_cache) create_cache ( "news", json_encode($prev_next, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ), "next_prev_l_".$row['id'] );

			}
			
			if ( $prev_next['next_link'] ) {
				$tpl->set( '[next-url]', "" );
				$tpl->set( '[/next-url]', "" );
				$tpl->set( '{next-url}', $prev_next['next_link'] );
				$tpl->set( '{next-title}', $prev_next['next_title'] );
			} else {
				$tpl->set( '{next-url}', "" );
				$tpl->set( '{next-title}', "" );
				$tpl->set_block( "'\\[next-url\\](.*?)\\[/next-url\\]'si", "" );
			}
			
			if ( $prev_next['prev_link'] ) {
				$tpl->set( '[prev-url]', "" );
				$tpl->set( '[/prev-url]', "" );
				$tpl->set( '{prev-url}', $prev_next['prev_link'] );
				$tpl->set( '{prev-title}', $prev_next['prev_title'] );
			} else {
				$tpl->set( '{prev-url}', "" );
				$tpl->set( '{prev-title}', "" );
				$tpl->set_block( "'\\[prev-url\\](.*?)\\[/prev-url\\]'si", "" );
			}

		}
		
		if( $config['allow_read_count'] AND !$news_page AND !$cstart) {
			
			$read_count_time = intval($config['read_count_time']) * 1000;
			
			if( $read_count_time < 1000 ) $read_count_time = 5000;
			
				$onload_scripts[] = <<<HTML
					setTimeout(function() {
						$.get(dle_root + "engine/ajax/controller.php?mod=adminfunction", { 'id': '{$row['id']}', action: 'newsread', user_hash: dle_login_hash });
					}, $read_count_time);
HTML;

		}
		
		if ($allow_full_cache AND !$full_cache) create_cache ( $cprefix, json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ), $sql_news );

		$news_found = true;
		$empty_full = false;
		$row['date'] = strtotime( $row['date'] );
		
		if( (strlen( $row['full_story'] ) < 13) and (strpos( $tpl->copy_template, "{short-story}" ) === false) ) {
			$row['full_story'] = $row['short_story'];
			$empty_full = true;
		}

		if( !$news_page ) {
			$news_page = 1;
		}

		if( $config['allow_alt_url'] ) {
			
			if( $config['seo_type'] == 1 OR $config['seo_type'] == 2 ) {
				
				if( $category_id AND $config['seo_type'] == 2 ) {

					$c_url = get_url(  $row['category'] );

					if($c_url) {
						$full_link = $config['http_home_url'] . $c_url . "/" . $row['id'] . "-" . $row['alt_name'] . ".html";
					} else {
						$full_link = $config['http_home_url'] . $row['id'] . "-" . $row['alt_name'] . ".html";
					}

					if ($config['seo_control'] AND ( isset($_GET['seourl']) OR strpos ( $_SERVER['REQUEST_URI'], "?" ) !== false ) ) {

						if ($_GET['seourl'] != $row['alt_name'] OR $_GET['seocat'] != $c_url OR strpos ( $_SERVER['REQUEST_URI'], "?" ) !== false OR (isset($_GET['news_page']) AND $_GET['news_page'] == 1 AND $cstart < 2 AND $view_template != "print") OR ($view_template == "print" AND $news_page > 1) ) {

							$re_url = explode ( "index.php", strtolower ( $_SERVER['PHP_SELF'] ) );
							$re_url = reset ( $re_url );

							header("HTTP/1.0 301 Moved Permanently");
							header("Location: {$re_url}{$c_url}/{$row['id']}-{$row['alt_name']}.html");
							die("Redirect");

						}

					}

					$print_link = $config['http_home_url'] . $c_url . "/print:page,1," . $row['id'] . "-" . $row['alt_name'] . ".html";
					$short_link = $config['http_home_url'] . $c_url . "/";
					$row['alt_name'] = $row['id'] . "-" . $row['alt_name'];
					$link_page = $config['http_home_url'] . $c_url . "/" . 'page,' . $news_page . ',';
					$news_name = $row['alt_name'];
				
				} else {
				
					$full_link = $config['http_home_url'] . $row['id'] . "-" . $row['alt_name'] . ".html";

					if ($config['seo_control'] AND ( isset($_GET['seourl']) OR strpos ( $_SERVER['REQUEST_URI'], "?" ) !== false ) ) {

						if ($_GET['seourl'] != $row['alt_name'] OR (isset($_GET['seocat']) AND $_GET['seocat']) OR (isset($_GET['news_name']) AND $_GET['news_name']) OR strpos ( $_SERVER['REQUEST_URI'], "?" ) !== false OR (isset($_GET['news_page']) AND $_GET['news_page'] == 1 AND $cstart < 2 AND $view_template != "print") OR ($view_template == "print" AND $news_page > 1) ) {

							$re_url = explode ( "index.php", strtolower ( $_SERVER['PHP_SELF'] ) );
							$re_url = reset ( $re_url );

							header("HTTP/1.0 301 Moved Permanently");
							header("Location: {$re_url}{$row['id']}-{$row['alt_name']}.html");
							die("Redirect");

						}

					}

					$print_link = $config['http_home_url'] . "print:page,1," . $row['id'] . "-" . $row['alt_name'] . ".html";
					$short_link = $config['http_home_url'];
					$row['alt_name'] = $row['id'] . "-" . $row['alt_name'];
					$link_page = $config['http_home_url'] . 'page,' . $news_page . ',';
					$news_name = $row['alt_name'];
				
				}
			
			} else {
				
				$full_link = $config['http_home_url'] . date( 'Y/m/d/', $row['date'] ) . $row['alt_name'] . ".html";

				if ( $config['seo_control'] ) {

					if ($_GET['newsid'] OR strpos ( $_SERVER['REQUEST_URI'], "?" ) !== false OR ($_GET['news_page'] == 1 AND $cstart < 2 AND $view_template != "print") OR ($view_template == "print" AND $news_page > 1) ) {

						$re_url = explode ( "index.php", strtolower ( $_SERVER['PHP_SELF'] ) );
						$re_url = reset ( $re_url );

						header("HTTP/1.0 301 Moved Permanently");
						header("Location: {$re_url}".date( 'Y/m/d/', $row['date'] ).$row['alt_name'].".html");
						die("Redirect");

					}

				}

				$print_link = $config['http_home_url'] . date( 'Y/m/d/', $row['date'] ) . "print:page,1," . $row['alt_name'] . ".html";
				$short_link = $config['http_home_url'] . date( 'Y/m/d/', $row['date'] );
				$link_page = $config['http_home_url'] . date( 'Y/m/d/', $row['date'] ) . 'page,' . $news_page . ',';
				$news_name = $row['alt_name'];
			
			}
		
		} else {
			
			$full_link = $config['http_home_url'] . "index.php?newsid=" . $row['id'];
			$print_link = $config['http_home_url'] . "index.php?mod=print&newsid=" . $row['id'];
			$short_link = "";
			$link_page = "";
			$news_name = "";
		
		}
		
		$i ++;

		$canonical = $full_link;

		$news_seiten = explode( "{PAGEBREAK}", $row['full_story'] );
		$anzahl_seiten = count( $news_seiten );
		
		if( $news_page <= 0 OR $news_page > $anzahl_seiten OR (isset($_GET['news_page']) AND $_GET['news_page'] === "0") ) {
			
			$news_page = 1;

			if ( $config['seo_control'] ) {
				
				$re_url = parse_url($full_link, PHP_URL_PATH);
				
				header("HTTP/1.0 301 Moved Permanently");
				header("Location: {$re_url}");
				die("Redirect");
			}
		}

		if( $view_template == "print" ) {
			
			$row['full_story'] = str_replace( "{PAGEBREAK}", "", $row['full_story'] );
			$row['full_story'] = preg_replace( "'\[page=(.*?)\](.*?)\[/page\]'si", "\\2", $row['full_story'] );
			$tpl->set_block( "'\\[pages\\](.*?)\\[/pages\\]'si", "" );
			$tpl->set( '{pages}', "" );
		
		} else {
			
			$row['full_story'] = $news_seiten[$news_page - 1];
			
			$row['full_story'] = preg_replace( '#(\A[\s]*<br[^>]*>[\s]*|<br[^>]*>[\s]*\Z)#is', '', $row['full_story'] ); 
			unset( $news_seiten );
			
			if( $anzahl_seiten > 1 ) {

				$tpl2 = new dle_template();
				$tpl2->dir = TEMPLATE_DIR;
				$tpl2->load_template( 'splitnewsnavigation.tpl' );
				
				if( $news_page < $anzahl_seiten ) {
					$pages = $news_page + 1;
					
					if( $config['allow_alt_url'] ) {
						$nextpage = "<a href=\"" . $short_link . "page," . $pages . "," . $row['alt_name'] . ".html\">";
					} else {
						$nextpage = "<a href=\"$PHP_SELF?newsid=" . $row['id'] . "&amp;news_page=" . $pages . "\">";
					}

					$tpl2->set( '[next-link]', $nextpage );
					$tpl2->set( '[/next-link]', "</a>" );

				} else {

					$tpl2->set_block( "'\\[next-link\\](.*?)\\[/next-link\\]'si", "<span>\\1</span>" );

				}
				
				if( $news_page > 1 ) {
					$pages = $news_page - 1;
					
					if( $config['allow_alt_url'] ) {
						if ( $pages == 1 ) $prevpage = "<a href=\"" . $full_link . "\">";
						else $prevpage = "<a href=\"" . $short_link . "page," . $pages . "," . $row['alt_name'] . ".html\">";
					} else {
						if ( $pages == 1 ) $prevpage = "<a href=\"" . $full_link. "\">";
						else $prevpage = "<a href=\"$PHP_SELF?newsid=" . $row['id'] . "&amp;news_page=" . $pages . "\">";
					}

					$tpl2->set( '[prev-link]', $prevpage );
					$tpl2->set( '[/prev-link]', "</a>" );

				} else {

					$tpl2->set_block( "'\\[prev-link\\](.*?)\\[/prev-link\\]'si", "<span>\\1</span>" );

				}

				$listpages ="";

				if( $anzahl_seiten <= 10 ) {
					
					for($j = 1; $j <= $anzahl_seiten; $j ++) {
						
						if( $j != $news_page ) {
							
							if( $config['allow_alt_url'] ) {

								if ($j == 1)
									$listpages .= "<a href=\"" . $full_link . "\">$j</a> ";
								else
									$listpages .= "<a href=\"" . $short_link . "page," . $j . "," . $row['alt_name'] . ".html\">$j</a> ";

							} else {

								if ($j == 1)
									$listpages .= "<a href=\"{$full_link}\">$j</a> ";
								else
									$listpages .= "<a href=\"$PHP_SELF?newsid=" . $row['id'] . "&amp;news_page=" . $j . "\">$j</a> ";

							}
						
						} else {
							
							$listpages .= "<span>$j</span> ";
							
							if( $config['allow_alt_url'] ) {

								if($j != 1) $canonical = $short_link . "page," . $j . "," . $row['alt_name'] . ".html";
								
							} else {
								
								if($j != 1) $canonical = "$PHP_SELF?newsid=" . $row['id'] . "&news_page=" . $j;
								
							}
						}
					
					}

				} else {
					
					$start = 1;
					$end = 10;
					$nav_prefix = "<span class=\"nav_ext\">{$lang['nav_trennen']}</span> ";
					
					if( $news_page > 1 ) {
						
						if( $news_page > 6 ) {
							
							$start = $news_page - 4;
							$end = $start + 8;
							
							if( $end >= $anzahl_seiten-1 ) {
								$start = $anzahl_seiten - 9;
								$end = $anzahl_seiten - 1;
							}
						
						}
					
					}
					
					if( $end >= $anzahl_seiten-1 ) $nav_prefix = ""; else $nav_prefix = "<span class=\"nav_ext\">{$lang['nav_trennen']}</span> ";
					
					if( $start >= 2 ) {
						
						if( $start >= 3 ) $before_prefix = "<span class=\"nav_ext\">{$lang['nav_trennen']}</span> "; else $before_prefix = "";
						
						$listpages .= "<a href=\"" . $full_link . "\">1</a> ".$before_prefix;
					
					}
					
					for($j = $start; $j <= $end; $j ++) {
						
						if( $j != $news_page ) {

							if( $config['allow_alt_url'] ) {

								if ($j == 1)
									$listpages .= "<a href=\"" . $full_link . "\">$j</a> ";
								else
									$listpages .= "<a href=\"" . $short_link . "page," . $j . "," . $row['alt_name'] . ".html\">$j</a> ";

							} else {

								if ($j == 1)
									$listpages .= "<a href=\"{$full_link}\">$j</a> ";
								else
									$listpages .= "<a href=\"$PHP_SELF?newsid=" . $row['id'] . "&amp;news_page=" . $j . "\">$j</a> ";

							}
						
						} else {
							
							$listpages .= "<span>$j</span> ";
						}
					
					}
					
					if( $news_page != $anzahl_seiten ) {
						
						if( $config['allow_alt_url'] ) $listpages .= $nav_prefix . "<a href=\"" . $short_link . "page," . $anzahl_seiten . "," . $row['alt_name'] . ".html\">{$anzahl_seiten}</a>";
						else $listpages .= $nav_prefix . "<a href=\"$PHP_SELF?newsid=" . $row['id'] . "&amp;news_page=" . $anzahl_seiten . "\">{$anzahl_seiten}</a>";
					
					} else
						$listpages .= "<span>{$anzahl_seiten}</span> ";

				}

				$tpl2->set( '{pages}', $listpages );
				$tpl2->compile( 'content' );
				
				$tpl->set( '{pages}', $tpl2->result['content'] );
				unset($tpl2);
				
				if( $config['allow_alt_url'] ) {
					
					$replacepage = "<a href=\"" . $short_link . "page," . "\\1" . "," . $row['alt_name'] . ".html\">\\2</a>";
				
				} else {
					
					$replacepage = "<a href=\"$PHP_SELF?newsid=" . $row['id'] . "&amp;news_page=\\1\">\\2</a>";
				}
				
				$row['full_story'] = preg_replace( "'\[page=(.*?)\](.*?)\[/page\]'si", $replacepage, $row['full_story'] );
				$tpl->set( '[pages]', "" );
				$tpl->set( '[/pages]', "" );

			
			} else {
				
				$tpl->set( '{pages}', '' );
				$row['full_story'] = preg_replace( "'\[page=(.*?)\](.*?)\[/page\]'si", "", $row['full_story'] );
				$tpl->set_block( "'\\[pages\\](.*?)\\[/pages\\]'si", "" );
			}
		}

		$row['title'] = stripslashes( $row['title'] );		
		$metatags['title'] = $row['title'];

		if( $config['schema_org'] ) {
			$schema->headline = $schema->name = $row['title'];
			
			if($config['schema_org'] == "SoftwareApplication") {
				$schema->applicationCategory = $my_cat;
			}
		}
		
		if( $config['create_metatags'] AND (!$row['keywords'] AND !$row['descr'] OR $news_page > 1) ) {
			create_keywords( $row['full_story'] );
		} else {
			$metatags['keywords'] = $row['keywords'];
			if( $row['descr'] ) $metatags['description'] = $row['descr']; else $metatags['description'] = $row['title'];
		}

		if ($row['metatitle']) $metatags['header_title'] = $row['metatitle'];

		$social_tags['url'] = $canonical;

		if( $config['schema_org'] ) {
			$schema->mainEntityOfPage = DLESEO::Thing("WebPage",  array('@id' => $canonical), false );
			$schema->datePublished = date('c', $row['date'] );
		}
		
		$comments_num = $row['comm_num'];
		
		$news_find = array ('{comments-num}' => number_format($row['comm_num'], 0, ',', ' '), '{views}' => number_format($row['news_read'], 0, ',', ' '), '{category}' => $my_cat, '{link-category}' => $my_cat_link, '{news-id}' => $row['id'] );
		
		$compare_date = compare_days_date( $row['date'] );
		
		if( !$compare_date ) {
			
			$tpl->set( '{date}', $lang['time_heute'] . langdate( ", H:i", $row['date'] ) );
		
		} elseif( $compare_date == 1 ) {
			
			$tpl->set( '{date}', $lang['time_gestern'] . langdate( ", H:i", $row['date'] ) );
		
		} else {
			
			$tpl->set( '{date}', langdate( $config['timestamp_active'], $row['date'] ) );
		
		}

		$news_date = $row['date'];
		$tpl->copy_template = preg_replace_callback ( "#\{date=(.+?)\}#i", "formdate", $tpl->copy_template );

		if ( $row['fixed'] ) {

			$tpl->set( '[fixed]', "" );
			$tpl->set( '[/fixed]', "" );
			$tpl->set_block( "'\\[not-fixed\\](.*?)\\[/not-fixed\\]'si", "" );

		} else {

			$tpl->set( '[not-fixed]', "" );
			$tpl->set( '[/not-fixed]', "" );
			$tpl->set_block( "'\\[fixed\\](.*?)\\[/fixed\\]'si", "" );
		}
		
		if ( $comments_num ) {
			
			$tpl->set( '[comments]', "" );
			$tpl->set( '[/comments]', "" );
			$tpl->set_block( "'\\[not-comments\\](.*?)\\[/not-comments\\]'si", "" );

		} else {
			
			$tpl->set( '[not-comments]', "" );
			$tpl->set( '[/not-comments]', "" );
			$tpl->set_block( "'\\[comments\\](.*?)\\[/comments\\]'si", "" );
		}

		if ( $row['votes'] ) {

			$tpl->set( '[poll]', "" );
			$tpl->set( '[/poll]', "" );
			$tpl->set_block( "'\\[not-poll\\](.*?)\\[/not-poll\\]'si", "" );

		} else {

			$tpl->set( '[not-poll]', "" );
			$tpl->set( '[/not-poll]', "" );
			$tpl->set_block( "'\\[poll\\](.*?)\\[/poll\\]'si", "" );
		}	

		if( $vk_url ) {
			$tpl->set( '[vk]', "" );
			$tpl->set( '[/vk]', "" );
			$tpl->set( '{vk_url}', $vk_url );	
		} else {
			$tpl->set_block( "'\\[vk\\](.*?)\\[/vk\\]'si", "" );
			$tpl->set( '{vk_url}', '' );	
		}
		if( $odnoklassniki_url ) {
			$tpl->set( '[odnoklassniki]', "" );
			$tpl->set( '[/odnoklassniki]', "" );
			$tpl->set( '{odnoklassniki_url}', $odnoklassniki_url );
		} else {
			$tpl->set_block( "'\\[odnoklassniki\\](.*?)\\[/odnoklassniki\\]'si", "" );
			$tpl->set( '{odnoklassniki_url}', '' );	
		}
		if( $facebook_url ) {
			$tpl->set( '[facebook]', "" );
			$tpl->set( '[/facebook]', "" );
			$tpl->set( '{facebook_url}', $facebook_url );	
		} else {
			$tpl->set_block( "'\\[facebook\\](.*?)\\[/facebook\\]'si", "" );
			$tpl->set( '{facebook_url}', '' );	
		}
		if( $google_url ) {
			$tpl->set( '[google]', "" );
			$tpl->set( '[/google]', "" );
			$tpl->set( '{google_url}', $google_url );
		} else {
			$tpl->set_block( "'\\[google\\](.*?)\\[/google\\]'si", "" );
			$tpl->set( '{google_url}', '' );	
		}
		if( $mailru_url ) {
			$tpl->set( '[mailru]', "" );
			$tpl->set( '[/mailru]', "" );
			$tpl->set( '{mailru_url}', $mailru_url );	
		} else {
			$tpl->set_block( "'\\[mailru\\](.*?)\\[/mailru\\]'si", "" );
			$tpl->set( '{mailru_url}', '' );	
		}
		if( $yandex_url ) {
			$tpl->set( '[yandex]', "" );
			$tpl->set( '[/yandex]', "" );
			$tpl->set( '{yandex_url}', $yandex_url );
		} else {
			$tpl->set_block( "'\\[yandex\\](.*?)\\[/yandex\\]'si", "" );
			$tpl->set( '{yandex_url}', '' );
		}
		
		if( $row['editdate'] AND $row['editdate'] > $_DOCUMENT_DATE ) $_DOCUMENT_DATE = $row['editdate'];
		elseif( $row['date'] > $_DOCUMENT_DATE ) $_DOCUMENT_DATE = $row['date'];

		if( $config['schema_org'] AND $row['editdate']) {
			$schema->dateModified = date('c', $row['editdate'] );
		}

		if( $row['view_edit'] and $row['editdate'] ) {

			$compare_date = compare_days_date($row['editdate']);

			if( !$compare_date ) {
				
				$tpl->set( '{edit-date}', $lang['time_heute'] . langdate( ", H:i", $row['editdate'] ) );
			
			} elseif( $compare_date == 1 ) {
				
				$tpl->set( '{edit-date}', $lang['time_gestern'] . langdate( ", H:i", $row['editdate'] ) );
			
			} else {
				
				$tpl->set( '{edit-date}', langdate( $config['timestamp_active'], $row['editdate'] ) );
			
			}

			$news_date = $row['editdate'];
			$tpl->copy_template = preg_replace_callback("#\{edit-date=(.+?)\}#i", "formdate", $tpl->copy_template);

			$tpl->set( '{editor}', $row['editor'] );
			$tpl->set( '{edit-reason}', $row['reason'] );
			
			if( $row['reason'] ) {
				
				$tpl->set( '[edit-reason]', "" );
				$tpl->set( '[/edit-reason]', "" );
			
			} else
				$tpl->set_block( "'\\[edit-reason\\](.*?)\\[/edit-reason\\]'si", "" );
			
			$tpl->set( '[edit-date]', "" );
			$tpl->set( '[/edit-date]', "" );
		
		} else {
			
			$tpl->set( '{edit-date}', "" );
			$tpl->set( '{editor}', "" );
			$tpl->set( '{edit-reason}', "" );
			$tpl->set_block( "'\\[edit-date\\](.*?)\\[/edit-date\\]'si", "" );
			$tpl->set_block( "'\\[edit-reason\\](.*?)\\[/edit-reason\\]'si", "" );
		}
		
		if( $config['allow_tags'] and $row['tags'] ) {
			
			$tpl->set( '[tags]', "" );
			$tpl->set( '[/tags]', "" );
			
			$social_tags['news_keywords'] = $row['tags'];
		
			$tags = array ();
			
			$row['tags'] = explode( ",", $row['tags'] );
			
			foreach ( $row['tags'] as $value ) {
				
				$value = trim( $value );
				$url_tag = str_replace(array("&#039;", "&quot;", "&amp;", "/"), array("'", '"', "&", "&frasl;"), $value);
				
				if( $config['allow_alt_url'] ) $tags[] = "<a href=\"" . $config['http_home_url'] . "tags/" . rawurlencode( dle_strtolower($url_tag) ) . "/\">" . $value . "</a>";
				else $tags[] = "<a href=\"$PHP_SELF?do=tags&amp;tag=" . rawurlencode( dle_strtolower($url_tag) ) . "\">" . $value . "</a>";
			
			}
			
			$tpl->set( '{tags}', implode( $config['tags_separator'], $tags ) );
		
		} else {
			
			$tpl->set_block( "'\\[tags\\](.*?)\\[/tags\\]'si", "" );
			$tpl->set( '{tags}', "" );
		
		}
		
		$tpl->set( '', $news_find );

		$url_cat = $category_id;
		$category_id = $row['category'];

		if( strpos( $tpl->copy_template, "[catlist=" ) !== false ) {
			$tpl->copy_template = preg_replace_callback ( "#\\[(catlist)=(.+?)\\](.*?)\\[/catlist\\]#is", "check_category", $tpl->copy_template );
		}
								
		if( strpos( $tpl->copy_template, "[not-catlist=" ) !== false ) {
			$tpl->copy_template = preg_replace_callback ( "#\\[(not-catlist)=(.+?)\\](.*?)\\[/not-catlist\\]#is", "check_category", $tpl->copy_template );
		}
		
		$temp_rating = $config['rating_type'];
		$config['rating_type'] = if_category_rating( $row['category'] );
		
		if ( $config['rating_type'] === false ) {
			$config['rating_type'] = $temp_rating;
		}
		
		$category_id = $url_cat;
	
		if( $category_id AND $cat_info[$category_id]['icon'] ) {
			
			$tpl->set( '{category-icon}', $cat_info[$category_id]['icon'] );
			$tpl->set( '[category-icon]', "" );
			$tpl->set( '[/category-icon]', "" );
			$tpl->set_block( "'\\[not-category-icon\\](.*?)\\[/not-category-icon\\]'si", "" );
			
		} else {
			
			$tpl->set( '{category-icon}', "{THEME}/dleimages/no_icon.gif" );
			$tpl->set( '[not-category-icon]', "" );
			$tpl->set( '[/not-category-icon]', "" );
			$tpl->set_block( "'\\[category-icon\\](.*?)\\[/category-icon\\]'si", "" );
			
		}
		
		if ( $row['category'] ) {
			
			if( $config['allow_alt_url'] ) {
				
				$cats_url = get_url( $row['category'] );
				
				if( $cats_url ) $cats_url .= "/";
			
				$tpl->set( '{category-url}', $config['http_home_url'] . $cats_url );
				
			} else {
				
				$cats_url = intval($row['category']);
				$tpl->set( '{category-url}', "{$PHP_SELF}?do=cat&category=".$cat_info[$cats_url]['alt_name'] );
				
			}
			
		} else $tpl->set( '{category-url}', "#" );	
		
		if ($config['allow_search_print']) {

			$tpl->set( '[print-link]', "<a href=\"" . $print_link . "\">" );
			$tpl->set( '[/print-link]', "</a>" );

		} else {

			$tpl->set( '[print-link]', "<a href=\"" . $print_link . "\" rel=\"nofollow\">" );
			$tpl->set( '[/print-link]', "</a>" );

		}

		if ( $config['rating_type'] == "1" ) {
				$tpl->set( '[rating-type-2]', "" );
				$tpl->set( '[/rating-type-2]', "" );
				$tpl->set_block( "'\\[rating-type-1\\](.*?)\\[/rating-type-1\\]'si", "" );
				$tpl->set_block( "'\\[rating-type-3\\](.*?)\\[/rating-type-3\\]'si", "" );
				$tpl->set_block( "'\\[rating-type-4\\](.*?)\\[/rating-type-4\\]'si", "" );
		} elseif ( $config['rating_type'] == "2" ) {
				$tpl->set( '[rating-type-3]', "" );
				$tpl->set( '[/rating-type-3]', "" );
				$tpl->set_block( "'\\[rating-type-1\\](.*?)\\[/rating-type-1\\]'si", "" );
				$tpl->set_block( "'\\[rating-type-2\\](.*?)\\[/rating-type-2\\]'si", "" );
				$tpl->set_block( "'\\[rating-type-4\\](.*?)\\[/rating-type-4\\]'si", "" );
		} elseif ( $config['rating_type'] == "3" ) {
				$tpl->set( '[rating-type-4]', "" );
				$tpl->set( '[/rating-type-4]', "" );
				$tpl->set_block( "'\\[rating-type-1\\](.*?)\\[/rating-type-1\\]'si", "" );
				$tpl->set_block( "'\\[rating-type-2\\](.*?)\\[/rating-type-2\\]'si", "" );
				$tpl->set_block( "'\\[rating-type-3\\](.*?)\\[/rating-type-3\\]'si", "" );
		} else {
				$tpl->set( '[rating-type-1]', "" );
				$tpl->set( '[/rating-type-1]', "" );
				$tpl->set_block( "'\\[rating-type-4\\](.*?)\\[/rating-type-4\\]'si", "" );
				$tpl->set_block( "'\\[rating-type-3\\](.*?)\\[/rating-type-3\\]'si", "" );
				$tpl->set_block( "'\\[rating-type-2\\](.*?)\\[/rating-type-2\\]'si", "" );	
		}	

		if( $row['allow_rate'] ) {
			
			$dislikes = ($row['vote_num'] - $row['rating'])/2;
			$likes = $row['vote_num'] - $dislikes;
			
			$tpl->set( '{likes}', "<span data-likes-id=\"" . $row['id'] . "\">".$likes."</span>" );
			$tpl->set( '{dislikes}', "<span data-dislikes-id=\"" . $row['id'] . "\">".$dislikes."</span>" );
			
			$tpl->set( '{rating}', ShowRating( $row['id'], $row['rating'], $row['vote_num'], $user_group[$member_id['user_group']]['allow_rating'] ) );
			$tpl->set( '{vote-num}', "<span data-vote-num-id=\"" . $row['id'] . "\">".$row['vote_num']."</span>" );
			$tpl->set( '[rating]', "" );
			$tpl->set( '[/rating]', "" );

			if( $row['vote_num'] ) $ratingscore = str_replace( ',', '.', round( ($row['rating'] / $row['vote_num']), 1 ) );
			else $ratingscore = 0;

			$tpl->set( '{ratingscore}', $ratingscore );
			
			if( $config['schema_org'] AND $row['vote_num'] AND !$config['rating_type'] AND in_array($config['schema_org'], array ('Book', 'Movie', 'Recipe', 'Product', 'SoftwareApplication') ) ) {
				$schema->aggregateRating = DLESEO::Thing("AggregateRating",  array('ratingValue' => $ratingscore, 'ratingCount' => $row['vote_num'], 'worstRating' => 1, 'bestRating' => 5), false );
			}
		
			if( $user_group[$member_id['user_group']]['allow_rating'] ) {

				if ( $config['rating_type'] ) {
						
					$tpl->set( '[rating-plus]', "<a href=\"#\" onclick=\"doRate('plus', '{$row['id']}'); return false;\" >" );
					$tpl->set( '[/rating-plus]', '</a>' );
					
					if ( $config['rating_type'] == "2" OR $config['rating_type'] == "3") {
						
						$tpl->set( '[rating-minus]', "<a href=\"#\" onclick=\"doRate('minus', '{$row['id']}'); return false;\" >" );
						$tpl->set( '[/rating-minus]', '</a>' );
						
					} else {
						$tpl->set_block( "'\\[rating-minus\\](.*?)\\[/rating-minus\\]'si", "" );
					}
					
				} else {
					$tpl->set_block( "'\\[rating-plus\\](.*?)\\[/rating-plus\\]'si", "" );
					$tpl->set_block( "'\\[rating-minus\\](.*?)\\[/rating-minus\\]'si", "" );
				}
				
			} else {
				$tpl->set_block( "'\\[rating-plus\\](.*?)\\[/rating-plus\\]'si", "" );
				$tpl->set_block( "'\\[rating-minus\\](.*?)\\[/rating-minus\\]'si", "" );				
			}

		} else { 

			$tpl->set( '{rating}', "" );
			$tpl->set( '{vote-num}', "" );
			$tpl->set( '{likes}', "" );
			$tpl->set( '{dislikes}', "" );
			$tpl->set( '{ratingscore}', "" );
			$tpl->set_block( "'\\[rating\\](.*?)\\[/rating\\]'si", "" );
			$tpl->set_block( "'\\[rating-plus\\](.*?)\\[/rating-plus\\]'si", "" );
			$tpl->set_block( "'\\[rating-minus\\](.*?)\\[/rating-minus\\]'si", "" );
		}
		
		$config['rating_type'] = $temp_rating;
		
		if ( $config['allow_comments'] AND $config['allow_subscribe'] AND $is_logged AND $row['allow_comm'] AND $user_group[$member_id['user_group']]['allow_subscribe'] ) {
			
			$tpl->set( '[comments-subscribe]', "<a href=\"#\" onclick=\"subscribe('{$row['id']}', 1); return false;\" >" );
			$tpl->set( '[comments-unsubscribe]', "<a href=\"#\" onclick=\"subscribe('{$row['id']}', 0); return false;\" >" );
			$tpl->set( '[/comments-subscribe]', '</a>' );
			$tpl->set( '[/comments-unsubscribe]', '</a>' );
			$tpl->set( '[allow-comments-subscribe]', "" );
			$tpl->set( '[/allow-comments-subscribe]', '</a>' );
			
		} else {
			
			$tpl->set_block( "'\\[comments-subscribe\\](.*?)\\[/comments-subscribe\\]'si", "" );
			$tpl->set_block( "'\\[comments-unsubscribe\\](.*?)\\[/comments-unsubscribe\\]'si", "" );
			$tpl->set_block( "'\\[allow-comments-subscribe\\](.*?)\\[/allow-comments-subscribe\\]'si", "" );
			
		}
		
		if( $config['allow_alt_url'] ) {
			
			$go_page = $config['http_home_url'] . "user/" . urlencode( $row['autor'] ) . "/";
			$tpl->set( '[day-news]', "<a href=\"".$config['http_home_url'] . date( 'Y/m/d/', $row['date'])."\" >" );
		
		} else {
			
			$go_page = "$PHP_SELF?subaction=userinfo&amp;user=" . urlencode( $row['autor'] );
			$tpl->set( '[day-news]', "<a href=\"$PHP_SELF?year=".date( 'Y', $row['date'])."&amp;month=".date( 'm', $row['date'])."&amp;day=".date( 'd', $row['date'])."\" >" );
		
		}
		
		$tpl->set( '[/day-news]', "</a>" );
		$tpl->set( '[profile]', "<a href=\"" . $go_page . "\">" );
		$tpl->set( '[/profile]', "</a>" );

		$tpl->set( '{login}', $row['autor'] );
		
		if( $config['schema_org'] ) {
			$schema->author = DLESEO::Thing("Person",  array('name' => $row['autor'], 'url' => $go_page), false );
		}
		
		$tpl->set( '{author}', "<a onclick=\"ShowProfile('" . urlencode( $row['autor'] ) . "', '" . $go_page . "', '" . $user_group[$member_id['user_group']]['admin_editusers'] . "'); return false;\" href=\"" . $go_page . "\">" . $row['autor'] . "</a>" );
		
		$_SESSION['referrer'] = htmlspecialchars($_SERVER['REQUEST_URI'], ENT_QUOTES, $config['charset'] );;
		
		$tpl->set( '[full-link]', "<a href=\"" . $full_link . "\">" );
		$tpl->set( '[/full-link]', "</a>" );
		
		$tpl->set( '{full-link}', $full_link );
		
		if( $row['allow_comm'] OR (!$row['allow_comm'] AND $row['comm_num']) ) {
			
			$tpl->set( '[com-link]', "<a id=\"dle-comm-link\" href=\"" . $full_link . "#comment\">" );
			$tpl->set( '[/com-link]', "</a>" );
		
		} else $tpl->set_block( "'\\[com-link\\](.*?)\\[/com-link\\]'si", "" );
		
		if( ! $row['approve'] and ($member_id['name'] == $row['autor'] and ! $user_group[$member_id['user_group']]['allow_all_edit']) ) {
			
			$tpl->set( '[edit]', "<a href=\"" . $config['http_home_url'] . "index.php?do=addnews&amp;id=" . $row['id'] . "\" >" );
			$tpl->set( '[/edit]', "</a>" );
			
			if( $config['allow_quick_wysiwyg'] ) $allow_comments_ajax = true;
			
		} elseif( $is_logged and (($member_id['name'] == $row['autor'] and $user_group[$member_id['user_group']]['allow_edit']) or $user_group[$member_id['user_group']]['allow_all_edit']) ) {
			
			$tpl->set( '[edit]', "<a onclick=\"return dropdownmenu(this, event, MenuNewsBuild('" . $row['id'] . "', 'full'), '170px')\" href=\"#\">" );
			$tpl->set( '[/edit]', "</a>" );
			
			if( $config['allow_quick_wysiwyg'] ) $allow_comments_ajax = true;
			
		} else $tpl->set_block( "'\\[edit\\](.*?)\\[/edit\\]'si", "" );
		
		if( $config['related_news'] AND $view_template != "print") {
			
			if ( $allow_full_cache ) $related_buffer = dle_cache( "related", $row['id'].$config['skin'], true ); else $related_buffer = false;
		
			if( $related_buffer === false ) {

				if ( $row['related_ids'] ) {
					
					$id_list = array();
					$id_temp = explode(",", $row['related_ids']);
					
					foreach ( $id_temp as $value ) {
						
						$value = intval($value);
						
						if ( $value > 0 ) $id_list[] = $value;
						
					}
					
					$row['related_ids'] = implode( ',', $id_list );
					
					unset($id_list);
					unset($id_temp);
			
					$db->query( "SELECT id, date, short_story, xfields, title, category, alt_name FROM " . PREFIX . "_post WHERE id IN({$row['related_ids']}) AND approve=1 ORDER BY FIND_IN_SET(id, '{$row['related_ids']}') LIMIT " . $config['related_number'] );
					$first_show = false;

				} else {
					
					$first_show = true;
					$related_ids = array();
			
					if( strlen( $row['full_story'] ) < strlen( $row['short_story'] ) ) $body = $row['short_story'];
					else $body = $row['full_story'];
					
					$body = strip_tags( stripslashes( $metatags['title'] . " " . $body ) );

					if( dle_strlen( $body, $config['charset'] ) > 1000 ) {
						$body = dle_substr( $body, 0, 1000, $config['charset'] );
					}
					
					$body = $db->safesql( $body );
					
					$config['related_number'] = intval( $config['related_number'] );
					if( $config['related_number'] < 1 ) $config['related_number'] = 5;
	
					$allowed_cats = array();
	
					foreach ($user_group as $value) {
						if ($value['allow_cats'] != "all" AND !$value['allow_short'] ) $allowed_cats[] = $db->safesql($value['allow_cats']);
					}
					
					$join_category = "";
					
					if (count($allowed_cats)) {

						$allowed_cats = implode(",", $allowed_cats);
						$allowed_cats = explode(",", $allowed_cats);
						$allowed_cats = array_unique($allowed_cats);
						sort($allowed_cats);
	
						if ($config['allow_multi_category']) {
							
							$join_category = "p INNER JOIN (SELECT DISTINCT(" . PREFIX . "_post_extras_cats.news_id) FROM " . PREFIX . "_post_extras_cats WHERE cat_id IN ('" . implode ( ',', $allowed_cats ) . "')) c ON (p.id=c.news_id) ";
							$allowed_cats = "";
						
						} else {
							
							$allowed_cats = "category IN ('" . implode ( "','", $allowed_cats ) . "') AND ";
						
						}

					} else $allowed_cats="";
					
					$not_allowed_cats = array();
	
					foreach ($user_group as $value) {
						if ($value['not_allow_cats'] != "" AND !$value['allow_short'] ) $not_allowed_cats[] = $db->safesql($value['not_allow_cats']);
					}
	
					if (count($not_allowed_cats)) {

						$not_allowed_cats = implode(",", $not_allowed_cats);
						$not_allowed_cats = explode(",", $not_allowed_cats);
						$not_allowed_cats = array_unique($not_allowed_cats);
						sort($not_allowed_cats);
	
						if ($config['allow_multi_category']) {
							
							$not_allowed_cats = "p.id NOT IN ( SELECT DISTINCT(" . PREFIX . "_post_extras_cats.news_id) FROM " . PREFIX . "_post_extras_cats WHERE cat_id IN (" . implode ( ',', $not_allowed_cats ) . ") ) AND ";
							$join_category = "p ";
							
						} else {
							
							$not_allowed_cats = "category NOT IN ('" . implode ( "','", $not_allowed_cats ) . "') AND ";
						
						}

					} else $not_allowed_cats="";

					if ($config['related_only_cats'] AND $row['category'] ) {

						$allowed_cats="";
						$not_allowed_cats = "";
						$allow_sub_cats = true;
						$all_cats = explode(",", $row['category']);
						$get_cats = array();

						foreach ($all_cats as $value) {

							if ( $cat_info[$value]['show_sub'] ) {
				
								if ( $cat_info[$value]['show_sub'] == 1 ) $get_cats[] = get_sub_cats ( $value );
								else { $get_cats[] = $value; }
				
							} else {
				
								if ( $config['show_sub_cats'] ) $get_cats[] = get_sub_cats ( $value );
								else { $get_cats[] = $value; }
				
							}

						}
						
						$get_cats = implode("|", $get_cats);
						$get_cats = explode("|", $get_cats);
						
						if ( count($get_cats) < 2 ) $allow_sub_cats = false;

						$get_cats = implode("|", $get_cats);
						
						if ($config['allow_multi_category']) {
							
							$get_cats = str_replace ( "|", "','", $get_cats );
							$join_category = "p INNER JOIN (SELECT DISTINCT(" . PREFIX . "_post_extras_cats.news_id) FROM " . PREFIX . "_post_extras_cats WHERE cat_id IN ('" . $get_cats . "')) c ON (p.id=c.news_id) ";
							$where_category = "";
				
						} else {
							
							if ( $allow_sub_cats ) {
								
								$get_cats = str_replace ( "|", "','", $get_cats );
								$where_category = "category IN ('" . $get_cats . "') AND ";
							
							} else {
								
								$where_category = "category = '{$get_cats}' AND ";
							
							}
						
						}

					} else $where_category = "";

					$db->query( "SELECT id, date, short_story, xfields, title, category, alt_name, MATCH (title, short_story, full_story, xfields) AGAINST ('{$body}') as score FROM " . PREFIX . "_post {$join_category}WHERE {$where_category}{$allowed_cats}{$not_allowed_cats}MATCH (title, short_story, full_story, xfields) AGAINST ('{$body}') AND id != " . $row['id'] . " AND approve=1" . $where_date . " ORDER BY score DESC LIMIT " . $config['related_number'] );
					
				}

				$tpl2 = new dle_template();
				$tpl2->dir = TEMPLATE_DIR;
				$tpl2->load_template( 'relatednews.tpl' );
								
				while ( $related = $db->get_row() ) {
					
					if ( $first_show ) $related_ids[] =	$related['id'];

					$related['date'] = strtotime( $related['date'] );

					if( ! $related['category'] ) {
						$my_cat = "---";
						$my_cat_link = "---";
					} else {
						
						$my_cat = array ();
						$my_cat_link = array ();
						$rel_cat_list = explode( ',', $related['category'] );
					 
						if( count( $rel_cat_list ) == 1 ) {
							
							if( $cat_info[$rel_cat_list[0]]['id'] ) {
								$my_cat[] = $cat_info[$rel_cat_list[0]]['name'];
								$my_cat_link = get_categories( $rel_cat_list[0], $config['category_separator'] );
							} else {
								$my_cat_link = "---";
							}
				
						} else {
							
							foreach ( $rel_cat_list as $element ) {
								if( $element AND $cat_info[$element]['id'] ) {
									$my_cat[] = $cat_info[$element]['name'];
									if( $config['allow_alt_url'] ) $my_cat_link[] = "<a href=\"" . $config['http_home_url'] . get_url( $element ) . "/\">{$cat_info[$element]['name']}</a>";
									else $my_cat_link[] = "<a href=\"$PHP_SELF?do=cat&category={$cat_info[$element]['alt_name']}\">{$cat_info[$element]['name']}</a>";
								}
							}
							
							if( count( $my_cat_link ) ) {
								$my_cat_link = implode( $config['category_separator'], $my_cat_link );
							} else $my_cat_link = "---";
				
						}
						
						if( count( $my_cat ) ) {
							$my_cat = implode( $config['category_separator'], $my_cat );
						} else $my_cat = "---";
						
					}
					
					if( $config['allow_alt_url'] ) {
						
						if( $config['seo_type'] == 1 OR $config['seo_type'] == 2 ) {
							
							if( $related['category'] and $config['seo_type'] == 2 ) {
								
								$cats_url = get_url( $related['category'] );
								
								if( $cats_url ) $cats_url .= "/";
			
								$rel_full_link = $config['http_home_url'] . $cats_url . $related['id'] . "-" . $related['alt_name'] . ".html";
							
							} else {
								
								$rel_full_link = $config['http_home_url'] . $related['id'] . "-" . $related['alt_name'] . ".html";
							
							}
						
						} else {
							
							$rel_full_link = $config['http_home_url'] . date( 'Y/m/d/', $related['date'] ) . $related['alt_name'] . ".html";
						}
					
					} else {
						
						$rel_full_link = $config['http_home_url'] . "index.php?newsid=" . $related['id'];
					
					}
					
					$related['category'] = intval( $related['category'] );
					
					$related['title'] = strip_tags( stripslashes( $related['title'] ) );

					$tpl2->set( '{title}', str_replace("&amp;amp;", "&amp;", htmlspecialchars( $related['title'], ENT_QUOTES, $config['charset'] ) ) );
					$tpl2->set( '{link}', $rel_full_link );
					$tpl2->set( '{category}', $my_cat );
					$tpl2->set( '{link-category}', $my_cat_link );
				
					$compare_date = compare_days_date($related['date']);

					if( !$compare_date ) {
						
						$tpl2->set( '{date}', $lang['time_heute'] . langdate( ", H:i", $related['date'] ) );
					
					} elseif( $compare_date == 1 ) {
						
						$tpl2->set( '{date}', $lang['time_gestern'] . langdate( ", H:i", $related['date'] ) );
					
					} else {
						
						$tpl2->set( '{date}', langdate( $config['timestamp_active'], $related['date'] ) );
					
					}
					$news_date = $related['date'];
					$tpl2->copy_template = preg_replace_callback ( "#\{date=(.+?)\}#i", "formdate", $tpl2->copy_template );

					$related['short_story'] = stripslashes( $related['short_story'] );
					
					if (stripos ( $related['short_story'], "[hide" ) !== false ) {
						
						$related['short_story'] = preg_replace_callback ( "#\[hide(.*?)\](.+?)\[/hide\]#is", 
							function ($matches) use ($member_id, $user_group, $lang) {
								
								$matches[1] = str_replace(array("=", " "), "", $matches[1]);
								$matches[2] = $matches[2];
				
								if( $matches[1] ) {
									
									$groups = explode( ',', $matches[1] );
				
									if( in_array( $member_id['user_group'], $groups ) OR $member_id['user_group'] == "1") {
										return $matches[2];
									} else return "<div class=\"quote dlehidden\">" . $lang['news_regus'] . "</div>";
									
								} else {
									
									if( $user_group[$member_id['user_group']]['allow_hide'] ) return $matches[2]; else return "<div class=\"quote dlehidden\">" . $lang['news_regus'] . "</div>";
									
								}
				
						}, $related['short_story'] );
					}
	
					if (stripos ( $tpl2->copy_template, "image-" ) !== false) {
			
						$images = array();
						preg_match_all('/(img|src)=("|\')[^"\'>]+/i', $related['short_story'], $media);
						$data=preg_replace('/(img|src)("|\'|="|=\')(.*)/i',"$3",$media[0]);
			
						foreach($data as $url) {
							$info = pathinfo($url);
							if (isset($info['extension'])) {
								if ($info['filename'] == "spoiler-plus" OR $info['filename'] == "spoiler-minus" OR strpos($info['dirname'], 'engine/data/emoticons') !== false) continue;
								$info['extension'] = strtolower($info['extension']);
								if (($info['extension'] == 'jpg') || ($info['extension'] == 'jpeg') || ($info['extension'] == 'gif') || ($info['extension'] == 'png') || ($info['extension'] == 'bmp') || ($info['extension'] == 'webp') || ($info['extension'] == 'avif')) array_push($images, $url);
							}
						}
			
						if ( count($images) ) {
							$i=0;
							foreach($images as $url) {
								$i++;
								$tpl2->copy_template = str_replace( '{image-'.$i.'}', $url, $tpl2->copy_template );
								$tpl2->copy_template = str_replace( '[image-'.$i.']', "", $tpl2->copy_template );
								$tpl2->copy_template = str_replace( '[/image-'.$i.']', "", $tpl2->copy_template );
								$tpl2->copy_template = preg_replace( "#\[not-image-{$i}\](.+?)\[/not-image-{$i}\]#is", "", $tpl2->copy_template );
							}
			
						}

						$tpl2->copy_template = preg_replace( "#\[image-(.+?)\](.+?)\[/image-(.+?)\]#is", "", $tpl2->copy_template );			
						$tpl2->copy_template = preg_replace( "#\\{image-(.+?)\\}#i", "{THEME}/dleimages/no_image.jpg", $tpl2->copy_template );
						$tpl2->copy_template = preg_replace( "#\[not-image-(.+?)\]#i", "", $tpl2->copy_template );
						$tpl2->copy_template = preg_replace( "#\[/not-image-(.+?)\]#i", "", $tpl2->copy_template );
			
					}

					if ( preg_match( "#\\{text limit=['\"](.+?)['\"]\\}#i", $tpl2->copy_template, $matches ) ) {
						$tpl2->set( $matches[0], clear_content($related['short_story'], $matches[1]) );
					} else $tpl2->set( '{text}', $related['short_story'] );

					if ( preg_match( "#\\{title limit=['\"](.+?)['\"]\\}#i", $tpl2->copy_template, $matches ) ) {
						$tpl2->set( $matches[0], clear_content($related['title'], $matches[1]) );
					}

					if( count($xfields) ) {
						$xfieldsdata = xfieldsdataload( $related['xfields'] );
						
						foreach ( $xfields as $value ) {
							$preg_safe_name = preg_quote( $value[0], "'" );

							if( !isset($xfieldsdata[$value[0]]) ) $xfieldsdata[$value[0]] = '';
							
							$xfieldsdata[$value[0]] = stripslashes( $xfieldsdata[$value[0]] );
							
							if( $value[20] ) {
							  
							  $value[20] = explode( ',', $value[20] );
							  
							  if( $value[20][0] AND !in_array( $member_id['user_group'], $value[20] ) ) {
								$xfieldsdata[$value[0]] = "";
							  }
							  
							}
				
							if ( $value[3] == "yesorno" ) {
								
								if( intval($xfieldsdata[$value[0]]) ) {
									$xfgiven = true;
									$xfieldsdata[$value[0]] = $lang['xfield_xyes'];
								} else {
									$xfgiven = false;
									$xfieldsdata[$value[0]] = $lang['xfield_xno'];
								}
								
							} else {
								if($xfieldsdata[$value[0]] == "") $xfgiven = false; else $xfgiven = true;
							}
							
							if( !$xfgiven ) {
								$tpl2->copy_template = preg_replace( "'\\[xfgiven_{$preg_safe_name}\\](.*?)\\[/xfgiven_{$preg_safe_name}\\]'is", "", $tpl2->copy_template );
								$tpl2->copy_template = str_ireplace( "[xfnotgiven_{$value[0]}]", "", $tpl2->copy_template );
								$tpl2->copy_template = str_ireplace( "[/xfnotgiven_{$value[0]}]", "", $tpl2->copy_template );
							} else {
								$tpl2->copy_template = preg_replace( "'\\[xfnotgiven_{$preg_safe_name}\\](.*?)\\[/xfnotgiven_{$preg_safe_name}\\]'is", "", $tpl2->copy_template );
								$tpl2->copy_template = str_ireplace( "[xfgiven_{$value[0]}]", "", $tpl2->copy_template );
								$tpl2->copy_template = str_ireplace( "[/xfgiven_{$value[0]}]", "", $tpl2->copy_template );
							}
							
							if(strpos( $tpl2->copy_template, "[ifxfvalue {$value[0]}" ) !== false ) {
								$tpl2->copy_template = preg_replace_callback ( "#\\[ifxfvalue(.+?)\\](.+?)\\[/ifxfvalue\\]#is", "check_xfvalue", $tpl2->copy_template );
							}

							if ($value[3] == "select" and isset($xfieldsdata[$value[0]]) and $xfieldsdata[$value[0]] and !$value[6]) {
								$xfieldsdata[$value[0]] = explode(',', $xfieldsdata[$value[0]]);
								$xfieldsdata[$value[0]] = implode($value[35], $xfieldsdata[$value[0]]);
							}

							if ( $value[6] AND !empty( $xfieldsdata[$value[0]] ) ) {
								$temp_array = explode( ",", $xfieldsdata[$value[0]] );
								$value3 = array();
				
								foreach ($temp_array as $value2) {
				
									$value2 = trim($value2);
									
									if($value2) {
										
										$value4 = str_replace(array("&#039;", "&quot;", "&amp;", "&#123;", "&#91;", "&#58;", "/"), array("'", '"', "&", "{", "[", ":", "&frasl;"), $value2);

										if( $value[3] == "datetime" ) {
										
											$value2 = strtotime( $value4 );
										
											if( !trim($value[24]) ) $value[24] = $config['timestamp_active'];

											if (strpos($tpl2->copy_template, "[xfvalue_{$value[0]} format=") !== false) {

												$tpl2->copy_template = preg_replace_callback("#\\[xfvalue_{$preg_safe_name} format=['\"](.*?)['\"]\\]#i",
													function ($matches) use ($value, $value2, $value4, $customlangdate, $config, $PHP_SELF) {

														$matches[1] = trim($matches[1]);

														if ($value[25]) {

															if ($value[26]) $value2 = langdate($matches[1], $value2);
															else return $value2 = langdate($matches[1], $value2, false, $customlangdate);
														} else $value2 = date($matches[1], $value2);

														if ($config['allow_alt_url']) return "<a href=\"" . $config['http_home_url'] . "xfsearch/" . $value[0] . "/" . rawurlencode(dle_strtolower($value4)) . "/\">" . $value2 . "</a>";
														else return "<a href=\"$PHP_SELF?do=xfsearch&amp;xfname=" . $value[0] . "&amp;xf=" . rawurlencode(dle_strtolower($value4)) . "\">" . $value2 . "</a>";

													}, $tpl2->copy_template);
											}

											if( $value[25] ) {
												
												if($value[26]) $value2 = langdate($value[24], $value2);
												else $value2 = langdate($value[24], $value2, false, $customlangdate);
												
											} else $value2 = date( $value[24], $value2 );
			
										}

										if( $config['allow_alt_url'] ) $value3[] = "<a href=\"" . $config['http_home_url'] . "xfsearch/" .$value[0]."/". rawurlencode( dle_strtolower($value4) ) . "/\">" . $value2 . "</a>";
										else $value3[] = "<a href=\"$PHP_SELF?do=xfsearch&amp;xfname=".$value[0]."&amp;xf=" . rawurlencode( dle_strtolower($value4) ) . "\">" . $value2 . "</a>";
									}

								}

								if ($value[3] == "select" and $value[35]) {
									$value[21] = $value[35];
								}

								if( empty($value[21]) ) $value[21] = ", ";
								
								$xfieldsdata[$value[0]] = implode($value[21], $value3);
				
								unset($temp_array);
								unset($value2);
								unset($value3);
								unset($value4);
				
							} elseif ( $value[3] == "datetime" AND !empty($xfieldsdata[$value[0]]) ) {
			
								$xfieldsdata[$value[0]] = strtotime( str_replace("&#58;", ":", $xfieldsdata[$value[0]]) );
			
								if( !trim($value[24]) ) $value[24] = $config['timestamp_active'];

								if (strpos ( $tpl2->copy_template, "[xfvalue_{$value[0]} format=" ) !== false) {
									
									$tpl2->copy_template = preg_replace_callback ( "#\\[xfvalue_{$preg_safe_name} format=['\"](.*?)['\"]\\]#i", 
										function ($matches) use ($value, $xfieldsdata, $customlangdate) {
											
											$matches[1] = trim($matches[1]);
											
											if ($value[25]) {

												if ($value[26]) return langdate($matches[1], $xfieldsdata[$value[0]]);
												else return langdate($matches[1], $xfieldsdata[$value[0]], false, $customlangdate);

											} else return date($matches[1], $xfieldsdata[$value[0]]);

											
										}, $tpl2->copy_template );
										
								}

								if( $value[25] ) {
									
									if($value[26]) $xfieldsdata[$value[0]] = langdate($value[24], $xfieldsdata[$value[0]]);
									else $xfieldsdata[$value[0]] = langdate($value[24], $xfieldsdata[$value[0]], false, $customlangdate);
												
								} else $xfieldsdata[$value[0]] = date( $value[24], $xfieldsdata[$value[0]] );
								
								
							}
							
							if($value[3] == "image" AND isset($xfieldsdata[$value[0]]) AND $xfieldsdata[$value[0]] ) {
								
								$temp_array = explode('|', $xfieldsdata[$value[0]]);
									
								if (count($temp_array) == 1 OR count($temp_array) == 5 ){
										
									$temp_alt = '';
									$temp_value = implode('|', $temp_array );
										
								} else {
										
									$temp_alt = $temp_array[0];
									$temp_alt = str_replace( "&amp;#44;", "&#44;", $temp_alt );
									$temp_alt = str_replace( "&amp;#124;", "&#124;", $temp_alt );
									
									unset($temp_array[0]);
									$temp_value =  implode('|', $temp_array );
										
								}
			
								$path_parts = get_uploaded_image_info($temp_value);
								
								if( $value[12] AND $path_parts->thumb ) {
									
									$tpl2->set( "[xfvalue_thumb_url_{$value[0]}]", $path_parts->thumb);
									$xfieldsdata[$value[0]] = "<a href=\"{$path_parts->url}\" data-highslide=\"single\" target=\"_blank\"><img class=\"xfieldimage {$value[0]}\" src=\"{$path_parts->thumb}\" alt=\"{$temp_alt}\"></a>";
			
								} else {
									
									$tpl2->set( "[xfvalue_thumb_url_{$value[0]}]", $path_parts->url);
									$xfieldsdata[$value[0]] = "<img class=\"xfieldimage {$value[0]}\" src=\"{$path_parts->url}\" alt=\"{$temp_alt}\">";
			
								}
								
								$tpl2->set( "[xfvalue_image_url_{$value[0]}]", $path_parts->url);
								$tpl2->set( "[xfvalue_image_description_{$value[0]}]", $temp_alt);
			
							}
							
							if($value[3] == "image" AND !$xfieldsdata[$value[0]]) {
			
								$tpl2->set( "[xfvalue_thumb_url_{$value[0]}]", "");
								$tpl2->set( "[xfvalue_image_url_{$value[0]}]", "");
								$tpl2->set( "[xfvalue_image_description_{$value[0]}]", "");
								
							}

							if (($value[3] == "video" or $value[3] == "audio") and $xfieldsdata[$value[0]]) {

								$fieldvalue_arr = explode(',', $xfieldsdata[$value[0]]);
								$playlist = array();
								$playlist_single = array();
								$xf_playlist_count = 0;

								if ($value[3] == "audio") {
									$xftag = "audio";
									$xftype = "audio/mp3";
								} else {
									$xftag = "video";
									$xftype = "video/mp4";
								}

								if (!isset($video_config)) {
									include_once(ENGINE_DIR . '/data/videoconfig.php');
								}

								if ($video_config['preload']) $preload = "metadata";
								else $preload = "none";

								$playlist_width = $video_config['width'];

								if (substr($playlist_width, -1, 1) != '%') $playlist_width = $playlist_width . "px";

								$playlist_width = "style=\"width:100%;max-width:{$playlist_width};\"";

								foreach ($fieldvalue_arr as $temp_value) {

									$xf_playlist_count++;

									$temp_value = trim($temp_value);

									if (!$temp_value) continue;

									$temp_array = explode('|', $temp_value);

									if (count($temp_array) < 4) {

										$temp_alt = '';
										$temp_url = $temp_array[0];
									} else {

										$temp_alt = $temp_array[0];
										$temp_url = $temp_array[1];
									}

									$filename = pathinfo($temp_url, PATHINFO_FILENAME);
									$filename = explode("_", $filename);
									if (count($filename) > 1 and intval($filename[0])) unset($filename[0]);
									$filename = implode("_", $filename);

									if (!$temp_alt) $temp_alt = $filename;

									$playlist[] = "<{$xftag} title=\"{$temp_alt}\" preload=\"{$preload}\" controls><source type=\"{$xftype}\" src=\"{$temp_url}\"></{$xftag}>";
									$playlist_single['[xfvalue_' . $value[0] . ' ' . $xftag . '="' . $xf_playlist_count . '"]'] = "<div class=\"dleplyrplayer\" {$playlist_width} theme=\"{$video_config['theme']}\"><{$xftag} title=\"{$temp_alt}\" preload=\"{$preload}\" controls><source type=\"{$xftype}\" src=\"{$temp_url}\"></{$xftag}></div>";

									$playlist_single['[xfvalue_' . $value[0] . ' ' . $xftag . '-description="' . $xf_playlist_count . '"]'] = $temp_alt;
									$playlist_single['[xfvalue_' . $value[0] . ' ' . $xftag . '-url="' . $xf_playlist_count . '"]'] = $temp_url;

									$tpl2->copy_template = str_ireplace('[xfgiven_' . $value[0] . ' ' . $xftag . '="' . $xf_playlist_count . '"]', "", $tpl2->copy_template);
									$tpl2->copy_template = str_ireplace('[/xfgiven_' . $value[0] . ' ' . $xftag . '="' . $xf_playlist_count . '"]', "", $tpl2->copy_template);
									$tpl2->copy_template = preg_replace("'\\[xfnotgiven_{$preg_safe_name} {$xftag}=\"{$xf_playlist_count}\"\\](.*?)\\[/xfnotgiven_{$preg_safe_name} {$xftag}=\"{$xf_playlist_count}\"\\]'is", "", $tpl2->copy_template);
								}

								if (count($playlist_single)) {

									foreach ($playlist_single as $temp_key => $temp_value) {
										$tpl2->set($temp_key, $temp_value);
									}
								}

								$xfieldsdata[$value[0]] = "<div class=\"dleplyrplayer\" {$playlist_width} theme=\"{$video_config['theme']}\">" . implode($playlist) . "</div>";
							}

							if($value[3] == "imagegalery" AND $xfieldsdata[$value[0]] ) {
								
								$fieldvalue_arr = explode(',', $xfieldsdata[$value[0]]);
								$gallery_image = array();
								$gallery_single_image = array();
								$xf_image_count = 0;
								
								foreach ($fieldvalue_arr as $temp_value) {
									
									$xf_image_count ++;
									
									$temp_value = trim($temp_value);
							
									if($temp_value == "") continue;
									
									$temp_array = explode('|', $temp_value);
									
									if (count($temp_array) == 1 OR count($temp_array) == 5 ){
											
										$temp_alt = '';
										$temp_value = implode('|', $temp_array );
											
									} else {
											
										$temp_alt = $temp_array[0];
										$temp_alt = str_replace( "&amp;#44;", "&#44;", $temp_alt );
										$temp_alt = str_replace( "&amp;#124;", "&#124;", $temp_alt );
										
										unset($temp_array[0]);
										$temp_value =  implode('|', $temp_array );
											
									}
				
									$path_parts = get_uploaded_image_info($temp_value);
								
									if($value[12] AND $path_parts->thumb) {
										
										$gallery_image[] = "<li><a href=\"{$path_parts->url}\" data-highslide=\"xf_{$row['id']}_{$value[0]} \" target=\"_blank\"><img src=\"{$path_parts->thumb}\" alt=\"{$temp_alt}\"></a></li>";
										$gallery_single_image['[xfvalue_'.$value[0].' image="'.$xf_image_count.'"]'] = "<a href=\"{$path_parts->url}\" data-highslide=\"single\" target=\"_blank\"><img class=\"xfieldimage {$value[0]}\" src=\"{$path_parts->thumb}\" alt=\"{$temp_alt}\"></a>";
										
									} else {
										
										$gallery_image[] = "<li><img src=\"{$path_parts->url}\" alt=\"{$temp_alt}\"></li>";
										$gallery_single_image['[xfvalue_'.$value[0].' image="'.$xf_image_count.'"]'] = "<img class=\"xfieldimage {$value[0]}\" src=\"{$path_parts->url}\" alt=\"{$temp_alt}\">";
										
									}
									
										$gallery_single_image['[xfvalue_'.$value[0].' image-description="'.$xf_image_count.'"]'] = $temp_alt;
										$gallery_single_image['[xfvalue_'.$value[0].' image-thumb-url="'.$xf_image_count.'"]'] = $path_parts->thumb;
										$gallery_single_image['[xfvalue_'.$value[0].' image-url="'.$xf_image_count.'"]'] = $path_parts->url;
										
										$tpl2->copy_template = str_ireplace( '[xfgiven_'.$value[0].' image="'.$xf_image_count.'"]', "", $tpl2->copy_template );
										$tpl2->copy_template = str_ireplace( '[/xfgiven_'.$value[0].' image="'.$xf_image_count.'"]', "", $tpl2->copy_template );
										$tpl2->copy_template = preg_replace( "'\\[xfnotgiven_{$preg_safe_name} image=\"{$xf_image_count}\"\\](.*?)\\[/xfnotgiven_{$preg_safe_name} image=\"{$xf_image_count}\"\\]'is", "", $tpl2->copy_template );

								}
								
								if(count($gallery_single_image) ) {
									foreach($gallery_single_image as $temp_key => $temp_value) $tpl2->set( $temp_key, $temp_value);
								}
								
								$xfieldsdata[$value[0]] = "<ul class=\"xfieldimagegallery {$value[0]}\">".implode($gallery_image)."</ul>";
								
							}
							
							$tpl2->copy_template = preg_replace( "'\\[xfgiven_{$preg_safe_name} (image|video|audio)=\"(\d+)\"\\](.*?)\\[/xfgiven_{$preg_safe_name} (image|video|audio)=\"(\d+)\"\\]'is", "", $tpl2->copy_template );
							$tpl2->copy_template = preg_replace( "'\\[xfnotgiven_{$preg_safe_name} (image|video|audio)=\"(\d+)\"\\]'i", "", $tpl2->copy_template );
							$tpl2->copy_template = preg_replace( "'\\[/xfnotgiven_{$preg_safe_name} (image|video|audio)=\"(\d+)\"\\]'i", "", $tpl2->copy_template );
				
							if ($value[30] AND $view_template != "print" ) $xfieldsdata[$value[0]] = preg_replace_callback ( "#<(img|iframe)(.+?)>#i", "enable_lazyload", $xfieldsdata[$value[0]] );

							$tpl2->set( "[xfvalue_{$value[0]}]", $xfieldsdata[$value[0]] );
			
							if ( preg_match( "#\\[xfvalue_{$preg_safe_name} limit=['\"](.+?)['\"]\\]#i", $tpl2->copy_template, $matches ) ) {
								$tpl2->set( $matches[0], clear_content($xfieldsdata[$value[0]], $matches[1]) );
							}

						}
					}

					$tpl2->compile( 'content' );
				
				}

				$related_buffer = $tpl2->result['content'];
				unset($tpl2);
				$db->free();

				if ( $first_show ) {
					if ( count($related_ids) ) {
						$related_ids = $row['related_ids'] = implode(",",$related_ids);
						$db->query( "UPDATE " . PREFIX . "_post_extras SET related_ids='{$related_ids}' WHERE news_id='{$row['id']}'" );
					}
				}

				if ( $allow_full_cache ) create_cache( "related", $related_buffer, $row['id'].$config['skin'], true );
			}
			
			if ( $related_buffer ) {

				$tpl->set( '[related-news]', "" );
				$tpl->set( '[/related-news]', "" );

			} else $tpl->set_block( "'\\[related-news\\](.*?)\\[/related-news\\]'si", "" );

			$tpl->set( '{related-news}', $related_buffer );
		
		}

		$tpl->set('{related-ids}', $row['related_ids']);
		
		if( $is_logged ) {
			
			$fav_arr = explode( ',', $member_id['favorites'] );
			
			if( !in_array( $row['id'], $fav_arr ) ) {

				$tpl->set( '{favorites}', "<a data-fav-id=\"{$row['id']}\" href=\"$PHP_SELF?do=favorites&amp;doaction=add&amp;id=" . $row['id'] . "\"><img src=\"" . $config['http_home_url'] . "templates/{$config['skin']}/dleimages/plus_fav.gif\" onclick=\"doFavorites('" . $row['id'] . "', 'plus', 0); return false;\" title=\"" . $lang['news_addfav'] . "\" style=\"vertical-align: middle;border: none;\" alt=\"\"></a>" );
				$tpl->set( '[add-favorites]', "<span data-favorites-add=\"{$row['id']}\" style=\"display:none\"></span><a onclick=\"doFavorites('" . $row['id'] . "', 'plus', 1, 'full'); return false;\" href=\"$PHP_SELF?do=favorites&amp;doaction=add&amp;id=" . $row['id'] . "\">" );
				$tpl->set( '[/add-favorites]', "</a>" );
				$tpl->set_block( "'\\[del-favorites\\](.*?)\\[/del-favorites\\]'si", "<span data-favorites-del=\"{$row['id']}\" style=\"display:none\"></span>" );
			
			} else { 

				$tpl->set( '{favorites}', "<a data-fav-id=\"{$row['id']}\" href=\"$PHP_SELF?do=favorites&amp;doaction=del&amp;id=" . $row['id'] . "\"><img src=\"" . $config['http_home_url'] . "templates/{$config['skin']}/dleimages/minus_fav.gif\" onclick=\"doFavorites('" . $row['id'] . "', 'minus', 0); return false;\" title=\"" . $lang['news_minfav'] . "\" style=\"vertical-align: middle;border: none;\" alt=\"\"></a>" );
				$tpl->set( '[del-favorites]', "<span data-favorites-del=\"{$row['id']}\" style=\"display:none\"></span><a id=\"fav-id-" . $row['id'] . "\" onclick=\"doFavorites('" . $row['id'] . "', 'minus', 1, 'full'); return false;\" href=\"$PHP_SELF?do=favorites&amp;doaction=del&amp;id=" . $row['id'] . "\">" );
				$tpl->set( '[/del-favorites]', "</a>" );
				$tpl->set_block( "'\\[add-favorites\\](.*?)\\[/add-favorites\\]'si", "<span data-favorites-add=\"{$row['id']}\" style=\"display:none\"></span>" );
			}
		
		} else {
			$tpl->set( '{favorites}', "" );
			$tpl->set_block( "'\\[add-favorites\\](.*?)\\[/add-favorites\\]'si", "" );
			$tpl->set_block( "'\\[del-favorites\\](.*?)\\[/del-favorites\\]'si", "" );
		}
		
		$tpl->set( '[complaint]', "<a href=\"javascript:AddComplaint('" . $row['id'] . "', 'news')\">" );
		$tpl->set( '[/complaint]', "</a>" );
			
		if( $row['votes'] ) $tpl->set( '{poll}', $tpl->result['poll'] );
		else $tpl->set( '{poll}', '' );
		
		if( $config['allow_banner'] ) {
			include (DLEPlugins::Check(ENGINE_DIR . '/modules/banners.php'));
		}
		
		if( $config['allow_banner'] AND count( $banners ) ) {
			
			foreach ( $banners as $name => $value ) {
				$tpl->copy_template = str_replace( "{banner_" . $name . "}", $value, $tpl->copy_template );

				if ( $value ) {
					$tpl->copy_template = str_replace ( "[banner_" . $name . "]", "", $tpl->copy_template );
					$tpl->copy_template = str_replace ( "[/banner_" . $name . "]", "", $tpl->copy_template );
				}
			}
		}
		
		$tpl->set_block( "'{banner_(.*?)}'si", "" );
		$tpl->set_block ( "'\\[banner_(.*?)\\](.*?)\\[/banner_(.*?)\\]'si", "" );

		$row['short_story'] = stripslashes($row['short_story']);
		$row['full_story'] = stripslashes($row['full_story']);
		$row['xfields'] = stripslashes( $row['xfields'] );

		if ($config['allow_links'] AND function_exists('replace_links') AND isset($replace_links['news']) ) {
			$row['short_story'] = replace_links ( $row['short_story'], $replace_links['news'] );
			$row['full_story'] = replace_links ( $row['full_story'], $replace_links['news'] );
		}

		if (stripos ( $tpl->copy_template, "{image-" ) !== false) {

			$images = array();
			preg_match_all('/(img|src)=("|\')[^"\'>]+/i', $row['short_story'].$row['xfields'], $media);
			$data=preg_replace('/(img|src)("|\'|="|=\')(.*)/i',"$3",$media[0]);
	
			foreach($data as $url) {
				$info = pathinfo($url);
				if (isset($info['extension'])) {
					if ($info['filename'] == "spoiler-plus" OR $info['filename'] == "spoiler-minus" OR strpos($info['dirname'], 'engine/data/emoticons') !== false) continue;
					$info['extension'] = strtolower($info['extension']);
					if (($info['extension'] == 'jpg') || ($info['extension'] == 'jpeg') || ($info['extension'] == 'gif') || ($info['extension'] == 'png') || ($info['extension'] == 'bmp') || ($info['extension'] == 'webp') || ($info['extension'] == 'avif')) array_push($images, $url);
				}
			}
	
			if ( count($images) ) {
				$i=0;
				foreach($images as $url) {
					$i++;
					$tpl->copy_template = str_replace( '{image-'.$i.'}', $url, $tpl->copy_template );
					$tpl->copy_template = str_replace( '[image-'.$i.']', "", $tpl->copy_template );
					$tpl->copy_template = str_replace( '[/image-'.$i.']', "", $tpl->copy_template );
					$tpl->copy_template = preg_replace( "#\[not-image-{$i}\](.+?)\[/not-image-{$i}\]#is", "", $tpl->copy_template );
				}
	
			}
	
			$tpl->copy_template = preg_replace( "#\[image-(.+?)\](.+?)\[/image-(.+?)\]#is", "", $tpl->copy_template );
			$tpl->copy_template = preg_replace( "#\\{image-(.+?)\\}#i", "{THEME}/dleimages/no_image.jpg", $tpl->copy_template );
			$tpl->copy_template = preg_replace( "#\[not-image-(.+?)\]#i", "", $tpl->copy_template );
			$tpl->copy_template = preg_replace( "#\[/not-image-(.+?)\]#i", "", $tpl->copy_template );
	
		}

		if (stripos ( $tpl->copy_template, "{fullimage-" ) !== false) {

			$images = array();
			preg_match_all('/(img|src)=("|\')[^"\'>]+/i', $row['full_story'], $media);
			$data=preg_replace('/(img|src)("|\'|="|=\')(.*)/i',"$3",$media[0]);
	
			foreach($data as $url) {
				$info = pathinfo($url);
				if (isset($info['extension'])) {
					if ($info['filename'] == "spoiler-plus" OR $info['filename'] == "spoiler-minus" OR strpos($info['dirname'], 'engine/data/emoticons') !== false) continue;
					$info['extension'] = strtolower($info['extension']);
					if (($info['extension'] == 'jpg') || ($info['extension'] == 'jpeg') || ($info['extension'] == 'gif') || ($info['extension'] == 'png') || ($info['extension'] == 'bmp') || ($info['extension'] == 'webp') || ($info['extension'] == 'avif')) array_push($images, $url);
				}
			}
	
			if ( count($images) ) {
				$i=0;
				foreach($images as $url) {
					$i++;
					$tpl->copy_template = str_replace( '{fullimage-'.$i.'}', $url, $tpl->copy_template );
					$tpl->copy_template = str_replace( '[fullimage-'.$i.']', "", $tpl->copy_template );
					$tpl->copy_template = str_replace( '[/fullimage-'.$i.']', "", $tpl->copy_template );
				}
	
			}
	
			$tpl->copy_template = preg_replace( "#\[fullimage-(.+?)\](.+?)\[/fullimage-(.+?)\]#is", "", $tpl->copy_template );
			$tpl->copy_template = preg_replace( "#\\{fullimage-(.+?)\\}#i", "{THEME}/dleimages/no_image.jpg", $tpl->copy_template );
	
		}

		$images = array();
		$schema_images = array();
		$allcontent = $row['full_story'].$row['short_story'].$row['xfields'];
		preg_match_all('/(img|src)=("|\')[^"\'>]+/i', $allcontent, $media);
		$data=preg_replace('/(img|src)("|\'|="|=\')(.*)/i',"$3",$media[0]);
	
		foreach($data as $url) {
			$info = pathinfo($url);
			if (isset($info['extension'])) {
				if ($info['filename'] == "spoiler-plus" OR $info['filename'] == "spoiler-minus" OR strpos($info['dirname'], 'engine/data/emoticons') !== false) continue;
				$info['extension'] = strtolower($info['extension']);
				if (($info['extension'] == 'jpg' || $info['extension'] == 'jpeg' || $info['extension'] == 'gif' || $info['extension'] == 'png' || $info['extension'] == 'bmp' || $info['extension'] == 'webp' || $info['extension'] == 'avif') AND !in_array($url, $images) ) {
					
					array_push($images, $url);
					
					$url = str_replace("/thumbs/","/",$url);
					$url = str_replace("/medium/","/",$url);
					$schema_images[] = $url;
					
				}
			}
		}

		if ( count($images) ) {
			$social_tags['image'] = str_replace("/thumbs/","/",$images[0]);
			$social_tags['image'] = str_replace("/medium/","/",$social_tags['image']);
		}

		$media=array();
		
		if ( preg_match("#<!--dle_video_begin:(.+?)-->#is", $allcontent, $media) ){
			$media[1] = str_replace( "&#124;", "|", $media[1] );
			
			$media[1] = explode( ",", trim( $media[1] ) );
			
			if( count($media[1]) > 1 AND stripos ( $media[1][0], "http" ) === false AND intval($media[1][0]) ) {
				$media[1] = explode( "|", $media[1][1] );
			} else $media[1] = explode( "|", $media[1][0] );
			
			$social_tags['video'] = $media[1][0];

		}

		if ( preg_match("#<!--dle_audio_begin:(.+?)-->#is", $allcontent, $media) ){
			$media[1] = str_replace( "&#124;", "|", $media[1] );
			
			$media[1] = explode( ",", trim( $media[1] ) );
			
			if( count($media[1]) > 1 AND stripos ( $media[1][0], "http" ) === false AND intval($media[1][0]) ) {
				$media[1] = explode( "|", $media[1][1] );
			} else $media[1] = explode( "|", $media[1][0] );
			
			$social_tags['audio'] = $media[1][0];

		}

		if ($smartphone_detected) {

			if (!$config['allow_smart_format']) {

					$row['short_story'] = strip_tags( $row['short_story'], '<p><br><a>' );
					$row['full_story'] = strip_tags( $row['full_story'], '<p><br><a>' );

			} else {

				if ( !$config['allow_smart_images'] ) {
	
					$row['short_story'] = preg_replace( "#<!--TBegin(.+?)<!--TEnd-->#is", "", $row['short_story'] );
					$row['short_story'] = preg_replace( "#<!--MBegin(.+?)<!--MEnd-->#is", "", $row['short_story'] );
					$row['short_story'] = preg_replace( "#<img(.+?)>#is", "", $row['short_story'] );
					$row['full_story'] = preg_replace( "#<!--TBegin(.+?)<!--TEnd-->#is", "", $row['full_story'] );
					$row['full_story'] = preg_replace( "#<!--MBegin(.+?)<!--MEnd-->#is", "", $row['full_story'] );
					$row['full_story'] = preg_replace( "#<img(.+?)>#is", "", $row['full_story'] );
	
				}
	
				if ( !$config['allow_smart_video'] ) {
	
					$row['short_story'] = preg_replace( "#<!--dle_video_begin(.+?)<!--dle_video_end-->#is", "", $row['short_story'] );
					$row['short_story'] = preg_replace( "#<!--dle_audio_begin(.+?)<!--dle_audio_end-->#is", "", $row['short_story'] );
					$row['short_story'] = preg_replace( "#<!--dle_media_begin(.+?)<!--dle_media_end-->#is", "", $row['short_story'] );
					$row['full_story'] = preg_replace( "#<!--dle_video_begin(.+?)<!--dle_video_end-->#is", "", $row['full_story'] );
					$row['full_story'] = preg_replace( "#<!--dle_audio_begin(.+?)<!--dle_audio_end-->#is", "", $row['full_story'] );
					$row['full_story'] = preg_replace( "#<!--dle_media_begin(.+?)<!--dle_media_end-->#is", "", $row['full_story'] );
	
				}

			}

		}
		
		$tpl->set( '{comments}', "<!--dlecomments-->" );
		$tpl->set( '{addcomments}', "<!--dleaddcomments-->" );
		$tpl->set( '{navigation}', "<!--dlenavigationcomments-->" );

		$all_xf_content = array();
		
		if( count($xfields) ) {
			$row['xfields_array'] = xfieldsdataload( $row['xfields'] );
		}
		
		if( count($xfields) ) {
			
			$xfieldsdata = $row['xfields_array'];
			$replaced_social_image = false;
			
			foreach ( $xfields as $value ) {
				$preg_safe_name = preg_quote( $value[0], "'" );
				
				if( !isset($xfieldsdata[$value[0]]) ) {
					$xfieldsdata[$value[0]] = '';
				}
				
				if( $value[20] ) {
				  
				  $value[20] = explode( ',', $value[20] );
				  
				  if( $value[20][0] AND !in_array( $member_id['user_group'], $value[20] ) ) {
					$xfieldsdata[$value[0]] = "";
				  }
				  
				}
				
				if ( $value[3] == "yesorno" ) {
					
				    if( intval($xfieldsdata[$value[0]]) ) {
						$xfgiven = true;
						$xfieldsdata[$value[0]] = $lang['xfield_xyes'];
					} else {
						$xfgiven = false;
						$xfieldsdata[$value[0]] = $lang['xfield_xno'];
					}
					
				} else {
					
					if($xfieldsdata[$value[0]] == "") $xfgiven = false; else $xfgiven = true;
					
				}
				
				if( !$xfgiven ) {
					$tpl->copy_template = preg_replace( "'\\[xfgiven_{$preg_safe_name}\\](.*?)\\[/xfgiven_{$preg_safe_name}\\]'is", "", $tpl->copy_template );
					$tpl->copy_template = str_ireplace( "[xfnotgiven_{$value[0]}]", "", $tpl->copy_template );
					$tpl->copy_template = str_ireplace( "[/xfnotgiven_{$value[0]}]", "", $tpl->copy_template );
				} else {
					$tpl->copy_template = preg_replace( "'\\[xfnotgiven_{$preg_safe_name}\\](.*?)\\[/xfnotgiven_{$preg_safe_name}\\]'is", "", $tpl->copy_template );
					$tpl->copy_template = str_ireplace( "[xfgiven_{$value[0]}]", "", $tpl->copy_template );
					$tpl->copy_template = str_ireplace( "[/xfgiven_{$value[0]}]", "", $tpl->copy_template );
				}
				
				if(strpos( $tpl->copy_template, "[ifxfvalue {$value[0]}" ) !== false ) {
					$tpl->copy_template = preg_replace_callback ( "#\\[ifxfvalue(.+?)\\](.+?)\\[/ifxfvalue\\]#is", "check_xfvalue", $tpl->copy_template );
				}

				if ($value[3] == "select" and isset($xfieldsdata[$value[0]]) and $xfieldsdata[$value[0]] and !$value[6]) {
					$xfieldsdata[$value[0]] = explode(',', $xfieldsdata[$value[0]]);
					$xfieldsdata[$value[0]] = implode($value[35], $xfieldsdata[$value[0]]);
				}

				if ( $value[6] AND !empty( $xfieldsdata[$value[0]] ) ) {
					$temp_array = explode( ",", $xfieldsdata[$value[0]] );
					$value3 = array();

					foreach ($temp_array as $value2) {

						$value2 = trim($value2);
						
						if($value2) {

							$value4 = str_replace(array("&#039;", "&quot;", "&amp;", "&#123;", "&#91;", "&#58;", "/"), array("'", '"', "&", "{", "[", ":", "&frasl;"), $value2);

							if( $value[3] == "datetime" ) {
							
								$value2 = strtotime( $value4 );
							
								if( !trim($value[24]) ) $value[24] = $config['timestamp_active'];

								if (strpos($tpl->copy_template, "[xfvalue_{$value[0]} format=") !== false) {

									$tpl->copy_template = preg_replace_callback("#\\[xfvalue_{$preg_safe_name} format=['\"](.*?)['\"]\\]#i",
										function ($matches) use ($value, $value2, $value4, $customlangdate, $config, $PHP_SELF) {

											$matches[1] = trim($matches[1]);

											if ($value[25]) {

												if ($value[26]) $value2 = langdate($matches[1], $value2);
												else return $value2 = langdate($matches[1], $value2, false, $customlangdate);
											} else $value2 = date($matches[1], $value2);

											if ($config['allow_alt_url']) return "<a href=\"" . $config['http_home_url'] . "xfsearch/" . $value[0] . "/" . rawurlencode(dle_strtolower($value4)) . "/\">" . $value2 . "</a>";
											else return "<a href=\"$PHP_SELF?do=xfsearch&amp;xfname=" . $value[0] . "&amp;xf=" . rawurlencode(dle_strtolower($value4)) . "\">" . $value2 . "</a>";

										}, $tpl->copy_template);
								}

								if( $value[25] ) {
									
									if($value[26]) $value2 = langdate($value[24], $value2);
									else $value2 = langdate($value[24], $value2, false, $customlangdate);
									
								} else $value2 = date( $value[24], $value2 );
	
							}

							if( $config['allow_alt_url'] ) $value3[] = "<a href=\"" . $config['http_home_url'] . "xfsearch/" .$value[0]."/". rawurlencode( dle_strtolower($value4) ) . "/\">" . $value2 . "</a>";
							else $value3[] = "<a href=\"$PHP_SELF?do=xfsearch&amp;xfname=".$value[0]."&amp;xf=" . rawurlencode( dle_strtolower($value4) ) . "\">" . $value2 . "</a>";
						}

					}

					if ($value[3] == "select" and $value[35]) {
						$value[21] = $value[35];
					}
					
					if( empty($value[21]) ) $value[21] = ", ";
					
					$xfieldsdata[$value[0]] = implode($value[21], $value3);

					unset($temp_array);
					unset($value2);
					unset($value3);
					unset($value4);

				} elseif ( $value[3] == "datetime" AND !empty($xfieldsdata[$value[0]]) ) {
	
					$xfieldsdata[$value[0]] = strtotime( str_replace("&#58;", ":", $xfieldsdata[$value[0]]) );
	
					if( !trim($value[24]) ) $value[24] = $config['timestamp_active'];

					if (strpos ( $tpl->copy_template, "[xfvalue_{$value[0]} format=" ) !== false) {
						
						$tpl->copy_template = preg_replace_callback ( "#\\[xfvalue_{$preg_safe_name} format=['\"](.*?)['\"]\\]#i", 
							function ($matches) use ($value, $xfieldsdata, $customlangdate) {
								
								$matches[1] = trim($matches[1]);
								
								if ($value[25]) {

									if ($value[26]) return langdate($matches[1], $xfieldsdata[$value[0]]);
									else return langdate($matches[1], $xfieldsdata[$value[0]], false, $customlangdate);

								} else return date($matches[1], $xfieldsdata[$value[0]]);

								
							}, $tpl->copy_template );
							
					}

					if( $value[25] ) {
						
						if($value[26]) $xfieldsdata[$value[0]] = langdate($value[24], $xfieldsdata[$value[0]]);
						else $xfieldsdata[$value[0]] = langdate($value[24], $xfieldsdata[$value[0]], false, $customlangdate);
									
					} else $xfieldsdata[$value[0]] = date( $value[24], $xfieldsdata[$value[0]] );
					
					
				}
				
				if ($config['allow_links'] AND $value[3] == "textarea" AND function_exists('replace_links') AND isset($replace_links['news']) ) $xfieldsdata[$value[0]] = replace_links ( $xfieldsdata[$value[0]], $replace_links['news'] );

				if($value[3] == "image" AND $xfieldsdata[$value[0]] ) {
					
					$temp_array = explode('|', $xfieldsdata[$value[0]]);
						
					if (count($temp_array) == 1 OR count($temp_array) == 5 ){
							
						$temp_alt = '';
						$temp_value = implode('|', $temp_array );
							
					} else {
							
						$temp_alt = $temp_array[0];
						$temp_alt = str_replace( "&amp;#44;", "&#44;", $temp_alt );
						$temp_alt = str_replace( "&amp;#124;", "&#124;", $temp_alt );
						
						unset($temp_array[0]);
						$temp_value =  implode('|', $temp_array );
							
					}

					$path_parts = get_uploaded_image_info($temp_value);
					
					if( !isset($social_tags['image']) OR (isset($social_tags['image']) AND !$social_tags['image']) OR ($value[29] AND !$replaced_social_image ) ) {
						$social_tags['image'] = $path_parts->url;
						$replaced_social_image = true;
					}
						
					if( $value[12] AND $path_parts->thumb ) {
						
						$tpl->set( "[xfvalue_thumb_url_{$value[0]}]", $path_parts->thumb);
						$xfieldsdata[$value[0]] = "<a href=\"{$path_parts->url}\" data-highslide=\"single\" target=\"_blank\"><img class=\"xfieldimage {$value[0]}\" src=\"{$path_parts->thumb}\" alt=\"{$temp_alt}\"></a>";

					} else {
						
						$tpl->set( "[xfvalue_thumb_url_{$value[0]}]", $path_parts->url);
						$xfieldsdata[$value[0]] = "<img class=\"xfieldimage {$value[0]}\" src=\"{$path_parts->url}\" alt=\"{$temp_alt}\">";

					}
					
					$schema_images[] = $path_parts->url;
					
					$tpl->set( "[xfvalue_image_url_{$value[0]}]", $path_parts->url);
					$tpl->set( "[xfvalue_image_description_{$value[0]}]", $temp_alt);
					
					if( $value[28] ) {
						
						if( !$path_parts->thumb ) $path_parts->thumb = $path_parts->url;
							
						$xfields_in_news['[xfvalue_image_url_'.$value[0].']'] = $path_parts->url;
						$xfields_in_news['[xfvalue_image_description_'.$value[0].']'] = $temp_alt;
						$xfields_in_news['[xfvalue_thumb_url_'.$value[0].']'] = $path_parts->thumb;
					
					}
					
				}
					
				if($value[3] == "image" AND !$xfieldsdata[$value[0]]) {
	
					$tpl->set( "[xfvalue_thumb_url_{$value[0]}]", "");
					$tpl->set( "[xfvalue_image_url_{$value[0]}]", "");
					$tpl->set( "[xfvalue_image_description_{$value[0]}]", "");
					
				}

				if (($value[3] == "video" or $value[3] == "audio") and $xfieldsdata[$value[0]]) {

					$fieldvalue_arr = explode(',', $xfieldsdata[$value[0]]);
					$playlist = array();
					$playlist_single = array();
					$xf_playlist_count = 0;

					if ($value[3] == "audio") {
						$xftag = "audio";
						$xftype = "audio/mp3";
					} else {
						$xftag = "video";
						$xftype = "video/mp4";
					}

					if (!isset($video_config)) {
						include_once(ENGINE_DIR . '/data/videoconfig.php');
					}

					if ($video_config['preload']) $preload = "metadata";
					else $preload = "none";

					$playlist_width = $video_config['width'];

					if (substr($playlist_width, -1, 1) != '%') $playlist_width = $playlist_width . "px";

					$playlist_width = "style=\"width:100%;max-width:{$playlist_width};\"";

					foreach ($fieldvalue_arr as $temp_value) {

						$xf_playlist_count++;

						$temp_value = trim($temp_value);

						if (!$temp_value) continue;

						$temp_array = explode('|', $temp_value);

						if (count($temp_array) < 4) {

							$temp_alt = '';
							$temp_url = $temp_array[0];
						} else {

							$temp_alt = $temp_array[0];
							$temp_url = $temp_array[1];
						}

						$filename = pathinfo($temp_url, PATHINFO_FILENAME);
						$filename = explode("_", $filename);
						if (count($filename) > 1 and intval($filename[0])) unset($filename[0]);
						$filename = implode("_", $filename);

						if (!$temp_alt) $temp_alt = $filename;

						$playlist[] = "<{$xftag} title=\"{$temp_alt}\" preload=\"{$preload}\" controls><source type=\"{$xftype}\" src=\"{$temp_url}\"></{$xftag}>";
						$playlist_single['[xfvalue_' . $value[0] . ' ' . $xftag . '="' . $xf_playlist_count . '"]'] = "<div class=\"dleplyrplayer\" {$playlist_width} theme=\"{$video_config['theme']}\"><{$xftag} title=\"{$temp_alt}\" preload=\"{$preload}\" controls><source type=\"{$xftype}\" src=\"{$temp_url}\"></{$xftag}></div>";

						$playlist_single['[xfvalue_' . $value[0] . ' ' . $xftag . '-description="' . $xf_playlist_count . '"]'] = $temp_alt;
						$playlist_single['[xfvalue_' . $value[0] . ' ' . $xftag . '-url="' . $xf_playlist_count . '"]'] = $temp_url;

						$tpl->copy_template = str_ireplace('[xfgiven_' . $value[0] . ' ' . $xftag . '="' . $xf_playlist_count . '"]', "", $tpl->copy_template);
						$tpl->copy_template = str_ireplace('[/xfgiven_' . $value[0] . ' ' . $xftag . '="' . $xf_playlist_count . '"]', "", $tpl->copy_template);
						$tpl->copy_template = preg_replace("'\\[xfnotgiven_{$preg_safe_name} {$xftag}=\"{$xf_playlist_count}\"\\](.*?)\\[/xfnotgiven_{$preg_safe_name} {$xftag}=\"{$xf_playlist_count}\"\\]'is", "", $tpl->copy_template);
					}

					if (count($playlist_single)) {

						foreach ($playlist_single as $temp_key => $temp_value) {

							$tpl->set($temp_key, $temp_value);

							if ($value[28]) {
								$xfields_in_news[$temp_key] = $temp_value;
							}
						}
					}

					$xfieldsdata[$value[0]] = "<div class=\"dleplyrplayer\" {$playlist_width} theme=\"{$video_config['theme']}\">" . implode($playlist) . "</div>";
				}

				if($value[3] == "imagegalery" AND $xfieldsdata[$value[0]] ) {
					
					$fieldvalue_arr = explode(',', $xfieldsdata[$value[0]]);
					$gallery_image = array();
					$gallery_single_image = array();
					$xf_image_count = 0;
					
					foreach ($fieldvalue_arr as $temp_value) {
						$xf_image_count ++;
						
						$temp_value = trim($temp_value);
				
						if($temp_value == "") continue;
						
						$temp_array = explode('|', $temp_value);
						
						if (count($temp_array) == 1 OR count($temp_array) == 5 ){
								
							$temp_alt = '';
							$temp_value = implode('|', $temp_array );
								
						} else {
								
							$temp_alt = $temp_array[0];
							$temp_alt = str_replace( "&amp;#44;", "&#44;", $temp_alt );
							$temp_alt = str_replace( "&amp;#124;", "&#124;", $temp_alt );
							
							unset($temp_array[0]);
							$temp_value =  implode('|', $temp_array );
								
						}
	
						$path_parts = get_uploaded_image_info($temp_value);
						
						if( !isset($social_tags['image']) OR (isset($social_tags['image']) AND !$social_tags['image'])  OR ($value[29] AND !$replaced_social_image ) ) {
							
							$social_tags['image'] = $path_parts->url;
							$replaced_social_image = true;
							
						}
						
						if($value[12] AND $path_parts->thumb) {
							
							$gallery_image[] = "<li><a href=\"{$path_parts->url}\" data-highslide=\"xf_{$row['id']}_{$value[0]}\" target=\"_blank\"><img src=\"{$path_parts->thumb}\" alt=\"{$temp_alt}\"></a></li>";
							$gallery_single_image['[xfvalue_'.$value[0].' image="'.$xf_image_count.'"]'] = "<a href=\"{$path_parts->url}\" data-highslide=\"single\" target=\"_blank\"><img class=\"xfieldimage {$value[0]}\" src=\"{$path_parts->thumb}\" alt=\"{$temp_alt}\"></a>";
							
						} else {
							$gallery_image[] = "<li><img src=\"{$path_parts->url}\" alt=\"{$temp_alt}\"></li>";
							$gallery_single_image['[xfvalue_'.$value[0].' image="'.$xf_image_count.'"]'] = "<img class=\"xfieldimage {$value[0]}\" src=\"{$path_parts->url}\" alt=\"{$temp_alt}\">";
						}
						
						$schema_images[] = $path_parts->url;
						
						if( !$path_parts->thumb ) $path_parts->thumb = $path_parts->url;
						
						$gallery_single_image['[xfvalue_'.$value[0].' image-description="'.$xf_image_count.'"]'] = $temp_alt;
						$gallery_single_image['[xfvalue_'.$value[0].' image-thumb-url="'.$xf_image_count.'"]'] = $path_parts->thumb;
						$gallery_single_image['[xfvalue_'.$value[0].' image-url="'.$xf_image_count.'"]'] = $path_parts->url;
						
						$tpl->copy_template = str_ireplace( '[xfgiven_'.$value[0].' image="'.$xf_image_count.'"]', "", $tpl->copy_template );
						$tpl->copy_template = str_ireplace( '[/xfgiven_'.$value[0].' image="'.$xf_image_count.'"]', "", $tpl->copy_template );
						$tpl->copy_template = preg_replace( "'\\[xfnotgiven_{$preg_safe_name} image=\"{$xf_image_count}\"\\](.*?)\\[/xfnotgiven_{$preg_safe_name} image=\"{$xf_image_count}\"\\]'is", "", $tpl->copy_template );

					}
					
					if(count($gallery_single_image) ) {
						
						foreach($gallery_single_image as $temp_key => $temp_value) {
							
							$tpl->set( $temp_key, $temp_value);
							
							if( $value[28] ) {
								$xfields_in_news[$temp_key] = $temp_value;
							}
							
						}
					}
					
					$xfieldsdata[$value[0]] = "<ul class=\"xfieldimagegallery {$value[0]}\">".implode($gallery_image)."</ul>";
					
				}
				
				$tpl->copy_template = preg_replace( "'\\[xfgiven_{$preg_safe_name} (image|video|audio)=\"(\d+)\"\\](.*?)\\[/xfgiven_{$preg_safe_name} (image|video|audio)=\"(\d+)\"\\]'is", "", $tpl->copy_template );
				$tpl->copy_template = preg_replace( "'\\[xfnotgiven_{$preg_safe_name} (image|video|audio)=\"(\d+)\"\\]'i", "", $tpl->copy_template );
				$tpl->copy_template = preg_replace( "'\\[/xfnotgiven_{$preg_safe_name} (image|video|audio)=\"(\d+)\"\\]'i", "", $tpl->copy_template );	

				if ($value[30] AND $view_template != "print") $xfieldsdata[$value[0]] = preg_replace_callback ( "#<(img|iframe)(.+?)>#i", "enable_lazyload", $xfieldsdata[$value[0]] );

				$tpl->set( "[xfvalue_{$value[0]}]", $xfieldsdata[$value[0]] );
				
				if( $value[28] ) {
					$xfields_in_news['[xfvalue_'.$value[0].']'] = $xfieldsdata[$value[0]];
				}
				
				if( ($value[3] == "text" OR $value[3] == "textarea") AND $xfieldsdata[$value[0]]) {
					$all_xf_content[] = $xfieldsdata[$value[0]];
				}

				if ( preg_match( "#\\[xfvalue_{$preg_safe_name} limit=['\"](.+?)['\"]\\]#i", $tpl->copy_template, $matches ) ) {
					$tpl->set( $matches[0], clear_content($xfieldsdata[$value[0]], $matches[1]) );
				}
				
			}
		}
			
		if( $config['schema_org'] AND count($schema_images) ) {
			$schema_images = array_unique($schema_images);
			$schema->image = DLESEO::Thing('', $schema_images, false );
		}
			
		if( count($all_xf_content) ) $all_xf_content = implode(" ", $all_xf_content);
		else $all_xf_content = "";

		if( $empty_full ) {
			$social_tags['description'] = clear_content( $row['full_story']." ".$all_xf_content, 300, false );
		} else {
			$social_tags['description'] = clear_content( $row['full_story']." ".$row['short_story']." ".$all_xf_content, 300, false );
		}
		
		if( $config['schema_org'] ) {
			$schema->description = $social_tags['description'];
		}
		
		unset($all_xf_content);
		
		if ($config['image_lazy'] AND $view_template != "print") {
			$row['short_story'] = preg_replace_callback ( "#<(img|iframe)(.+?)>#i", "enable_lazyload", $row['short_story'] );
			$row['full_story'] = preg_replace_callback ( "#<(img|iframe)(.+?)>#i", "enable_lazyload", $row['full_story'] );
		}

		$tpl->set( '{short-story}', $row['short_story'] );

		$tpl->set( '{full-story}', $row['full_story'] );

		if ( preg_match( "#\\{full-story limit=['\"](.+?)['\"]\\}#i", $tpl->copy_template, $matches ) ) {
			$tpl->set( $matches[0], clear_content($row['full_story'], $matches[1]) );
		}
		
		$tpl->set( '{title}', str_replace("&amp;amp;", "&amp;", htmlspecialchars( $row['title'], ENT_QUOTES, $config['charset'] ) ) );
		
		if ( preg_match( "#\\{title limit=['\"](.+?)['\"]\\}#i", $tpl->copy_template, $matches ) ) {
			$tpl->set( $matches[0], clear_content($row['title'], $matches[1]) );
		}
		
		if( $config['user_in_news'] ) {
			include (DLEPlugins::Check(ENGINE_DIR . '/modules/profile_innews.php'));
		}
		
		$xfieldsdata = $row['xfields'];
		$category_id = $row['category'];
		
		$tpl->compile( 'content', true, false );
		
		if( $config['schema_org'] ) {
			DLESEO::AddSchema( $schema );
		}
		
		if(is_array($xfields_in_news) AND count($xfields_in_news) ) {
			
			if (stripos ( $tpl->result['content'], "[xf" ) !== false ) {
				
				foreach ( $xfields_in_news as $key => $value) {
					$tpl->result['content'] = str_replace ( $key, $value, $tpl->result['content'] );
				}
				
			}
			
			$xfields_in_news = array();
		}
		
		if (stripos ( $tpl->result['content'], "[hide" ) !== false ) {
			
			$tpl->result['content'] = preg_replace_callback ( "#\[hide(.*?)\](.+?)\[/hide\]#is", 
				function ($matches) use ($member_id, $user_group, $lang) {
					
					$matches[1] = str_replace(array("=", " "), "", $matches[1]);
					$matches[2] = $matches[2];
	
					if( $matches[1] ) {
						
						$groups = explode( ',', $matches[1] );
	
						if( in_array( $member_id['user_group'], $groups ) OR $member_id['user_group'] == "1") {
							return $matches[2];
						} else return "<div class=\"quote dlehidden\">" . $lang['news_regus'] . "</div>";
						
					} else {
						
						if( $user_group[$member_id['user_group']]['allow_hide'] ) return $matches[2]; else return "<div class=\"quote dlehidden\">" . $lang['news_regus'] . "</div>";
						
					}
	
			}, $tpl->result['content'] );
		}
	
		if ( $config['allow_banner'] AND count($banner_in_news) ){
	
			foreach ( $banner_in_news as $name) {
				$tpl->result['content'] = str_replace( "{banner_" . $name . "}", $banners[$name], $tpl->result['content'] );
	
				if( $banners[$name] ) {
					$tpl->result['content'] = str_replace ( "[banner_" . $name . "]", "", $tpl->result['content'] );
					$tpl->result['content'] = str_replace ( "[/banner_" . $name . "]", "", $tpl->result['content'] );
				}
			}
	
			$tpl->result['content'] = preg_replace( "'\\[banner_(.*?)\\](.*?)\\[/banner_(.*?)\\]'si", '', $tpl->result['content'] );
		
		}
		
		$news_id = $row['id'];
		$allow_comments = $row['allow_comm'];

		$allow_add = true;

		if ( $config['max_comments_days'] ) {

			if ($row['date'] < ($_TIME - ($config['max_comments_days'] * 3600 * 24)) )	$allow_add = false;

		}
	
	}
	
	$tpl->news_mode = false;
	$tpl->clear();
	
	if( $config['files_allow'] AND $news_found) if( strpos( $tpl->result['content'], "[attachment=" ) !== false ) {
		$tpl->result['content'] = show_attach( $tpl->result['content'], $news_id );
	}

	if( !$news_found AND !$perm AND $need_pass ) {
		
		$form_n_pass = <<<HTML
<form method="post" action="">
{$lang['enter_n_pass_1']}
<br>{status}<br>
{$lang['enter_n_pass_2']}&nbsp;&nbsp;<input type="password" name="news_password" style="width:200px">
<br><br>
<button type="submit" class="bbcodes">{$lang['enter_n_pass_3']}</button>
</form>
HTML;

		if( trim($_POST['news_password']) ) {
			$form_n_pass = str_replace("{status}", "<br>".$lang['enter_n_pass_4']."<br>", $form_n_pass);
		} else $form_n_pass = str_replace("{status}","", $form_n_pass);
		
		@header( "HTTP/1.1 403 Forbidden" );
		msgbox( $lang['enter_n_pass'], $form_n_pass );
		
	} elseif( !$news_found AND !$perm ) {
		
		@header( "HTTP/1.1 403 Forbidden" );
		msgbox( $lang['all_err_1'], "<b>{$user_group[$member_id['user_group']]['group_name']}</b> " . $lang['news_err_28'] );
		
	} elseif( !$news_found ) {
		
		@header( "HTTP/1.1 404 Not Found" );
		
		if( $config['own_404'] AND file_exists(ROOT_DIR . '/404.html') ) {
			@header("Content-type: text/html; charset=".$config['charset']);
			echo file_get_contents( ROOT_DIR . '/404.html' );
			die();
			
		} else msgbox( $lang['all_err_1'], $lang['news_err_12'] );
		
	}
	
	unset( $row );
	
if( !$view_template AND $news_found) {
	
	if( $comments_num > 0 ) {

		$comments = new DLE_Comments( $db, $comments_num, intval($config['comm_nummers']), $allow_comments );

		if( $config['comm_msort'] == "" OR $config['comm_msort'] == "ASC" ) $comm_msort = "ASC"; else $comm_msort = "DESC";

		if( $config['tree_comments'] ) $comm_msort = "ASC";
		
		if( $config['allow_cmod'] ) $where_approve = " AND " . PREFIX . "_comments.approve=1";
		else $where_approve = "";

		$comments->query = "SELECT " . PREFIX . "_comments.id, post_id, " . PREFIX . "_comments.user_id, date, autor as gast_name, " . PREFIX . "_comments.email as gast_email, text, ip, is_register, " . PREFIX . "_comments.rating, " . PREFIX . "_comments.vote_num, " . PREFIX . "_comments.parent, name, " . USERPREFIX . "_users.email, news_num, comm_num, user_group, lastdate, reg_date, signature, foto, fullname, land, xfields FROM " . PREFIX . "_comments LEFT JOIN " . USERPREFIX . "_users ON " . PREFIX . "_comments.user_id=" . USERPREFIX . "_users.user_id WHERE " . PREFIX . "_comments.post_id = '$news_id'" . $where_approve . " ORDER BY " . PREFIX . "_comments.id " . $comm_msort;

		if ( $allow_full_cache AND $config['allow_comments_cache'] ) $allow_full_cache = $news_id; else $allow_full_cache = false;

		$comments->build_comments('comments.tpl', 'news', $allow_full_cache, $full_link );

		unset ($tpl->result['comments']);

		if( isset($_GET['news_page']) AND $_GET['news_page'] ) $user_query = "newsid=" . $newsid . "&amp;news_page=" . intval( $_GET['news_page'] ); else $user_query = "newsid=" . $newsid;

		$comments->build_navigation('navigation.tpl', $link_page . "{page}," . $news_name . ".html#comment", $user_query, $full_link);		

		unset ($comments);
		unset ($tpl->result['commentsnavigation']);

		$onload_scripts[] = "find_comment_onpage();";

	} elseif ($config['seo_control']  AND isset($_GET['cstart']) AND $_GET['cstart']) {

			$re_url = parse_url($full_link, PHP_URL_PATH);
			header("HTTP/1.0 301 Moved Permanently");
			header("Location: {$re_url}");
			die("Redirect");
	
	}

	if ($is_logged AND $config['comments_restricted'] AND (($_TIME - $member_id['reg_date']) < ($config['comments_restricted'] * 86400)) ) {

		$lang['news_info_6'] = str_replace( '{days}', intval($config['comments_restricted']), $lang['news_info_8'] );
		$allow_add = false;

	}

	if (!isset($member_id['restricted'])) $member_id['restricted'] = false;
	
	if( $member_id['restricted'] AND $member_id['restricted_days'] AND $member_id['restricted_date'] < $_TIME ) {
		
		$member_id['restricted'] = 0;
		$db->query( "UPDATE LOW_PRIORITY " . USERPREFIX . "_users SET restricted='0', restricted_days='0', restricted_date='' WHERE user_id='{$member_id['user_id']}'" );
	
	}
	
	if( $user_group[$member_id['user_group']]['allow_addc'] AND $config['allow_comments'] AND $allow_add AND $allow_comments AND $member_id['restricted'] != 2 AND $member_id['restricted'] != 3 ) {

		if( !$comments_num ) {
			
			if( strpos ( $tpl->result['content'], "<!--dlecomments-->" ) !== false ) {
	
				$tpl->result['content'] = str_replace ( "<!--dlecomments-->", "\n<div id=\"dle-ajax-comments\"></div>\n", $tpl->result['content'] );
	
			} else $tpl->result['content'] .= "\n<div id=\"dle-ajax-comments\"></div>\n";
			
		}
		
		$tpl->load_template( 'addcomments.tpl' );

		if ($config['allow_subscribe'] AND $is_logged AND $user_group[$member_id['user_group']]['allow_subscribe']) $allow_subscribe = true; else $allow_subscribe = false;
		
		if( strpos( $tpl->copy_template, "[catlist=" ) !== false ) {
			$tpl->copy_template = preg_replace_callback ( "#\\[(catlist)=(.+?)\\](.*?)\\[/catlist\\]#is", "check_category", $tpl->copy_template );
		}
								
		if( strpos( $tpl->copy_template, "[not-catlist=" ) !== false ) {
			$tpl->copy_template = preg_replace_callback ( "#\\[(not-catlist)=(.+?)\\](.*?)\\[/not-catlist\\]#is", "check_category", $tpl->copy_template );
		}
		
		$comments_image_uploader_loaded = false;
		
		if ( $user_group[$member_id['user_group']]['allow_image'] AND  $user_group[$member_id['user_group']]['allow_up_image'] ) {
			
			$tpl->set( '[image-upload]', "" );
			$tpl->set( '[/image-upload]', "" );
			
			if( strpos( $tpl->copy_template, "{image-upload}" ) !== false ) {
				
				$comments_image_uploader_loaded = true;
				$user_group[$member_id['user_group']]['up_count_image'] = intval($user_group[$member_id['user_group']]['up_count_image']);
				
				if($lang['direction'] == 'rtl') $rtl_prefix ='_rtl'; else $rtl_prefix = '';
				
				$css_array[] = "engine/classes/uploads/html5/fileuploader{$rtl_prefix}.css";
				$max_file_size = intval( $user_group[$member_id['user_group']]['up_image_size'] ) * 1024;
				
				$tpl->set( '{image-upload}', "<div id=\"comments-image-uploader\" class=\"comments-image-uploader\"></div><script>var plupoad_ui_plugin_loaded = true;</script>" );
				
				$config['file_chunk_size'] =  number_format(floatval($config['file_chunk_size']), 1, '.', '');
				if ($config['file_chunk_size'] < 1) $config['file_chunk_size'] = '1.5';

				$onload_scripts[] = <<<HTML

function comments_media_uploader() {

	$('#comments-image-uploader').plupload({

		runtimes: 'html5',
		url: dle_root + "engine/ajax/controller.php?mod=upload",
		file_data_name: "qqfile",

		max_file_size: '{$max_file_size}',

		chunk_size: '{$config['file_chunk_size']}mb',

		filters: [
			{title : "Image files", extensions : "gif,jpg,png,jpeg,bmp,webp"}
		],
		
		rename: true,
		sortable: true,
		dragdrop: true,

		views: {
			list: false,
			thumbs: true,
			active: 'thumbs',
			remember: false
		},
		
		multipart_params: {"subaction" : "upload", "news_id" : 0, "area" : 'comments', "author" : "{$member_id['name']}", "user_hash" : "{$dle_login_hash}"},
		
		init: function(event, args) {
			$('#comments-image-uploader .plupload_droptext').text('{$lang['media_upload_st_5']}');
		},
		selected: function(event, args) {
			var uploader = args.up;
			var commentsfiles_each_count = 0;
			var commentsfiles_count_errors = false;
			var comments_max_allow_files = {$user_group[$member_id['user_group']]['up_count_image']};

			plupload.each(uploader.files, function(file) {
				commentsfiles_each_count ++

				if(comments_max_allow_files && commentsfiles_each_count > comments_max_allow_files ) {
					commentsfiles_count_errors = true;

					setTimeout(function() {
						uploader.removeFile( file );
					}, 100);

				}

			});

			if(commentsfiles_count_errors) {
				$('#comments-image-uploader').plupload('notify', 'error', "{$lang['error_max_queue']}");
			}

			$('#comments-image-uploader').data('files', 'selected');
			$('.plupload_container').addClass('plupload_files_selected');

		},
		removed: function(event, args) {
			if(args.up.files.length) {
				$('.plupload_container').addClass('plupload_files_selected');
			} else {
				$('.plupload_container').removeClass('plupload_files_selected');
			}
		},
		started: function(event, args) {
			ShowLoading('');
		},
		complete: function(event, args) {
			HideLoading('');
			$('#comments-image-uploader').data('files', 'uploaded');
			doAddComments();
			
		}
		
	});

}

if (typeof $.fn.plupload !== "function" ) {

	$.getCachedScript(dle_root + 'engine/classes/uploads/html5/plupload/plupload.full.min.js?v={$config['cache_id']}').done(function() {
		$.getCachedScript(dle_root +'engine/classes/uploads/html5/plupload/plupload.ui.min.js?v={$config['cache_id']}').done(function() {
			$.getCachedScript(dle_root + 'engine/classes/uploads/html5/plupload/i18n/{$lang['language_code']}.js?v={$config['cache_id']}').done(function() {
				comments_media_uploader();
			});
		});
	});
	
} else {
	comments_media_uploader();
}

HTML;

			}
			
		} else {
			
			$tpl->set_block( "'\\[image-upload\\](.*?)\\[/image-upload\\]'si", "" );
			$tpl->set( '{image-upload}', "" );
			
		}
		
		$text='';

		if( $config['comments_mobile_editor'] AND ($tpl->smartphone OR $tpl->tablet) ) $comments_mobile_editor = true; else $comments_mobile_editor = false;

		if ( $config['allow_comments_wysiwyg'] > 0 ) {

			$p_name = urlencode($member_id['name']);
			$p_id = 0;
			include_once(DLEPlugins::Check(ENGINE_DIR . '/editor/comments.php'));
			$bb_code = "";
			$allow_comments_ajax = true;
			$tpl->set('{editor}', $wysiwyg);

		} else {

			include_once(DLEPlugins::Check(ENGINE_DIR . '/modules/bbcode.php'));
			
			$tpl->set('{editor}', $bb_code);
		}

		if ( $is_logged AND $user_group[$member_id['user_group']]['disable_comments_captcha'] AND $member_id['comm_num'] >= $user_group[$member_id['user_group']]['disable_comments_captcha'] ) {
		
			$user_group[$member_id['user_group']]['comments_question'] = false;
			$user_group[$member_id['user_group']]['captcha'] = false;
		
		}

		if( $user_group[$member_id['user_group']]['comments_question'] ) {

			$tpl->set( '[question]', "" );
			$tpl->set( '[/question]', "" );

			$question = $db->super_query("SELECT id, question FROM " . PREFIX . "_question ORDER BY RAND() LIMIT 1");
			$tpl->set( '{question}', "<span id=\"dle-question\">".htmlspecialchars( stripslashes( $question['question'] ), ENT_QUOTES, $config['charset'] )."</span>" );

			$_SESSION['question'] = $question['id'];

		} else {

			$tpl->set_block( "'\\[question\\](.*?)\\[/question\\]'si", "" );
			$tpl->set( '{question}', "" );

		}
		
		if( $user_group[$member_id['user_group']]['captcha'] ) {

			if ( $config['allow_recaptcha'] ) {

				$tpl->set( '[recaptcha]', "" );
				$tpl->set( '[/recaptcha]', "" );
				
				$captcha_name = "g-recaptcha";
				$captcha_url = "https://www.google.com/recaptcha/api.js?hl={$lang['language_code']}";
				
				if( $config['allow_recaptcha'] == 3) {
					
					$captcha_name = "h-captcha";
					$captcha_url = "https://js.hcaptcha.com/1/api.js?hl={$lang['language_code']}";
				
				}

				if ($config['allow_recaptcha'] == 4) {

					$captcha_name = "cf-turnstile";
					$captcha_url = "https://challenges.cloudflare.com/turnstile/v0/api.js?compat=recaptcha";
				}

				if( $config['allow_recaptcha'] == 2) {
						
					$tpl->set( '{recaptcha}', "");
					$tpl->copy_template .= "<input type=\"hidden\" name=\"g-recaptcha-response\" id=\"g-recaptcha-response\" value=\"\"><script src=\"https://www.google.com/recaptcha/api.js?render={$config['recaptcha_public_key']}\" async defer></script>";
						
				} else {
					
					$tpl->set( '{recaptcha}', "<div class=\"{$captcha_name}\" data-sitekey=\"{$config['recaptcha_public_key']}\" data-theme=\"{$config['recaptcha_theme']}\" data-language=\"{$lang['language_code']}\"></div><script src=\"{$captcha_url}\" async defer></script>" );
					
				}
				
				$tpl->set_block( "'\\[sec_code\\](.*?)\\[/sec_code\\]'si", "" );
				$tpl->set( '{reg_code}', "" );

			} else {

				$tpl->set( '[sec_code]', "" );
				$tpl->set( '[/sec_code]', "" );
				$path = parse_url( $config['http_home_url'] );
				$tpl->set( '{sec_code}', "<a onclick=\"reload(); return false;\" title=\"{$lang['reload_code']}\" href=\"#\"><span id=\"dle-captcha\"><img src=\"" . $path['path'] . "engine/modules/antibot/antibot.php\" alt=\"{$lang['reload_code']}\" width=\"160\" height=\"80\"></span></a>" );
				$tpl->set_block( "'\\[recaptcha\\](.*?)\\[/recaptcha\\]'si", "" );
				$tpl->set( '{recaptcha}', "" );
			}

		} else {
			$tpl->set( '{sec_code}', "" );
			$tpl->set( '{recaptcha}', "" );
			$tpl->set_block( "'\\[recaptcha\\](.*?)\\[/recaptcha\\]'si", "" );
			$tpl->set_block( "'\\[sec_code\\](.*?)\\[/sec_code\\]'si", "" );
		}
		
		$tpl->set( '{title}', $lang['news_addcom'] );

		if( $vk_url ) {
			$tpl->set( '[vk]', "" );
			$tpl->set( '[/vk]', "" );
			$tpl->set( '{vk_url}', $vk_url );	
		} else {
			$tpl->set_block( "'\\[vk\\](.*?)\\[/vk\\]'si", "" );
			$tpl->set( '{vk_url}', '' );	
		}
		if( $odnoklassniki_url ) {
			$tpl->set( '[odnoklassniki]', "" );
			$tpl->set( '[/odnoklassniki]', "" );
			$tpl->set( '{odnoklassniki_url}', $odnoklassniki_url );
		} else {
			$tpl->set_block( "'\\[odnoklassniki\\](.*?)\\[/odnoklassniki\\]'si", "" );
			$tpl->set( '{odnoklassniki_url}', '' );	
		}
		if( $facebook_url ) {
			$tpl->set( '[facebook]', "" );
			$tpl->set( '[/facebook]', "" );
			$tpl->set( '{facebook_url}', $facebook_url );	
		} else {
			$tpl->set_block( "'\\[facebook\\](.*?)\\[/facebook\\]'si", "" );
			$tpl->set( '{facebook_url}', '' );	
		}
		if( $google_url ) {
			$tpl->set( '[google]', "" );
			$tpl->set( '[/google]', "" );
			$tpl->set( '{google_url}', $google_url );
		} else {
			$tpl->set_block( "'\\[google\\](.*?)\\[/google\\]'si", "" );
			$tpl->set( '{google_url}', '' );	
		}
		if( $mailru_url ) {
			$tpl->set( '[mailru]', "" );
			$tpl->set( '[/mailru]', "" );
			$tpl->set( '{mailru_url}', $mailru_url );	
		} else {
			$tpl->set_block( "'\\[mailru\\](.*?)\\[/mailru\\]'si", "" );
			$tpl->set( '{mailru_url}', '' );	
		}
		if( $yandex_url ) {
			$tpl->set( '[yandex]', "" );
			$tpl->set( '[/yandex]', "" );
			$tpl->set( '{yandex_url}', $yandex_url );
		} else {
			$tpl->set_block( "'\\[yandex\\](.*?)\\[/yandex\\]'si", "" );
			$tpl->set( '{yandex_url}', '' );
		}
		
		if ( $allow_subscribe ) {
			
			$tpl->set( '[comments-subscribe]', "<a href=\"#\" onclick=\"subscribe('{$news_id}', 1); return false;\" >" );
			$tpl->set( '[comments-unsubscribe]', "<a href=\"#\" onclick=\"subscribe('{$news_id}', 0); return false;\" >" );
			$tpl->set( '[/comments-subscribe]', '</a>' );
			$tpl->set( '[/comments-unsubscribe]', '</a>' );
			$tpl->set( '[allow-comments-subscribe]', "" );
			$tpl->set( '[/allow-comments-subscribe]', '</a>' );
			$tpl->set( '{comments-subscribe}', "<label class=\"comments_subscribe\"><input type=\"checkbox\" name=\"allow_subscribe\" id=\"allow_subscribe\" value=\"1\"><span>{$lang['c_subscribe']}</span></label>" );
			
		} else {
			
			$tpl->set_block( "'\\[comments-subscribe\\](.*?)\\[/comments-subscribe\\]'si", "" );
			$tpl->set_block( "'\\[comments-unsubscribe\\](.*?)\\[/comments-unsubscribe\\]'si", "" );
			$tpl->set_block( "'\\[allow-comments-subscribe\\](.*?)\\[/allow-comments-subscribe\\]'si", "" );
			$tpl->set( '{comments-subscribe}', "");
		}
		
		if( ! $is_logged ) {
			$tpl->set( '[not-logged]', '' );
			$tpl->set( '[/not-logged]', '' );
		} else $tpl->set_block( "'\\[not-logged\\](.*?)\\[/not-logged\\]'si", "" );
		
		if( $is_logged ) $hidden = "<input type=\"hidden\" name=\"name\" id=\"name\" value=\"{$member_id['name']}\"><input type=\"hidden\" name=\"mail\" id=\"mail\" value=\"\">";
		else $hidden = "";
		
		$tpl->copy_template = "<form  method=\"post\" name=\"dle-comments-form\" id=\"dle-comments-form\" >" . $tpl->copy_template . "
		<input type=\"hidden\" name=\"subaction\" value=\"addcomment\">{$hidden}
		<input type=\"hidden\" name=\"post_id\" id=\"post_id\" value=\"{$news_id}\"><input type=\"hidden\" name=\"user_hash\" value=\"{$dle_login_hash}\"></form>";


		if( $config['allow_recaptcha'] == 2 AND $user_group[$member_id['user_group']]['captcha'] ) {
			
			$onload_scripts[] = <<<HTML
				$('#dle-comments-form').submit(function() {
				
					grecaptcha.execute('{$config['recaptcha_public_key']}', {action: 'comments'}).then(function(token) { 
					
						$('#g-recaptcha-response').val(token);
						
						if( $('#comments-image-uploader').data('files') == 'selected' ) {
							$('#comments-image-uploader').plupload('start');
						} else {
							doAddComments();
						}
						
					});
		
					return false;
				});
HTML;


		} else {
			
			$onload_scripts[] = <<<HTML
				$('#dle-comments-form').submit(function() {
					if( $('#comments-image-uploader').data('files') == 'selected' ) {
						$('#comments-image-uploader').plupload('start');
					} else {
						doAddComments();
					}
					return false;
				});
HTML;
			
		}
		
		$tpl->compile( 'addcomments' );
		$tpl->clear();

		if ( strpos ( $tpl->result['content'], "<!--dleaddcomments-->" ) !== false ) {

			$tpl->result['content'] = str_replace ( "<!--dleaddcomments-->", $tpl->result['addcomments'], $tpl->result['content'] );

		} else {

			$tpl->result['content'] .= $tpl->result['addcomments'];

		}

		unset ($tpl->result['addcomments']);

	} elseif( $member_id['restricted'] ) {
		
		$tpl->load_template( 'info.tpl' );
		
		if( $member_id['restricted_days'] ) {
			
			$lang['news_info_2'] = str_replace('{date}', langdate( "j F Y H:i", $member_id['restricted_date'] ), $lang['news_info_2'] );
			
			$tpl->set( '{error}', $lang['news_info_2'] );
			$tpl->set( '{date}', langdate( "j F Y H:i", $member_id['restricted_date'] ) );
		
		} else $tpl->set( '{error}', $lang['news_info_3'] );
		
		$tpl->set( '{title}', $lang['all_info'] );
		$tpl->compile( 'comments_not_allowed' );
		$tpl->clear();

		if ( strpos ( $tpl->result['content'], "<!--dleaddcomments-->" ) !== false ) {

			$tpl->result['content'] = str_replace ( "<!--dleaddcomments-->", $tpl->result['comments_not_allowed'], $tpl->result['content'] );

		} else {

			$tpl->result['content'] .= $tpl->result['comments_not_allowed'];

		}

		unset ($tpl->result['comments_not_allowed']);
		
	} elseif( !$allow_add ) {

		$tpl->load_template( 'info.tpl' );
		$tpl->set( '{error}', str_replace( '{days}', intval($config['max_comments_days']), $lang['news_info_6'] ) );
		$tpl->set( '{title}', $lang['all_info'] );
		$tpl->compile( 'comments_not_allowed' );
		$tpl->clear();

		if ( strpos ( $tpl->result['content'], "<!--dleaddcomments-->" ) !== false ) {

			$tpl->result['content'] = str_replace ( "<!--dleaddcomments-->", $tpl->result['comments_not_allowed'], $tpl->result['content'] );

		} else {

			$tpl->result['content'] .= $tpl->result['comments_not_allowed'];

		}

		unset ($tpl->result['comments_not_allowed']);
		
	} elseif( !$allow_comments AND $comments_num) {
		
		$tpl->load_template( 'info.tpl' );
		$tpl->set( '{error}', $lang['news_info_9'] );
		$tpl->set( '{title}', $lang['all_info'] );
		$tpl->compile( 'comments_not_allowed' );
		$tpl->clear();

		if ( strpos ( $tpl->result['content'], "<!--dleaddcomments-->" ) !== false ) {

			$tpl->result['content'] = str_replace ( "<!--dleaddcomments-->", $tpl->result['comments_not_allowed'], $tpl->result['content'] );

		} else {

			$tpl->result['content'] .= $tpl->result['comments_not_allowed'];

		}

		unset ($tpl->result['comments_not_allowed']);
		
	} elseif( $config['allow_comments'] AND !$user_group[$member_id['user_group']]['allow_addc']) {
		
		$lang['news_info_1'] = str_replace('{group}', $user_group[$member_id['user_group']]['group_name'], $lang['news_info_1'] );
		
		$tpl->load_template( 'info.tpl' );
		$tpl->set( '{error}', $lang['news_info_1'] );
		$tpl->set( '{group}', $user_group[$member_id['user_group']]['group_name'] );
		$tpl->set( '{title}', $lang['all_info'] );
		$tpl->compile( 'comments_not_allowed' );
		$tpl->clear();
		
		if ( strpos ( $tpl->result['content'], "<!--dleaddcomments-->" ) !== false ) {

			$tpl->result['content'] = str_replace ( "<!--dleaddcomments-->", $tpl->result['comments_not_allowed'], $tpl->result['content'] );

		} else {

			$tpl->result['content'] .= $tpl->result['comments_not_allowed'];

		}

		unset ($tpl->result['comments_not_allowed']);
	
	}
	
}

?>
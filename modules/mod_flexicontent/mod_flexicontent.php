<?php
/**
 * @version 1.2 $Id: mod_flexicontent.php 1952 2014-09-12 08:25:57Z ggppdk $
 * @package Joomla
 * @subpackage FLEXIcontent Module
 * @copyright (C) 2009 Emmanuel Danan - www.vistamedia.fr
 * @license GNU/GPL v2
 * 
 * Universal Content Listing Module for flexicontent.
 *
 * FLEXIcontent is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 */

// no direct access
defined('_JEXEC') or die('Restricted access');

// Decide whether to show module contents
$app    = JFactory::getApplication();
$view   = JRequest::getVar('view');
$option = JRequest::getVar('option');

if ($option=='com_flexicontent')
	$_view = ($view==FLEXI_ITEMVIEW) ? 'item' : $view;
else
	$_view = 'others';

$show_in_views = $params->get('show_in_views', array());
$show_in_views = !is_array($show_in_views) ? array($show_in_views) : $show_in_views;
$views_show_mod =!count($show_in_views) || in_array($_view,$show_in_views);

if ($params->get('enable_php_rule', 0)) {
	$php_show_mod = eval($params->get('php_rule'));
	$show_mod = $params->get('combine_show_rules', 'AND')=='AND'
		? ($views_show_mod && $php_show_mod)  :  ($views_show_mod || $php_show_mod);
} else {
	$show_mod = $views_show_mod;
}

if ( $show_mod )
{
	global $modfc_jprof;
	jimport('joomla.profiler.profiler');
	$modfc_jprof = new JProfiler();
	$modfc_jprof->mark('START: FLEXIcontent Module');
	global $mod_fc_run_times;
	$mod_fc_run_times = array();
	
	// Logging Info variables
	global $fc_content_plg_microtime;
	$fc_content_plg_microtime = 0;
	
	static $mod_initialized = null;
	$modulename = 'mod_flexicontent';
	if ($mod_initialized === null)
	{
		// Load english language file for current module then override (forcing a reload) with current language file
		JFactory::getLanguage()->load($modulename, JPATH_SITE, 'en-GB', $force_reload = false, $load_default = true);
		JFactory::getLanguage()->load($modulename, JPATH_SITE, null, $force_reload = true, $load_default = true);
		
		// Load english language file for 'com_flexicontent' and then override with current language file. Do not force a reload for either (not needed)
		JFactory::getLanguage()->load('com_flexicontent', JPATH_SITE, 'en-GB', $force_reload = false, $load_default = true);
		JFactory::getLanguage()->load('com_flexicontent', JPATH_SITE, null, $force_reload = false, $load_default = true);
		$mod_initialized = true;
	}
	
	// initialize various variables
	global $globalcats;
	$document = JFactory::getDocument();
	$caching 	= $app->getCfg('caching', 0);
	$flexiparams = JComponentHelper::getParams('com_flexicontent');
	
	// include the helper only once
	require_once (dirname(__FILE__).DS.'helper.php');
	
	// Verify parameters (like forced menu item id and comments showing)
	modFlexicontentHelper::verifyParams( $params );
	
	// get module ordering & count parameters
	$ordering					= $params->get('ordering', array());
	$ordering_addtitle= $params->get('ordering_addtitle',1);
	$count 					= (int)$params->get('count', 5);
	$featured				= (int)$params->get('count_feat', 1);
	
	if ( $ordering_addtitle && (int)$params->get('orderbycustomfieldid', 0) && in_array('field', $ordering) )
	{
		$orderbycustomfieldid = (int)$params->get('orderbycustomfieldid', 0);
		JTable::addIncludePath(JPATH_ADMINISTRATOR.DS.'components'.DS.'com_flexicontent'.DS.'tables');
		$orderby_custom_field = JTable::getInstance('flexicontent_fields', '');
		$orderby_custom_field->load($orderbycustomfieldid);
	}
	
	if ( empty($orderby_custom_field) ) {
		$orderby_custom_field = new stdClass();
		$orderby_custom_field->label = 'NA';
	}
	
	// Default ordering is 'added' if none ordering is set. Also make sure $ordering is an array (of ordering groups)
	if ( empty($ordering) )    $ordering = array('added');
	if (!is_array($ordering))  $ordering = explode(',', $ordering);
	
	// get module's basic display parameters
	$moduleclass_sfx= $params->get('moduleclass_sfx', '');
	$layout 				= $params->get('layout', 'default');
	$add_ccs 				= $params->get('add_ccs', !$flexiparams->get('disablecss', 0));
	$add_tooltips 	= $params->get('add_tooltips', 1);
	$width 					= $params->get('width');
	$height 				= $params->get('height');
	
	// get module basic fields parameters
	// standard
	$display_title 		= $params->get('display_title');
	$link_title 			= $params->get('link_title');
	$display_date 		= $params->get('display_date');
	$display_text 		= $params->get('display_text');
	$display_hits			= $params->get('display_hits');
	$display_voting		= $params->get('display_voting');
	$display_comments	= $params->get('display_comments');
	$mod_readmore	 		= $params->get('mod_readmore');
	$mod_use_image	 	= $params->get('mod_use_image');
	$mod_link_image		= $params->get('mod_link_image');
	
	// featured
	$display_title_feat 	= $params->get('display_title_feat');
	$link_title_feat 			= $params->get('link_title_feat');
	$display_date_feat		= $params->get('display_date_feat');
	$display_text_feat 		= $params->get('display_text_feat');
	$display_hits_feat 		= $params->get('display_hits_feat');
	$display_voting_feat	= $params->get('display_voting_feat');
	$display_comments_feat= $params->get('display_comments_feat');
	$mod_readmore_feat		= $params->get('mod_readmore_feat');
	$mod_use_image_feat 	= $params->get('mod_use_image_feat');
	$mod_link_image_feat 	= $params->get('mod_link_image_feat');
	
	// get module custom fields parameters
	// standard
	$use_fields 				= $params->get('use_fields',1);
	$display_label 			= $params->get('display_label');
	$hide_label_onempty	= $params->get('hide_label_onempty');
	$text_after_label		= $params->get('text_after_label');
	$fields 						= $params->get('fields');
	// featured
	$use_fields_feat 				= $params->get('use_fields_feat',1);
	$display_label_feat 		= $params->get('display_label_feat');
	$hide_label_onempty_feat= $params->get('hide_label_onempty_feat');
	$text_after_label_feat 	= $params->get('text_after_label_feat');
	$fields_feat 						= $params->get('fields_feat');
	
	// module display params
	$show_more		= (int)$params->get('show_more', 1);
	$more_link		= $params->get('more_link');
	$more_title		= $params->get('more_title', 'FLEXI_MODULE_READ_MORE');
	$more_css			= $params->get('more_css');
	
	// custom parameters
	$custom1 				= $params->get('custom1');
	$custom2 				= $params->get('custom2');
	$custom3 				= $params->get('custom3');
	$custom4 				= $params->get('custom4');
	$custom5 				= $params->get('custom5');
	
	// Create Item List Data
	$list_arr = modFlexicontentHelper::getList($params);
	
	// Get comments for the items (if enabled), NOTE !! TODO: modify templates and XML file so that this used
	$comments_arr = modFlexicontentHelper::getComments($params, $list_arr);
	
	$mod_fc_run_times['category_data_retrieval'] = $modfc_jprof->getmicrotime();
	
	// Get Category List Data
	$catdata_arr = modFlexicontentHelper::getCategoryData($params);
	$catdata_arr = $catdata_arr ? $catdata_arr : array (false);
	
	$mod_fc_run_times['category_data_retrieval'] = $modfc_jprof->getmicrotime() - $mod_fc_run_times['category_data_retrieval'];
	
	$mod_fc_run_times['rendering_template'] = $modfc_jprof->getmicrotime();
	
	// Load needed JS libs & CSS styles
	//JHtml::_('behavior.framework', true);
	flexicontent_html::loadFramework('jQuery');
	flexicontent_html::loadFramework('flexi_tmpl_common');
	
	// Add tooltips
	if ($add_tooltips) JHtml::_('bootstrap.tooltip');
	
	// Add css
	if ($add_ccs && $layout) {
		// Work around for extension that capture module's HTML 
		if ($add_ccs==2) {
			if (file_exists(dirname(__FILE__).DS.'tmpl'.DS.$layout.DS.$layout.'.css')) {
				// active layout css
				echo '<link rel="stylesheet" href="'.JURI::base(true).'/modules/'.$modulename.'/tmpl/'.$layout.'/'.$layout.'.css?'.FLEXI_VHASH.'">';
			}
			echo '<link rel="stylesheet" href="'.JURI::base(true).'/modules/'.$modulename.'/tmpl_common/module.css?'.FLEXI_VHASH.'">';
			echo '<link rel="stylesheet" href="'.JURI::base(true).'/components/com_flexicontent/assets/css/flexicontent.css?'.FLEXI_VHASH.'">';
			//allow css override
			if (file_exists(JPATH_SITE.DS.'templates'.DS.$app->getTemplate().DS.'css'.DS.'flexicontent.css')) {
				echo '<link rel="stylesheet" href="'.JURI::base(true).'/templates/'.$app->getTemplate().'/css/flexicontent.css">';
			}
		}
		
		// Standards compliant implementation by placing CSS link into the HTML HEAD
		else {
			if (file_exists(dirname(__FILE__).DS.'tmpl'.DS.$layout.DS.$layout.'.css')) {
				// active layout css
				$document->addStyleSheetVersion(JURI::base(true).'/modules/'.$modulename.'/tmpl/'.$layout.'/'.$layout.'.css', FLEXI_VHASH);
			}
			$document->addStyleSheetVersion(JURI::base(true).'/modules/'.$modulename.'/tmpl_common/module.css', FLEXI_VHASH);
			$document->addStyleSheetVersion(JURI::base(true).'/components/com_flexicontent/assets/css/flexicontent.css', FLEXI_VHASH);
			//allow css override
			if (file_exists(JPATH_SITE.DS.'templates'.DS.$app->getTemplate().DS.'css'.DS.'flexicontent.css')) {
				$document->addStyleSheet(JURI::base(true).'/templates/'.$app->getTemplate().'/css/flexicontent.css');
			}
		}
	}
	
	// Include module header, but make sure that file exists, because otherwise the Joomla module helper will include ... 'default.php'
	// module template ! thus a Joomla override template header is allowed only if template header.php file exists locally too !!
	if ( file_exists(dirname(__FILE__).DS.'tmpl'.DS.$layout.DS.'header.php') )
		require(JModuleHelper::getLayoutPath('mod_flexicontent', $layout.'/header'));
	
	// Render Layout, (once per category if apply per category is enabled ...)
	foreach ($catdata_arr as $i => $catdata) {
		$list = & $list_arr[$i];
	
		// Check items exist
		$items_exist = false;
		foreach ($ordering as $ord)
		{
			if ( !empty($list[$ord]['featured']) || !empty($list[$ord]['standard']) ) {
				$items_exist = true;
				break;
			}
		}
	
		// Decide whether to skip rendering of the layout
		$can_skip_category = !$catdata || $params->get('skip_category_if_noitems', 0);
		if ( !$items_exist && $can_skip_category && !$params->get('display_favlist', 0) ) continue;
	
		require(JModuleHelper::getLayoutPath('mod_flexicontent', $layout));
	}
	
	// Include module footer, e.g. includes module's Read More, because otherwise the Joomla module helper will include ... 'default.php'
	// module template ! thus a Joomla override template header is allowed only if template header.php file exists locally too !!
	if ( file_exists(dirname(__FILE__).DS.'tmpl'.DS.$layout.DS.'footer.php') )
		require(JModuleHelper::getLayoutPath('mod_flexicontent', $layout.'/footer'));
	
	
	$mod_fc_run_times['rendering_template'] = $modfc_jprof->getmicrotime() - $mod_fc_run_times['rendering_template'];
	
	$task_lbls = array(
	'query_items'=>'Main DB Querying of Items ('.count($catdata_arr).' queries): %.2f secs',
	'query_items_sec'=>'Sec SQL Querying of Items ('.count($catdata_arr).' queries): %.2f secs',
	'empty_fields_filter'=>'Empty fields filter (skip items)): %.2f secs',
	'item_list_creation'=>'Item list creation (with custom field rendering): %.2f secs',
	'category_data_retrieval'=>'Category data retrieval: %.2f secs',
	'rendering_template'=>'Adding css/js & Rendering Template with item/category/etc data: %.2f secs'
	);
	
	// append performance stats to global variable
	if ( $flexiparams->get('print_logging_info') )
	{
		$modfc_jprof->mark('END: FLEXIcontent Module');
		$msg  = '<br/><br/>'.implode('<br/>', $modfc_jprof->getbuffer());
		$msg .= sprintf( '<code> <b><u>including</u></b>: <br/> -- Content Plugins: %.2f secs</code><br/>', $fc_content_plg_microtime/1000000);
		foreach ($mod_fc_run_times as $modtask => $modtime) {
			$msg .= '<code>'.sprintf( ' -- '.$task_lbls[$modtask].'<br/>', $modtime) .'</code>';
		}
		global $fc_performance_msg;
		$fc_performance_msg .= $msg;
	}
}
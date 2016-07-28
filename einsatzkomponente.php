<?php
/**
 * @package     Joomla.Plugin
 * @subpackage  Search.Einsatzkomponente
 *
 * @copyright   Copyright (C) 2016 - Julian Schäfer
 * @license     GNU/GPL Lizenz
 *******************************************************************************************************
 * Dieses Plugin erweitert die Standard-Joomlasuche mit Einsatzberichten aus der Einsatzkomponente V3.x
 *******************************************************************************************************
 */

// To prevent accessing the document directly
// no direct access
defined( '_JEXEC' ) or die( 'Restricted access' );


// Require the component's router file
require_once JPATH_SITE .  '/components/com_einsatzkomponente/router.php';

/*
 * All functions need to get wrapped in a class
 */
class plgSearchEinsatzkomponente extends JPlugin
{
	/**
	 * Constructor
	 *
	 * @access      protected
	 * @param       object  $subject The object to observe
	 * @param       array   $config  An array that holds the plugin configuration
	 * @since       1.6
	 */
	public function __construct(& $subject, $config)
	{
		parent::__construct($subject, $config);
		$this->loadLanguage();
	}

	// Define a function to return an array of search areas.
	// Note the value of the array key is normally a language string
	function onContentSearchAreas()
	{
		static $areas = array(
			'einsatzkomponente' => 'PLG_SEARCH_EINSATZKOMPONENTE_NAME_SEARCH_AREA'
		);
		return $areas;
	}
	 /**
	 * The sql must return the following fields that are used in a common display
	 * routine: href, title, section, created, text, browsernav
	 *
	 * @param string Target search string
	 * @param string mathcing option, exact|any|all
	 * @param string ordering option, newest|oldest|popular|alpha|category
	 * @param mixed An array if the search it to be restricted to areas, null if search all
	 */
	function onContentSearch( $text, $phrase='', $ordering='', $areas=null )
	{
		$db 	= JFactory::getDbo();
		$user	= JFactory::getUser();
		$groups	= implode(',', $user->getAuthorisedViewLevels());

		// If the array is not correct, return it:
		if (is_array( $areas )) {
			if (!array_intersect( $areas, array_keys( $this->onContentSearchAreas() ) )) {
				return array();
			}
		}

		// Now retrieve the plugin parameters :
		$openInWindow = $this->params->get('openInWindow', 1 );

		$showOrga = $this->params->get('showOrga', 0 );
		$showAddress = $this->params->get('showAddress', 0 );
		$showType = $this->params->get('showType', 0 );
		$showCat = $this->params->get('showCat', 0 );

		$searchAddress = $this->params->get('searchAddress', 0 );
		$searchBoss = $this->params->get('searchBoss', 0 );
		$searchBoss2 = $this->params->get('searchBoss2', 0 );
		$searchCat = $this->params->get('searchCat', 0 );
		$searchType = $this->params->get('searchType', 0 );

		// Use the PHP function trim to delete spaces in front of or at the back of the searching terms
		$text = trim( $text );

		// Return Array when nothing was filled in.
		if ($text == '') {
			return array();
		}

		// The database part
		$wheres = array();
		switch ($phrase) {

			// Search exact
			case 'exact':
				$text		= $db->Quote( '%'.$db->escape( $text, true ).'%', false );
				$wheres2 	= array();
				$wheres2[] 	= 'a.summary LIKE '.$text;
				$wheres2[] 	= 'a.desc LIKE '.$text;
				$wheres2[] 	= 'd.name LIKE '.$text;
				if($searchAddress == 1):$wheres2[] 	= 'a.address LIKE '.$text; endif;
				if($searchBoss == 1): 	$wheres2[] 	= 'a.boss LIKE '.$text; endif;
				if($searchBoss2 == 1):	$wheres2[] 	= 'a.boss2 LIKE '.$text; endif;
				if($searchCat == 1):  	$wheres2[] 	= 'b.title LIKE '.$text; endif;
				if($searchType == 1): 	$wheres2[] 	= 'c.title LIKE '.$text; endif;
				$where 		= '(' . implode( ') OR (', $wheres2 ) . ')';
				break;

			// Search all or any
			case 'all':
			case 'any':

			// Set default
			default:
				$words 	= explode( ' ', $text );
				$wheres = array();
				foreach ($words as $word)
				{
					$word		= $db->Quote( '%'.$db->escape( $word, true ).'%', false );
					$wheres2 	= array();
					$wheres2[] 	= 'LOWER(a.summary) LIKE '.$word;
					$wheres2[] 	= 'LOWER(a.desc) LIKE '.$word;
					$wheres2[] 	= 'LOWER(d.name) LIKE '.$word;
					if($searchAddress == 1): $wheres2[] = 'LOWER(a.address) LIKE '.$word; endif;
					if($searchBoss == 1): 	 $wheres2[] = 'LOWER(a.boss) LIKE '.$word; endif;
					if($searchBoss2 == 1): 	 $wheres2[] = 'LOWER(a.boss2) LIKE '.$word; endif;
					if($searchCat == 1):  	 $wheres2[] = 'LOWER(b.title) LIKE '.$word; endif;
					if($searchType == 1): 	 $wheres2[] = 'LOWER(c.title) LIKE '.$word; endif;
					$wheres[] 	= implode( ' OR ', $wheres2 );
				}
				$where = '(' . implode( ($phrase == 'all' ? ') AND (' : ') OR ('), $wheres ) . ')';
				break;
		}

		// Ordering of the results
		switch ( $ordering ) {

			//Alphabetic, ascending
			case 'alpha':
				$order = 'a.summary ASC';
				break;

			// Oldest first
			case 'oldest':
				$order = 'a.date1 ASC';
				break;

			// Popular first
			case 'popular':
				$order = 'a.counter DESC';
				break;

			// Newest first
			case 'newest':
				$order = 'a.date1 DESC';
				break;


			// Default setting: alphabetic, ascending
			default:
				$order = 'a.date1 ASC';
		}

		// Replace nameofplugin
		$section = JText::_( 'PLG_SEARCH_EINSATZKOMPONENTE_NAME_PLUGIN' );

		// The database query
		$query	= $db->getQuery(true);
		$query->select('a.id, a.data1, a.address, a.date1, a.counter, a.summary, a.desc, a.tickerkat, a.auswahl_orga, a.boss, a.boss2, b.title AS type, c.title AS category, d.name AS orga');
				$query->from('#__eiko_einsatzberichte AS a');
				$query->leftJoin('#__eiko_tickerkat as b ON b.id = a.tickerkat');
				$query->leftJoin('#__eiko_einsatzarten as c ON c.id = a.data1');
				$query->leftJoin('#__eiko_organisationen as d ON d.id = a.auswahl_orga');
				$query->where('('. $where .')' . 'AND a.state = 1' );
				$query->order($order);

		// Set query
		$db->setQuery($query);
		$rows = $db->loadObjectList();


		// The 'output' of the displayed link
		$params = JComponentHelper::getParams('com_einsatzkomponente');
		foreach($rows as $key => $row) {
			$rows[$key]->title = $row->summary;
				if($showOrga == 1 AND $showAddress == 0): $rows[$key]->title = $rows[$key]->title.' ('.$row->orga.')';
				elseif($showOrga == 0 AND $showAddress == 1): $rows[$key]->title = $rows[$key]->title.' ('.$row->address.')';
				elseif($showOrga == 1 AND $showAddress == 1): $rows[$key]->title = $rows[$key]->title.' ('.$row->orga.' | '.$row->address.')';
				endif;

			$rows[$key]->section = $section;
				if($showType == 1 AND $showCat == 0): $rows[$key]->section = $rows[$key]->section.' | '.$row->type.')';
				elseif($showType == 0 AND $showCat == 1): $rows[$key]->section = $rows[$key]->section.' | '.$row->category;
				elseif($showType == 1 AND $showCat == 1): $rows[$key]->section = $rows[$key]->section.' | '.$row->category.' | '.$row->type;
				endif;

			$rows[$key]->href = JRoute::_( JURI::root() . 'index.php?option=com_einsatzkomponente&view=einsatzbericht&id='.$row->id).'&Itemid='.$params->get('homelink','');
			$rows[$key]->created = $row->date1;
			$rows[$key]->text = $row->desc;

			if($openInWindow == 1){
				$rows[$key]->browsernav = 1;
			}
			else{
				$rows[$key]->browsernav = 0;
			}
		}

	//Return the search results in an array
	return $rows;
	}
}
?>


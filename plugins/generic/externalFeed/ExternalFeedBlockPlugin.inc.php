<?php

/**
 * @file plugins/generic/externalFeed/ExternalFeedBlockPlugin.inc.php
 *
 * Copyright (c) 2014-2017 Simon Fraser University
 * Copyright (c) 2003-2017 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class ExternalFeedBlockPlugin
 * @ingroup plugins_generic_externalFeed
 *
 * @brief Class for block component of external feed plugin
 */

import('lib.pkp.classes.plugins.BlockPlugin');

class ExternalFeedBlockPlugin extends BlockPlugin {
	/** @var string Name of parent plugin */
	var $parentPluginName;

	function __construct($parentPluginName) {
		$this->parentPluginName = $parentPluginName;
		parent::__construct();
	}

	/**
	 * Hide this plugin from the management interface (it's subsidiary)
	 */
	function getHideManagement() {
		return true;
	}

	/**
	 * Get the name of this plugin. The name must be unique within
	 * its category.
	 * @return String name of plugin
	 */
	function getName() {
		return 'ExternalFeedBlockPlugin';
	}

	/**
	 * Get the display name of this plugin.
	 * @return String
	 */
	function getDisplayName() {
		return __('plugins.generic.externalFeed.block.displayName');
	}

	/**
	 * Get a description of the plugin.
	 */
	function getDescription() {
		return __('plugins.generic.externalFeed.description');
	}

	/**
	 * Get the external feed plugin
	 * @return object
	 */
	function &getExternalFeedPlugin() {
		$plugin =& PluginRegistry::getPlugin('generic', $this->parentPluginName);
		return $plugin;
	}

	/**
	 * Override the builtin to get the correct plugin path.
	 * @return string
	 */
	function getPluginPath() {
		$plugin =& $this->getExternalFeedPlugin();
		return $plugin->getPluginPath();
	}

	/**
	 * Get the HTML contents for this block.
	 * @param $templateMgr object
	 * @param $request PKPRequest
	 * @return $string
	 */
	function getContents(&$templateMgr, $request = null) {
		$journal = $request->getJournal();
		if (!$journal) return '';

		$journalId = $journal->getId();
		$plugin =& $this->getExternalFeedPlugin();
		if (!$plugin->getEnabled()) return '';

		$requestedPage = $request->getRequestedPage();
		$externalFeedDao = DAORegistry::getDAO('ExternalFeedDAO');
		$plugin->import('simplepie.SimplePie');

		$feeds = $externalFeedDao->getExternalFeedsByJournalId($journal->getId());
		while ($currentFeed = $feeds->next()) {
			$displayBlock = $currentFeed->getDisplayBlock();
			if (($displayBlock == EXTERNAL_FEED_DISPLAY_BLOCK_NONE) ||
				(($displayBlock == EXTERNAL_FEED_DISPLAY_BLOCK_HOMEPAGE &&
				(!empty($requestedPage)) && $requestedPage != 'index'))
			) continue;

			$feed = new SimplePie();
			$feed->set_feed_url($currentFeed->getUrl());
			$feed->enable_order_by_date(false);
			$feed->set_cache_location(CacheManager::getFileCachePath());
			$feed->init();

			if ($currentFeed->getLimitItems()) {
				$recentItems = $currentFeed->getRecentItems();
			} else {
				$recentItems = 0;
			}

			$externalFeeds[] = array(
				'title' => $currentFeed->getLocalizedTitle(),
				'items' => $feed->get_items(0, $recentItems)
			);
		}

		if (!isset($externalFeeds)) return '';

		$templateMgr->assign('externalFeeds', $externalFeeds);
		return parent::getContents($templateMgr, $request);
	}
}

?>

<?php 

/**
 * @file classes/repositories/IssueRepository.php
 *
 * Copyright (c) 2014-2017 Simon Fraser University
 * Copyright (c) 2000-2017 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @interface IssueRepository
 * @ingroup repositories
 *
 * @brief Issue repository implementation
 */

namespace OJS\Repositories;

use \Issue;
use \Journal;
use \DAORegistry;
use \ServicesContainer;

class IssueRepository implements IssueRepositoryInterface {

	/**
	 * @copy IssueRepositoryInterface::validate()
	 */
	public function validate($issueData) {

		// issue creation rule
		if (!isset($issueData['id'])) {
			if (!$issueData['showVolume'] && !$issueData['showYear'] && !$issueData['showNumber'] && !$issueData['showTitle']) {
				throw new Exceptions\ValidationException(__('editor.issues.issueIdentificationRequired'));
			}
		}

		if ($issueData['showVolume'] && !$issueData['volume']) {
			throw new Exceptions\ValidationException(__('editor.issues.volumeRequired'));
		}

		if ($issueData['showYear'] && !$issueData['year']) {
			throw new Exceptions\ValidationException(__('editor.issues.yearRequired'));
		}

		if ($issueData['showNumber'] && empty($issueData['number'])) {
			throw new Exceptions\ValidationException(__('editor.issues.numberRequired'));
		}

		if ($issueData['showTitle'] && empty($issueData['title'])) {
			throw new Exceptions\ValidationException(__('editor.issues.titleRequired'));
		}

		if (!empty($issueData['title']) && !is_array($issueData['title'])) {
			throw new Exceptions\ValidationException('A locale must be specified for the Title field.');
		}

		if (!empty($issueData['description']) && !is_array($issueData['description'])) {
			throw new Exceptions\ValidationException('A locale must be specified for the Description field.');
		}

		return true;
	}

	/**
	 * Returns an array of default data for issue initialization
	 * @param $issue \Issue
	 * @return array
	 */
	protected function getDefaultIssueData(\Issue $issue = null) {
		return array(
			'showVolume'	=> !is_null($issue) ? $issue->getShowVolume() : false,
			'volume'	=> !is_null($issue) ? $issue->getVolume() : 0,
			'showYear'	=> !is_null($issue) ? $issue->getShowYear() : false,
			'year'		=> !is_null($issue) ? $issue->getYear() : 0,
			'showNumber'	=> !is_null($issue) ? $issue->getShowNumber() : false,
			'number'	=> !is_null($issue) ? $issue->getNumber() : '',
			'showTitle'	=> !is_null($issue) ? $issue->getShowTitle() : false,
			'title'		=> !is_null($issue) ? $issue->getTitle(null) : '',
			'description'	=> !is_null($issue) ? $issue->getDescription(null) : '',
		);
	}

	/**
	 * Helper function to store issue information
	 * @param \Issue $issue
	 * @param array $issueData
	 */
	protected function storeIssueData($issue, $issueData) {
		$issue->setTitle($issueData['title'], null);
		$issue->setVolume($issueData['volume']);
		$issue->setNumber($issueData['number']);
		$issue->setYear($issueData['year']);
		$issue->setDescription($issueData['description'], null);
		$issue->setShowVolume($issueData['showVolume']);
		$issue->setShowNumber($issueData['showNumber']);
		$issue->setShowYear($issueData['showYear']);
		$issue->setShowTitle($issueData['showTitle']);
		$issue->setAccessStatus(isset($issueData['accessStatus']) ? $issueData['accessStatus'] : ISSUE_ACCESS_OPEN);
		
		if (isset($issueData['enableOpenAccessDate'])) {
			$issue->setOpenAccessDate($issueData['enableOpenAccessDate']);
		}
		else {
			$issue->setOpenAccessDate(null);
		}
	}

	/**
	 * @copy IssueRepositoryInterface::create()
	 */
	public function create($journal, $issueData) {
		
		$issueDataDefault = $this->getDefaultIssueData();
		$issueDataDefault['accessStatus'] = ServicesContainer::instance()->get('issue')->determineAccessStatus($journal);
		
		$issueData = array_merge($issueDataDefault, $issueData);
		$this->validate($issueData);
		
		$issueDao = DAORegistry::getDAO('IssueDAO');
		$issue = $issueDao->newDataObject();
		
		$issue->setJournalId($journal->getId());
		$this->storeIssueData($issue, $issueData);
		
		$issue->setPublished(0);
		$issue->setCurrent(0);
		
		$issueDao->insertObject($issue);
		
		return $issue;
	}

	/**
	 * @copy IssueRepositoryInterface::update()
	 */
	public function update(Issue $issue, $issueData) {
		$issueDataDefault = $this->getDefaultIssueData($issue);
		$issueData = array_merge($issueDataDefault, $issueData);
		if (!isset($issueData['id'])) {
			$issueData['id'] = $issue->getId();
		}
		
		$this->validate($issueData);
		$this->storeIssueData($issue, $issueData);
		
		$issueDao = \DAORegistry::getDAO('IssueDAO');
		$issueDao->updateObject($issue);
		
		return $issue;
	}

	/**
	 * @copy IssueRepositoryInterface::delete()
	 */
	public function delete(Issue $issue, Journal $journal) {
		// remove all published articles and return original articles to editing queue
		$articleDao = DAORegistry::getDAO('ArticleDAO');
		$publishedArticleDao = DAORegistry::getDAO('PublishedArticleDAO');
		$publishedArticles = $publishedArticleDao->getPublishedArticles($issue->getId());

		$isBackIssue = $issue->getPublished() > 0 ? true: false;
		if (isset($publishedArticles) && !empty($publishedArticles)) {
			// Insert article tombstone if the issue is published
			import('classes.article.ArticleTombstoneManager');
			$articleTombstoneManager = new ArticleTombstoneManager();
			foreach ($publishedArticles as $article) {
				if ($isBackIssue) {
					$articleTombstoneManager->insertArticleTombstone($article, $journal);
				}
				$articleDao->changeStatus($article->getId(), STATUS_QUEUED);
				$publishedArticleDao->deletePublishedArticleById($article->getPublishedArticleId());
			}
		}

		$issueDao = DAORegistry::getDAO('IssueDAO');
		$issueDao->deleteObject($issue);
		if ($issue->getCurrent()) {
			$issues = $issueDao->getPublishedIssues($journal->getId());
			if (!$issues->eof()) {
				$issue = $issues->next();
				$issue->setCurrent(1);
				$issueDao->updateObject($issue);
			}
		}
		
		return true;
	}

	/**
	 * @copy IssueRepositoryInterface::publish()
	 */
	public function publish(Issue $issue, Journal $journal) {
		$journalId = $journal->getId();
		$articleSearchIndex = null;
		if (!$issue->getPublished()) {
			
		}
	}
}
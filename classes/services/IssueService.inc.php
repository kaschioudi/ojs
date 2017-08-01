<?php

/**
 * @file classes/services/IssueService.php
*
* Copyright (c) 2014-2017 Simon Fraser University
* Copyright (c) 2000-2017 John Willinsky
* Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
*
* @class IssueService
* @ingroup services
*
* @brief Helper class that encapsulates issue business logic
*/

namespace OJS\Services;

use \Journal;

class IssueService {

	/**
	 * Determine if a user can access galleys for a specific issue
	 *
	 * @param \Journal $journal
	 * @param \Issue $issue
	 *
	 * @return boolean
	 */
	public function userHasAccessToGalleys(Journal $journal, \Issue $issue) {
		import('classes.issue.IssueAction');
		$issueAction = new \IssueAction();

		$subscriptionRequired = $issueAction->subscriptionRequired($issue, $journal);
		$subscribedUser = $issueAction->subscribedUser($journal, $issue);
		$subscribedDomain = $issueAction->subscribedDomain($journal, $issue);

		return !$subscriptionRequired || $issue->getAccessStatus() == ISSUE_ACCESS_OPEN || $subscribedUser || $subscribedDomain;
	}

	/**
	 * Determine issue access status based on journal publishing mode
	 * @param \Journal $journal
	 *
	 * @return int
	 */
	public function determineAccessStatus(Journal $journal) {
		import('classes.issue.Issue');
		$accessStatus = null;

		switch ($journal->getSetting('publishingMode')) {
			case PUBLISHING_MODE_SUBSCRIPTION:
			case PUBLISHING_MODE_NONE:
				$accessStatus = ISSUE_ACCESS_SUBSCRIPTION;
				break;
			case PUBLISHING_MODE_OPEN:
			default:
				$accessStatus = ISSUE_ACCESS_OPEN;
				break;
		}

		return $accessStatus;
	}
}

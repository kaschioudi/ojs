<?php

/**
 * SectionEditorHandler.inc.php
 *
 * Copyright (c) 2003-2004 The Public Knowledge Project
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @package pages.sectionEditor
 *
 * Handle requests for section editor functions. 
 *
 * $Id$
 */

import('pages.sectionEditor.TrackSubmissionHandler');

class SectionEditorHandler extends Handler {

	/**
	 * Display section editor index page.
	 */
	function index() {
		SectionEditorHandler::validate();
		SectionEditorHandler::setupTemplate();
		
		$templateMgr = &TemplateManager::getManager();
		$templateMgr->display('sectionEditor/index.tpl');
	}

	/**
	 * Validate that user is a section editor in the selected journal.
	 * Redirects to user index page if not properly authenticated.
	 */
	function validate() {
		parent::validate();
		$journal = &Request::getJournal();
		if (!isset($journal) || !Validation::isSectionEditor($journal->getJournalId())) {
			Request::redirect('user');
		}
	}
	
	/**
	 * Setup common template variables.
	 * @param $subclass boolean set to true if caller is below this handler in the hierarchy
	 */
	function setupTemplate($subclass = false) {
		if (Request::getRequestedPage() == 'editor') {
			EditorHandler::setupTemplate($subclass);
			
		} else {
			$templateMgr = &TemplateManager::getManager();
			$templateMgr->assign('pageHierarchy',
				$subclass ? array(array('user', 'navigation.user'), array('sectionEditor', 'sectionEditor.journalSectionEditor'))
					: array(array('user', 'navigation.user'))
			);
			$templateMgr->assign('pagePath', '/user/sectionEditor');
		}
	}
	
	//
	// Submission Tracking
	//

	function assignments($args) {
		TrackSubmissionHandler::assignments($args);
	}
	
	function summary($args) {
		TrackSubmissionHandler::summary($args);
	}
	
	function submission($args) {
		TrackSubmissionHandler::submission($args);
	}

	function submissionReview($args) {
		TrackSubmissionHandler::submissionReview($args);
	}
	
	function submissionEditing($args) {
		TrackSubmissionHandler::submissionEditing($args);
	}
	
	function submissionHistory($args) {
		TrackSubmissionHandler::submissionHistory($args);
	}
	
	function designateReviewVersion() {
		TrackSubmissionHandler::designateReviewVersion();
	}
		
	function changeSection() {
		TrackSubmissionHandler::changeSection();
	}
	
	function recordDecision() {
		TrackSubmissionHandler::recordDecision();
	}
	
	function selectReviewer($args) {
		TrackSubmissionHandler::selectReviewer($args);
	}
	
	function notifyReviewer($args) {
		TrackSubmissionHandler::notifyReviewer($args);
	}
	
	function initiateReview() {
		TrackSubmissionHandler::initiateReview();
	}
	
	function reinitiateReview() {
		TrackSubmissionHandler::reinitiateReview();
	}
	
	function initiateAllReviews() {
		TrackSubmissionHandler::initiateAllReviews();
	}
	
	function cancelReview() {
		TrackSubmissionHandler::cancelReview();
	}
	
	function removeReview() {
		TrackSubmissionHandler::removeReview();
	}

	function remindReviewer($args) {
		TrackSubmissionHandler::remindReviewer($args);
	}

	function replaceReviewer($args) {
		TrackSubmissionHandler::replaceReviewer($args);
	}
	
	function thankReviewer($args) {
		TrackSubmissionHandler::thankReviewer($args);
	}
	
	function rateReviewer() {
		TrackSubmissionHandler::rateReviewer();
	}
	
	function makeReviewerFileViewable() {
		TrackSubmissionHandler::makeReviewerFileViewable();
	}
	
	function setDueDate($args) {
		TrackSubmissionHandler::setDueDate($args);
	}
	
	function enterReviewerRecommendation($args) {
		TrackSubmissionHandler::enterReviewerRecommendation($args);
	}
	
	function viewMetadata($args) {
		TrackSubmissionHandler::viewMetadata($args);
	}
	
	function saveMetadata() {
		TrackSubmissionHandler::saveMetadata();
	}

	function editorReview() {
		TrackSubmissionHandler::editorReview();
	}

	function notifyAuthor($args) {
		TrackSubmissionHandler::notifyAuthor($args);
	}

	function selectCopyeditor($args) {
		TrackSubmissionHandler::selectCopyeditor($args);
	}
	
	function replaceCopyeditor($args) {
		TrackSubmissionHandler::replaceCopyeditor($args);
	}
	
	function notifyCopyeditor($args) {
		TrackSubmissionHandler::notifyCopyeditor($args);
	}
	
	function initiateCopyedit() {
		TrackSubmissionHandler::initiateCopyedit();
	}
	
	function thankCopyeditor($args) {
		TrackSubmissionHandler::thankCopyeditor($args);
	}

	function notifyAuthorCopyedit($args) {
		TrackSubmissionHandler::notifyAuthorCopyedit($args);
	}
	
	function thankAuthorCopyedit($args) {
		TrackSubmissionHandler::thankAuthorCopyedit($args);
	}
	
	function notifyFinalCopyedit($args) {
		TrackSubmissionHandler::notifyFinalCopyedit($args);
	}
	
	function thankFinalCopyedit($args) {
		TrackSubmissionHandler::thankFinalCopyedit($args);
	}
	
	function selectCopyeditRevisions() {
		TrackSubmissionHandler::selectCopyeditRevisions();
	}
	
	function uploadReviewVersion() {
		TrackSubmissionHandler::uploadReviewVersion();
	}
	
	function uploadCopyeditVersion() {
		TrackSubmissionHandler::uploadCopyeditVersion();
	}

	function addSuppFile($args) {
		TrackSubmissionHandler::addSuppFile($args);
	}
	
	function saveSuppFile($args) {
		TrackSubmissionHandler::saveSuppFile($args);
	}
	
	function archiveSubmission() {
		TrackSubmissionHandler::archiveSubmission();
	}

	function restoreToQueue() {
		TrackSubmissionHandler::restoreToQueue();
	}

	function downloadFile($args) {
		TrackSubmissionHandler::downloadFile($args);
	}

	function submissionEventLog($args) {
		TrackSubmissionHandler::submissionEventLog($args);
	}
	
	//
	// Submission Notes Functions
	//
	function addSubmissionNote() {
		TrackSubmissionHandler::addSubmissionNote();
	}

	function removeSubmissionNote() {
		TrackSubmissionHandler::removeSubmissionNote();
	}		

	function updateSubmissionNote() {
		TrackSubmissionHandler::updateSubmissionNote();
	}

	function clearAllSubmissionNotes() {
		TrackSubmissionHandler::clearAllSubmissionNotes();
	}

	function submissionNotes($args) {
		TrackSubmissionHandler::submissionNotes($args);
	}		

	function submissionEventLogType($args) {
		TrackSubmissionHandler::submissionEventLogType($args);
	}
	
	function clearSubmissionEventLog($args) {
		TrackSubmissionHandler::clearSubmissionEventLog($args);
	}
	
	function submissionEmailLog($args) {
		TrackSubmissionHandler::submissionEmailLog($args);
	}
	
	function submissionEmailLogType($args) {
		TrackSubmissionHandler::submissionEmailLogType($args);
	}
	
	function clearSubmissionEmailLog($args) {
		TrackSubmissionHandler::clearSubmissionEmailLog($args);
	}
}

?>

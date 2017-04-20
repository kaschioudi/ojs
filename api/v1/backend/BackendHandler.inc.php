<?php

/**
 * @file api/v1/backend/BackendHandler.inc.php
 *
 * Copyright (c) 2014-2016 Simon Fraser University Library
 * Copyright (c) 2003-2016 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class BackendHandler
 * @ingroup api_v1_backend
 *
 * @brief Handle API requests for backend operations.
 *
 */

import('lib.pkp.classes.handler.APIHandler');

class BackendHandler extends APIHandler {
	
	/**
	 * Constructor
	 */
	public function __construct() {
		$rootPattern = '/{contextPath}/api/{version}/backend';
		$this->_endpoints = array(
			'GET' => array(
				array(
					'pattern' => "{$rootPattern}/submissions/list",
					'handler' => array($this, 'getSubmissionList'),
					'roles' => array(
						ROLE_ID_SITE_ADMIN,
						ROLE_ID_MANAGER,
						ROLE_ID_SUB_EDITOR,
						ROLE_ID_AUTHOR,
						ROLE_ID_REVIEWER,
						ROLE_ID_ASSISTANT,
					),
				),
			),
		);
		parent::__construct();
	}
	
	/**
	 * Get submission list
	 * 
	 * @param $slimRequest Request Slim request object
	 * @param $response Response object
	 * @param array $args arguments
	 * 
	 * @return Response
	 */
	public function getSubmissionList($slimRequest, $response, $args) {
		$request = $this->getRequest();
		$currentUser = $request->getUser();
		$context = $request->getContext();
		$contextId = $context->getId();
		
		$assignee = null;
		if (!$currentUser->hasRole(array(ROLE_ID_MANAGER, ROLE_ID_SITE_ADMIN), $context->getId())) {
			$assignee = $currentUser->getId();
		}
		
		if (($param = $this->getParameter('assignedTo')) != null) {
			$assignee = $param;
		}
		
		// Only journal managers and admins can access unassigned submissions
		if (($this->getParameter('unassigned') != null) && 
				$currentUser->hasRole(array(ROLE_ID_MANAGER, ROLE_ID_SITE_ADMIN), $context->getId())) {
			$assignee = -1;
		}
		
		$orderColumn = 'id';
		if ((($param = $this->getParameter('orderBy')) != null) && in_array($param, array('id', 'dateSubmitted', 'lastModified'))) {
			$orderColumn = $param;	
		}
		
		$orderDirection = ($this->getParameter('order') == 'ASC') ? $this->getParameter('order') : 'DESC';
		
		$statuses = null;
		if (($param = $this->getParameter('status')) != null) {
			if (strpos($param, ',') > -1) {
				$statuses = explode(',', $param);
			}
			else {
				$statuses = array($param);
			}
			
		}
		
		$searchPhrase = $this->getParameter('searchPhrase');
		
		// Enforce a maximum count to prevent the API from crippling the server
		$count = !is_null($count = $this->getParameter('count')) ? min(20, $count) : 20;
		$page = !is_null($page = $this->getParameter('page')) ? $page : 1;
		
		// Prevent users from viewing submissions they're not assigned to,
		// except for journal managers and admins.
		if (!$currentUser->hasRole(array(ROLE_ID_MANAGER, ROLE_ID_SITE_ADMIN), $context->getId())
				&& $params['assignedTo'] != $currentUser->getId()) {
			return $response->withStatus(403)->withJsonError('api.submissions.403.requestedOthersUnpublishedSubmissions');
		}
		
		import('lib.pkp.classes.core.ServicesContainer');
		$sContainer = ServicesContainer::instance();
		
		$submissionService = $sContainer->get('submission');
		$submissions = $submissionService->retrieveSubmissionList(
			$contextId, $orderColumn, $orderDirection, $assignee, $statuses, $searchPhrase, $count, $page
		);
		
		return $response->withJson($submissions);
	}
}
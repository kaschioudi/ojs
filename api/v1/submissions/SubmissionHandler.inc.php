<?php

/**
 * @file api/v1/submission/SubmissionHandler.inc.php
 *
 * Copyright (c) 2014-2016 Simon Fraser University Library
 * Copyright (c) 2003-2016 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class SubmissionHandler
 * @ingroup api_v1_submission
 *
 * @brief Handle API requests for submission operations.
 *
 */

import('lib.pkp.classes.handler.APIHandler');
import('lib.pkp.classes.core.ServicesContainer');

class SubmissionHandler extends APIHandler {
	/**
	 * Constructor
	 */
	public function __construct() {
		$roles = array(ROLE_ID_MANAGER, ROLE_ID_SUB_EDITOR, ROLE_ID_ASSISTANT, ROLE_ID_REVIEWER, ROLE_ID_AUTHOR);
		$rootPattern = '/{contextPath}/api/{version}/submissions';
		$this->_endpoints = array(
			'GET' => array (
				array(
					'pattern' => "{$rootPattern}/{submissionId}/files",
					'handler' => array($this,'getFiles'),
					'roles' => $roles
				),
				array(
					'pattern' => "{$rootPattern}/{submissionId}",
					'handler' => array($this,'submissionMetadata'),
					'roles' => $roles
				),
			)
		);
		parent::__construct();
	}

	//
	// Implement methods from PKPHandler
	//
	function authorize($request, &$args, $roleAssignments) {
		$routeName = null;
		$slimRequest = $this->getSlimRequest();

		if (!is_null($slimRequest) && ($route = $slimRequest->getAttribute('route'))) {
			$routeName = $route->getName();
		}

		if (in_array($routeName, array('getFiles', 'submissionMetadata'))) {
			import('lib.pkp.classes.security.authorization.SubmissionAccessPolicy');
			$this->addPolicy(new SubmissionAccessPolicy($request, $args, $roleAssignments));
		}

		if ($routeName == 'getFiles') {
			$stageId = $slimRequest->getQueryParam('stageId', WORKFLOW_STAGE_ID_SUBMISSION);
			import('lib.pkp.classes.security.authorization.WorkflowStageAccessPolicy');
			$this->addPolicy(new WorkflowStageAccessPolicy($request, $args, $roleAssignments, 'submissionId', $stageId));
		}

		return parent::authorize($request, $args, $roleAssignments);
	}

	/**
	 * Retrieve submission file list
	 *
	 * @param $slimRequest Request Slim request object
	 * @param $response Response object
	 * @param array $args arguments
	 * @return Response
	 */
	public function getFiles($slimRequest, $response, $args) {
		$request = $this->getRequest();
		$context = $request->getContext();
		$data = array();

		$sContainer = ServicesContainer::instance();
		$submissionService = $sContainer->get('submission');

		try {
			$submissionId = $this->getParameter('submissionId');
			$fileStage = $slimRequest->getQueryParam('fileStage', null);
			$submissionFiles = $submissionService->getFiles($context->getId(), $submissionId, $fileStage);
			foreach ($submissionFiles as $submissionFile) {
				$data[] = array(
					'fileId'			=> $submissionFile->getFileId(),
					'revision'			=> $submissionFile->getRevision(),
					'submissionId'		=> $submissionFile->getSubmissionId(),
					'filename'			=> $submissionFile->getName(),
					'fileLabel'			=> $submissionFile->getFileLabel(),
					'fileStage'			=> $submissionFile->getFileStage(),
					'uploaderUserId'	=> $submissionFile->getUploaderUserId(),
					'userGroupId'		=> $submissionFile->getUserGroupId()
				);
			}
		}
		catch (App\Services\Exceptions\InvalidSubmissionException $e) {
			return $response->withStatus(404)->withJsonError('api.submissions.404.invalidSubmission');
		}

		return $response->withJson($data, 200);
	}

	/**
	 * Get submission metadata
	 * @param $slimRequest Request Slim request object
	 * @param $response Response object
	 * @param array $args arguments
	 * @return Response
	 */
	public function submissionMetadata($slimRequest, $response, $args) {
		$request = $this->_request;
		$submission = $this->getAuthorizedContextObject(ASSOC_TYPE_SUBMISSION);
		assert($submission);

		$queryParams = $slimRequest->getQueryParams();
		$format = isset($queryParams['format'])?$queryParams['format']:'';
		import('plugins.metadata.dc11.schema.Dc11Schema');
		if ($format == 'dc11' || $format == '') {
			$schema = new Dc11Schema();
			return $this->getMetadaJSON($submission, $schema);
		}
		import('plugins.metadata.mods34.schema.Mods34Schema');
		if ($format == 'mods34') {
			$schema = new Mods34Schema();
			return $this->getMetadaJSON($submission, $schema);
		}
	}

	function getMetadaJSON($submission, $schema) {
		$metadata = array();
		$dcDescription = $submission->extractMetadata($schema);
		foreach ($dcDescription->getProperties() as $propertyName => $property) {
			if ($dcDescription->hasStatement($propertyName)) {
				if ($property->getTranslated()) {
					$values = $dcDescription->getStatementTranslations($propertyName);
				} else {
					$values = $dcDescription->getStatement($propertyName);
				}
				$metadata[$propertyName][] = $values;
			}
		}
		return json_encode($metadata);
	}
}

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

class SubmissionHandler extends APIHandler {
	/**
	 * Constructor
	 */
	public function SubmissionHandler() {
		parent::APIHandler();
		$app = $this->getApp();
		$app->get('/{contextPath}/api/{version}/submissions/{submissionId}/files/{fileId}', array($this, 'getFile'));
		$app->get('/{contextPath}/api/{version}/submissions/{submissionId}', array($this, 'submissionMetadata'));

		$this->addRoleAssignment(
			array(ROLE_ID_MANAGER, ROLE_ID_SUB_EDITOR, ROLE_ID_ASSISTANT, ROLE_ID_REVIEWER, ROLE_ID_AUTHOR),
			array('getFile', 'submissionMetadata')
		);
	}

	//
	// Implement methods from PKPHandler
	//
	function authorize($request, &$args, $roleAssignments) {
		//import('lib.pkp.classes.security.authorization.SubmissionFileAccessPolicy');
		//$this->addPolicy(new SubmissionFileAccessPolicy($request, $args, $roleAssignments, SUBMISSION_FILE_ACCESS_READ));
		import('lib.pkp.classes.security.authorization.SubmissionAccessPolicy');
		$this->addPolicy(new SubmissionAccessPolicy($request, $args, $roleAssignments));
		return parent::authorize($request, $args, $roleAssignments);
	}

	/**
	 * Get the entity ID for a specified parameter name.
	 * (Parameter names are generally defined in authorization policies
	 * @return int|string?
	 */
	public function getEntityId($parameterName) {
		switch ($parameterName) {
			case 'submissionId':
				$parts = explode('/', trim($_SERVER['PATH_INFO'], '/'));
				return $parts[4];
				break;
		}
		return parent::getEntityId($parameterName);
	}


	//
	// Public handler methods
	//
	/**
	 * Handle file download
	 * @param $slimRequest Request Slim request object
	 * @param $response Response object
	 * @param array $args arguments
	 * @return Response
	 */
	public function getFile($slimRequest, $response, $args) {
		//$fileId = $slimRequest->getAttribute('fileId');
		//$response->getBody()->write("Serving file with id: {$fileId}");
		//return $response;
		$request = $this->_request;
		$submissionFile = $this->getAuthorizedContextObject(ASSOC_TYPE_SUBMISSION_FILE);
		assert($submissionFile); // Should have been validated already
		$context = $request->getContext();
		$fileManager = new SubmissionFileManager($context->getId(), $submissionFile->getSubmissionId());
		if (!$fileManager->downloadFile($submissionFile->getFileId(), $submissionFile->getRevision(), false, $submissionFile->getClientFileName())) {
			error_log('FileApiHandler: File ' . $submissionFile->getFilePath() . ' does not exist or is not readable!');
			header('HTTP/1.0 500 Internal Server Error');
			fatalError('500 Internal Server Error');
		}
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

		import('plugins.metadata.dc11.schema.Dc11Schema');
		$metadata = array();
		$dcDescription = $submission->extractMetadata(new Dc11Schema());
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

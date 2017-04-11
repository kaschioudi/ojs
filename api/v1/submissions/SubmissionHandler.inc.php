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
	public function __construct() {
		$roles = array(ROLE_ID_MANAGER, ROLE_ID_SUB_EDITOR, ROLE_ID_ASSISTANT, ROLE_ID_REVIEWER, ROLE_ID_AUTHOR);
		$this->_endpoints = array(
			'GET' => array (
				array(
					'pattern' => '/{contextPath}/api/{version}/submissions/{submissionId}/files',
					'handler' => array($this,'getFile'),
					'roles' => $roles
				),
				array(
					'pattern' => '/{contextPath}/api/{version}/submissions/{submissionId}',
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

		if (in_array($routeName, array('getFile', 'submissionMetadata'))) {
			import('lib.pkp.classes.security.authorization.SubmissionAccessPolicy');
			$this->addPolicy(new SubmissionAccessPolicy($request, $args, $roleAssignments));
		}

		if ($routeName == 'getFile') {
			import('lib.pkp.classes.security.authorization.SubmissionFileAccessPolicy');
			$this->addPolicy(new SubmissionFileAccessPolicy($request, $args, $roleAssignments, SUBMISSION_FILE_ACCESS_READ));
		}

		return parent::authorize($request, $args, $roleAssignments);
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
		$request = $this->_request;
		$submissionFile = $this->getAuthorizedContextObject(ASSOC_TYPE_SUBMISSION_FILE);
		assert($submissionFile); // Should have been validated already
		$context = $request->getContext();
		import('lib.pkp.classes.file.SubmissionFileManager');
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

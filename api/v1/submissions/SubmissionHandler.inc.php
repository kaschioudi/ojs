<?php

/**
 * @file api/v1/submission/SubmissionHandler.inc.php
 *
 * Copyright (c) 2014-2017 Simon Fraser University
 * Copyright (c) 2003-2017 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class SubmissionHandler
 * @ingroup api_v1_submission
 *
 * @brief Handle API requests for submission operations.
 *
 */

import('lib.pkp.classes.handler.APIHandler');
import('classes.core.ServicesContainer');

class SubmissionHandler extends APIHandler {

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->_handlerPath = 'submissions';
		$roles = array(ROLE_ID_MANAGER, ROLE_ID_SUB_EDITOR, ROLE_ID_ASSISTANT, ROLE_ID_REVIEWER, ROLE_ID_AUTHOR);
		$this->_endpoints = array(
			'GET' => array (
				array(
					'pattern' => $this->getEndpointPattern() . '/{submissionId}/files',
					'handler' => array($this,'getFiles'),
					'roles' => $roles
				),
				array(
					'pattern' => $this->getEndpointPattern() . '/{submissionId}',
					'handler' => array($this,'getSubmission'),
					'roles' => $roles
				),
				array(
					'pattern' => $this->getEndpointPattern() . '/{submissionId}/participants',
					'handler' => array($this,'getParticipants'),
					'roles' => array(ROLE_ID_ASSISTANT, ROLE_ID_MANAGER, ROLE_ID_SUB_EDITOR)		// as per StageParticipantGridHandler::__construct()
				),
				array(
					'pattern' => $this->getEndpointPattern() . '/{submissionId}/galleys',
					'handler' => array($this,'getGalleys'),
					'roles' => $roles
				),
			),
			'POST' => array(
				array(
					'pattern' => $this->getEndpointPattern() . '/{submissionId}/files',
					'handler' => array($this,'postFile'),
					'roles' => $roles
				)
			),
			'PUT' => array(
				array(
					'pattern' => $this->getEndpointPattern() . '/{submissionId}/files/{fileId}',
					'handler' => array($this,'editFileMetadata'),
					'roles' => $roles
				)
			),
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

		import('lib.pkp.classes.security.authorization.SubmissionAccessPolicy');
		$this->addPolicy(new SubmissionAccessPolicy($request, $args, $roleAssignments));

		if (in_array($routeName, array('getFiles','getParticipants'))) {
			$stageId = $slimRequest->getQueryParam('stageId', WORKFLOW_STAGE_ID_SUBMISSION);
			import('lib.pkp.classes.security.authorization.WorkflowStageAccessPolicy');
			$this->addPolicy(new WorkflowStageAccessPolicy($request, $args, $roleAssignments, 'submissionId', $stageId));
		}

		if ($routeName == 'getGalleys') {
			import('lib.pkp.classes.security.authorization.WorkflowStageAccessPolicy');
			$this->addPolicy(new WorkflowStageAccessPolicy($request, $args, $roleAssignments, 'submissionId', WORKFLOW_STAGE_ID_PRODUCTION));
		}

		if ($routeName == 'postFile') {
			import('controllers.wizard.fileUpload.FileUploadWizardHandler');
			$fileUploadWizardHandler = new FileUploadWizardHandler();
			$authorize = $fileUploadWizardHandler->authorize($request, $args, $roleAssignments);
			if (!$authorize) {
				return $authorize;
			}
		}

		if ($routeName == 'editFileMetadata') {
			import('lib.pkp.classes.security.authorization.SubmissionFileAccessPolicy');
			$this->addPolicy(new SubmissionFileAccessPolicy($request, $args, $roleAssignments));
		}

		return parent::authorize($request, $args, $roleAssignments);
	}

	/**
	 * Helper function that returns submission file metadata
	 * @param \SubmissionFile $submissionFile
	 */
	protected function buildSubmissionFileDataEntry($submissionFile) {
		$entry = array(
			'fileId'           => $submissionFile->getFileId(),
			'revision'         => $submissionFile->getRevision(),
			'submissionId'     => $submissionFile->getSubmissionId(),
			'filename'         => $submissionFile->getName(),
			'fileLabel'        => $submissionFile->getFileLabel(),
			'fileStage'        => $submissionFile->getFileStage(),
			'uploaderUserId'   => $submissionFile->getUploaderUserId(),
			'userGroupId'      => $submissionFile->getUserGroupId()
		);
		if (is_a($submissionFile, 'SupplementaryFile')) {
			$entry['metadata'] = array(
				'description'   => $submissionFile->getDescription(null),
				'creator'       => $submissionFile->getCreator(null),
				'publisher'     => $submissionFile->getPublisher(null),
				'source'        => $submissionFile->getSource(null),
				'subject'       => $submissionFile->getSubject(null),
				'sponsor'       => $submissionFile->getSponsor(null),
				'dateCreated'   => $submissionFile->getDateCreated(null),
				'language'      => $submissionFile->getLanguage(),
			);
		}
		if (is_a($submissionFile, 'SubmissionArtworkFile')) {
			$entry['metadata'] = array(
				'caption'               => $submissionFile->getCaption(),
				'credit'                => $submissionFile->getCredit(),
				'copyrightOwner'        => $submissionFile->getCopyrightOwner(),
				'CopyrightOwnerContact' => $submissionFile->getCopyrightOwnerContactDetails(),
				'permissionTerms'       => $submissionFile->getPermissionTerms(),
			);
		}
		return $entry;
	}

	/**
	 * Retrieve submission file list
	 *
	 * @param $slimRequest Request Slim request object
	 * @param $response Response object
	 * @param array $args arguments
	 *
	 * @return Response
	 */
	public function getFiles($slimRequest, $response, $args) {
		$request = $this->getRequest();
		$context = $request->getContext();
		$data = array();

		$sContainer = ServicesContainer::instance();
		$submissionService = $sContainer->get('submission');

		try {
			$submission = $this->getAuthorizedContextObject(ASSOC_TYPE_SUBMISSION);
			$fileStage = $slimRequest->getQueryParam('fileStage');
			$submissionFiles = $submissionService->getFiles($context->getId(), $submission, $fileStage);
			foreach ($submissionFiles as $submissionFile) {
				$data[] = $this->buildSubmissionFileDataEntry($submissionFile);
			}
		}
		catch (PKP\Services\Exceptions\InvalidSubmissionException $e) {
			return $response->withStatus(404)->withJsonError('api.submissions.404.resourceNotFound');
		}

		return $response->withJson($data, 200);
	}

	/**
	 * Retrieve participant list by stage
	 *
	 * @param $slimRequest Request Slim request object
	 * @param $response Response object
	 * @param array $args arguments
	 *
	 * @return Response
	 */
	public function getParticipants($slimRequest, $response, $args) {
		$request = $this->getRequest();
		$context = $request->getContext();
		$data = array();

		$sContainer = ServicesContainer::instance();
		$submissionService = $sContainer->get('submission');

		$submission = $this->getAuthorizedContextObject(ASSOC_TYPE_SUBMISSION);
		$stageId = $slimRequest->getQueryParam('stageId', WORKFLOW_STAGE_ID_SUBMISSION);
		$data = $submissionService->getParticipantsByStage($context->getId(), $submission, $stageId);

		return $response->withJson($data, 200);
	}

	/**
	 * Retrieve galley list
	 *
	 * @param $slimRequest Request Slim request object
	 * @param $response Response object
	 * @param array $args arguments
	 *
	 * @return Response
	 */
	public function getGalleys($slimRequest, $response, $args) {
		$request = $this->getRequest();
		$context = $request->getContext();
		$data = array();

		$sContainer = ServicesContainer::instance();
		$submissionService = $sContainer->get('submission');

		try {
			$submission = $this->getAuthorizedContextObject(ASSOC_TYPE_SUBMISSION);
			$data = $submissionService->getGalleys($context->getId(), $submission);
		}
		catch (PKP\Services\Exceptions\SubmissionStageNotValidException $e) {
			return $response->withStatus(400)->withJsonError('api.submissions.400.stageNotValid');
		}

		return $response->withJson($data, 200);
	}

	/**
	 * Get submission metadata
	 * @param $slimRequest Request Slim request object
	 * @param $response Response object
	 * @param array $args arguments
	 * @return array
	 */
	protected function submissionMetadata($slimRequest, $response, $args) {
		$request = $this->_request;
		$journal = $request->getContext();
		$submission = $this->getAuthorizedContextObject(ASSOC_TYPE_SUBMISSION);
		assert($submission);

		$queryParams = $slimRequest->getQueryParams();
		$format = isset($queryParams['format'])?$queryParams['format']:'';

		$metadataPlugins = (array) PluginRegistry::loadCategory('metadata', true, $journal->getId());
		$schema = null;
		foreach($metadataPlugins as $plugin) {
			if ($plugin->supportsFormat($format)) {
				$schema = $plugin->getSchemaObject($format);
			}
		}
		if (!$schema && array_key_exists('Dc11MetadataPlugin', $metadataPlugins)) {
			$schema = $metadataPlugins['Dc11MetadataPlugin']->getSchemaObject('dc11');
		}
		if (is_a($schema, 'MetadataSchema') && in_array(ASSOC_TYPE_SUBMISSION, $schema->getAssocTypes())) {
			return $this->getMetadata($submission, $schema);
		};
		assert(false);
		return array();
	}

	function getMetadata($submission, $schema) {
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
		return $metadata;
	}

	/**
	 * Get submission metadata
	 * @param $slimRequest Request Slim request object
	 * @param $response Response object
	 * @param array $args arguments
	 *
	 * @return Response
	 */
	public function getSubmission($slimRequest, $response, $args) {
		AppLocale::requireComponents(LOCALE_COMPONENT_PKP_READER, LOCALE_COMPONENT_PKP_SUBMISSION);

		$request = $this->getRequest();
		$dispatcher = $request->getDispatcher();
		$context = $request->getContext();
		$journal = $request->getJournal();

		$submission = $this->getAuthorizedContextObject(ASSOC_TYPE_SUBMISSION);

		import('classes.core.ServicesContainer');
		$args = array(
			'journal' 	=> $journal,
			'slimRequest' 	=> $slimRequest
		);
		$data = ServicesContainer::instance()->get('submission')->getFullProperties($submission, $args);
		return $response->withJson($data, 200);
	}

	/**
	 * Post submission file
	 * @param $slimRequest Request Slim request object
	 * @param $response Response object
	 * @param array $args arguments
	 *
	 * @return Response
	 */
	public function postFile($slimRequest, $response, $args) {
		$request = $this->getRequest();
		$context = $request->getContext();
		$user = $request->getUser();
		$submission = $this->getAuthorizedContextObject(ASSOC_TYPE_SUBMISSION);
		$revisedFileId = $slimRequest->getQueryParam('revisedFileId');
		$genreId = $revisedFileId ? null : (int) $slimRequest->getQueryParam('genreId');
		$fileStage = $slimRequest->getQueryParam('fileStage');
		$assocType = $slimRequest->getQueryParam('assocType', null);
		$assocId = $slimRequest->getQueryParam('assocId', null);
		$uploaderUserGroupId = $slimRequest->getQueryParam('uploaderUserGroupId');
		$uploadData = array(
			'revisedFileId'         => $revisedFileId,
			'genreId'             	=> $genreId,
			'uploaderUserGroupId'   => $uploaderUserGroupId,
			'assocType'             => $assocType,
			'assocId'               => $assocId,
			'fileStage'             => $fileStage
		);

		try {
			import('classes.core.ServicesContainer');
			$submissionService = ServicesContainer::instance()->get('submission');
			$submissionFile = $submissionService->saveUploadedFile(
				$context->getId(),
				$submission->getId(),
				$user,
				$uploadData
			);
		}
		catch (Exception $e) {
			return $response->withStatus(400)->withJson(array(
				'error' 	=> 'api.submissions.400.missingRequired',
				'errorMessage'	=> $e->getMessage(),
			));
		}

		if (!$submissionFile) {
			return $response->withStatus(400)->withJsonError('api.submissions.400.missingRequired');
		}

		$this->updateFileMetadata($submissionFile, $slimRequest->getParams());

		// Persist the submission file.
		$submissionFileDao = DAORegistry::getDAO('SubmissionFileDAO');
		$submissionFileDao->updateObject($submissionFile);

		$data = $this->buildSubmissionFileDataEntry($submissionFile);
		return $response->withJson($data, 200);
	}

	/**
	 * set file metadata from query string parameter
	 *
	 * @param $slimRequest Request Slim request object
	 * @param array $submittedData user submitted data
	 *
	 * @return boolean
	 */
	protected function updateFileMetadata($submissionFile, $submittedData) {
		$dirty = false;

		// set name if provided
		if (isset($submittedData['name']) && !is_null($submittedData['name'])) {
			$submissionFile->setName($submittedData['name'], null);
			$dirty = true;
		}

		// set supplementary file metadata
		if (is_a($submissionFile, 'SupplementaryFile')) {
			$validFields = array(
				'description','creator','publisher','source','subject','sponsor','dateCreated','language',
			);
			foreach ($validFields as $field) {
				if (isset($submittedData[$field]) && !is_null($submittedData[$field])) {
					$dirty = true;
					$setter = 'set' . ucfirst($field);
					$submissionFile->$setter($submittedData[$field], null);
				}
			}
		}

		// set submission artwork file metadata
		if (is_a($submissionFile, 'SubmissionArtworkFile')) {
			$validFields = array(
				'artworkCaption','artworkCredit','artworkCopyrightOwner','artworkCopyrightOwnerContact','artworkPermissionTerms',
			);
			foreach ($validFields as $field) {
				if (isset($submittedData[$field]) && !is_null($submittedData[$field])) {
					$dirty = true;
					$setter = 'set' . str_replace('artwork', '', $field);
					$submissionFile->$setter($submittedData[$field], null);
				}
			}
		}

		return $dirty;
	 }


	/**
	 * Edit submission file metadata
	 * @param $slimRequest Request Slim request object
	 * @param $response Response object
	 * @param array $args arguments
	 *
	 * @return Response
	 */
	public function editFileMetadata($slimRequest, $response, $args) {
		$submittedData = $slimRequest->getParams();
		$submissionFile = $this->getAuthorizedContextObject(ASSOC_TYPE_SUBMISSION_FILE);

		$dirty = $this->updateFileMetadata($submissionFile, $submittedData);
		if ($dirty) {
			$submissionFileDao = DAORegistry::getDAO('SubmissionFileDAO');
			$submissionFileDao->updateObject($submissionFile);
		}

		$data = $this->buildSubmissionFileDataEntry($submissionFile);
		return $response->withJson($data, 200);
	}
}

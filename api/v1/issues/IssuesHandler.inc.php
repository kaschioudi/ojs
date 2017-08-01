<?php 

/**
 * @file api/v1/issues/IssuesHandler.inc.php
 *
 * Copyright (c) 2014-2017 Simon Fraser University
 * Copyright (c) 2003-2017 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class IssuesHandler
 * @ingroup api_v1_issues
 *
 * @brief Handle API requests for issues operations.
 *
 */

import('lib.pkp.classes.handler.APIHandler');
import('classes.core.ServicesContainer');

use OJS\Repositories\IssueRepositoryInterface;

class IssuesHandler extends APIHandler {
	
	/** @var OJS\Repositories\IssueRepositoryInterface $repository */
	protected $repository = null;

	/**
	 * Constructor
	 */
	public function __construct(IssueRepositoryInterface $repository) {
		$this->repository = $repository;
		$this->_handlerPath = 'issues';
		AppLocale::requireComponents(LOCALE_COMPONENT_APP_EDITOR);

		$roles = array(ROLE_ID_MANAGER, ROLE_ID_SUB_EDITOR, ROLE_ID_ASSISTANT, ROLE_ID_REVIEWER, ROLE_ID_AUTHOR);
		$this->_endpoints = array(
			'GET' => array (
				array(
					'pattern' => $this->getEndpointPattern(),
					'handler' => array($this,'getIssueList'),
					'roles' => $roles
				),
				array(
					'pattern' => $this->getEndpointPattern().  '/{issueId}',
					'handler' => array($this,'getIssue'),
					'roles' => $roles
				),
			),
			'POST' => array(
				array(
					'pattern' => $this->getEndpointPattern(),
					'handler' => array($this, 'createIssue'),
					'roles' => $roles
				),
				array(
					'pattern' => $this->getEndpointPattern().  '/{issueId}/cover',
					'handler' => array($this, 'modifyIssueCoverImage'),
					'roles' => $roles
				)
			),
                        'PUT' => array(
                                array(
                                        'pattern' => $this->getEndpointPattern() . '/{issueId}',
                                        'handler' => array($this, 'editIssue'),
                                        'roles' => $roles
                                )
                        ),
			'DELETE' => array(
				array(
					'pattern' => $this->getEndpointPattern() . '/{issueId}',
					'handler' => array($this, 'deleteIssue'),
					'roles' => $roles
				),
				array(
					'pattern' => $this->getEndpointPattern().  '/{issueId}/cover',
					'handler' => array($this, 'deleteIssueCoverImage'),
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
		
		if ($routeName === 'getIssueList') {
			import('lib.pkp.classes.security.authorization.ContextRequiredPolicy');
			$this->addPolicy(new ContextRequiredPolicy($request));
		
			import('classes.security.authorization.OjsJournalMustPublishPolicy');
			$this->addPolicy(new OjsJournalMustPublishPolicy($request));
		}
		
		if (in_array($routeName, array('getIssue', 'editIssue', 'modifyIssueCoverImage','deleteIssueCoverImage','deleteIssue'))) {
			import('classes.security.authorization.OjsIssueRequiredPolicy');
			$this->addPolicy(new OjsIssueRequiredPolicy($request, $args));
		}

		if (in_array($routeName, array('createIssue', 'editIssue', 'modifyIssueCoverImage','deleteIssueCoverImage','deleteIssue'))) {
			import('lib.pkp.classes.security.authorization.ContextAccessPolicy');
			$this->addPolicy(new ContextAccessPolicy($request, $roleAssignments));
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
	public function getIssueList($slimRequest, $response, $args) {
		$request = $this->getRequest();
		$context = $request->getContext();
		$journal = $request->getJournal();
		$data = array();
		
		$volume = $this->getParameter('volume', null);
		$number = $this->getParameter('number', null);
		$year = $this->getParameter('year', null);
		
		$issueDao = DAORegistry::getDAO('IssueDAO');
		$issues = $issueDao->getPublishedIssuesByNumber($journal->getId(), $volume, $number, $year);
		
		while ($issue = $issues->next()) {
			$data[] = array(
				'id'		=> $issue->getBestIssueId(),
				'title'		=> $issue->getLocalizedTitle(),
				'series'	=> $issue->getIssueSeries(),
				'datePublished'	=> $issue->getDatePublished(),
				'lastModified'	=> $issue->getLastModified(),
				'current'	=> (bool) ($issue->getCurrent() == $issue->getBestIssueId()),
			);
		}
		
		return $response->withJson($data, 200);
	}
	
	/**
	 * Get issue metadata
	 * 
	 * @param $slimRequest Request Slim request object
	 * @param $response Response object
	 * @param array $args arguments
	 * 
	 * @return Response
	 */
	public function getIssue($slimRequest, $response, $args) {
		$request = $this->getRequest();
		$context = $request->getContext();
		$journal = $request->getJournal();
		
		$issue = $this->getAuthorizedContextObject(ASSOC_TYPE_ISSUE);
		$issueGalleyDao = DAORegistry::getDAO('IssueGalleyDAO');
		$publishedArticleDao = DAORegistry::getDAO('PublishedArticleDAO');
		
		$publishedArticlesData = array();
		$issueService = ServicesContainer::instance()->get('issue');
		$publishedArticles = $publishedArticleDao->getPublishedArticlesInSections($issue->getId(), true);
		foreach ($publishedArticles as $pArticles) {
			$publishedArticlesData[$pArticles['title']] = array_map(function($article) use($journal, $issue, $issueService)
			{
				$item = array(
					'id'		=> $article->getId(),
					'title'		=> $article->getTitle(null),
					'author'	=> $article->getAuthorString(),
					'datePublished'	=> $article->getDatePublished(),
				);
				
				$hasAccess = $issueService->userHasAccessToGalleys($journal, $issue);
				if ($hasAccess) {
					$galleys = array();
					foreach ($article->getGalleys() as $galley) {
						$galleys[] = array(
							'id'		=> $galley->getId(),
							'label'		=> $galley->getGalleyLabel(),
							'submissionId'	=> $galley->getSubmissionId(),
						);
					}
					$item['galleys'] = $galleys;
				}
				
				return $item;
			}, $pArticles['articles']);
		}
		
		$data = array(
			'id'			=> $issue->getBestIssueId(),
			'volume'		=> $issue->getVolume(),
			'number'		=> $issue->getNumber(),
			'year'			=> $issue->getYear(),
			'current'		=> (bool) ($issue->getCurrent() == $issue->getBestIssueId()),
			'title'			=> $issue->getLocalizedTitle(),
			'series'		=> $issue->getIssueSeries(),
			'issueCover'		=> $issue->getLocalizedCoverImageUrl(),
			'overImageAltText'	=> $issue->getLocalizedCoverImageAltText(),
			'description'		=> $issue->getLocalizedDescription(),
			'datePublished'		=> $issue->getDatePublished(),
			'lastModified'		=> $issue->getLastModified(),
			'pubId'			=> $issue->getStoredPubId(),
			'issueGalleys'		=> $issueGalleyDao->getByIssueId($issue->getId()),
			'articles'		=> $publishedArticlesData,
		);
		
		return $response->withJson($data, 200);
	}

	/**
	 * Internal helper function which return a generic array of issue metadata
	 * 
	 * @param \Issue $issue
	 *
	 * @return array
	 */
	protected function makeIssueData(Issue $issue) {
		return array(
			'id'			=> $issue->getBestIssueId(),
			'volume'		=> $issue->getVolume(),
			'number'		=> $issue->getNumber(),
			'year'			=> $issue->getYear(),
			'current'		=> (bool) ($issue->getCurrent() == $issue->getBestIssueId()),
			'title'			=> $issue->getTitle(null),
			'series'		=> $issue->getIssueSeries(),
			'issueCover'		=> $issue->getLocalizedCoverImageUrl(),
			'coverImageAltText'	=> $issue->getCoverImageAltText(null),
			'description'		=> $issue->getDescription(null),
			'datePublished'		=> $issue->getDatePublished(),
			'lastModified'		=> $issue->getLastModified(),
			'pubId'			=> $issue->getStoredPubId(),
		);
	}

	/**
	 * Helper method to handler issue cover image
	 *
	 * @param $journal \Journal
	 * @param $request \PKPRequest
	 * @param $issue \Issue
	 * @param $postData array
	 *
	 * @return void
	 */
	protected function uploadIssueCoverImage($journal, $request, $issue, $postData) {
		$locale = AppLocale::getLocale();
		$issueDao = DAORegistry::getDAO('IssueDAO');

		$user = $request->getUser();
		import('lib.pkp.classes.file.TemporaryFileManager');
		$temporaryFileManager = new TemporaryFileManager();
		$temporaryFile = $temporaryFileManager->handleUpload('uploadedFile', $user->getId());
		if ($temporaryFile) {
			import('classes.file.PublicFileManager');
			$publicFileManager = new PublicFileManager();
			$newFileName = 'cover_issue_' . $issue->getId() . '_' . $locale . $publicFileManager->getImageExtension($temporaryFile->getFileType());
			$publicFileManager->copyJournalFile($journal->getId(), $temporaryFile->getFilePath(), $newFileName);
			$issue->setCoverImage($newFileName, $locale);
			$issueDao->updateObject($issue);
		}

		if (isset($postData['coverImageAltText'])) {
			$issue->setCoverImageAltText($postData['coverImageAltText'], $locale);
		}

		$issueDao->updateObject($issue);
	}

	/**
	 * Create issue
	 *
	 * @param $slimRequest Request Slim request object
	 * @param $response Response object
	 * @param array $args arguments
	 *
	 * @return Response
	 */
	public function createIssue($slimRequest, $response, $args) {
		$request = $this->getRequest();
		$context = $request->getContext();
		$journal = $request->getJournal();

		$issue = null;
		$postData = $slimRequest->getParsedBody();

		try {
			$issue = $this->repository->create($journal, $postData);
		}
		catch (OJS\Repositories\Exceptions\ValidationException $e) {
			return $response->withStatus(400)->withJson(array(
				'error'		=> 'api.submissions.400.missingRequired',
				'errorMsg'	=> $e->getMessage()
			));
		}

		// upload cover file for the issue if available
		$uploadedfiles = $slimRequest->getUploadedFiles();
		if (isset($uploadedfiles['uploadedFile'])) {
			$this->uploadIssueCoverImage($journal, $request, $issue, $postData);
		}

		$data = $this->makeIssueData($issue);
		return $response->withJson($data, 200);
	}

	/**
	 * Update issue
	 *
	 * @param $slimRequest Request Slim request object
	 * @param $response Response object
	 * @param array $args arguments
	 *
	 * @return Response
	 */
	public function editIssue($slimRequest, $response, $args) {
		$request = $this->getRequest();
		$context = $request->getContext();
		$journal = $request->getJournal();

		$postData = $slimRequest->getQueryParams();
		$issue = $this->getAuthorizedContextObject(ASSOC_TYPE_ISSUE);

		try {
			$issue = $this->repository->update($issue, $postData);
		}
		catch (OJS\Repositories\Exceptions\ValidationException $e) {
			return $response->withStatus(400)->withJson(array(
				'error'		=> 'api.submissions.400.missingRequired',
				'errorMsg'	=> $e->getMessage()
			));
		}

		$data = $this->makeIssueData($issue);
		return $response->withJson($data, 200);
	}

	/**
	 * Modify issue cover image
	 *
	 * @param $slimRequest Request Slim request object
	 * @param $response Response object
	 * @param array $args arguments
	 *
	 * @return Response
	 */
	public function modifyIssueCoverImage($slimRequest, $response, $args) {
		$request = $this->getRequest();
		$context = $request->getContext();
		$journal = $request->getJournal();
		$issue = $this->getAuthorizedContextObject(ASSOC_TYPE_ISSUE);
		$uploadedfiles = $slimRequest->getUploadedFiles();
		if (isset($uploadedfiles['uploadedFile'])) {
			$this->uploadIssueCoverImage($journal, $request, $issue, $postData);
			$data = $this->makeIssueData($issue);
			return $response->withJson($data, 200);
		}
		else {
			return $response->withStatus(400)->withJson(array(
				'error'		=> 'api.submissions.400.missingRequired',
				'errorMsg'	=> __('common.uploadFailed'),
			));
		}
	}

	/**
	 * Delete issue cover image
	 *
	 * @param $slimRequest Request Slim request object
	 * @param $response Response object
	 * @param array $args arguments
	 *
	 * @return Response
	 */
	public function deleteIssueCoverImage($slimRequest, $response, $args) {
		$request = $this->getRequest();
		$context = $request->getContext();
		$journal = $request->getJournal();

		$locale = AppLocale::getLocale();
		$params = $slimRequest->getQueryParams();
		$issue = $this->getAuthorizedContextObject(ASSOC_TYPE_ISSUE);
		if (isset($params['coverImage']) && ($params['coverImage'] == $issue->getCoverImage($locale))) {
			// Remove cover image and alt text from issue settings
			$issue->setCoverImage('', $locale);
			$issue->setCoverImageAltText('', $locale);
			$issueDao = DAORegistry::getDAO('IssueDAO');
			$issueDao->updateObject($issue);

			$publicFileManager = new PublicFileManager();
			if ($publicFileManager->removeJournalFile($issue->getJournalId(), $params['coverImage'])) {
				$data = $this->makeIssueData($issue);
				return $response->withJson($data, 200);
			} else {
				return $response->withStatus(404)->withJson(array(
					'error'		=> 'api.submissions.404.resourceNotFound',
					'errorMsg'	=> __('editor.issues.removeCoverImageFileNotFound'),
				));
			}

		}
		else {
			return $response->withStatus(400)->withJson(array(
				'error'		=> 'api.submissions.400.missingRequired',
				'errorMsg'	=> __('editor.issues.removeCoverImageFileNameMismatch'),
			));
		}
	}

	/**
	 * Delete issue 
	 *
	 * @param $slimRequest Request Slim request object
	 * @param $response Response object
	 * @param array $args arguments
	 *
	 * @return Response
	 */
	public function deleteIssue($slimRequest, $response, $args) {
		$request = $this->getRequest();
		$context = $request->getContext();
		$journal = $request->getJournal();

		$issue = $this->getAuthorizedContextObject(ASSOC_TYPE_ISSUE);
		if (!$issue) {
			return $response->withStatus(404)->withJson(array(
				'error'		=> 'api.submissions.404.resourceNotFound',
				'errorMsg'	=> __('api.submissions.404.resourceNotFound'),
			));
		}

		try {
			$this->repository->delete($issue, $journal);
		}
		catch (\Exception $e) {
			return $response->withStatus(400)->withJson(array(
				'error'		=> 'api.submissions.400.missingRequired',
				'errorMsg'	=> $e->getMessage(),
			));
		}

		return $response->withJson(array('deleted' => true), 200);
	}
}
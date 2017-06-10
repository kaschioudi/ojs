<?php 

/**
 * @file api/v1/issues/IssuesHandler.inc.php
 *
 * Copyright (c) 2014-2016 Simon Fraser University Library
 * Copyright (c) 2003-2016 John Willinsky
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

use App\Repositories\IssueRepositoryInterface;

class IssuesHandler extends APIHandler {
	
        /** @var App\Repositories\IssueRepositoryInterface $repository */
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
			'GET' => array(
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
                                )
                        ),
                        'PUT' => array(
                                array(
                                        'pattern' => $this->getEndpointPattern() . '/{issueId}',
                                        'handler' => array($this, 'editIssue'),
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
		
		if (in_array($routeName, array('getIssue', 'editIssue'))) {
		        import('classes.security.authorization.OjsIssueRequiredPolicy');
		        $this->addPolicy(new OjsIssueRequiredPolicy($request, $args));
		}
		
		if (in_array($routeName, array('createIssue', 'editIssue'))) {
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
				'id'                => $issue->getBestIssueId(),
				'title'             => $issue->getLocalizedTitle(),
				'series'            => $issue->getIssueSeries(),
				'datePublished'	    => $issue->getDatePublished(),
				'lastModified'      => $issue->getLastModified(),
				'current'           => (bool) ($issue->getCurrent() == $issue->getBestIssueId()),
			);
		}
		
		return $response->withJson($data, 200);
	}
	
	/**
	 * Internal helper function which return a generic array of issue metadata
	 * @param \Issue $issue
	 *
	 * @return array
	 */
	protected function makeIssueData(Issue $issue) {
	        return array(
                        'id'                    => $issue->getBestIssueId(),
                        'volume'                => $issue->getVolume(),
                        'number'                => $issue->getNumber(),
                        'year'                  => $issue->getYear(),
                        'current'               => (bool) ($issue->getCurrent() == $issue->getBestIssueId()),
                        'title'                 => $issue->getLocalizedTitle(),
                        'series'                => $issue->getIssueSeries(),
                        'issueCover'            => $issue->getLocalizedCoverImageUrl(),
                        'overImageAltText'      => $issue->getLocalizedCoverImageAltText(),
                        'description'           => $issue->getLocalizedDescription(),
                        'datePublished'         => $issue->getDatePublished(),
                        'lastModified'          => $issue->getLastModified(),
                        'pubId'                 => $issue->getStoredPubId(),
	        );
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
	        $dispatcher = $request->getDispatcher();
	
	        $issue = $this->getAuthorizedContextObject(ASSOC_TYPE_ISSUE);
	        $issueGalleyDao = DAORegistry::getDAO('IssueGalleyDAO');
	        $publishedArticleDao = DAORegistry::getDAO('PublishedArticleDAO');
	
	        $publishedArticlesData = array();
	        $issueService = ServicesContainer::instance()->get('issue');
	        $publishedArticles = $publishedArticleDao->getPublishedArticlesInSections($issue->getId(), true);
	        foreach ($publishedArticles as $pArticles) {
	                $publishedArticlesData[$pArticles['title']] = array_map(function($article) 
	                                use ($context, $request, $dispatcher, $journal, $issue, $issueService)
                        {
                                $item = array(
                                                'id'                    => $article->getId(),
                                                'title'                 => $article->getTitle(null),
                                                'author'                => $article->getAuthorString(),
                                                'datePublished'         => $article->getDatePublished(),
                                );

                                $hasAccess = $issueService->userHasAccessToGalleys($journal, $issue);
                                if ($hasAccess) {
                                        $galleys = array();
                                        foreach ($article->getGalleys() as $galley) {
                                                $url = null;
                                                if ($galley->getRemoteURL()) {
                                                        $url = $galley->getRemoteURL();
                                                }
                                                else {
                                                        $url = $dispatcher->url($request, ROUTE_PAGE, $context, 'article', 'download',
                                                                        array($article->getId(), $galley->getBestGalleyId()));
                                                }

                                                $galleys[] = array(
                                                        'id'            => $galley->getId(),
                                                        'label'         => $galley->getGalleyLabel(),
                                                        'submissionId'  => $galley->getSubmissionId(),
                                                        'filetype'      => $galley->getFileType(),
                                                        'url'           => $url,
                                                );
                                        }
                                        $item['galleys'] = $galleys;
                                }

                                return $item;
	                }, $pArticles['articles']);
	        }
	
	        $data = $this->makeIssueData($issue);
	        $data['issueGalleys'] = $issueGalleyDao->getByIssueId($issue->getId());
	        $data['articles'] = $publishedArticlesData;
	
	        return $response->withJson($data, 200);
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
	        catch (App\Repositories\Exceptions\ValidationException $e) {
	                return $response->withJson(array(
                                'error' => $e->getCode(),
                                'errorMsg' => $e->getMessage()
	                ), 401);
	        }
	
	        $data = $this->makeIssueData($issue);
	        return $response->withJson($data, 200);
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
	public function editIssue($slimRequest, $response, $args) {
	        $request = $this->getRequest();
	        $context = $request->getContext();
	        $journal = $request->getJournal();
	
	        $postData = $slimRequest->getParsedBody();
	        $issue = $this->getAuthorizedContextObject(ASSOC_TYPE_ISSUE);
	
	        try {
	                $issue = $this->repository->update($issue, $postData);
	        }
	        catch (App\Repositories\Exceptions\ValidationException $e) {
	                return $response->withJson(array(
                                'error' => $e->getCode(),
                                'errorMsg' => $e->getMessage()
	                ), 401);
	        }
	
	        $data = $this->makeIssueData($issue);
	        return $response->withJson($data, 200);
	}
}
<?php
/**
 * @file classes/security/authorization/OjsJournalMustPublishPolicy.inc.php
 *
 * Copyright (c) 2014-2017 Simon Fraser University
 * Copyright (c) 2000-2017 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class OjsJournalMustPublishPolicy
 * @ingroup security_authorization
 *
 * @brief Access policy to limit access to journals that do not publish online.
 */

import('lib.pkp.classes.security.authorization.PolicySet');
import('lib.pkp.classes.security.authorization.AuthorizationPolicy');

class OjsJournalMustPublishPolicy extends AuthorizationPolicy {

	var $_context;

	/**
	 * Constructor
	 * @param $request PKPRequest
	 * @param $args array request arguments
	 * @param $roleAssignments array
	 */
	function __construct($request) {
		parent::__construct('user.authorization.journalDoesNotPublish');
		$this->_context = $request->getContext();
	}

	//
	// Implement template methods from AuthorizationPolicy
	//
	function effect() {
		if (!$this->_context) {
			$this->setAuthorizationDenialErrorCode(AUTHORIZATION_ERROR_BAD_REQUEST);
			return AUTHORIZATION_DENY;
		}

		// Certain roles are allowed to see unpublished content.
		$userRoles = (array) $this->getAuthorizedContextObject(ASSOC_TYPE_USER_ROLES);
		if (count(array_intersect(
			$userRoles,
			array(
				ROLE_ID_MANAGER,
				ROLE_ID_SITE_ADMIN,
				ROLE_ID_ASSISTANT,
				ROLE_ID_SUB_EDITOR
			)
		))>0) {
			$this->setAuthorizationDenialErrorCode(AUTHORIZATION_ERROR_FORBIDDEN);
			return AUTHORIZATION_PERMIT;
		}

		if ($this->_context->getSetting('publishingMode') == PUBLISHING_MODE_NONE) {
			$this->setAuthorizationDenialErrorCode(AUTHORIZATION_ERROR_NOT_FOUND);
			return AUTHORIZATION_DENY;
		}

		return AUTHORIZATION_PERMIT;
	}
}

?>

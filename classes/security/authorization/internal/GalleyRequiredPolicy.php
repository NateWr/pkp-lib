<?php
/**
 * @file classes/security/authorization/internal/GalleyRequiredPolicy.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class GalleyRequiredPolicy
 * @ingroup security_authorization_internal
 *
 * @brief Policy that ensures that the request contains a valid galley id.
 *
 * The authorized context objects must contain a valid publication for this to work.
 * See PublicationRequiredPolicy.
 */

namespace PKP\security\authorization\internal;

use APP\core\Application;
use APP\core\Request;
use APP\facades\Repo;
use PKP\security\authorization\AuthorizationPolicy;
use PKP\security\authorization\DataObjectRequiredPolicy;

class GalleyRequiredPolicy extends DataObjectRequiredPolicy
{
    protected string $galleyParameterName;

    public function __construct(Request $request, array &$args, string $galleyParameterName)
    {
        parent::__construct($request, $args, $galleyParameterName, 'user.authorization.invalidGalley', null);
        $this->galleyParameterName = $galleyParameterName;

        $callOnDeny = [$request->getDispatcher(), 'handle404', []];
        $this->setAdvice(
            AuthorizationPolicy::AUTHORIZATION_ADVICE_CALL_ON_DENY,
            $callOnDeny
        );
    }

    //
    // Implement template methods from AuthorizationPolicy
    //
    /**
     * @see DataObjectRequiredPolicy::dataObjectEffect()
     */
    public function dataObjectEffect()
    {
        $galley = Repo::galley()->get((int) $this->getDataObjectId());
        $publication = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_PUBLICATION);

        if ($galley && $publication && $galley->getData('publicationId') === $publication->getId()) {
            $this->addAuthorizedContextObject(Application::ASSOC_TYPE_GALLEY, $galley);
            return AuthorizationPolicy::AUTHORIZATION_PERMIT;
        }

        return AuthorizationPolicy::AUTHORIZATION_DENY;
    }
}

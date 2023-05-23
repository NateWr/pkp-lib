{**
 * lib/pkp/templates/layouts/backend.tpl
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * Common site header.
 *}
<!DOCTYPE html>
<html>
<body>
	<div id="app" data-page='{htmlspecialchars(json_encode($page), ENT_QUOTES, 'UTF-8')}'></div>
</body>
</html>
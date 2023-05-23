<?php
namespace PKP\template;

use APP\core\Application;
use PKP\core\JSONMessage;

class InertiaPKP
{
    public static function render(string $component)
    {
        $request = Application::get()->getRequest();
        $templateMgr = PKPTemplateManager::getManager($request);
        $templateVars = $templateMgr->getTemplateVars();
        ksort($templateVars);

        $props = [
            'component' => $component,
            'props' => $templateVars,
            'url' => $request->getRequestUrl(),
            'version' => 'TODO',
        ];

        if (self::isInertiaRequest()) {
            header('Content-Type: application/json');
            header('Vary: Accept');
            header('X-Inertia: true');
            echo json_encode($props);
            die;
        }

        $templateMgr->assign('page', $templateVars);

        return $templateMgr->display('inertia.tpl');
    }

    public static function isInertiaRequest()
    {
        $headers = array_change_key_case(getallheaders(), CASE_LOWER);

        return (
            isset($headers['x-inertia'])
            && $headers['x-inertia'] === 'true'
        );
    }
}

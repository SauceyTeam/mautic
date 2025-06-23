<?php

namespace Mautic\CoreBundle\Controller;

use Mautic\CoreBundle\CoreEvents;
use Mautic\CoreBundle\Event\GlobalSearchEvent;
use Symfony\Component\HttpFoundation\Request;

/**
 * Almost all other Mautic Bundle controllers extend this default controller.
 */
class DefaultController extends CommonController
{
    /**
     * @return \Symfony\Component\HttpFoundation\RedirectResponse|\Symfony\Component\HttpFoundation\Response
     */
    public function indexAction(Request $request)
    {
        $root = $this->coreParametersHelper->get('webroot');

        if (empty($root)) {
            return $this->redirectToRoute('mautic_dashboard_index');
        } else {
            /** @var \Mautic\PageBundle\Model\PageModel $pageModel */
            $pageModel = $this->getModel('page');
            $page      = $pageModel->getEntity($root);

            if (empty($page)) {
                return $this->notFound();
            }

            $slug = $pageModel->generateSlug($page);

            $request->attributes->set('ignore_mismatch', true);

            return $this->forward('Mautic\PageBundle\Controller\PublicController::indexAction', ['slug' => $slug]);
        }
    }

    public function globalSearchAction(Request $request): \Symfony\Component\HttpFoundation\Response
    {
        $searchStr = $request->get('global_search', $request->getSession()->get('mautic.global_search', ''));
        $request->getSession()->set('mautic.global_search', $searchStr);

        if (!empty($searchStr)) {
            $event = new GlobalSearchEvent($searchStr, $this->translator);
            $this->dispatcher->dispatch($event, CoreEvents::GLOBAL_SEARCH);
            $results = $event->getResults();
        } else {
            $results = [];
        }

        return $this->render('@MauticCore/GlobalSearch/globalsearch.html.twig',
            [
                'results'      => $results,
                'searchString' => $searchStr,
            ]
        );
    }

    public function notificationsAction(): \Symfony\Component\HttpFoundation\Response
    {
        /** @var \Mautic\CoreBundle\Model\NotificationModel $model */
        $model = $this->getModel('core.notification');

        [$notifications, $showNewIndicator, $updateMessage] = $model->getNotificationContent(null, false, 200);

        return $this->delegateView(
            [
                'contentTemplate' => '@MauticCore/Notification/notifications.html.twig',
                'viewParameters'  => [
                    'showNewIndicator' => $showNewIndicator,
                    'notifications'    => $notifications,
                    'updateMessage'    => $updateMessage,
                ],
            ]
        );
    }

    public function genConfigAction(Request $request): \Symfony\Component\HttpFoundation\Response
    {
        $host = $_SERVER["HTTP_HOST"];
        if (strpos($host, ':') !== false) {
            $host = substr($host, 0, strpos($host, ':'));
        }

        $tenant = preg_match('/^([a-zA-Z0-9]+)-mt\./', $host, $matches) ? $matches[1] : null;
        
        if (!$tenant) {
            return new \Symfony\Component\HttpFoundation\JsonResponse(['error' => 'No tenant found in host'], 400);
        }

        $root = realpath(__DIR__.'/../../../../..');
        $projectRoot = $root;

        // Get main DB credentials from env vars
        $mainDbHost = getenv('MAUTIC_DB_HOST');
        $mainDbPort = getenv('MAUTIC_DB_PORT') ?: 3306;
        $mainDbUser = getenv('MAUTIC_DB_USER');
        $mainDbPassword = getenv('MAUTIC_DB_PASSWORD');

        $dsn = "mysql:host=$mainDbHost;port=$mainDbPort;dbname=mautic_main;charset=utf8mb4";
        try {
            $pdo = new \PDO($dsn, $mainDbUser, $mainDbPassword, [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            ]);
            $stmt = $pdo->prepare('SELECT * FROM tenants WHERE url = ? LIMIT 1');
            $stmt->execute([$host]);
            error_log($host);
            $tenantRow = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            if ($tenantRow) {
                $template = file_get_contents(__DIR__.'/../../../config/config_template.php');
                // Generate a random secret key
                $secretKey = bin2hex(random_bytes(32));
                $replacements = [
                    '{{db_host}}' => $mainDbHost,
                    '{{db_port}}' => $mainDbPort,
                    '{{db_name}}' => $tenantRow['db_name'],
                    '{{db_user}}' => $tenantRow['username'],
                    '{{db_password}}' => $tenantRow['password'],
                    '{{secret_key}}' => $secretKey,
                    '{{site_url}}' => $_SERVER["HTTP_HOST"],
                ];
                $config = str_replace(array_keys($replacements), array_values($replacements), $template);
                $configPath = $projectRoot.'/config/local-'.$tenant.'.php';
                file_put_contents($configPath, $config);
                
                return new \Symfony\Component\HttpFoundation\JsonResponse([
                    'success' => true,
                    'message' => 'Config regenerated successfully',
                    'tenant' => $tenant,
                    'config_path' => $configPath
                ]);
            } else {
                return new \Symfony\Component\HttpFoundation\JsonResponse(['error' => 'No tenant found for host: ' . $host], 404);
            }
        } catch (\Exception $e) {
            return new \Symfony\Component\HttpFoundation\JsonResponse(['error' => 'Error generating config: ' . $e->getMessage()], 500);
        }
    }
}

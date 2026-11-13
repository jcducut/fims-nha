<?php

namespace PHPMaker2024\FIMS;

use Psr\Container\ContainerInterface;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use PHPMaker2024\FIMS\Attributes\Delete;
use PHPMaker2024\FIMS\Attributes\Get;
use PHPMaker2024\FIMS\Attributes\Map;
use PHPMaker2024\FIMS\Attributes\Options;
use PHPMaker2024\FIMS\Attributes\Patch;
use PHPMaker2024\FIMS\Attributes\Post;
use PHPMaker2024\FIMS\Attributes\Put;

class NotificationReadsController extends ControllerBase
{
    // list
    #[Map(["GET","POST","OPTIONS"], "/NotificationReadsList[/{keys:.*}]", [PermissionMiddleware::class], "list.notification_reads")]
    public function list(Request $request, Response $response, array $args): Response
    {
        return $this->runPage($request, $response, $this->getKeyParams($args), "NotificationReadsList");
    }

    // add
    #[Map(["GET","POST","OPTIONS"], "/NotificationReadsAdd[/{keys:.*}]", [PermissionMiddleware::class], "add.notification_reads")]
    public function add(Request $request, Response $response, array $args): Response
    {
        return $this->runPage($request, $response, $this->getKeyParams($args), "NotificationReadsAdd");
    }

    // view
    #[Map(["GET","POST","OPTIONS"], "/NotificationReadsView[/{keys:.*}]", [PermissionMiddleware::class], "view.notification_reads")]
    public function view(Request $request, Response $response, array $args): Response
    {
        return $this->runPage($request, $response, $this->getKeyParams($args), "NotificationReadsView");
    }

    // edit
    #[Map(["GET","POST","OPTIONS"], "/NotificationReadsEdit[/{keys:.*}]", [PermissionMiddleware::class], "edit.notification_reads")]
    public function edit(Request $request, Response $response, array $args): Response
    {
        return $this->runPage($request, $response, $this->getKeyParams($args), "NotificationReadsEdit");
    }

    // delete
    #[Map(["GET","POST","OPTIONS"], "/NotificationReadsDelete[/{keys:.*}]", [PermissionMiddleware::class], "delete.notification_reads")]
    public function delete(Request $request, Response $response, array $args): Response
    {
        return $this->runPage($request, $response, $this->getKeyParams($args), "NotificationReadsDelete");
    }

    // Get keys as associative array
    protected function getKeyParams($args)
    {
        global $RouteValues;
        if (array_key_exists("keys", $args)) {
            $sep = Container("notification_reads")->RouteCompositeKeySeparator;
            $keys = explode($sep, $args["keys"]);
            if (count($keys) == 2) {
                $keyArgs = array_combine(["notification_id","user_id"], $keys);
                $RouteValues = array_merge(Route(), $keyArgs);
                $args = array_merge($args, $keyArgs);
            }
        }
        return $args;
    }
}

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

class PermissionModuleCaptionsController extends ControllerBase
{
    // list
    #[Map(["GET","POST","OPTIONS"], "/PermissionModuleCaptionsList[/{table_name:.*}]", [PermissionMiddleware::class], "list.permission_module_captions")]
    public function list(Request $request, Response $response, array $args): Response
    {
        return $this->runPage($request, $response, $args, "PermissionModuleCaptionsList");
    }

    // add
    #[Map(["GET","POST","OPTIONS"], "/PermissionModuleCaptionsAdd[/{table_name:.*}]", [PermissionMiddleware::class], "add.permission_module_captions")]
    public function add(Request $request, Response $response, array $args): Response
    {
        return $this->runPage($request, $response, $args, "PermissionModuleCaptionsAdd");
    }

    // view
    #[Map(["GET","POST","OPTIONS"], "/PermissionModuleCaptionsView[/{table_name:.*}]", [PermissionMiddleware::class], "view.permission_module_captions")]
    public function view(Request $request, Response $response, array $args): Response
    {
        return $this->runPage($request, $response, $args, "PermissionModuleCaptionsView");
    }

    // edit
    #[Map(["GET","POST","OPTIONS"], "/PermissionModuleCaptionsEdit[/{table_name:.*}]", [PermissionMiddleware::class], "edit.permission_module_captions")]
    public function edit(Request $request, Response $response, array $args): Response
    {
        return $this->runPage($request, $response, $args, "PermissionModuleCaptionsEdit");
    }

    // delete
    #[Map(["GET","POST","OPTIONS"], "/PermissionModuleCaptionsDelete[/{table_name:.*}]", [PermissionMiddleware::class], "delete.permission_module_captions")]
    public function delete(Request $request, Response $response, array $args): Response
    {
        return $this->runPage($request, $response, $args, "PermissionModuleCaptionsDelete");
    }
}

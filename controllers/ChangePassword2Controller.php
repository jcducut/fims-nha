<?php

namespace PHPMaker2024\FIMS;

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use PHPMaker2024\FIMS\Attributes\Delete;
use PHPMaker2024\FIMS\Attributes\Get;
use PHPMaker2024\FIMS\Attributes\Map;
use PHPMaker2024\FIMS\Attributes\Options;
use PHPMaker2024\FIMS\Attributes\Patch;
use PHPMaker2024\FIMS\Attributes\Post;
use PHPMaker2024\FIMS\Attributes\Put;

/**
 * ChangePassword2 controller
 */
class ChangePassword2Controller extends ControllerBase
{
    // custom
    #[Map(["GET", "POST", "OPTIONS"], "/ChangePassword2[/{params:.*}]", [PermissionMiddleware::class], "custom.ChangePassword2")]
    public function custom(Request $request, Response $response, array $args): Response
    {
        return $this->runPage($request, $response, $args, "ChangePassword2");
    }
}

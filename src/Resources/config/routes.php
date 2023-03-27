<?php

use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

$routes = new RouteCollection();

$routes->add('api.api-test.check', new Route('/api/_action/endereco-shopware6-client/verify', [
    '_controller' => 'Endereco\Shopware6Client\Controller\Api\ApiTestController::checkAPICredentials',
    '_routeScope' => ['api'], // Custom attribute to represent the route scope
]));

return $routes;
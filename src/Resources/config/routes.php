<?php

use Endereco\Shopware6Client\Controller\Storefront\AddressCheckProxyController;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * A collection of routes for the plugin
 * @var RouteCollection $routes
 */
$routes = new RouteCollection();

/**
 * Route for verifying the API key from the settings page.
 *
 * This route is used to check the api key from the settings page.
 * It uses the ApiTestController's checkAPICredentials action.
 *
 * @var Route $checkApiKeyFromSettingsRoute
 */
$checkApiKeyFromSettingsRoute = new Route('/api/_action/endereco-shopware6-client/verify', [
    '_controller' => 'Endereco\Shopware6Client\Controller\Api\ApiTestController::checkAPICredentials',
    '_routeScope' => ['api'], // Custom attribute to represent the route scope
]);
$routes->add('api.api-test.check', $checkApiKeyFromSettingsRoute);

/**
 * Route for saving updated address from the checkout page.
 *
 * This route is used to save updated address from the checkout page.
 * It uses the AddressController's saveAddress action.
 *
 * It's SEO friendly option is turned off and it only accepts POST method.
 *
 * @var Route $routeForAjaxAddressSaving
 */
$routeForAjaxAddressSaving = new Route(
    '/account/endereco/address',
    [
        '_controller' => 'Endereco\Shopware6Client\Controller\Storefront\AddressController::saveAddress',
        'XmlHttpRequest' => true,
        '_routeScope' => ['storefront'],
    ]
);
$routeForAjaxAddressSaving->setOptions(['seo' => false]);
$routeForAjaxAddressSaving->setMethods(['POST']); // Only allow POST.
$routes->add('frontend.endereco.account.address.edit.save', $routeForAjaxAddressSaving);

$addressCheckProxyRoute = new Route(
    '/endereco/address-check',
    [
        '_controller' => AddressCheckProxyController::class,
        'XmlHttpRequest' => true,
        'csrf_protected' => false,
        '_routeScope' => ['storefront'],
    ]
);
$addressCheckProxyRoute->setOptions(['seo' => false]);
$addressCheckProxyRoute->setMethods(['POST']); // Only allow POST.
$routes->add('frontend.endereco.address.check', $addressCheckProxyRoute);

/**
 * Return the collection of routes
 * @return RouteCollection
 */
return $routes;

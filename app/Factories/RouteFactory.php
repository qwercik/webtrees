<?php

/**
 * webtrees: online genealogy
 * Copyright (C) 2022 webtrees development team
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

declare(strict_types=1);

namespace Fisharebest\Webtrees\Factories;

use Aura\Router\RouterContainer;
use Fisharebest\Webtrees\Contracts\RouteFactoryInterface;
use Fisharebest\Webtrees\Html;
use Fisharebest\Webtrees\Validator;
use Psr\Http\Message\ServerRequestInterface;

use function app;
use function array_filter;
use function assert;
use function parse_url;
use function strlen;
use function substr;

use const ARRAY_FILTER_USE_KEY;
use const PHP_URL_PATH;

/**
 * Make a URL for a route.
 */
class RouteFactory implements RouteFactoryInterface
{
    /**
     * Generate a URL for a named route.
     *
     * @param string                                    $route_name
     * @param array<bool|int|string|array<string>|null> $parameters
     *
     * @return string
     */
    public function route(string $route_name, array $parameters = []): string
    {
        $request = app(ServerRequestInterface::class);
        assert($request instanceof ServerRequestInterface);

        $base_url = Validator::attributes($request)->string('base_url');

        $router_container = app(RouterContainer::class);
        assert($router_container instanceof RouterContainer);

        $route = $router_container->getMap()->getRoute($route_name);

        // Generate the URL.
        $url = $router_container->getGenerator()->generate($route_name, $parameters);

        // Aura ignores parameters that are not tokens.  We need to add them as query parameters.
        $parameters = array_filter($parameters, static function (string $key) use ($route): bool {
            return !str_contains($route->path, '{' . $key . '}') && !str_contains($route->path, '{/' . $key . '}');
        }, ARRAY_FILTER_USE_KEY);

        if (Validator::attributes($request)->boolean('rewrite_urls', false)) {
            // Make the pretty URL absolute.
            $base_path = parse_url($base_url, PHP_URL_PATH) ?: '';
            $url       = $base_url . substr($url, strlen($base_path));
        } else {
            // Turn the pretty URL into an ugly one.
            $path       = parse_url($url, PHP_URL_PATH);
            $parameters = ['route' => $path] + $parameters;
            $url        = $base_url . '/index.php';
        }

        return Html::url($url, $parameters);
    }
}

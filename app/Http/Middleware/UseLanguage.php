<?php

/**
 * webtrees: online genealogy
 * Copyright (C) 2019 webtrees development team
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

declare(strict_types=1);

namespace Fisharebest\Webtrees\Http\Middleware;

use Fisharebest\Localization\Locale;
use Fisharebest\Localization\Locale\LocaleEnUs;
use Fisharebest\Localization\Locale\LocaleInterface;
use Fisharebest\Webtrees\I18N;
use Fisharebest\Webtrees\Module\LanguageEnglishUnitedStates;
use Fisharebest\Webtrees\Module\ModuleLanguageInterface;
use Fisharebest\Webtrees\Services\ModuleService;
use Fisharebest\Webtrees\Session;
use Fisharebest\Webtrees\Tree;
use Generator;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

use function app;

/**
 * Middleware to select a language.
 */
class UseLanguage implements MiddlewareInterface
{
    /** @var ModuleService */
    private $module_service;

    /**
     * UseTheme constructor.
     *
     * @param ModuleService $module_service
     */
    public function __construct(ModuleService $module_service)
    {
        $this->module_service = $module_service;
    }

    /**
     * @param ServerRequestInterface  $request
     * @param RequestHandlerInterface $handler
     *
     * @return ResponseInterface
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        foreach ($this->languages($request) as $language) {
            if ($language instanceof ModuleLanguageInterface) {
                I18N::init($language->locale()->languageTag());
                Session::put('language', $language->locale()->languageTag());
                break;
            }
        }

        return $handler->handle($request);
    }

    /**
     * The language can be chosen in various ways.
     * Language module names have the form "language-<code>>".
     *
     * @param ServerRequestInterface $request
     *
     * @return Generator<ModuleLanguageInterface|null>
     */
    private function languages(ServerRequestInterface $request): Generator
    {
        $tree = $request->getAttribute('tree');

        $languages = $this->module_service->findByInterface(ModuleLanguageInterface::class);

        // Last language used
        yield $languages->get('language-' . Session::get('language', ''));

        // Browser negotiation
        $locales = $this->module_service->findByInterface(ModuleLanguageInterface::class, true)
            ->map(static function (ModuleLanguageInterface $module): LocaleInterface {
                return $module->locale();
            });

        if ($tree instanceof Tree) {
            $default = Locale::create($tree->getPreference('LANGUAGE', 'en-US'));
        } else {
            $default = new LocaleEnUs();
        }

        $locale = Locale::httpAcceptLanguage($request->getServerParams(), $locales->all(), $default);

        yield $languages->get('language-' . $locale->languageTag());

        // No languages enabled?  Use en-US
        yield app(LanguageEnglishUnitedStates::class);
    }
}

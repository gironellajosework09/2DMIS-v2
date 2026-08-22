<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class AuthorizationArchitectureTest extends TestCase
{
    /**
     * P8 A.3: action authorization is a layer ON TOP of the page gate
     * (page:* -> action:*). If any route ever carries action:<page> without
     * the matching page:<page>, the action dimension has silently become a
     * standalone authorization layer and this test fails.
     */
    public function test_every_action_gate_is_nested_inside_its_page_group(): void
    {
        $seen = [];

        foreach (Route::getRoutes() as $route) {
            $pageKeys = [];
            $actionPages = [];

            foreach ($route->gatherMiddleware() as $middleware) {
                if (! is_string($middleware)) {
                    continue;
                }

                if (str_starts_with($middleware, 'page:')) {
                    $pageKeys[] = substr($middleware, strlen('page:'));
                }

                if (str_starts_with($middleware, 'action:')) {
                    $actionPages[] = explode(',', substr($middleware, strlen('action:')))[0];
                }
            }

            foreach ($actionPages as $pageName) {
                $this->assertContains(
                    $pageName,
                    $pageKeys,
                    "Route [{$route->uri()}] enforces action:{$pageName} without page:{$pageName} — the approved PAGE ∧ ACTION composition is broken.",
                );

                foreach ($route->gatherMiddleware() as $middleware) {
                    if (is_string($middleware) && str_starts_with($middleware, 'action:')) {
                        [$page, $action] = explode(',', substr($middleware, strlen('action:')));

                        if ($page === $pageName) {
                            $seen["{$page}|".strtoupper($action)] = true;
                        }
                    }
                }
            }
        }

        $expected = [];
        $pages = config('authorization.pages', []);

        $this->assertNotEmpty($pages, 'authorization.pages config must not be empty.');

        foreach ($pages as $pageName => $page) {
            foreach ($page['actions'] ?? [] as $action) {
                if ($action !== 'VIEW') {
                    $expected["{$pageName}|{$action}"] = true;
                }
            }
        }

        $missing = array_keys(array_diff_key($expected, $seen));

        $this->assertSame(
            [],
            $missing,
            'Every non-VIEW action of every pilot page must be gated by an action: middleware instance. Missing: '.implode(', ', $missing),
        );
    }

    /**
     * P8 A.4: the authorization config shape is load-bearing for fail-closed
     * behavior (an unknown page fails open by design for non-pilot pages), so
     * the exact key set, catalogs and at-rest enforcement state are pinned.
     */
    public function test_authorization_config_declares_all_five_pilot_pages_safely(): void
    {
        $pages = config('authorization.pages');

        $this->assertIsArray($pages);
        $this->assertSame(
            ['clients.php', 'household.php', 'all_transactions.php', 'scholars.php', 'register.php'],
            array_keys($pages),
            'The pilot page key set changed — flipping/removal is an audited cutover act, not a config edit.',
        );

        $catalog = config('authorization.catalog');
        $this->assertSame(['VIEW', 'CREATE', 'EDIT', 'DELETE', 'EXPORT'], $catalog);

        $expectedCatalogs = [
            'clients.php' => ['VIEW', 'CREATE', 'EDIT', 'DELETE'],
            'household.php' => ['VIEW', 'CREATE', 'DELETE'],
            'all_transactions.php' => ['VIEW', 'CREATE', 'EDIT', 'DELETE', 'EXPORT'],
            'scholars.php' => ['VIEW', 'CREATE', 'EDIT'],
            'register.php' => ['CREATE'],
        ];

        foreach ($pages as $pageName => $page) {
            $this->assertArrayHasKey('enforcement', $page, "{$pageName} is missing its enforcement flag.");
            $this->assertIsBool($page['enforcement']);
            $this->assertFalse(
                $page['enforcement'],
                "{$pageName} ships unenforced; enabling it is an owner-approved cutover step.",
            );
            $this->assertNotEmpty($page['actions'], "{$pageName} has no action catalog.");

            foreach ($page['actions'] as $action) {
                $this->assertContains($action, $catalog, "{$pageName} declares unknown action {$action}.");
            }
        }

        foreach ($expectedCatalogs as $pageName => $catalog) {
            $this->assertSame($catalog, $pages[$pageName]['actions'], "Action catalog drift on {$pageName}.");
        }
    }
}

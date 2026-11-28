<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class RouteRegistryTest extends TestCase
{
    public function test_sanctum_scan_route_names_are_unique(): void
    {
        $names = [];

        foreach (Route::getRoutes() as $route) {
            $name = $route->getName();

            if ($name !== null && str_contains($name, 'sanctum')) {
                $names[] = $name;
            }
        }

        $this->assertNotSame([], $names);

        $duplicates = array_keys(array_filter(
            array_count_values($names),
            fn (int $count): bool => $count > 1
        ));

        $this->assertSame(
            [],
            array_values($duplicates),
            'Duplicate Sanctum Scan route names: '.implode(', ', $duplicates)
        );
    }

    public function test_settlement_and_public_scan_routes_resolve_to_callable_actions(): void
    {
        $routes = Route::getRoutes();

        $show = $routes->getByName('admin.sanctum.exchanges.settlement.show');
        $transition = $routes->getByName('admin.sanctum.exchanges.settlement.transition');

        $this->assertNotNull($show);
        $this->assertNotNull($transition);
        $this->assertContains('GET', $show->methods());
        $this->assertContains('POST', $transition->methods());
        $this->assertStringContainsString(
            'SanctumScanExchangeSettlementController@show',
            $show->getActionName()
        );
        $this->assertStringContainsString(
            'SanctumScanExchangeSettlementController@transition',
            $transition->getActionName()
        );
        $this->assertNotNull($routes->getByName('sanctum.scan.public'));
    }

    public function test_route_names_are_globally_unique_outside_the_tracked_legacy_allowlist(): void
    {
        $allowlist = $this->allowlist()['duplicate_names'];
        $counts = [];

        foreach (Route::getRoutes() as $route) {
            $name = $route->getName();

            if ($name !== null) {
                $counts[$name] = ($counts[$name] ?? 0) + 1;
            }
        }

        $duplicates = array_keys(array_filter($counts, fn (int $count): bool => $count > 1));
        $unexpected = array_values(array_diff($duplicates, $allowlist));

        $this->assertSame(
            [],
            $unexpected,
            'Duplicate route names outside tools/route-registry-allowlist.php: '.implode(', ', $unexpected)
        );

        $stale = array_values(array_diff($allowlist, $duplicates));

        $this->assertSame(
            [],
            $stale,
            'Allowlisted duplicate route names are no longer duplicated; remove them: '.implode(', ', $stale)
        );
    }

    public function test_every_controller_route_action_resolves_to_an_existing_class_and_method(): void
    {
        $allowlist = $this->allowlist()['unresolvable_actions'];
        $failures = [];

        foreach (Route::getRoutes() as $route) {
            $action = $route->getActionName();

            if ($action === 'Closure') {
                continue;
            }

            [$class, $method] = str_contains($action, '@')
                ? explode('@', $action, 2)
                : [$action, '__invoke'];

            $signature = $this->actionSignature($route)." -> {$action}";

            if (! class_exists($class)) {
                $failures[$signature] = "{$signature} (missing class {$class})";

                continue;
            }

            if (! method_exists($class, $method)) {
                $failures[$signature] = "{$signature} (missing method {$class}::{$method})";
            }
        }

        $unexpected = array_values(array_diff(array_keys($failures), $allowlist));

        $this->assertSame(
            [],
            $unexpected,
            "Routes with unresolvable actions:\n".implode(
                "\n",
                array_map(fn (string $key): string => $failures[$key], $unexpected)
            )
        );

        $stale = array_values(array_diff($allowlist, array_keys($failures)));

        $this->assertSame([], $stale, 'Allowlisted unresolvable actions now resolve; remove them: '.implode(', ', $stale));
    }

    public function test_state_changing_routes_declare_middleware_outside_the_global_stack(): void
    {
        $allowlist = $this->allowlist()['public_write_routes'];
        $violations = [];

        foreach (Route::getRoutes() as $route) {
            $methods = array_values(array_intersect(['POST', 'PUT', 'PATCH'], $route->methods()));

            if ($methods === []) {
                continue;
            }

            $nonGlobal = array_filter(
                $route->middleware(),
                fn ($middleware): bool => ! in_array($middleware, ['web', 'api'], true)
            );

            if ($nonGlobal !== []) {
                continue;
            }

            $violations[] = $this->actionSignature($route);
        }

        $unexpected = array_values(array_diff($violations, $allowlist));

        $this->assertSame(
            [],
            $unexpected,
            "Write routes with no middleware outside the global stack:\n".implode("\n", $unexpected)
        );

        $stale = array_values(array_diff($allowlist, $violations));

        $this->assertSame(
            [],
            $stale,
            'Allowlisted public write routes are no longer middleware-free; remove them: '.implode(', ', $stale)
        );
    }

    public function test_pusher_beams_routes_keep_their_participant_authentication_middleware(): void
    {
        $expectedMiddleware = [
            'api/user/pusher/beams-auth' => 'auth.api',
            'agent-api/agent/pusher/beams-auth' => 'agent.api',
            'merchant-api/merchant/pusher/beams-auth' => 'merchant.api',
        ];

        foreach ($expectedMiddleware as $uri => $requiredMiddleware) {
            $route = null;

            foreach (Route::getRoutes() as $registeredRoute) {
                if (
                    $registeredRoute->uri() === $uri
                    && in_array('GET', $registeredRoute->methods(), true)
                ) {
                    $route = $registeredRoute;
                    break;
                }
            }

            $this->assertNotNull($route, "Expected Pusher Beams route is missing: {$uri}");
            $this->assertContains(
                $requiredMiddleware,
                $route->middleware(),
                "Pusher Beams route must retain {$requiredMiddleware}: {$uri}"
            );
        }
    }

    /**
     * @return array{duplicate_names: list<string>, public_write_routes: list<string>, unresolvable_actions: list<string>}
     */
    private function allowlist(): array
    {
        return require base_path('tools/route-registry-allowlist.php');
    }

    private function actionSignature(\Illuminate\Routing\Route $route): string
    {
        $methods = array_values(array_intersect(['POST', 'PUT', 'PATCH', 'DELETE', 'GET'], $route->methods()));

        return ($methods[0] ?? 'GET').' '.$route->uri();
    }
}

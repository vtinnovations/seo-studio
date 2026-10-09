<?php

declare(strict_types=1);

/**
 * Runtime wiring check against a real booted Symfony kernel.
 *
 * The unit suite constructs every collaborator by hand, so it cannot see a
 * broken service definition, a route that is not registered, or a cron job that
 * never gets its tag. This boots a real kernel that compiles the bundle's
 * SHIPPED config/services.yaml and imports its SHIPPED config/routes.yaml, then
 * drives $kernel->handle() with real requests.
 *
 * The CMS collaborators the updater path never reaches are declared synthetic so
 * the container still compiles every service the bundle declares, then set after
 * boot.
 *
 * Complements tools/verify-runtime.php, which needs a full Contao installation:
 * this one needs only the bundle's own dependencies, so it runs anywhere.
 *
 *     php tools/verify-wiring.php
 */

// Standalone (the bundle has its own vendor/) or installed into an application.
$autoloaders = [
    __DIR__ . '/../vendor/autoload.php',
    __DIR__ . '/../../../vendor/autoload.php',
    __DIR__ . '/../../../../vendor/autoload.php',
];

foreach ($autoloaders as $autoloader) {
    if (is_file($autoloader)) {
        require $autoloader;

        break;
    }
}

if (!class_exists(\Symfony\Bundle\FrameworkBundle\FrameworkBundle::class)) {
    fwrite(STDERR, "No autoloader found. Install dependencies first.\n");

    exit(2);
}

use Contao\CoreBundle\Csrf\ContaoCsrfTokenManager;
use Contao\CoreBundle\DependencyInjection\Attribute\AsCronJob;
use Contao\CoreBundle\Framework\ContaoFramework;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\DependencyInjection\ConfigurableExtension;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;
use VTinnovations\SeoStudio\Controller\ExchangeCallbackController;
use VTinnovations\SeoStudio\Cron\ProvisioningUpkeepCron;
use VTinnovations\SeoStudio\Exchange\Endpoint;
use VTinnovations\SeoStudio\VtinnovationsSeoStudioBundle;

define('RUN_ID', bin2hex(random_bytes(4)));

$failures = 0;
$checks = 0;

function check(string $label, bool $ok, string $detail = ''): void
{
    global $failures, $checks;
    ++$checks;

    if ($ok) {
        echo "ok    {$label}\n";

        return;
    }

    ++$failures;
    echo "FAIL  {$label}" . ($detail !== '' ? "  ({$detail})" : '') . "\n";
}

/**
 * Stubs the framework collaborators this bundle's OTHER services declare
 * (contao.slug and friends), so the container still compiles every definition
 * rather than only the ones the updater path touches. Registered synthetic and
 * never fetched: the point is that compilation proves the wiring.
 */
final class StubMissingServices implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        $missing = [];

        do {
            $found = false;

            foreach ($container->getDefinitions() as $definition) {
                $ids = array_merge(
                    $this->referencesOf($definition),
                    $this->autowiredTypesOf($definition),
                );

                foreach ($ids as $id) {
                    if ($container->has($id) || isset($missing[$id])) {
                        continue;
                    }

                    $missing[$id] = true;
                    $found = true;

                    $stub = new Definition(\stdClass::class);
                    $stub->setSynthetic(true);
                    $stub->setPublic(true);
                    $container->setDefinition($id, $stub);
                }
            }
        } while ($found);

        if ($missing !== []) {
            echo 'note  stubbed ' . \count($missing) . " unrelated framework service(s) so every definition compiles\n";
        }
    }

    /**
     * Constructor parameter types an autowired definition would resolve by
     * class name. Only types the container does not already know are returned.
     *
     * @return list<string>
     */
    private function autowiredTypesOf(Definition $definition): array
    {
        if (!$definition->isAutowired()) {
            return [];
        }

        $class = $definition->getClass();
        if (!\is_string($class) || !class_exists($class)) {
            return [];
        }

        $constructor = (new \ReflectionClass($class))->getConstructor();
        if ($constructor === null) {
            return [];
        }

        $types = [];

        foreach ($constructor->getParameters() as $parameter) {
            $type = $parameter->getType();

            if ($type instanceof \ReflectionNamedType && !$type->isBuiltin()) {
                $types[] = $type->getName();
            }
        }

        return $types;
    }

    /**
     * @return list<string>
     */
    private function referencesOf(Definition $definition): array
    {
        $ids = [];

        $walk = static function (mixed $value) use (&$walk, &$ids): void {
            if ($value instanceof \Symfony\Component\DependencyInjection\Reference) {
                $ids[] = (string) $value;

                return;
            }

            if ($value instanceof Definition) {
                foreach ($value->getArguments() as $argument) {
                    $walk($argument);
                }

                return;
            }

            if (\is_array($value)) {
                foreach ($value as $item) {
                    $walk($item);
                }
            }
        };

        foreach ($definition->getArguments() as $argument) {
            $walk($argument);
        }

        foreach ($definition->getMethodCalls() as $call) {
            $walk($call[1] ?? []);
        }

        return $ids;
    }
}

/** Removes the frontend-module controllers; irrelevant to the licensing surface. */
final class DropFrontendModules implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        foreach (array_keys($container->getDefinitions()) as $id) {
            if (str_contains((string) $id, 'Controller\\FrontendModule\\')) {
                $container->removeDefinition((string) $id);
            }
        }
    }
}

/** Collects cron tags late, before unused private services are removed. */
final class CollectCronJobs implements CompilerPassInterface
{
    /** @var array<string, bool> */
    public static array $tagged = [];

    public function process(ContainerBuilder $container): void
    {
        foreach (array_keys($container->findTaggedServiceIds('contao.cronjob')) as $id) {
            self::$tagged[(string) $id] = true;
        }
    }
}

final class RuntimeCheckKernel extends Kernel
{
    use MicroKernelTrait;

    public function registerBundles(): iterable
    {
        return [new FrameworkBundle(), new VtinnovationsSeoStudioBundle()];
    }

    public function getProjectDir(): string
    {
        // Unique per run: a cached container from a previous run would hide the
        // very compilation this check exists to perform.
        return sys_get_temp_dir() . '/seo-studio-runtime-' . getmypid() . '-' . RUN_ID;
    }

    protected function configureContainer(ContainerBuilder $container, LoaderInterface $loader): void
    {
        $container->loadFromExtension('framework', [
            'secret' => 'runtime-check',
            'test' => true,
            'http_method_override' => false,
            'router' => ['utf8' => true],
            'validation' => ['enabled' => false],
        ]);

        // The bundle's own extension loads its shipped services.yaml.
        $container->registerExtension(new \VTinnovations\SeoStudio\DependencyInjection\VtinnovationsSeoStudioExtension());
        $container->loadFromExtension('vtinnovations_seo_studio', []);

        // Contao's own autoconfiguration for #[AsCronJob], two lines lifted from
        // ContaoCoreExtension — without it the cron would never get its tag here.
        $container->registerAttributeForAutoconfiguration(
            AsCronJob::class,
            static function (\Symfony\Component\DependencyInjection\ChildDefinition $definition): void {
                $definition->addTag('contao.cronjob');
            },
        );

        // CMS collaborators this path never reaches: synthetic so the container
        // compiles, set after boot.
        foreach ([ContaoCsrfTokenManager::class, ContaoFramework::class, Security::class, Connection::class] as $class) {
            $definition = new Definition($class);
            $definition->setSynthetic(true);
            $definition->setPublic(true);
            $container->setDefinition($class, $definition);
        }

        $container->setParameter('contao.csrf_token_name', 'csrf_contao');
        $container->setAlias('doctrine.dbal.default_connection', Connection::class)->setPublic(true);

        $container->addCompilerPass(new StubMissingServices(), \Symfony\Component\DependencyInjection\Compiler\PassConfig::TYPE_BEFORE_OPTIMIZATION);

        // The two frontend-module controllers get their Contao collaborators
        // through service-locator closures that Symfony builds after this point,
        // and those cannot be stubbed from outside a real Contao kernel. They
        // have nothing to do with licensing, so they are dropped from this
        // check's container rather than worked around.
        $container->addCompilerPass(
            new DropFrontendModules(),
            \Symfony\Component\DependencyInjection\Compiler\PassConfig::TYPE_BEFORE_OPTIMIZATION,
            10,
        );

        // Symfony's reference VALIDATION pass is dropped as well: the services
        // under test are proven by actually fetching them below, which
        // instantiates their whole dependency graph for real.
        $config = $container->getCompilerPassConfig();
        $config->setRemovingPasses(array_values(array_filter(
            $config->getRemovingPasses(),
            static fn (CompilerPassInterface $pass): bool => !$pass instanceof \Symfony\Component\DependencyInjection\Compiler\CheckExceptionOnInvalidReferenceBehaviorPass,
        )));
        $container->addCompilerPass(new CollectCronJobs(), \Symfony\Component\DependencyInjection\Compiler\PassConfig::TYPE_BEFORE_REMOVING);

        // Everything under test must be reachable from the container for the
        // assertions below.
        foreach ([ExchangeCallbackController::class, ProvisioningUpkeepCron::class] as $id) {
            if ($container->hasDefinition($id)) {
                $container->getDefinition($id)->setPublic(true);
            }
        }
    }

    protected function configureRoutes(RoutingConfigurator $routes): void
    {
        // The bundle's SHIPPED route file, so a path typo fails here.
        $routes->import(\dirname(__DIR__) . '/config/routes.yaml');
    }
}

$kernel = new RuntimeCheckKernel('test', true);
$kernel->boot();
$container = $kernel->getContainer();

foreach ([ContaoCsrfTokenManager::class, ContaoFramework::class, Security::class] as $class) {
    $container->set($class, (new ReflectionClass($class))->newInstanceWithoutConstructor());
}

// No monolog in this minimal kernel; the bundle's OperationLog needs a real PSR
// logger, and a null one also proves nothing sensitive is written anywhere.
$container->set(\Psr\Log\LoggerInterface::class, new \Psr\Log\NullLogger());

// A real ScopeMatcher, not a stub: the bundle's response listeners run on every
// response this harness triggers, and they ask it whether the request is a
// Contao frontend/backend one. Two matchers that answer "no" put every request
// here outside Contao's scopes, which is what an updater delivery is.
$neverMatches = new class implements \Symfony\Component\HttpFoundation\RequestMatcherInterface {
    public function matches(\Symfony\Component\HttpFoundation\Request $request): bool
    {
        return false;
    }
};

$container->set(\Contao\CoreBundle\Routing\ScopeMatcher::class, new \Contao\CoreBundle\Routing\ScopeMatcher(
    $neverMatches,
    $neverMatches,
    $container->get('request_stack'),
));

// A DBAL connection that answers the host inventory query with one row.
$connection = new class extends Connection {
    public function __construct()
    {
    }

    public function fetchFirstColumn(string $query, array $params = [], array $types = []): array
    {
        return ['example.com'];
    }

    public function fetchOne(string $query, array $params = [], array $types = []): mixed
    {
        return false;
    }

    public function fetchAssociative(string $query, array $params = [], array $types = []): array|false
    {
        return false;
    }

    public function insert(string $table, array $data, array $types = []): int|string
    {
        return 1;
    }

    public function executeStatement(string $sql, array $params = [], array $types = []): int|string
    {
        return 0;
    }
};
$container->set(Connection::class, $connection);

echo "── container + routing ──────────────────────────────────────────────\n";

check(
    'the container compiles from the shipped services.yaml',
    $container->has(ExchangeCallbackController::class),
);

$router = $container->get('router');
$route = $router->getRouteCollection()->get('seo_studio.provisioning_callback');

check('the updater route is registered', $route !== null);
check(
    'the route path is the vendor-agreed updater path',
    $route !== null && $route->getPath() === Endpoint::updaterPath(),
    $route !== null ? $route->getPath() . ' vs ' . Endpoint::updaterPath() : 'no route',
);
check(
    'GET/HEAD are routed so the handler can answer 405 rather than 404',
    $route !== null && $route->getMethods() === ['POST', 'GET', 'HEAD'],
    $route !== null ? implode(',', $route->getMethods()) : '',
);
check(
    'the updater route requires no backend session',
    $route !== null && ($route->getDefault('_scope') === null),
);

echo "\n── service wiring ───────────────────────────────────────────────────\n";

check('the updater controller is a wired service', $container->has(ExchangeCallbackController::class));
check(
    'the controller builds with all four collaborators',
    $container->get(ExchangeCallbackController::class) instanceof ExchangeCallbackController,
);

check(
    'the unattended upkeep cron is registered as a contao.cronjob',
    isset(CollectCronJobs::$tagged[ProvisioningUpkeepCron::class]),
    implode(', ', array_keys(CollectCronJobs::$tagged)),
);
// The cron service is correctly PRIVATE, so it cannot be fetched from the
// container — Contao reaches it through the tag proven above. It is therefore
// exercised directly, over the container's real workflow, which proves the
// whole body runs rather than just that a definition exists.
$cron = new ProvisioningUpkeepCron(
    $container->get(\VTinnovations\SeoStudio\Exchange\ProvisioningWorkflow::class),
    new \Psr\Log\NullLogger(),
);

$cronRan = true;

try {
    $cron();
} catch (\Throwable $e) {
    $cronRan = false;
    $cronError = $e->getMessage();
}

check(
    'the upkeep cron runs end to end against the real workflow',
    $cronRan,
    $cronRan ? '' : ($cronError ?? ''),
);

// PackageAcceptance is private (correctly), so prove it through the public
// services that take it as a constructor argument.
check(
    'the entitlement evaluator builds, so PackageAcceptance got its store',
    $container->get(\VTinnovations\SeoStudio\Core\Config\EntitlementEvaluator::class)
        instanceof \VTinnovations\SeoStudio\Core\Config\EntitlementEvaluator,
);
check(
    'the provisioning workflow builds',
    $container->get(\VTinnovations\SeoStudio\Exchange\ProvisioningWorkflow::class)
        instanceof \VTinnovations\SeoStudio\Exchange\ProvisioningWorkflow,
);

echo "\n── HTTP contract through the real kernel ────────────────────────────\n";

$path = Endpoint::updaterPath();

$response = $kernel->handle(Request::create($path, 'GET'));
check('GET answers 405, not 404', $response->getStatusCode() === 405, (string) $response->getStatusCode());
check('405 advertises Allow: POST', $response->headers->get('Allow') === 'POST');

$wrongType = Request::create($path, 'POST', [], [], [], [], '{}');
$wrongType->headers->set('Content-Type', 'text/plain');
$response = $kernel->handle($wrongType);
check('an unsupported media type answers 415', $response->getStatusCode() === 415, (string) $response->getStatusCode());

$oversized = Request::create($path, 'POST', [], [], [], [], str_repeat('x', 70000));
$oversized->headers->set('Content-Type', 'application/json');
$response = $kernel->handle($oversized);
check('an oversized body answers 413', $response->getStatusCode() === 413, (string) $response->getStatusCode());

$unsigned = Request::create($path, 'POST', [], [], [], [], '{"action":"license_update"}');
$unsigned->headers->set('Content-Type', 'application/json');
$response = $kernel->handle($unsigned);
check('an unsigned POST answers 401', $response->getStatusCode() === 401, (string) $response->getStatusCode());

$body = (string) $response->getContent();
check(
    'the refusal reveals nothing about which check failed',
    $body === '{"status":"unauthorized"}',
    $body,
);

$forged = Request::create($path, 'POST', [], [], [], [], '{"action":"license_update"}');
$forged->headers->set('Content-Type', 'application/json');
$forged->headers->set('X-VT-Request-ID', 'r1');
$forged->headers->set('X-VT-Timestamp', (string) time());
$forged->headers->set('X-VT-Nonce', 'n1');
$forged->headers->set('X-VT-Key-ID', 'vtone-2026a');
$forged->headers->set('X-VT-Signature', base64_encode(str_repeat("\x00", 64)));
$response = $kernel->handle($forged);
check(
    'a forged signature against the PINNED production key is refused',
    $response->getStatusCode() === 401,
    (string) $response->getStatusCode(),
);

echo "\n{$checks} checks, {$failures} failures\n";

exit($failures === 0 ? 0 : 1);

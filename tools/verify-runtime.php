<?php

declare(strict_types=1);

/**
 * Runtime acceptance inside a real Contao installation.
 *
 * Run from the Contao project root:
 *
 *     php packages/vtinnovations-seo-studio/tools/verify-runtime.php
 *
 * It proves the things static review cannot: that the container compiles, that
 * the licence section really appears in the Contao → Settings palette, that the
 * rendered controls exist and carry Contao's request token, that each control's
 * POST reaches a handler and moves the authoritative state, and that the public
 * updater path answers 405/401 through the real HTTP kernel.
 *
 * Vendor traffic is never sent: the workflow under test is built with a mocked
 * HTTP client and a test-only key ring, because a genuine licence cannot (and
 * must not) be forged locally.
 */

$projectDir = getcwd();

if (!is_file($projectDir . '/vendor/autoload.php')) {
    fwrite(STDERR, "Run this from the Contao project root.\n");

    exit(2);
}

require $projectDir . '/vendor/autoload.php';

use Contao\CoreBundle\Csrf\ContaoCsrfTokenManager;
use Contao\Controller;
use Contao\ManagerBundle\HttpKernel\ContaoKernel;
use Contao\System;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Attribute\AttributeBag;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use VTinnovations\SeoStudio\Core\Config\EntitlementEvaluator;
use VTinnovations\SeoStudio\Core\Config\EntitlementState;
use VTinnovations\SeoStudio\Core\Config\InstanceSettingsListener;
use VTinnovations\SeoStudio\Core\Config\InstanceStatePanel;
use VTinnovations\SeoStudio\Core\Config\PackagePolicy;
use VTinnovations\SeoStudio\Core\Config\ProvisioningStore;
use VTinnovations\SeoStudio\Core\Content\HostInventory;
use VTinnovations\SeoStudio\Core\Content\HostName;
use VTinnovations\SeoStudio\Core\Security\CanonicalForm;
use VTinnovations\SeoStudio\Core\Security\SignatureVerifier;
use VTinnovations\SeoStudio\Core\Security\TrustAnchor;
use VTinnovations\SeoStudio\Core\Security\TrustAnchors;
use VTinnovations\SeoStudio\Exchange\Endpoint;
use VTinnovations\SeoStudio\Exchange\EntryClaim;
use VTinnovations\SeoStudio\Exchange\Journal;
use VTinnovations\SeoStudio\Exchange\OperationLog;
use VTinnovations\SeoStudio\Exchange\PackageAcceptance;
use VTinnovations\SeoStudio\Exchange\ProvisioningWorkflow;
use VTinnovations\SeoStudio\Exchange\SignalTransport;
use VTinnovations\SeoStudio\Exchange\VerifyClient;

$failures = [];
$checks = 0;

function check(string $label, bool $ok, string $detail = ''): void
{
    global $failures, $checks;

    ++$checks;

    if ($ok) {
        fwrite(STDOUT, "ok    $label\n");

        return;
    }

    $failures[] = $label . ($detail !== '' ? ' — ' . $detail : '');
    fwrite(STDERR, "FAIL  $label" . ($detail !== '' ? ' — ' . $detail : '') . "\n");
}

// ── Boot the real kernel ─────────────────────────────────────────────────────
$kernel = ContaoKernel::fromInput($projectDir, new ArgvInput([]));
$kernel->boot();
$container = $kernel->getContainer();

check('the container compiles with the bundle installed', true);

foreach ([
    EntitlementEvaluator::class,
    ProvisioningWorkflow::class,
    EntryClaim::class,
    \VTinnovations\SeoStudio\Core\Content\SiteInventory::class,
] as $id) {
    check(sprintf('service %s resolves', $id), $container->has($id) && $container->get($id) !== null);
}

// ── Route registration ───────────────────────────────────────────────────────
$router = $container->get('router');
$routes = $router->getRouteCollection();
$route = $routes->get('seo_studio.provisioning_callback');

check('the updater route is registered', $route !== null);
check(
    'the updater path is exactly the documented one',
    $route !== null && $route->getPath() === Endpoint::updaterPath(),
    $route !== null ? $route->getPath() : 'missing',
);
check(
    'the updater route points at the callback controller',
    $route !== null && str_contains((string) $route->getDefault('_controller'), 'ExchangeCallbackController'),
);
check('the updater route disables the browser token check', $route !== null && $route->getDefault('_token_check') === false);
check(
    'GET is routed too so the handler can answer 405 rather than 404',
    $route !== null && \in_array('GET', $route->getMethods(), true) && \in_array('POST', $route->getMethods(), true),
);

// Every backend AJAX route still requires Contao's token check.
foreach ($routes as $name => $definition) {
    if (str_starts_with($name, 'seo_studio.') && str_starts_with($definition->getPath(), '/contao/seostudio')) {
        check(
            sprintf('%s enforces the request token', $name),
            $definition->getDefault('_token_check') === true,
        );
    }
}

// ── Contao framework: DCA + language + widget ────────────────────────────────
$requestStack = $container->get('request_stack');
\assert($requestStack instanceof RequestStack);

$session = new Session(new MockArraySessionStorage());
$session->registerBag(new AttributeBag('contao_backend'));

$backendRequest = Request::create('https://example.com/contao?do=settings');
$backendRequest->attributes->set('_scope', 'backend');
$backendRequest->setSession($session);
$requestStack->push($backendRequest);

$container->get('contao.framework')->initialize();

// A configured site-root domain is what gives an installation an identity, so
// the panel and the host policy can only be exercised with one. It is created
// here and removed again at the end, leaving the installation as it was.
$connection = $container->get('database_connection');
$runtimeHost = 'seo-studio-runtime.example.com';

$connection->insert('tl_page', [
    'pid' => 0,
    'sorting' => 128,
    'tstamp' => time(),
    'title' => 'SEO Studio runtime check',
    'alias' => 'seo-studio-runtime-check',
    'type' => 'root',
    'dns' => $runtimeHost,
    'language' => 'de',
    'fallback' => '1',
    'published' => '1',
]);

$runtimeRootId = (int) $connection->lastInsertId();

System::loadLanguageFile('default');
System::loadLanguageFile('tl_settings');
Controller::loadDataContainer('tl_settings');

check(
    'the licence field is added to tl_settings',
    isset($GLOBALS['TL_DCA']['tl_settings']['fields']['vtoneSeoStudioLicence']),
);
check(
    'the field uses the licence panel widget',
    ($GLOBALS['TL_DCA']['tl_settings']['fields']['vtoneSeoStudioLicence']['inputType'] ?? null) === 'seoStudioProvisioningPanel',
);
check(
    'the widget is registered in BE_FFL',
    ($GLOBALS['BE_FFL']['seoStudioProvisioningPanel'] ?? null) === InstanceStatePanel::class,
);
check(
    'the licence section is part of the Settings palette',
    str_contains((string) ($GLOBALS['TL_DCA']['tl_settings']['palettes']['default'] ?? ''), '{vtone_licence_legend},vtoneSeoStudioLicence'),
);
check(
    'the shared legend headline is exactly "V-T.ONE Licence management"',
    ($GLOBALS['TL_LANG']['tl_settings']['vtone_licence_legend'] ?? null) === 'V-T.ONE Licence management',
    (string) ($GLOBALS['TL_LANG']['tl_settings']['vtone_licence_legend'] ?? 'missing'),
);
check(
    'the field label is exactly the project title',
    ($GLOBALS['TL_DCA']['tl_settings']['fields']['vtoneSeoStudioLicence']['label'][0] ?? null) === PackagePolicy::TITLE,
    (string) ($GLOBALS['TL_DCA']['tl_settings']['fields']['vtoneSeoStudioLicence']['label'][0] ?? 'missing'),
);
check(
    'the palette carries no second licence section',
    substr_count((string) ($GLOBALS['TL_DCA']['tl_settings']['palettes']['default'] ?? ''), 'vtoneSeoStudioLicence') === 1,
);
check(
    'the tl_settings onsubmit callback is registered',
    !empty($GLOBALS['TL_DCA']['tl_settings']['config']['onsubmit_callback']),
);

// Render the panel exactly as Contao would.
$container->get(\VTinnovations\SeoStudio\Core\Content\SiteInventory::class)->reset();

$widget = new InstanceStatePanel([
    'name' => 'vtoneSeoStudioLicence',
    'id' => 'vtoneSeoStudioLicence',
    'strTable' => 'tl_settings',
    'strField' => 'vtoneSeoStudioLicence',
]);

$markup = $widget->generate();

check('the panel renders server-side markup', trim($markup) !== '');
check('the panel never shows a loading placeholder', !str_contains(strtolower($markup), 'loading'));
check('the panel states the current licence status', str_contains($markup, 'tl_info') || str_contains($markup, 'tl_error') || str_contains($markup, 'tl_confirm'));
check('the panel offers a key input', str_contains($markup, 'name="vtoneSeoStudioKey"'));
check('activate is a real submit control', str_contains($markup, 'name="vtoneSeoStudioAction" value="activate"'));
check('update is a real submit control', str_contains($markup, 'name="vtoneSeoStudioAction" value="refresh"'));
check('no control is a decorative or empty action', !str_contains($markup, 'href="#"') && !str_contains($markup, 'javascript:'));
check('the panel loads no JavaScript at all', !str_contains($markup, '<script'));
check('the panel never renders a licence key', !str_contains($markup, 'license_key') && !str_contains($markup, 'licenseKey'));

// Translations resolve for real: an unresolved key would render as "[key]".
check(
    'every string on the licence screen resolves from the language files',
    preg_match('/\[[a-z][a-zA-Z0-9_]*\.[a-zA-Z0-9_.]+\]/', $markup) !== 1,
    (string) (preg_match('/\[[a-z][a-zA-Z0-9_.]+\]/', $markup, $unresolved) === 1 ? $unresolved[0] : ''),
);
check(
    'the panel shows translated text rather than raw keys',
    str_contains($markup, (string) ($GLOBALS['TL_LANG']['SEO_STUDIO']['licence']['activateButton'] ?? 'n/a')),
);

// ── Action wiring: drive the three controls through the real listener ────────
$temporaryProjectDir = sys_get_temp_dir() . '/seo-studio-runtime-' . bin2hex(random_bytes(6));
mkdir($temporaryProjectDir, 0700, true);

$seed = str_repeat("\x2a", SODIUM_CRYPTO_SIGN_SEEDBYTES);
$pair = sodium_crypto_sign_seed_keypair($seed);
$secret = sodium_crypto_sign_secretkey($pair);
$publicKey = sodium_crypto_sign_publickey($pair);

$testRing = new TrustAnchors([
    new TrustAnchor('test-key', 'ed25519', $publicKey, [
        TrustAnchor::PURPOSE_DOCUMENT,
        TrustAnchor::PURPOSE_ENVELOPE,
        TrustAnchor::PURPOSE_REQUEST,
    ], 0, null),
]);

$inventory = new class implements HostInventory {
    public function configuredHosts(): array
    {
        return ['example.com'];
    }

    public function intersect(array $signedHosts): array
    {
        return array_values(array_intersect($signedHosts, $this->configuredHosts()));
    }

    public function matchedHost(array $signedHosts): ?string
    {
        return $this->intersect($signedHosts)[0] ?? null;
    }

    public function outboundHost(): ?string
    {
        return 'example.com';
    }

    public function reset(): void
    {
    }
};

$document = [
    'schema_version' => 2,
    'project' => PackagePolicy::PROJECT,
    'project_slug' => PackagePolicy::PROJECT_SLUG,
    'license_key' => 'SS-PRO-RUNTIME-0001',
    'license_domain' => 'example.com',
    'license_domains' => ['example.com'],
    'license_max_domains' => 9999,
    'license_package' => 'pro',
    'license_features' => [],
    'license_version' => 3,
    'license_issued_at' => time() - 86400,
    'license_starts_at' => time() - 86400,
    'license_expires_at' => time() + 86400 * 365,
    'license_lifetime' => false,
    'license_verified_at' => time(),
    'free_available' => false,
    'validation_status' => 'valid',
];

$document['signature'] = base64_encode(sodium_crypto_sign_detached(
    CanonicalForm::encode(CanonicalForm::decode((string) json_encode($document, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE))),
    $secret,
));

$bytes = (string) json_encode($document, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

$envelope = [
    'project' => PackagePolicy::PROJECT,
    'project_slug' => PackagePolicy::PROJECT_SLUG,
    'license_version' => 3,
    'license_md5' => md5($bytes),
    'generated_at' => time(),
    'key_id' => 'test-key',
    'signature_algorithm' => 'ed25519',
];
$envelope['signature'] = base64_encode(sodium_crypto_sign_detached(
    CanonicalForm::encode(CanonicalForm::decode((string) json_encode($envelope))),
    $secret,
));

$requestCount = 0;
$mockClient = new MockHttpClient(function (string $method, string $url, array $options) use (&$requestCount, $bytes, $envelope): MockResponse {
    ++$requestCount;

    /** @var array<string, mixed> $body */
    $body = json_decode((string) $options['body'], true);

    return new MockResponse((string) json_encode([
        'status' => 'valid',
        'request_id' => $body['request_id'],
        'server_time' => time(),
        'license_payload_b64' => base64_encode($bytes),
        'integrity' => $envelope,
    ]), ['http_code' => 200, 'response_headers' => ['content-type' => 'application/json']]);
});

$log = new OperationLog($container->get('monolog.logger.contao') ?? new \Psr\Log\NullLogger());
$store = new ProvisioningStore($temporaryProjectDir);
$verifier = new SignatureVerifier($testRing);
$acceptance = new PackageAcceptance($verifier, $testRing, $inventory);
$entitlement = new EntitlementEvaluator($store, $acceptance, $inventory);
$journal = new Journal($container->get('database_connection'));

$workflow = new ProvisioningWorkflow(
    new VerifyClient($mockClient, $log),
    $acceptance,
    $store,
    $inventory,
    $entitlement,
    $journal,
    $log,
);

// TokenChecker is a private service (its autowiring into EntryClaim is proven by
// the service-resolution check above), so this stands in for an authenticated
// backend session while the workflow itself stays real.
$backendSessionStub = new class extends \Contao\CoreBundle\Security\Authentication\Token\TokenChecker {
    public function __construct()
    {
    }

    public function hasBackendUser(): bool
    {
        return true;
    }
};

$entryClaim = new EntryClaim(
    $requestStack,
    $backendSessionStub,
    $entitlement,
    new SignalTransport(new MockHttpClient(), $log, false),
);

// Contao's token storage is normally filled from the session by a request
// listener; in CLI it has to be initialized explicitly. The manager itself is
// the real Contao implementation, so the token the listener validates is a real
// Contao request token.
$tokenStorage = new \Contao\CoreBundle\Csrf\MemoryTokenStorage();
$tokenStorage->initialize([]);

$tokenName = (string) $container->getParameter('contao.csrf_token_name');
$csrfManager = new ContaoCsrfTokenManager(
    $requestStack,
    'csrf_',
    new \Symfony\Component\Security\Csrf\TokenGenerator\UriSafeTokenGenerator(),
    $tokenStorage,
);

$validToken = $csrfManager->getToken($tokenName)->getValue();

$security = $container->get('security.helper');

$listener = new InstanceSettingsListener(
    $requestStack,
    $security,
    $csrfManager,
    $workflow,
    $entitlement,
    $entryClaim,
    $tokenName,
);

$dataContainer = new class extends \Contao\DataContainer {
    public function __construct()
    {
    }

    public function copy()
    {
        return '';
    }

    public function create()
    {
        return '';
    }

    public function cut()
    {
        return '';
    }

    public function delete()
    {
        return '';
    }

    public function edit()
    {
        return '';
    }

    public function move()
    {
        return '';
    }

    public function show()
    {
        return '';
    }

    public function getPalette()
    {
        return '';
    }

    protected function save($varValue)
    {
    }
};

/**
 * Drives one settings POST exactly as the browser would.
 *
 * @param array<string, string> $fields
 */
$post = static function (array $fields) use ($requestStack, $listener, $session, $dataContainer): Request {
    $request = Request::create('https://example.com/contao?do=settings', 'POST', $fields);
    $request->attributes->set('_scope', 'backend');
    $request->setSession($session);

    $requestStack->push($request);

    try {
        $listener($dataContainer);
    } finally {
        $requestStack->pop();
    }

    return $request;
};

// Gate 1 — permissions. No authenticated administrator yet, so even a valid
// token must not reach the workflow.
$post(['vtoneSeoStudioAction' => 'activate', 'vtoneSeoStudioKey' => 'SS-PRO-RUNTIME-0001', 'REQUEST_TOKEN' => $validToken]);
check('an unauthenticated request cannot activate', !$store->exists());
check('an unauthenticated request performs no vendor request', $requestCount === 0);

// Authenticate a real administrator token in Contao's own token storage, so the
// real security helper answers ROLE_ADMIN from here on.
$container->get('security.token_storage')->setToken(
    new \Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken(
        new \Symfony\Component\Security\Core\User\InMemoryUser('runtime-admin', null, ['ROLE_ADMIN']),
        'contao_backend',
        ['ROLE_ADMIN'],
    ),
);

check('the administrator is now authenticated', $security->isGranted('ROLE_ADMIN'));

// Gate 2 — request token. Authenticated, but the posted token is wrong.
$post(['vtoneSeoStudioAction' => 'activate', 'vtoneSeoStudioKey' => 'SS-PRO-RUNTIME-0001', 'REQUEST_TOKEN' => 'wrong']);
check('an invalid request token is rejected', !$store->exists());
check('an invalid request token performs no vendor request', $requestCount === 0);

// Unknown action: nothing happens at all.
$post(['vtoneSeoStudioAction' => 'nonsense', 'REQUEST_TOKEN' => $validToken]);
check('an unknown action is ignored', !$store->exists() && $requestCount === 0);

// Activate: one local action, one mocked vendor request, state stored.
$post(['vtoneSeoStudioAction' => 'activate', 'vtoneSeoStudioKey' => 'SS-PRO-RUNTIME-0001', 'REQUEST_TOKEN' => $validToken]);
check('activation reached the vendor exchange exactly once', $requestCount === 1, 'requests: ' . $requestCount);
check('activation persisted the authoritative state', $store->exists());
check('the stored bytes are byte-identical to the issued bytes', $store->exists() && file_get_contents($temporaryProjectDir . '/var/seostudio/provisioning/record.json') === $bytes);

$entitlement->invalidate();
check('the instance is licensed after activation', $entitlement->current()->licensed);
check('the evaluated state reports the matched host', $entitlement->current()->matchedHost === 'example.com');

// Refresh: uses the stored key, sends action=refresh plus the stored version.
$refreshBodies = [];
$refreshClient = new MockHttpClient(function (string $method, string $url, array $options) use (&$refreshBodies, $bytes, $envelope): MockResponse {
    $refreshBodies[] = json_decode((string) $options['body'], true);

    return new MockResponse((string) json_encode([
        'status' => 'valid',
        'request_id' => $refreshBodies[\count($refreshBodies) - 1]['request_id'],
        'server_time' => time(),
        'license_payload_b64' => base64_encode($bytes),
        'integrity' => $envelope,
    ]), ['http_code' => 200, 'response_headers' => ['content-type' => 'application/json']]);
});

$refreshWorkflow = new ProvisioningWorkflow(
    new VerifyClient($refreshClient, $log),
    $acceptance,
    $store,
    $inventory,
    $entitlement,
    $journal,
    $log,
);

$refreshListener = new InstanceSettingsListener($requestStack, $security, $csrfManager, $refreshWorkflow, $entitlement, $entryClaim, $tokenName);

$refreshRequest = Request::create('https://example.com/contao?do=settings', 'POST', [
    'vtoneSeoStudioAction' => 'refresh',
    'vtoneSeoStudioKey' => '',
    'REQUEST_TOKEN' => $validToken,
]);
$refreshRequest->attributes->set('_scope', 'backend');
$refreshRequest->setSession($session);
$requestStack->push($refreshRequest);

try {
    $refreshListener($dataContainer);
} finally {
    $requestStack->pop();
}

check('update licence performed exactly one vendor request', \count($refreshBodies) === 1);
check('update licence uses action=refresh', ($refreshBodies[0]['action'] ?? null) === 'refresh');
check('update licence sends the stored version', ($refreshBodies[0]['current_license_version'] ?? null) === 3);
check('update licence sends no browser-supplied key', ($refreshBodies[0]['license_key'] ?? null) === 'SS-PRO-RUNTIME-0001');
check('the state survives the refresh', $store->exists());

// Remove: refused without the confirmation, then honoured.
$post(['vtoneSeoStudioAction' => 'remove', 'REQUEST_TOKEN' => $validToken]);
check('removal without confirmation keeps the licence', $store->exists());

$post(['vtoneSeoStudioAction' => 'remove', 'vtoneSeoStudioConfirmRemove' => '1', 'REQUEST_TOKEN' => $validToken]);
check('confirmed removal deletes the authoritative state', !$store->exists());

$entitlement->invalidate();
check('the instance returns to the unlicensed default', $entitlement->current()->status === EntitlementState::ABSENT);

// Retained (brownfield) state: the new path reads, refreshes and removes it.
file_put_contents($temporaryProjectDir . '/var/seostudio/provisioning/record.json', $bytes);
file_put_contents(
    $temporaryProjectDir . '/var/seostudio/provisioning/record.seal',
    (string) json_encode($envelope, JSON_UNESCAPED_SLASHES),
);
$entitlement->invalidate();
check('a pre-existing stored licence is read by the new surface', $entitlement->current()->licensed);

$post(['vtoneSeoStudioAction' => 'remove', 'vtoneSeoStudioConfirmRemove' => '1', 'REQUEST_TOKEN' => $validToken]);
check('a retained licence can be removed through the new action path', !$store->exists());

foreach (glob($temporaryProjectDir . '/var/seostudio/provisioning/*') ?: [] as $file) {
    @unlink($file);
}
@rmdir($temporaryProjectDir . '/var/seostudio/provisioning');
@rmdir($temporaryProjectDir . '/var/seostudio');
@rmdir($temporaryProjectDir . '/var');
@rmdir($temporaryProjectDir);

// ── The public updater path through the real HTTP kernel ─────────────────────
$requestStack->pop();

$getResponse = $kernel->handle(Request::create(Endpoint::updaterPath(), 'GET'), HttpKernelInterface::MAIN_REQUEST, false);
check(
    'GET on the updater path returns 405, not 404',
    $getResponse->getStatusCode() === 405,
    'status ' . $getResponse->getStatusCode(),
);
check('the 405 advertises POST', $getResponse->headers->get('Allow') === 'POST');

$wrongType = Request::create(Endpoint::updaterPath(), 'POST', [], [], [], [], '{}');
$wrongType->headers->set('Content-Type', 'text/plain');
$wrongTypeResponse = $kernel->handle($wrongType, HttpKernelInterface::MAIN_REQUEST, false);
check(
    'an unsupported media type returns 415',
    $wrongTypeResponse->getStatusCode() === 415,
    'status ' . $wrongTypeResponse->getStatusCode(),
);

$unsigned = Request::create(Endpoint::updaterPath(), 'POST', [], [], [], [], (string) json_encode(['action' => 'license_update']));
$unsigned->headers->set('Content-Type', 'application/json');
$unsignedResponse = $kernel->handle($unsigned, HttpKernelInterface::MAIN_REQUEST, false);
check(
    'an unsigned POST is rejected with 401',
    $unsignedResponse->getStatusCode() === 401,
    'status ' . $unsignedResponse->getStatusCode(),
);
check(
    'the rejection reveals nothing about the checks',
    $unsignedResponse->getContent() === '{"status":"unauthorized"}',
    (string) $unsignedResponse->getContent(),
);

$oversized = Request::create(Endpoint::updaterPath(), 'POST', [], [], [], [], str_repeat('x', 70000));
$oversized->headers->set('Content-Type', 'application/json');
check(
    'an oversized body returns 413',
    $kernel->handle($oversized, HttpKernelInterface::MAIN_REQUEST, false)->getStatusCode() === 413,
);

// ── Host policy against the real installation configuration ─────────────────
$realInventory = $container->get(\VTinnovations\SeoStudio\Core\Content\SiteInventory::class);
$realInventory->reset();
$configured = $realInventory->configuredHosts();

check(
    'the configured host inventory is read from the site root configuration',
    \in_array($runtimeHost, $configured, true),
    implode(', ', $configured) ?: '(empty)',
);

foreach ($configured as $host) {
    check(sprintf('configured host "%s" is canonical', $host), HostName::normalize($host) === $host);
}

check(
    'a host that is not configured never intersects',
    $realInventory->intersect(['attacker.example.net']) === [],
);
check(
    'the outbound domain is a configured host',
    \in_array((string) $realInventory->outboundHost(), $configured, true),
);

// ── Unlicensed default behaviour ─────────────────────────────────────────────
$realEntitlement = $container->get(EntitlementEvaluator::class);
check(
    'this installation is unlicensed (no vendor-signed licence present)',
    !$realEntitlement->isLicensed(),
    $realEntitlement->current()->status,
);
check(
    'the production key ring is non-empty even so',
    !(new TrustAnchors())->isEmpty(),
);
check(
    'without a licence Contao gets no SEO Studio menu group',
    !isset($GLOBALS['BE_MOD']['seo_studio']),
);

// ── Leave the installation exactly as it was ────────────────────────────────
$connection->delete('tl_page', ['id' => $runtimeRootId]);
check('the temporary site root was removed again', $connection->fetchOne('SELECT COUNT(*) FROM tl_page WHERE id = ?', [$runtimeRootId]) === 0);

fwrite(STDOUT, sprintf("\n%d checks, %d failures\n", $checks, \count($failures)));

if ($failures !== []) {
    fwrite(STDERR, "\nRUNTIME ACCEPTANCE FAILED:\n - " . implode("\n - ", $failures) . "\n");

    exit(1);
}

fwrite(STDOUT, "runtime acceptance passed\n");

exit(0);

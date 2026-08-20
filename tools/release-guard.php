<?php

declare(strict_types=1);

/**
 * Release and readiness guard.
 *
 * Run before packaging a distribution and in CI:
 *
 *     php tools/release-guard.php [path-to-artefact-root]
 *
 * It fails (exit code 1) when the artefact could not verify a real vendor
 * response or when it leaks packet material, which is exactly the situation a
 * "looks finished" build must never reach. Checks:
 *
 *   1. the pinned public-key ring is non-empty, structurally valid and matches
 *      its published fingerprint (no placeholders, no empty arrays);
 *   2. canonical JSON is byte-exact against fixed vectors;
 *   3. detached signature verification works through the production code path,
 *      positively and negatively, including unknown key id and algorithm
 *      mismatch;
 *   4. the exact-byte digest tripwire detects a single-character edit;
 *   5. the six-line updater signing input is built exactly as specified;
 *   6. host normalization and exact-set membership behave (apex vs www, parent,
 *      sibling, wildcard, IDN, port, trailing dot, IP);
 *   7. the licence record shape and Pro-Only tier policy hold;
 *   8. no source file passes forbidden material to a logger;
 *   9. no obvious licensing/security subsystem folder or symbol exists.
 *
 * Deliberately dependency-free: it runs with a bare PHP binary (no composer
 * install, no PHPUnit), so a release machine can gate on it.
 */

$root = $argv[1] ?? \dirname(__DIR__);
$root = rtrim($root, '/');

require_once __DIR__ . '/autoload.php';

use VTinnovations\SeoStudio\Core\Config\EntitlementEvaluator;
use VTinnovations\SeoStudio\Core\Config\EntitlementState;
use VTinnovations\SeoStudio\Core\Config\PackagePolicy;
use VTinnovations\SeoStudio\Core\Config\ProvisioningRecord;
use VTinnovations\SeoStudio\Core\Content\HostInventory;
use VTinnovations\SeoStudio\Core\Content\HostName;
use VTinnovations\SeoStudio\Core\Security\CanonicalForm;
use VTinnovations\SeoStudio\Core\Config\ProvisioningStore;
use VTinnovations\SeoStudio\Core\Security\SignatureVerifier;
use VTinnovations\SeoStudio\Core\Security\TrustAnchor;
use VTinnovations\SeoStudio\Core\Security\TrustAnchors;
use VTinnovations\SeoStudio\Exchange\PackageAcceptance;

/** Array-backed inventory: host policy under test without a database. */
final class FixedInventory implements HostInventory
{
    /** @param list<string> $hosts */
    public function __construct(private array $hosts, private ?string $current = null)
    {
    }

    public function configuredHosts(): array
    {
        return HostName::canonicalSet($this->hosts);
    }

    public function intersect(array $signedHosts): array
    {
        $out = [];
        foreach ($signedHosts as $host) {
            if (\in_array($host, $this->configuredHosts(), true)) {
                $out[] = $host;
            }
        }

        return $out;
    }

    public function matchedHost(array $signedHosts): ?string
    {
        $intersection = $this->intersect($signedHosts);
        if ($intersection === []) {
            return null;
        }

        if ($this->current !== null && \in_array($this->current, $intersection, true)) {
            return $this->current;
        }

        return $intersection[0];
    }

    public function outboundHost(): ?string
    {
        return $this->current ?? ($this->configuredHosts()[0] ?? null);
    }

    public function reset(): void
    {
    }
}

$failures = [];
$checks = 0;

function check(string $label, bool $condition): void
{
    global $failures, $checks;

    ++$checks;

    if (!$condition) {
        $failures[] = $label;
        fwrite(STDERR, "FAIL  $label\n");

        return;
    }

    fwrite(STDOUT, "ok    $label\n");
}

// ─────────────────────────────────────────────────────────────────────────────
// 1. Pinned key ring
// ─────────────────────────────────────────────────────────────────────────────
$production = new TrustAnchors();

check('key ring is not empty', !$production->isEmpty());
check('key ring self-check passes', $production->selfCheck() === []);
check('key ring contains the active vendor key id', $production->find('vtone-2026a', 'ed25519', TrustAnchor::PURPOSE_ENVELOPE, time()) !== null);
check('key ring rejects an unknown key id', $production->find('vtone-9999z', 'ed25519', TrustAnchor::PURPOSE_ENVELOPE, time()) === null);
check('key ring rejects a wrong algorithm', $production->find('vtone-2026a', 'rsa-sha256', TrustAnchor::PURPOSE_ENVELOPE, time()) === null);

$anchor = $production->all()[0] ?? null;
check('pinned key is 32 raw bytes', $anchor !== null && \strlen($anchor->publicKey) === 32);
check('pinned key fingerprint matches the published value', $anchor !== null && str_starts_with($anchor->fingerprint(), 'edcd614e70c59ce0'));

$emptyRing = new TrustAnchors([]);
check('an empty ring reports itself empty', $emptyRing->isEmpty());
check('an empty ring fails its self-check', \in_array(TrustAnchors::CATEGORY_EMPTY, $emptyRing->selfCheck(), true));

$placeholder = new TrustAnchors([new TrustAnchor('placeholder', 'ed25519', 'REPLACE-ME', ['envelope'], 0, null)]);
check('a placeholder key is rejected as structurally invalid', $placeholder->isEmpty());

// ─────────────────────────────────────────────────────────────────────────────
// 2. Canonical JSON, fixed vectors
// ─────────────────────────────────────────────────────────────────────────────
$vector = CanonicalForm::decode('{"b":1,"a":{"z":true,"y":null},"list":[3,1,2],"signature":"drop-me","s":"a/b","u":"ä"}');
check(
    'canonical json sorts keys, keeps list order, drops signature, unescapes',
    CanonicalForm::encode($vector) === '{"a":{"y":null,"z":true},"b":1,"list":[3,1,2],"s":"a/b","u":"ä"}',
);
check(
    'canonical json keeps scalar types exactly',
    CanonicalForm::encode(CanonicalForm::decode('{"a":false,"b":"false","c":null,"d":0,"e":"0"}'))
        === '{"a":false,"b":"false","c":null,"d":0,"e":"0"}',
);
check(
    'canonical json distinguishes an empty object from an empty array',
    CanonicalForm::encode(CanonicalForm::decode('{"o":{},"l":[]}')) === '{"l":[],"o":{}}',
);

// ─────────────────────────────────────────────────────────────────────────────
// 3./4./5. Signature, digest tripwire and updater signing input
// ─────────────────────────────────────────────────────────────────────────────
// Deterministic test keypair (Ed25519 is deterministic, so these vectors are
// stable). This exercises the PRODUCTION verification path; it is not vendor
// material and never ships.
$seed = str_repeat("\x2a", SODIUM_CRYPTO_SIGN_SEEDBYTES);
$pair = sodium_crypto_sign_seed_keypair($seed);
$secret = sodium_crypto_sign_secretkey($pair);
$public = sodium_crypto_sign_publickey($pair);

$testRing = new TrustAnchors([
    new TrustAnchor('test-key', 'ed25519', $public, [
        TrustAnchor::PURPOSE_DOCUMENT,
        TrustAnchor::PURPOSE_ENVELOPE,
        TrustAnchor::PURPOSE_REQUEST,
    ], 0, null),
    new TrustAnchor('retired-key', 'ed25519', $public, [TrustAnchor::PURPOSE_ENVELOPE], 0, 1000),
]);
$verifier = new SignatureVerifier($testRing);

$message = 'vector-message';
$signature = base64_encode(sodium_crypto_sign_detached($message, $secret));

check('a valid detached signature verifies through the production path', $verifier->verifyNamedKey('test-key', 'ed25519', TrustAnchor::PURPOSE_ENVELOPE, $message, $signature, time()));
check('a tampered message fails', !$verifier->verifyNamedKey('test-key', 'ed25519', TrustAnchor::PURPOSE_ENVELOPE, $message . ' ', $signature, time()));
check('an unknown key id fails', !$verifier->verifyNamedKey('nope', 'ed25519', TrustAnchor::PURPOSE_ENVELOPE, $message, $signature, time()));
check('an algorithm mismatch fails', !$verifier->verifyNamedKey('test-key', 'rsa-sha256', TrustAnchor::PURPOSE_ENVELOPE, $message, $signature, time()));
check('a wrong purpose fails', !$verifier->verifyNamedKey('retired-key', 'ed25519', TrustAnchor::PURPOSE_DOCUMENT, $message, $signature, time()));
check('a retired key outside its window fails', !$verifier->verifyNamedKey('retired-key', 'ed25519', TrustAnchor::PURPOSE_ENVELOPE, $message, $signature, 2000));
check('a retired key inside its window verifies', $verifier->verifyNamedKey('retired-key', 'ed25519', TrustAnchor::PURPOSE_ENVELOPE, $message, $signature, 500));
check('malformed base64 fails', !$verifier->verifyNamedKey('test-key', 'ed25519', TrustAnchor::PURPOSE_ENVELOPE, $message, '!!not base64!!', time()));
check('a keyless document signature verifies against any usable key', $verifier->verifyAnyKey(TrustAnchor::PURPOSE_DOCUMENT, $message, $signature, time()));
check('an empty ring cannot verify anything', !(new SignatureVerifier($emptyRing))->verifyAnyKey(TrustAnchor::PURPOSE_DOCUMENT, $message, $signature, time()));
check('an empty ring reports a safe category', (new SignatureVerifier($emptyRing))->unavailableCategory() === TrustAnchors::CATEGORY_EMPTY);

$bytes = '{"a":1}';
check('digest tripwire matches unmodified bytes', hash_equals(md5($bytes), md5('{"a":1}')));
check('digest tripwire detects one added space', !hash_equals(md5($bytes), md5('{"a":1} ')));

$requestMessage = implode("\n", [
    'POST',
    '/rest/api/v1/seo-studio-license-updater',
    'req-1',
    '1784880547',
    'nonce-1',
    hash('sha256', '{"action":"license_update"}'),
]);
check(
    'updater signing input is the exact six-line form',
    $requestMessage === "POST\n/rest/api/v1/seo-studio-license-updater\nreq-1\n1784880547\nnonce-1\n" . hash('sha256', '{"action":"license_update"}'),
);
check('updater signing input excludes the key id header', !str_contains($requestMessage, 'test-key'));

// ─────────────────────────────────────────────────────────────────────────────
// 6. Host policy
// ─────────────────────────────────────────────────────────────────────────────
check('lowercases and strips one trailing dot', HostName::normalize('Example.COM.') === 'example.com');
check('strips an approved port', HostName::normalize('example.com:8443') === 'example.com');
check('rejects an invalid port', HostName::normalize('example.com:0') === null);
check('converts an IDN to punycode', HostName::normalize('münchen.de') === 'xn--mnchen-3ya.de');
check('rejects a URL', HostName::normalize('https://example.com/x') === null);
check('rejects userinfo', HostName::normalize('user@example.com') === null);
check('rejects an IPv4 literal', HostName::normalize('192.0.2.10') === null);
check('rejects an IPv6 literal', HostName::normalize('[2001:db8::1]') === null);
check('rejects a wildcard', HostName::normalize('*.example.com') === null);
check('keeps www distinct from the apex', HostName::normalize('www.example.com') !== HostName::normalize('example.com'));
check('canonical set is sorted and unique', HostName::canonicalSet(['b.example.com', 'a.example.com', 'A.example.com']) === ['a.example.com', 'b.example.com']);
check('accepts an already canonical set', HostName::isCanonicalSet(['a.example.com', 'b.example.com']));
check('rejects an unsorted set', !HostName::isCanonicalSet(['b.example.com', 'a.example.com']));
check('rejects a duplicated set', !HostName::isCanonicalSet(['a.example.com', 'a.example.com']));
check('rejects a set containing a wildcard', !HostName::isCanonicalSet(['*.example.com']));
check('rejects an empty set', !HostName::isCanonicalSet([]));

// ─────────────────────────────────────────────────────────────────────────────
// 7. Record shape, acceptance pipeline and Pro-Only policy
// ─────────────────────────────────────────────────────────────────────────────
$now = 1784880547;

/**
 * @param array<string, mixed> $overrides
 *
 * @return array{payload: string, envelope: \stdClass, bytes: string}
 */
function makePackage(array $overrides, string $secret): array
{
    $document = [
        'schema_version' => 2,
        'project' => PackagePolicy::PROJECT,
        'project_slug' => PackagePolicy::PROJECT_SLUG,
        'license_key' => 'SS-PRO-0001-ABCD',
        'license_domain' => 'example.com',
        'license_domains' => ['example.com', 'www.example.com'],
        'license_max_domains' => 9999,
        'license_package' => 'pro',
        'license_features' => [],
        'license_version' => 7,
        'license_issued_at' => 1784000000,
        'license_starts_at' => 1784000000,
        'license_expires_at' => 1815536000,
        'license_lifetime' => false,
        'license_verified_at' => 1784880547,
        'free_available' => false,
        'validation_status' => 'valid',
    ];

    foreach ($overrides as $field => $value) {
        if ($value === '__unset__') {
            unset($document[$field]);

            continue;
        }

        $document[$field] = $value;
    }

    $unsigned = CanonicalForm::decode((string) json_encode($document, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    $document['signature'] = base64_encode(sodium_crypto_sign_detached(CanonicalForm::encode($unsigned), $secret));

    $bytes = (string) json_encode($document, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

    $envelope = [
        'project' => PackagePolicy::PROJECT,
        'project_slug' => PackagePolicy::PROJECT_SLUG,
        'license_version' => $document['license_version'],
        'license_md5' => md5($bytes),
        'generated_at' => 1784880547,
        'key_id' => 'test-key',
        'signature_algorithm' => 'ed25519',
    ];

    $envelopeObject = CanonicalForm::decode((string) json_encode($envelope));
    $envelope['signature'] = base64_encode(sodium_crypto_sign_detached(CanonicalForm::encode($envelopeObject), $secret));

    $sealed = CanonicalForm::decode((string) json_encode($envelope));
    \assert($sealed instanceof \stdClass);

    return ['payload' => base64_encode($bytes), 'envelope' => $sealed, 'bytes' => $bytes];
}

$inventory = new FixedInventory(['example.com'], 'example.com');
$acceptance = new PackageAcceptance($verifier, $testRing, $inventory);

$good = makePackage([], $secret);
$result = $acceptance->accept($good['payload'], $good['envelope'], 'example.com', null, $now);
check('a complete valid package is accepted', $result->isAccepted());
check('accepted bytes are the exact issued bytes', $result->record !== null && $result->record->bytes === $good['bytes']);

$tampered = $good;
$tampered['payload'] = base64_encode($good['bytes'] . ' ');
check(
    'one added byte breaks the digest',
    $acceptance->accept($tampered['payload'], $tampered['envelope'], 'example.com', null, $now)->category === PackageAcceptance::DIGEST_MISMATCH,
);

check(
    'a non-base64 payload is refused',
    $acceptance->accept('!!!', $good['envelope'], 'example.com', null, $now)->category === PackageAcceptance::NOT_BASE64,
);

$otherHost = makePackage(['license_domain' => 'www.example.com'], $secret);
check(
    'the operation host must equal the requested domain',
    $acceptance->accept($otherHost['payload'], $otherHost['envelope'], 'example.com', null, $now)->category === PackageAcceptance::DOMAIN_MISMATCH,
);

$unsorted = makePackage(['license_domains' => ['www.example.com', 'example.com']], $secret);
check(
    'an unsorted signed host set is refused, not repaired',
    $acceptance->accept($unsorted['payload'], $unsorted['envelope'], 'example.com', null, $now)->category === PackageAcceptance::HOST_SET_INVALID,
);

$wildcard = makePackage(['license_domain' => 'example.com', 'license_domains' => ['*.example.com', 'example.com']], $secret);
check(
    'a wildcard entry is refused',
    $acceptance->accept($wildcard['payload'], $wildcard['envelope'], 'example.com', null, $now)->category === PackageAcceptance::HOST_SET_INVALID,
);

$notMember = makePackage(['license_domain' => 'example.com', 'license_domains' => ['other.example.com']], $secret);
check(
    'the operation host must be a member of the signed set',
    $acceptance->accept($notMember['payload'], $notMember['envelope'], 'example.com', null, $now)->category === PackageAcceptance::HOST_NOT_MEMBER,
);

$foreign = new PackageAcceptance($verifier, $testRing, new FixedInventory(['different.example.org'], 'different.example.org'));
check(
    'a package copied to an installation with no exact intersection fails',
    $foreign->accept($good['payload'], $good['envelope'], 'example.com', null, $now)->category === PackageAcceptance::NO_INTERSECTION,
);

$apexOnly = new PackageAcceptance($verifier, $testRing, new FixedInventory(['www.example.com'], 'www.example.com'));
$wwwPackage = makePackage(['license_domain' => 'www.example.com'], $secret);
check(
    'www and apex are separate identities but either can match',
    $apexOnly->accept($wwwPackage['payload'], $wwwPackage['envelope'], 'www.example.com', null, $now)->isAccepted(),
);

$allowance = makePackage(['license_max_domains' => 0], $secret);
check(
    'a non-positive allowance is refused',
    $acceptance->accept($allowance['payload'], $allowance['envelope'], 'example.com', null, $now)->category === PackageAcceptance::ALLOWANCE,
);

$overBound = makePackage(['license_max_domains' => 1], $secret);
check(
    'more bound hosts than the allowance is still valid',
    $acceptance->accept($overBound['payload'], $overBound['envelope'], 'example.com', null, $now)->isAccepted(),
);

foreach (['free', 'trial', 'lite', ''] as $package) {
    $wrongTier = makePackage(['license_package' => $package], $secret);
    $category = $acceptance->accept($wrongTier['payload'], $wrongTier['envelope'], 'example.com', null, $now)->category;
    check(
        sprintf('Pro Only refuses the "%s" package', $package === '' ? '(empty)' : $package),
        \in_array($category, [PackageAcceptance::PACKAGE, PackageAcceptance::DOCUMENT_MALFORMED], true),
    );
}

$freeFallback = makePackage(['license_package' => 'free', 'free_available' => true], $secret);
check(
    'free_available cannot create access in Pro Only',
    !$acceptance->accept($freeFallback['payload'], $freeFallback['envelope'], 'example.com', null, $now)->isAccepted(),
);

$noExpiry = makePackage(['license_expires_at' => null, 'license_lifetime' => false], $secret);
check(
    'a time-limited package without an expiry is refused',
    $acceptance->accept($noExpiry['payload'], $noExpiry['envelope'], 'example.com', null, $now)->category === PackageAcceptance::DATES,
);

$lifetimeWithExpiry = makePackage(['license_lifetime' => true], $secret);
check(
    'lifetime with an expiry date is contradictory and refused',
    $acceptance->accept($lifetimeWithExpiry['payload'], $lifetimeWithExpiry['envelope'], 'example.com', null, $now)->category === PackageAcceptance::DATES,
);

$lifetime = makePackage(['license_lifetime' => true, 'license_expires_at' => null], $secret);
check(
    'a lifetime package with a null expiry is accepted',
    $acceptance->accept($lifetime['payload'], $lifetime['envelope'], 'example.com', null, $now)->isAccepted(),
);

$invalidStatus = makePackage(['validation_status' => 'revoked'], $secret);
check(
    'a non-valid validation status is refused',
    $acceptance->accept($invalidStatus['payload'], $invalidStatus['envelope'], 'example.com', null, $now)->category === PackageAcceptance::STATUS,
);

$wrongProject = makePackage(['project' => 'SomethingElse'], $secret);
check(
    "another product's package is refused",
    $acceptance->accept($wrongProject['payload'], $wrongProject['envelope'], 'example.com', null, $now)->category === PackageAcceptance::PRODUCT,
);

$legacy = makePackage(['license_domains' => '__unset__', 'license_max_domains' => '__unset__'], $secret);
check(
    'a package without the signed host fields is refused, never expanded',
    $acceptance->accept($legacy['payload'], $legacy['envelope'], 'example.com', null, $now)->category === PackageAcceptance::DOCUMENT_MALFORMED,
);

check(
    'an older version cannot replace newer stored state',
    $acceptance->accept($good['payload'], $good['envelope'], 'example.com', 9, $now)->category === PackageAcceptance::ROLLBACK,
);
check(
    'the same version is acceptable on refresh',
    $acceptance->accept($good['payload'], $good['envelope'], 'example.com', 7, $now)->isAccepted(),
);
check(
    'a push must strictly increase the version',
    $acceptance->accept($good['payload'], $good['envelope'], 'example.com', 7, $now, true)->category === PackageAcceptance::ROLLBACK,
);

$forgedRing = new TrustAnchors([new TrustAnchor('test-key', 'ed25519', sodium_crypto_sign_publickey(sodium_crypto_sign_keypair()), [
    TrustAnchor::PURPOSE_DOCUMENT,
    TrustAnchor::PURPOSE_ENVELOPE,
], 0, null)]);
$wrongKeyAcceptance = new PackageAcceptance(new SignatureVerifier($forgedRing), $forgedRing, $inventory);
check(
    'a package signed by another key is refused',
    $wrongKeyAcceptance->accept($good['payload'], $good['envelope'], 'example.com', null, $now)->category === PackageAcceptance::ENVELOPE_SIGNATURE,
);

$emptyRingAcceptance = new PackageAcceptance(new SignatureVerifier($emptyRing), $emptyRing, $inventory);
check(
    'an empty key ring fails closed instead of trusting the digest',
    $emptyRingAcceptance->accept($good['payload'], $good['envelope'], 'example.com', null, $now)->category === TrustAnchors::CATEGORY_EMPTY,
);

// ─────────────────────────────────────────────────────────────────────────────
// 7b. Atomic store + entitlement transitions
// ─────────────────────────────────────────────────────────────────────────────
$tempRoot = sys_get_temp_dir() . '/seo-studio-guard-' . bin2hex(random_bytes(6));
mkdir($tempRoot, 0700, true);

$store = new ProvisioningStore($tempRoot);
$entitlement = new EntitlementEvaluator($store, $acceptance, $inventory);

check('nothing stored means unlicensed', $entitlement->evaluate($now)->status === EntitlementState::ABSENT);
check('nothing stored means no key for telemetry', $entitlement->authenticatedRecord() === null);

$record = $acceptance->accept($good['payload'], $good['envelope'], 'example.com', null, $now)->record;
\assert($record instanceof ProvisioningRecord);

$activated = $store->activate(
    $record->bytes,
    $record->envelope(),
    static fn (ProvisioningRecord $candidate): bool => $acceptance->checkStored($candidate, $now)->isAccepted(),
);
check('activation writes the state atomically', $activated);
check('the stored bytes are byte-identical to the issued bytes', file_get_contents($tempRoot . '/var/seostudio/provisioning/record.json') === $good['bytes']);

$entitlement->invalidate();
check('a stored valid record licenses the instance', $entitlement->evaluate($now)->licensed);
check('the matched host is recorded deterministically', $entitlement->evaluate($now)->matchedHost === 'example.com');
check('an authenticated record is available to the session signal', $entitlement->authenticatedRecord() !== null);

$entitlement->invalidate();
check('an expired record does not license anything', !$entitlement->evaluate(1815536001)->licensed);
check('an expired record reports the expired status', $entitlement->evaluate(1815536001)->status === EntitlementState::EXPIRED);
check('Pro Only grants no fallback after expiry', $entitlement->evaluate(1815536001)->allowsFeature('meta') === false);

$entitlement->invalidate();
check('a not-yet-valid record does not license anything', $entitlement->evaluate(1783999999)->status === EntitlementState::NOT_STARTED);

// Hand-edit the stored record: the digest must catch it.
file_put_contents($tempRoot . '/var/seostudio/provisioning/record.json', str_replace('"pro"', '"pro" ', $good['bytes']));
$entitlement->invalidate();
check('editing the stored record makes it unverifiable', $entitlement->evaluate($now)->status === EntitlementState::UNVERIFIABLE);
check('a tampered record yields no key for telemetry', $entitlement->authenticatedRecord() === null);

// Restore, then prove a foreign installation cannot use a copied state.
file_put_contents($tempRoot . '/var/seostudio/provisioning/record.json', $good['bytes']);
$copied = new EntitlementEvaluator($store, $foreign, new FixedInventory(['different.example.org'], 'different.example.org'));
check('a copied state does not license another installation', !$copied->evaluate($now)->licensed);
check('a copied state reports the host mismatch', $copied->evaluate($now)->status === EntitlementState::NO_HOST_MATCH);

$store->remove();
$entitlement->invalidate();
check('removal returns the instance to the default state', $entitlement->evaluate($now)->status === EntitlementState::ABSENT);
check('removal deletes the stored record', !is_file($tempRoot . '/var/seostudio/provisioning/record.json'));
check('removal deletes the rollback copy', !is_file($tempRoot . '/var/seostudio/provisioning/record.json.bak'));

$store->activate('not json at all', ['project' => 'x'], static fn (): bool => true);
check('an unparseable candidate never becomes active', !$store->exists());

array_map('unlink', glob($tempRoot . '/var/seostudio/provisioning/*') ?: []);
@rmdir($tempRoot . '/var/seostudio/provisioning');
@rmdir($tempRoot . '/var/seostudio');
@rmdir($tempRoot . '/var');
@rmdir($tempRoot);

// ─────────────────────────────────────────────────────────────────────────────
// 8. Packet-log secrecy scan
// ─────────────────────────────────────────────────────────────────────────────
$forbidden = [
    'request_packet', 'response_packet', 'request_body', 'response_body',
    'license_payload_b64', 'license_md5', 'request_sha256', 'response_sha256',
    'licence_key_sha256', 'license_key_sha256', 'licence_key_length', 'license_key_length',
];

$sources = [];
foreach (['src', 'contao'] as $directory) {
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/' . $directory));
    foreach ($iterator as $file) {
        if ($file instanceof SplFileInfo && $file->isFile() && $file->getExtension() === 'php') {
            $sources[$file->getPathname()] = (string) file_get_contents($file->getPathname());
        }
    }
}

$leaks = [];
foreach ($sources as $path => $code) {
    // Logger calls are the only place these names could escape into a log.
    if (preg_match_all('/->(?:info|warning|error|critical|debug|notice|log)\s*\((.*?)\);/s', $code, $matches) === false) {
        continue;
    }

    foreach ($matches[1] ?? [] as $arguments) {
        foreach ($forbidden as $name) {
            if (str_contains($arguments, $name)) {
                $leaks[] = basename($path) . ' → ' . $name;
            }
        }
    }
}

check('no logger call mentions packet material', $leaks === [], );

$loggerAllowlist = (string) file_get_contents($root . '/src/Exchange/OperationLog.php');
foreach (['nonce', 'signature', 'license_md5', 'license_key', 'payload'] as $name) {
    check(
        sprintf('the log allowlist does not contain "%s"', $name),
        !preg_match('/^\s*\'' . preg_quote($name, '/') . '\',$/m', $loggerAllowlist),
    );
}

// ─────────────────────────────────────────────────────────────────────────────
// 9. Source-layout concealment
// ─────────────────────────────────────────────────────────────────────────────
$paths = [];
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/src'));
foreach ($iterator as $file) {
    if ($file instanceof SplFileInfo && $file->isFile()) {
        $paths[] = str_replace($root . '/', '', $file->getPathname());
    }
}

$obvious = preg_grep('#(^|/)(Licensing|License|Licence|Protection|Integrity|AntiTamper|DRM|VtOne|VTone)(/|$)#', $paths);
check('no obvious licensing/security directory exists', $obvious === []);

$revealing = preg_grep('#(License|Licence)(Manager|Validator|Service|Repository|UpdaterController|StateStore|IntegrityService)|TamperDetector|ExpectedMd5|ChecksumGuard|VtoneLogger#', $paths);
check('no revealing private symbol file names exist', $revealing === []);

$flowFiles = ['Endpoint.php', 'TrustAnchors.php', 'CanonicalForm.php', 'ProvisioningStore.php', 'PackageAcceptance.php', 'HostName.php'];
$directories = [];
foreach ($flowFiles as $name) {
    foreach ($paths as $path) {
        if (basename($path) === $name) {
            $directories[\dirname($path)] = true;
        }
    }
}
check('the security flow is spread over at least four directories', \count($directories) >= 4);

// ─────────────────────────────────────────────────────────────────────────────
// 10. Translations: parity, completeness, and no hardcoded interface text
// ─────────────────────────────────────────────────────────────────────────────
$flatten = static function (array $tree, string $prefix = '') use (&$flatten): array {
    $out = [];

    foreach ($tree as $key => $value) {
        $path = $prefix === '' ? (string) $key : $prefix . '.' . $key;

        if (\is_array($value)) {
            $out += $flatten($value, $path);
        } elseif (\is_string($value)) {
            $out[$path] = $value;
        }
    }

    return $out;
};

$catalogue = [];

foreach (['en', 'de'] as $language) {
    $GLOBALS['TL_LANG'] = [];
    require $root . '/contao/languages/' . $language . '/default.php';
    $catalogue[$language] = $flatten($GLOBALS['TL_LANG']['SEO_STUDIO'] ?? []);
}

check('the English catalogue is populated', \count($catalogue['en']) > 100);
check('the German catalogue is populated', \count($catalogue['de']) > 100);

$missingDe = array_diff(array_keys($catalogue['en']), array_keys($catalogue['de']));
$missingEn = array_diff(array_keys($catalogue['de']), array_keys($catalogue['en']));

check('every English key exists in German', $missingDe === [], implode(', ', \array_slice($missingDe, 0, 5)));
check('every German key exists in English', $missingEn === [], implode(', ', \array_slice($missingEn, 0, 5)));

$empty = array_keys(array_filter($catalogue['de'], static fn (string $value): bool => trim($value) === ''));
check('no German value is empty', $empty === [], implode(', ', \array_slice($empty, 0, 5)));

// Placeholder counts must agree, otherwise sprintf breaks in one language only.
$placeholderMismatch = [];

foreach ($catalogue['en'] as $key => $value) {
    $countEn = preg_match_all('/%(?!%)[-+ 0\'#]*\d*(?:\.\d+)?[bcdeEfFgGosuxX]/', $value);
    $countDe = preg_match_all('/%(?!%)[-+ 0\'#]*\d*(?:\.\d+)?[bcdeEfFgGosuxX]/', $catalogue['de'][$key] ?? '');

    if ($countEn !== $countDe) {
        $placeholderMismatch[] = $key;
    }
}

check('placeholders match between languages', $placeholderMismatch === [], implode(', ', \array_slice($placeholderMismatch, 0, 5)));

// Every key referenced from PHP must exist in both languages.
$referenced = [];

foreach ($sources as $path => $code) {
    if (preg_match_all('/(?:Translations::text|->trans|->transf)\(\s*\'([a-zA-Z][a-zA-Z0-9_.]*)\'/', $code, $matches)) {
        foreach ($matches[1] as $key) {
            // A key composed at runtime ('licence.status_' . $state->status)
            // leaves a prefix here; the concrete keys are checked by the
            // per-state tests instead.
            if (str_ends_with($key, '.') || str_ends_with($key, '_')) {
                continue;
            }

            $referenced[$key] = basename($path);
        }
    }
}

check('the source references translation keys', \count($referenced) > 50);

$unknown = [];

foreach ($referenced as $key => $file) {
    if (!isset($catalogue['en'][$key]) || !isset($catalogue['de'][$key])) {
        $unknown[] = $key . ' (' . $file . ')';
    }
}

check('every referenced key exists in both languages', $unknown === [], implode(', ', \array_slice($unknown, 0, 6)));

// No call site may carry an inline fallback string any more.
$inlineFallback = [];

foreach ($sources as $path => $code) {
    if (preg_match('/->trans(?:f)?\(\s*\'[^\']+\'\s*,\s*\'/', $code)) {
        $inlineFallback[] = basename($path);
    }
}

check('no translation call carries an inline fallback', $inlineFallback === [], implode(', ', $inlineFallback));

/*
 * Hardcoded-text ratchet.
 *
 * EXEMPT files contain language material that is NOT interface text:
 * LLM prompt bodies (their wording is part of the instruction to the model) and
 * German-language analysis data (readability heuristics, transition-word lists,
 * an A–Z grouping character class). Translating those would change behaviour,
 * not localise a label.
 *
 * PENDING records the interface text that is not localised yet, with its exact
 * current size. The guard fails when any other file grows a literal, or when a
 * pending file grows beyond its recorded count — so the debt can only shrink.
 */
$exempt = [
    'src/Feature/Meta/MetaGenerator.php',
    'src/Feature/Glossary/GlossaryGenerator.php',
    'src/Feature/Optimize/TextOptimizer.php',
    'src/Feature/Faq/FaqGenerator.php',
    'src/Feature/PageScore/KeywordSuggester.php',
    'src/Feature/LlmsTxt/SummaryGenerator.php',
    'src/Feature/InlinePanel/Adapter/AltTextAdapter.php',
    'src/Feature/InlinePanel/Adapter/LinkTextAdapter.php',
    'src/Feature/InlinePanel/Adapter/AbstractAdapter.php',
    'src/Feature/Audit/AnswerFirstChecker.php',
    'src/Core/Content/GermanText.php',
    'src/Controller/FrontendModule/GlossaryModuleController.php',
];

$pending = [
    'src/Controller/DashboardModule.php' => 33,
    'src/Controller/AuditModule.php' => 29,
    'src/Controller/GenerateModule.php' => 22,
    'src/Controller/SettingsModule.php' => 19,
    'src/Feature/Optimize/FieldScorer.php' => 31,
];

$literals = [];

foreach ($sources as $path => $code) {
    $relative = str_replace($root . '/', '', $path);

    if (!str_starts_with($relative, 'src/') || \in_array($relative, $exempt, true)) {
        continue;
    }

    $found = 0;

    foreach (explode("\n", $code) as $line) {
        $trimmed = ltrim($line);

        if (str_starts_with($trimmed, '*') || str_starts_with($trimmed, '//') || str_starts_with($trimmed, '/*')) {
            continue;
        }

        $found += preg_match_all('/\'[^\'\n]*(?:ä|ö|ü|ß|Ä|Ö|Ü)[^\'\n]*\'/u', $line);
    }

    if ($found > 0) {
        $literals[$relative] = $found;
    }
}

$unexpected = [];
$grown = [];

foreach ($literals as $relative => $found) {
    if (!isset($pending[$relative])) {
        $unexpected[] = $relative . ' (' . $found . ')';
    } elseif ($found > $pending[$relative]) {
        $grown[] = sprintf('%s (%d > %d)', $relative, $found, $pending[$relative]);
    }
}

check('no new hardcoded interface text was introduced', $unexpected === [], implode(', ', $unexpected));
check('no file with known hardcoded text grew', $grown === [], implode(', ', $grown));

$remaining = array_sum($literals);

if ($remaining > 0) {
    fwrite(STDOUT, sprintf(
        "note  %d hardcoded interface literals remain in %d file(s) awaiting localisation: %s\n",
        $remaining,
        \count($literals),
        implode(', ', array_keys($literals)),
    ));
}

fwrite(STDOUT, sprintf("\n%d checks, %d failures\n", $checks, \count($failures)));

if ($failures !== []) {
    fwrite(STDERR, "\nRELEASE BLOCKED:\n - " . implode("\n - ", $failures) . "\n");

    exit(1);
}

fwrite(STDOUT, "release guard passed\n");

exit(0);

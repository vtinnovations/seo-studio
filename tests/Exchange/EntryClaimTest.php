<?php

declare(strict_types=1);

/**
 * @package   vtinnovations/seo-studio
 * @author    VT Innovations Team
 * @license   LGPL-3.0-or-later
 * @copyright VT Innovations 2026
 */

namespace VTinnovations\SeoStudio\Tests\Exchange;

use Contao\CoreBundle\Security\Authentication\Token\TokenChecker;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Attribute\AttributeBag;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use VTinnovations\SeoStudio\Core\Config\EntitlementEvaluator;
use VTinnovations\SeoStudio\Core\Config\ProvisioningStore;
use VTinnovations\SeoStudio\Exchange\EntryClaim;
use VTinnovations\SeoStudio\Exchange\OperationLog;
use VTinnovations\SeoStudio\Exchange\PackageAcceptance;
use VTinnovations\SeoStudio\Exchange\SignalTransport;
use VTinnovations\SeoStudio\Tests\PackageFixture;

/**
 * The once-per-authenticated-backend-session module-entry signal.
 *
 * Proves: one event on first entry, none on reload/second entry/parallel tab,
 * one again in a NEW session, none without an authentic record, none for an
 * anonymous (frontend/CLI) context, and no retry after a transport failure.
 */
final class EntryClaimTest extends TestCase
{
    use PackageFixture;

    private string $projectDir = '';

    /** @var list<string> */
    private array $sent = [];

    protected function setUp(): void
    {
        $this->projectDir = sys_get_temp_dir() . '/seo-studio-entry-' . bin2hex(random_bytes(6));
        mkdir($this->projectDir, 0700, true);
        $this->sent = [];
    }

    protected function tearDown(): void
    {
        $directory = $this->projectDir . '/var/seostudio/provisioning';

        foreach (glob($directory . '/*') ?: [] as $file) {
            @unlink($file);
        }

        @rmdir($directory);
        @rmdir($this->projectDir . '/var/seostudio');
        @rmdir($this->projectDir . '/var');
        @rmdir($this->projectDir);
    }

    public function testFirstModuleEntrySendsExactlyOneEvent(): void
    {
        $session = $this->session();
        $claim = $this->claim($session, licensed: true);

        $claim->claim();
        self::assertTrue($claim->hasPending(), 'the claim happens before delivery');

        $claim->flush();

        self::assertCount(1, $this->sent);

        /** @var array<string, mixed> $payload */
        $payload = json_decode($this->sent[0], true);
        self::assertSame(['domain', 'key'], array_keys($payload));
        self::assertSame('example.com', $payload['domain']);
        self::assertSame('SS-PRO-0001-ABCD', $payload['key']);
    }

    public function testReloadsAndParallelTabsInTheSameSessionSendNothingMore(): void
    {
        $session = $this->session();

        $first = $this->claim($session, licensed: true);
        $first->claim();
        $first->flush();

        // Same session, new request (reload), and a "parallel tab".
        foreach ([1, 2, 3] as $ignored) {
            $next = $this->claim($session, licensed: true);
            $next->claim();
            $next->flush();
        }

        self::assertCount(1, $this->sent);
    }

    public function testRepeatedClaimInTheSameRequestSendsOnce(): void
    {
        $claim = $this->claim($this->session(), licensed: true);

        $claim->claim();
        $claim->claim();
        $claim->claim();
        $claim->flush();
        $claim->flush();

        self::assertCount(1, $this->sent);
    }

    public function testANewSessionMayClaimAgain(): void
    {
        $first = $this->claim($this->session(), licensed: true);
        $first->claim();
        $first->flush();

        $second = $this->claim($this->session(), licensed: true);
        $second->claim();
        $second->flush();

        self::assertCount(2, $this->sent);
    }

    public function testNoAuthenticRecordSendsNothingAndDoesNotConsumeTheClaim(): void
    {
        $session = $this->session();
        $claim = $this->claim($session, licensed: false);

        $claim->claim();
        $claim->flush();

        self::assertSame([], $this->sent);

        // Nothing was claimed, so activating later in the same session may signal.
        self::assertNull($session->getBag('contao_backend')->get('seoStudioEntrySignal.seo-studio'));
    }

    public function testAnonymousContextSendsNothing(): void
    {
        $claim = $this->claim($this->session(), licensed: true, backendUser: false);

        $claim->claim();
        $claim->flush();

        self::assertSame([], $this->sent);
    }

    public function testTransportFailureIsNotRetriedInTheSameSession(): void
    {
        $session = $this->session();

        $failing = new MockHttpClient(static fn (): MockResponse => new MockResponse('', ['http_code' => 500]));
        $claim = $this->claim($session, licensed: true, client: $failing);

        $claim->claim();
        $claim->flush();

        // A second entry must not try again: the claim was already consumed.
        $again = $this->claim($session, licensed: true);
        $again->claim();
        $again->flush();

        self::assertSame([], $this->sent, 'the retry would have used the capturing client');
    }

    public function testRemovingTheLicenceReleasesTheClaim(): void
    {
        $session = $this->session();

        $claim = $this->claim($session, licensed: true);
        $claim->claim();
        $claim->flush();
        self::assertCount(1, $this->sent);

        $claim->resetClaim();

        $after = $this->claim($session, licensed: true);
        $after->claim();
        $after->flush();

        self::assertCount(2, $this->sent);
    }

    public function testTheMarkerHoldsNoSensitiveValue(): void
    {
        $session = $this->session();
        $claim = $this->claim($session, licensed: true);
        $claim->claim();

        $marker = $session->getBag('contao_backend')->all();

        $serialized = (string) json_encode($marker);

        self::assertStringNotContainsString('SS-PRO-0001-ABCD', $serialized);
        self::assertStringNotContainsString('example.com', $serialized);
        self::assertSame(1, $marker['seoStudioEntrySignal.seo-studio']);
    }

    private function session(): Session
    {
        // Same shape Contao uses: the storage key differs from the bag name, and
        // the name is what getBag() resolves.
        $bag = new AttributeBag('_contao_backend');
        $bag->setName('contao_backend');

        $session = new Session(new MockArraySessionStorage());
        $session->registerBag($bag);

        return $session;
    }

    private function claim(
        Session $session,
        bool $licensed,
        bool $backendUser = true,
        ?MockHttpClient $client = null,
    ): EntryClaim {
        $request = new Request();
        $request->setSession($session);

        $stack = new RequestStack();
        $stack->push($request);

        $tokenChecker = $this->createMock(TokenChecker::class);
        $tokenChecker->method('hasBackendUser')->willReturn($backendUser);

        $store = new ProvisioningStore($this->projectDir);
        $inventory = $this->inventory();
        $acceptance = new PackageAcceptance($this->testVerifier(), $this->testRing(), $inventory);

        if ($licensed && !$store->exists()) {
            $package = $this->package();
            $record = $acceptance->accept($package['payload'], $package['envelope'], 'example.com', null, 1784880547)->record;
            self::assertNotNull($record, 'fixture package must be acceptable');
            $store->activate($record->bytes, $record->envelope(), static fn (): bool => true);
        }

        $capturing = $client ?? new MockHttpClient(function (string $method, string $url, array $options): MockResponse {
            $this->sent[] = (string) $options['body'];

            return new MockResponse('', ['http_code' => 204]);
        });

        return new EntryClaim(
            $stack,
            $tokenChecker,
            new EntitlementEvaluator($store, $acceptance, $inventory),
            new SignalTransport($capturing, new OperationLog(new NullLogger()), false),
        );
    }
}

<?php

declare(strict_types=1);

/**
 * @package   vtinnovations/seo-studio
 * @author    VT Innovations Team
 * @license   LGPL-3.0-or-later
 * @copyright VT Innovations 2026
 */

namespace VTinnovations\SeoStudio\Tests\Exchange;

use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use VTinnovations\SeoStudio\Exchange\OperationLog;

/**
 * Captured-logger proof that packet material cannot reach ordinary logs.
 */
final class OperationLogTest extends TestCase
{
    public function testForbiddenFieldsAreDroppedEvenWhenPassedIn(): void
    {
        $logger = $this->capturingLogger();

        (new OperationLog($logger))->info('exchange', [
            'operation' => 'activate',
            'request_id' => 'r-1',
            'http_status' => 200,
            // Everything below must never survive.
            'license_key' => 'SS-PRO-0001-ABCD',
            'licence_key_sha256' => hash('sha256', 'SS-PRO-0001-ABCD'),
            'license_key_length' => 16,
            'nonce' => 'abc',
            'license_md5' => md5('x'),
            'signature' => 'sig',
            'license_payload_b64' => base64_encode('{}'),
            'request_body' => '{"license_key":"SS-PRO-0001-ABCD"}',
            'response_body' => '{"status":"valid"}',
            'request_sha256' => hash('sha256', '{}'),
            'response_sha256' => hash('sha256', '{}'),
            'stack' => 'trace',
            'path' => '/var/www/secret',
        ]);

        self::assertCount(1, $logger->records);

        $context = $logger->records[0]['context'];

        self::assertSame(['request_id' => 'r-1', 'operation' => 'activate', 'http_status' => 200], $context);

        $serialized = (string) json_encode($logger->records);

        foreach ([
            'SS-PRO-0001-ABCD',
            'license_key',
            'licence_key_sha256',
            'license_key_length',
            'nonce',
            'license_md5',
            'signature',
            'license_payload_b64',
            'request_body',
            'response_body',
            'request_sha256',
            'response_sha256',
            'stack',
            '/var/www/secret',
        ] as $forbidden) {
            self::assertStringNotContainsString($forbidden, $serialized);
        }
    }

    public function testOnlyAllowlistedOperationalMetadataSurvives(): void
    {
        $logger = $this->capturingLogger();

        (new OperationLog($logger))->warning('exchange', [
            'operation' => 'refresh',
            'result' => 'transport_failure',
            'elapsed_ms' => 42,
            'license_version' => 7,
            'domain' => 'example.com',
            'key_id' => 'vtone-2026a',
        ]);

        self::assertSame([
            'operation' => 'refresh',
            'result' => 'transport_failure',
            'elapsed_ms' => 42,
            'license_version' => 7,
            'domain' => 'example.com',
            'key_id' => 'vtone-2026a',
        ], $logger->records[0]['context']);
    }

    public function testLongValuesAreTruncated(): void
    {
        $logger = $this->capturingLogger();

        (new OperationLog($logger))->error('exchange', ['domain' => str_repeat('a', 5000)]);

        self::assertLessThanOrEqual(253, \strlen((string) $logger->records[0]['context']['domain']));
    }

    private function capturingLogger(): object
    {
        return new class extends AbstractLogger {
            /** @var list<array{message: string, context: array<string, mixed>}> */
            public array $records = [];

            public function log($level, \Stringable|string $message, array $context = []): void
            {
                $this->records[] = ['message' => (string) $message, 'context' => $context];
            }
        };
    }
}

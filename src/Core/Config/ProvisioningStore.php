<?php

declare(strict_types=1);

/*
 * AI SEO Studio
 *
 * Package: vtinnovations/seo-studio
 * Copyright: VT Innovations Team
 * Licence: LGPL-3.0-or-later
 */

namespace VTinnovations\SeoStudio\Core\Config;


/**
 * Transactional storage for the provisioning record.
 *
 * Two files form ONE logical state: the exact issued bytes and the signed
 * envelope that seals them. They must never disagree, so activation runs as a
 * transaction under an exclusive lock:
 *
 *   validate candidate -> write temp -> fsync -> re-read + validate temp
 *   -> back up current -> swap both -> re-read + validate active
 *   -> roll back atomically on any failure -> clean up -> release
 *
 * Everything lives in a private directory under var/ (never web-reachable),
 * with 0600/0700 permissions, and every path is a constant derived from the
 * project directory — a request can never influence where bytes are written.
 * Nothing here ever writes executable source.
 */
final class ProvisioningStore
{
    private const DIRECTORY = 'var/seostudio/provisioning';

    private const RECORD = 'record.json';

    private const SEAL = 'record.seal';

    private const LOCK = '.transaction.lock';

    /**
     * The rollback barrier: the highest licence version this installation has
     * ever accepted, per licence key.
     *
     * It exists because record.json/record.seal are exactly what an
     * administrator has in a backup. Restoring them restores a genuinely
     * signed, still perfectly verifying licence — which, after a withdrawal,
     * would hand the entitlement back. The barrier is therefore consulted on
     * every read and is deliberately NOT removed by remove(): otherwise
     * "Remove licence" followed by restoring a backup would be the one-click
     * way around a revocation.
     *
     * Barrier only, never a grant. It is never read as entitlement, so removal
     * still removes.
     */
    private const FLOOR = 'record.floor';

    /** Throttle marker for the unattended re-check. Carries no entitlement. */
    private const ATTEMPT = 'record.attempt';

    /**
     * Entries kept in the barrier. An installation legitimately sees a handful
     * of licence keys in its lifetime; the cap only stops an unbounded file.
     */
    private const FLOOR_ENTRIES = 64;

    private int $depth = 0;

    /** @var resource|null */
    private mixed $lockHandle = null;

    public function __construct(
        private readonly string $projectDir,
    ) {
    }

    /**
     * The stored record, or null when nothing is stored or the pair is
     * unreadable/incomplete. Trust decisions are NOT made here — the caller
     * verifies signatures and digests before acting on the result.
     */
    public function load(): ?ProvisioningRecord
    {
        $bytes = $this->read($this->path(self::RECORD));
        $seal = $this->read($this->path(self::SEAL));

        if ($bytes === null || $seal === null) {
            return null;
        }

        try {
            /** @var mixed $envelope */
            $envelope = json_decode($seal, true, 32, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }

        if (!\is_array($envelope)) {
            return null;
        }

        /** @var array<string, mixed> $envelope */
        return ProvisioningRecord::parse($bytes, $envelope);
    }

    public function exists(): bool
    {
        return is_file($this->path(self::RECORD)) && is_file($this->path(self::SEAL));
    }

    /**
     * Runs $body while holding the exclusive state lock. Re-entrant, so an
     * activation nested inside a compare-and-set stays in one critical section.
     *
     * @template T
     *
     * @param callable(): T $body
     *
     * @return T
     */
    public function transaction(callable $body): mixed
    {
        $this->acquire();

        try {
            return $body();
        } finally {
            $this->release();
        }
    }

    /**
     * Atomically makes the candidate the active state.
     *
     * $validate is called with the candidate re-read from disk, both before the
     * swap and again afterwards. A false result rolls the previous state back,
     * so a partially written or mismatched pair can never become active.
     *
     * @param array<string, mixed>                 $envelope
     * @param callable(ProvisioningRecord): bool   $validate
     */
    public function activate(string $bytes, array $envelope, callable $validate): bool
    {
        return $this->transaction(function () use ($bytes, $envelope, $validate): bool {
            $seal = json_encode($envelope, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            if ($seal === false) {
                return false;
            }

            if (!$this->prepareDirectory()) {
                return false;
            }

            $recordPath = $this->path(self::RECORD);
            $sealPath = $this->path(self::SEAL);
            $recordTemp = $recordPath . '.tmp';
            $sealTemp = $sealPath . '.tmp';
            $recordBackup = $recordPath . '.bak';
            $sealBackup = $sealPath . '.bak';

            if (!$this->write($recordTemp, $bytes) || !$this->write($sealTemp, $seal)) {
                $this->discard($recordTemp, $sealTemp);

                return false;
            }

            // The candidate must survive a real round trip through the
            // filesystem before it is allowed anywhere near the active pair.
            $candidate = $this->readPair($recordTemp, $sealTemp);
            if ($candidate === null || !$validate($candidate)) {
                $this->discard($recordTemp, $sealTemp);

                return false;
            }

            $hadPrevious = $this->exists();
            if ($hadPrevious) {
                @copy($recordPath, $recordBackup);
                @copy($sealPath, $sealBackup);
            }

            if (!@rename($recordTemp, $recordPath) || !@rename($sealTemp, $sealPath)) {
                $this->rollback($hadPrevious);
                $this->discard($recordTemp, $sealTemp);

                return false;
            }

            $active = $this->readPair($recordPath, $sealPath);
            if ($active === null || !$validate($active)) {
                $this->rollback($hadPrevious);
                $this->discard($recordTemp, $sealTemp);

                return false;
            }

            $this->discard($recordTemp, $sealTemp);

            return true;
        });
    }

    /**
     * Administrator removal: the authoritative state and its rollback copy both
     * go away, so protected behaviour returns to the framework default
     * immediately and nothing can quietly resurrect the old entitlement.
     *
     * The version barrier is deliberately left in place. Removing it here
     * would make "Remove licence", then restore a pre-transfer backup, the
     * supported route around a revocation. The barrier grants nothing on its
     * own, so keeping it cannot keep the product switched on.
     */
    public function remove(): void
    {
        $this->transaction(function (): void {
            foreach ([self::RECORD, self::SEAL] as $name) {
                $path = $this->path($name);
                @unlink($path);
                @unlink($path . '.bak');
                @unlink($path . '.tmp');
            }
        });
    }

    /**
     * The highest version ever accepted for this licence key, or 0.
     *
     * Keyed per LICENCE, not per installation. Version numbers count within
     * one licence, so a replacement licence bought after a withdrawal
     * legitimately starts at version 1 while the withdrawn one had reached 60 —
     * a single global watermark would refuse exactly the customers who did the
     * right thing.
     */
    public function floor(string $licenceKey): int
    {
        $marks = $this->marks();
        $version = $marks[$this->mark($licenceKey)] ?? 0;

        return \is_int($version) && $version > 0 ? $version : 0;
    }

    /**
     * Raises the barrier for this licence key. Never lowers it, whatever is
     * passed, and whatever is currently on disk.
     */
    public function raise(string $licenceKey, int $version): void
    {
        if ($version < 1) {
            return;
        }

        $this->transaction(function () use ($licenceKey, $version): void {
            $marks = $this->marks();
            $mark = $this->mark($licenceKey);

            $current = $marks[$mark] ?? 0;
            if (\is_int($current) && $current >= $version) {
                return;
            }

            $marks[$mark] = $version;

            // Oldest-written entries go first; PHP preserves insertion order.
            while (\count($marks) > self::FLOOR_ENTRIES) {
                array_shift($marks);
            }

            $encoded = json_encode($marks, JSON_UNESCAPED_SLASHES);
            if ($encoded === false || !$this->prepareDirectory()) {
                return;
            }

            $this->write($this->path(self::FLOOR), $encoded);
        });
    }

    /** When the unattended re-check last ran, or 0 when it never has. */
    public function lastAttempt(): int
    {
        $raw = $this->read($this->path(self::ATTEMPT));
        if ($raw === null || preg_match('/^\d{1,12}$/', trim($raw)) !== 1) {
            return 0;
        }

        return (int) trim($raw);
    }

    public function markAttempt(int $now): void
    {
        if ($this->prepareDirectory()) {
            $this->write($this->path(self::ATTEMPT), (string) $now);
        }
    }

    public function directory(): string
    {
        return $this->projectDir . '/' . self::DIRECTORY;
    }

    /**
     * The barrier's key for a licence key: a digest, so a leaked barrier file
     * carries no usable licence key.
     *
     * @return non-empty-string
     */
    private function mark(string $licenceKey): string
    {
        return hash('sha256', 'vt-one/mark-v1:' . $licenceKey);
    }

    /**
     * @return array<string, mixed>
     */
    private function marks(): array
    {
        $raw = $this->read($this->path(self::FLOOR));
        if ($raw === null) {
            return [];
        }

        try {
            /** @var mixed $decoded */
            $decoded = json_decode($raw, true, 8, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return [];
        }

        if (!\is_array($decoded)) {
            return [];
        }

        /** @var array<string, mixed> $decoded */
        return $decoded;
    }

    private function readPair(string $recordPath, string $sealPath): ?ProvisioningRecord
    {
        $bytes = $this->read($recordPath);
        $seal = $this->read($sealPath);

        if ($bytes === null || $seal === null) {
            return null;
        }

        try {
            /** @var mixed $envelope */
            $envelope = json_decode($seal, true, 32, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }

        if (!\is_array($envelope)) {
            return null;
        }

        /** @var array<string, mixed> $envelope */
        return ProvisioningRecord::parse($bytes, $envelope);
    }

    private function rollback(bool $hadPrevious): void
    {
        $recordPath = $this->path(self::RECORD);
        $sealPath = $this->path(self::SEAL);

        if (!$hadPrevious) {
            @unlink($recordPath);
            @unlink($sealPath);

            return;
        }

        @copy($recordPath . '.bak', $recordPath);
        @copy($sealPath . '.bak', $sealPath);
    }

    private function discard(string ...$paths): void
    {
        foreach ($paths as $path) {
            if (is_file($path)) {
                @unlink($path);
            }
        }
    }

    private function read(string $path): ?string
    {
        if (!is_file($path)) {
            return null;
        }

        $content = @file_get_contents($path);

        return \is_string($content) && $content !== '' ? $content : null;
    }

    private function write(string $path, string $content): bool
    {
        $handle = @fopen($path, 'wb');
        if (!\is_resource($handle)) {
            return false;
        }

        $written = @fwrite($handle, $content);
        @fflush($handle);

        // Durability: without this a crash can leave a zero-length record.
        if (\function_exists('fsync')) {
            @fsync($handle);
        }

        @fclose($handle);
        @chmod($path, 0600);

        return $written === \strlen($content);
    }

    private function prepareDirectory(): bool
    {
        $dir = $this->directory();

        if (!is_dir($dir) && !@mkdir($dir, 0700, true) && !is_dir($dir)) {
            return false;
        }

        return is_writable($dir);
    }

    private function path(string $name): string
    {
        return $this->directory() . '/' . $name;
    }

    private function acquire(): void
    {
        if ($this->depth++ > 0) {
            return;
        }

        $this->prepareDirectory();

        $handle = @fopen($this->path(self::LOCK), 'c');
        if (\is_resource($handle)) {
            @chmod($this->path(self::LOCK), 0600);
            @flock($handle, LOCK_EX);
            $this->lockHandle = $handle;
        }
    }

    private function release(): void
    {
        if (--$this->depth > 0) {
            return;
        }

        $this->depth = 0;

        if (\is_resource($this->lockHandle)) {
            @flock($this->lockHandle, LOCK_UN);
            @fclose($this->lockHandle);
        }

        $this->lockHandle = null;
    }
}

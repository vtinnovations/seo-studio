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

    public function directory(): string
    {
        return $this->projectDir . '/' . self::DIRECTORY;
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

<?php

declare(strict_types=1);

/*
 * AI SEO Studio
 *
 * Package: vtinnovations/seo-studio
 * Copyright: VT Innovations Team
 * Licence: LGPL-3.0-or-later
 */

namespace VTinnovations\SeoStudio\Core\Security;

use Defuse\Crypto\Crypto;
use Defuse\Crypto\Exception\CryptoException;
use Defuse\Crypto\Key;

/**
 * Encrypted-at-rest store for secrets (API keys). Anti-pattern this exists to
 * kill: Access+ kept the OpenAI key in cleartext in tl_page.openApiKey.
 *
 * Design (the project guidelines §3.1):
 *   - Secrets are encrypted with defuse/php-encryption (authenticated AES).
 *   - Ciphertext lives in var/seostudio/secrets.json (hex strings, never plain).
 *   - The encryption key lives in a SEPARATE file var/seostudio/secret.key with
 *     mode 0600, generated on first use, listed in .gitignore. Keeping key and
 *     ciphertext in different files means a leaked DB dump or a leaked
 *     secrets.json alone is useless.
 *   - The key is NEVER written to the DB, VCS, logs, frontend, or exceptions.
 *
 * Failure philosophy: a decryption failure (wrong/rotated key, tampered file)
 * returns null from get() rather than throwing, so the UI degrades to "no key
 * set" instead of leaking a stack trace. set()/the key bootstrap DO throw —
 * those are real, actionable misconfigurations.
 */
final class SecretStore
{
    private const KEY_RELATIVE_PATH = 'var/seostudio/secret.key';

    private const STORE_RELATIVE_PATH = 'var/seostudio/secrets.json';

    private ?Key $key = null;

    public function __construct(
        private readonly string $projectDir,
    ) {
    }

    /**
     * Encrypt and persist a secret under a logical name (e.g. "ai_api_key").
     * An empty string deletes the entry instead of storing empty ciphertext.
     */
    public function set(string $name, string $plaintext): void
    {
        if ($plaintext === '') {
            $this->delete($name);

            return;
        }

        try {
            $cipher = Crypto::encrypt($plaintext, $this->loadOrCreateKey());
        } catch (CryptoException $e) {
            // Do NOT include $plaintext or the exception detail verbatim in a
            // way that could surface the secret; the message is generic.
            throw new \RuntimeException('Failed to encrypt secret "' . $name . '".', 0, $e);
        }

        $store = $this->readStore();
        $store[$name] = $cipher;
        $this->writeStore($store);
    }

    /**
     * Decrypt and return a secret, or null if it is absent or undecryptable.
     */
    public function get(string $name): ?string
    {
        $store = $this->readStore();
        $cipher = $store[$name] ?? null;

        if (!\is_string($cipher) || $cipher === '') {
            return null;
        }

        try {
            return Crypto::decrypt($cipher, $this->loadOrCreateKey());
        } catch (CryptoException) {
            // Wrong key / tampered ciphertext. Treat as "not set" — never echo
            // the cause to the caller (could aid an attacker, never the user).
            return null;
        }
    }

    /**
     * Whether a (decryptable) secret exists. Lets the UI show "gesetzt/leer"
     * without ever revealing the value.
     */
    public function has(string $name): bool
    {
        return $this->get($name) !== null;
    }

    public function delete(string $name): void
    {
        $store = $this->readStore();
        if (!\array_key_exists($name, $store)) {
            return;
        }

        unset($store[$name]);
        $this->writeStore($store);
    }

    /**
     * Loads the encryption key from var/seostudio/secret.key, generating it on
     * first use with mode 0600.
     */
    private function loadOrCreateKey(): Key
    {
        if ($this->key instanceof Key) {
            return $this->key;
        }

        $path = $this->projectDir . '/' . self::KEY_RELATIVE_PATH;

        if (is_file($path)) {
            $ascii = @file_get_contents($path);
            if ($ascii === false || trim($ascii) === '') {
                throw new \RuntimeException('Encryption key file exists but is unreadable or empty.');
            }

            try {
                return $this->key = Key::loadFromAsciiSafeString(trim($ascii));
            } catch (CryptoException $e) {
                throw new \RuntimeException('Encryption key file is corrupt.', 0, $e);
            }
        }

        $key = Key::createNewRandomKey();
        $this->persistKeyFile($path, $key->saveToAsciiSafeString());

        return $this->key = $key;
    }

    private function persistKeyFile(string $path, string $ascii): void
    {
        $dir = \dirname($path);
        if (!is_dir($dir) && !@mkdir($dir, 0700, true) && !is_dir($dir)) {
            throw new \RuntimeException('Could not create secret directory.');
        }

        if (@file_put_contents($path, $ascii, LOCK_EX) === false) {
            throw new \RuntimeException('Could not write encryption key file.');
        }

        // Best-effort lockdown; @ because some shared hosts disallow chmod.
        @chmod($path, 0600);
    }

    /**
     * @return array<string, string>
     */
    private function readStore(): array
    {
        $path = $this->projectDir . '/' . self::STORE_RELATIVE_PATH;
        if (!is_file($path)) {
            return [];
        }

        $content = @file_get_contents($path);
        if ($content === false || $content === '') {
            return [];
        }

        $data = json_decode($content, true);
        if (!\is_array($data)) {
            return [];
        }

        $out = [];
        foreach ($data as $name => $cipher) {
            if (\is_string($name) && \is_string($cipher)) {
                $out[$name] = $cipher;
            }
        }

        return $out;
    }

    /**
     * @param array<string, string> $store
     */
    private function writeStore(array $store): void
    {
        $path = $this->projectDir . '/' . self::STORE_RELATIVE_PATH;
        $dir = \dirname($path);

        if (!is_dir($dir) && !@mkdir($dir, 0700, true) && !is_dir($dir)) {
            throw new \RuntimeException('Could not create secret directory.');
        }

        $json = json_encode($store, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new \RuntimeException('Failed to encode secret store.');
        }

        if (@file_put_contents($path, $json, LOCK_EX) === false) {
            throw new \RuntimeException('Could not write secret store.');
        }

        @chmod($path, 0600);
    }
}

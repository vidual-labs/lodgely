<?php

namespace App\Support\Backup;

use RuntimeException;

/**
 * Passphrase encryption for the database dump inside a backup archive.
 *
 * A lodgely backup is a complete copy of every lead's name, email, phone and
 * message body in cleartext. That is fine while it sits in
 * storage/app/private/backups on a server the operator controls, and much less
 * fine the moment it is downloaded, synced to object storage, or mailed
 * around — which is the entire point of taking backups. Setting
 * LODGELY_BACKUP_PASSPHRASE makes the dump unreadable without the passphrase.
 *
 * Construction: AES-256-GCM over the plaintext split into fixed-size frames,
 * with the key derived from the passphrase via PBKDF2-SHA256.
 *
 * Framing exists because the alternative — one openssl_encrypt() over the
 * whole dump — needs the entire database in memory twice, which falls over on
 * exactly the large installs that most need backups. Each frame is written as:
 *
 *     [uint32 big-endian ciphertext length][16-byte GCM tag][ciphertext]
 *
 * Each frame gets its own nonce: an 8-byte random prefix (shared by the file)
 * followed by a 4-byte big-endian frame counter. Reusing a key+nonce pair is
 * the one thing that breaks GCM, and a counter makes repeats impossible within
 * a file while a fresh random prefix per file makes them impossible across
 * files. Because the counter is part of the nonce, a reordered or duplicated
 * frame fails its tag check rather than decrypting into the wrong place.
 *
 * Dropped *trailing* frames would otherwise go unnoticed — every remaining
 * frame still authenticates — so the plaintext length is recorded in the
 * manifest and verified after decryption.
 *
 * The salt, nonce prefix and frame size are not secret; they live in the
 * archive manifest next to the ciphertext. The passphrase never does.
 */
class ArchiveCipher
{
    private const CIPHER = 'aes-256-gcm';

    /** Plaintext bytes per frame. 1 MiB keeps peak memory flat regardless of dump size. */
    private const CHUNK_BYTES = 1048576;

    /** PBKDF2-SHA256 iterations — OWASP's floor for this algorithm. */
    private const KDF_ITERATIONS = 210000;

    private const KEY_BYTES = 32;

    private const SALT_BYTES = 16;

    private const NONCE_PREFIX_BYTES = 8;

    private const TAG_BYTES = 16;

    private const LENGTH_HEADER_BYTES = 4;

    /**
     * Encrypt $sourcePath to $destPath and return the metadata a later
     * decrypt() needs, for storage in the archive manifest.
     *
     * @return array<string, mixed>
     */
    public function encryptFile(string $sourcePath, string $destPath, string $passphrase): array
    {
        $this->assertCipherAvailable();

        $salt = random_bytes(self::SALT_BYTES);
        $noncePrefix = random_bytes(self::NONCE_PREFIX_BYTES);
        $key = $this->deriveKey($passphrase, $salt);

        $in = $this->open($sourcePath, 'rb');
        $out = $this->open($destPath, 'wb');

        $plaintextBytes = 0;
        $counter = 0;

        try {
            while (! feof($in)) {
                $plain = fread($in, self::CHUNK_BYTES);

                if ($plain === false) {
                    throw new RuntimeException('Could not read the database dump while encrypting the backup.');
                }

                // fread can legitimately return '' at EOF; don't emit an empty frame for it.
                if ($plain === '') {
                    continue;
                }

                $tag = '';
                $cipher = openssl_encrypt(
                    $plain,
                    self::CIPHER,
                    $key,
                    OPENSSL_RAW_DATA,
                    $this->nonce($noncePrefix, $counter),
                    $tag,
                    '',
                    self::TAG_BYTES,
                );

                if ($cipher === false) {
                    throw new RuntimeException('Backup encryption failed: '.$this->opensslErrors());
                }

                $this->write($out, pack('N', strlen($cipher)).$tag.$cipher);

                $plaintextBytes += strlen($plain);
                $counter++;
            }
        } finally {
            fclose($in);
            fclose($out);
            $this->wipe($key);
        }

        return [
            'cipher' => self::CIPHER,
            'kdf' => 'pbkdf2-sha256',
            'kdf_iterations' => self::KDF_ITERATIONS,
            'salt' => base64_encode($salt),
            'nonce_prefix' => base64_encode($noncePrefix),
            'chunk_bytes' => self::CHUNK_BYTES,
            'plaintext_bytes' => $plaintextBytes,
        ];
    }

    /**
     * Decrypt $sourcePath to $destPath using the metadata encryptFile() returned.
     *
     * @param  array<string, mixed>  $meta
     */
    public function decryptFile(string $sourcePath, string $destPath, string $passphrase, array $meta): void
    {
        $this->assertCipherAvailable();

        $cipherName = (string) ($meta['cipher'] ?? '');
        if ($cipherName !== self::CIPHER) {
            throw new RuntimeException(
                "This backup was encrypted with '{$cipherName}', which this version of lodgely cannot read."
            );
        }

        $salt = base64_decode((string) ($meta['salt'] ?? ''), true);
        $noncePrefix = base64_decode((string) ($meta['nonce_prefix'] ?? ''), true);
        $iterations = (int) ($meta['kdf_iterations'] ?? 0);

        if ($salt === false || $noncePrefix === false || $salt === '' || $noncePrefix === '' || $iterations < 1) {
            throw new RuntimeException('This backup archive is missing its encryption parameters and cannot be decrypted.');
        }

        $key = $this->deriveKey($passphrase, $salt, $iterations);

        $in = $this->open($sourcePath, 'rb');
        $out = $this->open($destPath, 'wb');

        $plaintextBytes = 0;
        $counter = 0;

        try {
            while (true) {
                $header = $this->readExactly($in, self::LENGTH_HEADER_BYTES, allowEof: true);
                if ($header === null) {
                    break;
                }

                /** @var array{1: int} $unpacked */
                $unpacked = unpack('N', $header);
                $length = $unpacked[1];

                $tag = $this->readExactly($in, self::TAG_BYTES);
                $cipherText = $this->readExactly($in, $length);

                $plain = openssl_decrypt(
                    $cipherText,
                    self::CIPHER,
                    $key,
                    OPENSSL_RAW_DATA,
                    $this->nonce($noncePrefix, $counter),
                    $tag,
                );

                // GCM authenticates as well as decrypts, so a false here means
                // the wrong passphrase or a tampered/truncated archive. There
                // is no way to tell those apart, and saying so is more useful
                // than a generic failure.
                if ($plain === false) {
                    throw new RuntimeException(
                        'Could not decrypt this backup. Check LODGELY_BACKUP_PASSPHRASE matches the one used '
                        .'when the archive was created — a wrong passphrase and a corrupted archive look identical here.'
                    );
                }

                $this->write($out, $plain);

                $plaintextBytes += strlen($plain);
                $counter++;
            }
        } finally {
            fclose($in);
            fclose($out);
            $this->wipe($key);
        }

        $expected = (int) ($meta['plaintext_bytes'] ?? -1);
        if ($expected >= 0 && $expected !== $plaintextBytes) {
            throw new RuntimeException(sprintf(
                'This backup is truncated: expected %d bytes of database dump, recovered %d. Refusing to restore from it.',
                $expected,
                $plaintextBytes,
            ));
        }
    }

    /**
     * Best-effort scrub of the derived key. sodium is bundled with PHP 8.4 but
     * can be compiled out, and a missing extension must not turn a working
     * backup into a fatal error — so this degrades to letting GC handle it.
     */
    private function wipe(string &$key): void
    {
        if (function_exists('sodium_memzero')) {
            sodium_memzero($key);
        }
    }

    private function deriveKey(string $passphrase, string $salt, ?int $iterations = null): string
    {
        return hash_pbkdf2(
            'sha256',
            $passphrase,
            $salt,
            $iterations ?? self::KDF_ITERATIONS,
            self::KEY_BYTES,
            true,
        );
    }

    /** 12-byte GCM nonce: per-file random prefix + big-endian frame counter. */
    private function nonce(string $prefix, int $counter): string
    {
        return $prefix.pack('N', $counter);
    }

    private function assertCipherAvailable(): void
    {
        if (! in_array(self::CIPHER, openssl_get_cipher_methods(), true)) {
            throw new RuntimeException(
                'This PHP build has no '.self::CIPHER.' support, so encrypted backups are unavailable. '
                .'Unset LODGELY_BACKUP_PASSPHRASE to write unencrypted archives.'
            );
        }
    }

    /** @return resource */
    private function open(string $path, string $mode)
    {
        $handle = @fopen($path, $mode);

        if ($handle === false) {
            throw new RuntimeException("Could not open {$path} while processing the backup archive.");
        }

        return $handle;
    }

    /**
     * fwrite() can short-write; loop until the buffer is flushed so a frame is
     * never silently truncated.
     *
     * @param  resource  $handle
     */
    private function write($handle, string $bytes): void
    {
        $total = strlen($bytes);
        $written = 0;

        while ($written < $total) {
            $result = fwrite($handle, substr($bytes, $written));

            if ($result === false || $result === 0) {
                throw new RuntimeException('Could not write the backup archive (out of disk space?).');
            }

            $written += $result;
        }
    }

    /**
     * Read exactly $bytes, looping over short reads. Returns null at a clean
     * end-of-file when $allowEof is set; anything else short is a truncated
     * archive and throws.
     *
     * @param  resource  $handle
     */
    private function readExactly($handle, int $bytes, bool $allowEof = false): ?string
    {
        if ($bytes < 0) {
            throw new RuntimeException('This backup archive declares a negative frame length and is unreadable.');
        }

        $buffer = '';

        while (strlen($buffer) < $bytes) {
            $chunk = fread($handle, $bytes - strlen($buffer));

            if ($chunk === false || $chunk === '') {
                if ($buffer === '' && $allowEof) {
                    return null;
                }

                throw new RuntimeException('This backup archive is truncated and cannot be decrypted.');
            }

            $buffer .= $chunk;
        }

        return $buffer;
    }

    private function opensslErrors(): string
    {
        $messages = [];

        while ($error = openssl_error_string()) {
            $messages[] = $error;
        }

        return $messages === [] ? 'unknown OpenSSL error' : implode('; ', $messages);
    }
}

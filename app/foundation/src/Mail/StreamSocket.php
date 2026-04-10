<?php

declare(strict_types=1);

namespace App\Foundation\Mail;

use Marko\Mail\Smtp\SocketInterface;

class StreamSocket implements SocketInterface
{
    /** @var resource|null */
    private $stream = null;

    public bool $connected {
        get => $this->stream !== null;
    }

    public function connect(
        string $host,
        int $port,
        ?string $encryption = null,
        int $timeout = 30,
    ): void {
        $address = ($encryption === 'ssl' ? 'ssl://' : '') . $host . ':' . $port;

        $this->stream = @stream_socket_client(
            $address,
            $errorCode,
            $errorMessage,
            $timeout,
        );

        if ($this->stream === false) {
            $this->stream = null;
            throw new \RuntimeException("Failed to connect to {$address}: {$errorMessage} ({$errorCode})");
        }

        stream_set_timeout($this->stream, $timeout);
    }

    public function read(): string
    {
        if ($this->stream === null) {
            return '';
        }

        $response = '';

        while ($line = fgets($this->stream, 512)) {
            $response .= $line;

            // SMTP multi-line responses use "NNN-" prefix; last line uses "NNN "
            if (isset($line[3]) && $line[3] === ' ') {
                break;
            }

            // Single-line response without continuation
            if (!isset($line[3]) || $line[3] !== '-') {
                break;
            }
        }

        return $response;
    }

    public function write(string $data): void
    {
        if ($this->stream === null) {
            return;
        }

        fwrite($this->stream, $data);
    }

    public function enableTls(): bool
    {
        if ($this->stream === null) {
            return false;
        }

        return stream_socket_enable_crypto(
            $this->stream,
            true,
            STREAM_CRYPTO_METHOD_TLS_CLIENT,
        );
    }

    public function close(): void
    {
        if ($this->stream !== null) {
            fclose($this->stream);
            $this->stream = null;
        }
    }
}

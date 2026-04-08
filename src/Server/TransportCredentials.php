<?php

declare(strict_types=1);

namespace Thesis\Grpc\Server;

use Amp\Socket\Certificate;
use Amp\Socket\ServerTlsContext;

/**
 * @api
 */
final class TransportCredentials
{
    private ?Certificate $defaultCertificate = null;

    /** @var array<non-empty-string, Certificate> */
    private array $certificates = [];

    /** @var ?non-empty-string */
    private ?string $peerName = null;

    /** @var ?non-empty-string */
    private ?string $caCert = null;

    /** @var ?non-empty-string */
    private ?string $caPath = null;

    private bool $verifyPeer = false;

    public function withDefaultCertificate(Certificate $certificate): self
    {
        $credentials = clone $this;
        $credentials->defaultCertificate = $certificate;

        return $credentials;
    }

    /**
     * @param array<non-empty-string, Certificate> $certificates
     */
    public function withCertificates(array $certificates): self
    {
        $credentials = clone $this;
        $credentials->certificates = [
            ...$credentials->certificates,
            ...$certificates,
        ];

        return $credentials;
    }

    /**
     * @param ?non-empty-string $peerName
     */
    public function withPeerName(?string $peerName = null): self
    {
        $credentials = clone $this;
        $credentials->peerName = $peerName;

        return $credentials;
    }

    /**
     * @param non-empty-string $caCert
     */
    public function withCaCert(string $caCert): self
    {
        $credentials = clone $this;
        $credentials->caCert = $caCert;

        return $credentials;
    }

    /**
     * @param non-empty-string $caPath
     */
    public function withCaPath(string $caPath): self
    {
        $credentials = clone $this;
        $credentials->caPath = $caPath;

        return $credentials;
    }

    public function withVerifyPeer(bool $verifyPeer = true): self
    {
        $credentials = clone $this;
        $credentials->verifyPeer = $verifyPeer;

        return $credentials;
    }

    public function createContext(): ServerTlsContext
    {
        $context = new ServerTlsContext()
            ->withDefaultCertificate($this->defaultCertificate)
            ->withCertificates($this->certificates)
            ->withPeerName($this->peerName)
            ->withCaFile($this->caCert)
            ->withCaPath($this->caPath)
            ->withApplicationLayerProtocols(['h2'])
            ->withoutPeerVerification();

        if ($this->verifyPeer) {
            $context = $context->withPeerVerification();
        }

        return $context;
    }
}

<?php

declare(strict_types=1);

namespace Thesis\Grpc\Credentials;

use Amp\Socket\Certificate;
use Amp\Socket\ClientTlsContext;
use Amp\Socket\ServerTlsContext;
use Thesis\Grpc\Client;
use Thesis\Grpc\Server;

/**
 * @api
 */
final class SecureTransportCredentials implements
    Client\TransportCredentials,
    Server\TransportCredentials
{
    private string $peerName = '';

    /** @var ?non-empty-string */
    private ?string $caCert = null;

    /** @var ?non-empty-string */
    private ?string $caPath = null;

    private bool $verifyPeer = true;

    private bool $verifyPeerName = true;

    private ?Certificate $certificate = null;

    /** @var array<non-empty-string, Certificate> */
    private array $certificates = [];

    public function withPeerName(string $peerName): self
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

    public function withVerifyPeerName(bool $verifyPeerName = true): self
    {
        $credentials = clone $this;
        $credentials->verifyPeerName = $verifyPeerName;

        return $credentials;
    }

    public function withCertificate(Certificate $certificate): self
    {
        $credentials = clone $this;
        $credentials->certificate = $certificate;

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

    #[\Override]
    public function createClientContext(): ClientTlsContext
    {
        $context = new ClientTlsContext($this->peerName)
            ->withCaFile($this->caCert)
            ->withCaPath($this->caPath)
            ->withoutPeerVerification()
            ->withoutPeerNameVerification()
            ->withCertificate($this->certificate)
            ->withApplicationLayerProtocols(['2']);

        if ($this->verifyPeer) {
            $context = $context->withPeerVerification();
        }

        if ($this->verifyPeerName) {
            $context = $context->withPeerNameVerification();
        }

        return $context;
    }

    #[\Override]
    public function createServerContext(): ServerTlsContext
    {
        $context = new ServerTlsContext()
            ->withCaFile($this->caCert)
            ->withCaPath($this->caPath)
            ->withoutPeerVerification()
            ->withoutPeerNameVerification()
            ->withPeerName($this->peerName)
            ->withCertificates($this->certificates)
            ->withApplicationLayerProtocols(['2']);

        if ($this->verifyPeer) {
            $context = $context->withPeerVerification();
        }

        if ($this->verifyPeerName) {
            $context = $context->withPeerNameVerification();
        }

        return $context;
    }
}

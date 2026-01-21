<?php

declare(strict_types=1);

namespace Thesis\Grpc\Client\Credentials;

use Amp\Socket\Certificate;
use Amp\Socket\ClientTlsContext;
use Thesis\Grpc\Client\TransportCredentials;

/**
 * @api
 */
final class SecureTransportCredentials implements TransportCredentials
{
    private string $peerName = '';

    /** @var ?non-empty-string */
    private ?string $caCert = null;

    /** @var ?non-empty-string */
    private ?string $caPath = null;

    private bool $verifyPeer = true;

    private bool $verifyPeerName = true;

    private ?Certificate $certificate = null;

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

    #[\Override]
    public function buildTlsContext(): ClientTlsContext
    {
        $context = new ClientTlsContext($this->peerName)
            ->withCaFile($this->caCert)
            ->withCaPath($this->caPath)
            ->withoutPeerVerification()
            ->withPeerNameVerification()
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
}

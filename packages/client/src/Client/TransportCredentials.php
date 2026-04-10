<?php

declare(strict_types=1);

namespace Thesis\Grpc\Client;

use Amp\Socket\Certificate;
use Amp\Socket\ClientTlsContext;

/**
 * @api
 */
final class TransportCredentials
{
    private string $peerName = '';

    /** @var ?non-empty-string */
    private ?string $caCert = null;

    /** @var ?non-empty-string */
    private ?string $caPath = null;

    private bool $verifyPeer = true;

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

    public function withCertificate(Certificate $certificate): self
    {
        $credentials = clone $this;
        $credentials->certificate = $certificate;

        return $credentials;
    }

    public function createContext(): ClientTlsContext
    {
        $context = new ClientTlsContext($this->peerName)
            ->withCaFile($this->caCert)
            ->withCaPath($this->caPath)
            ->withCertificate($this->certificate)
            ->withApplicationLayerProtocols(['h2'])
            ->withoutPeerVerification();

        if ($this->verifyPeer) {
            $context = $context->withPeerVerification();
        }

        return $context;
    }
}

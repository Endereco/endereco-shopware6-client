<?php
namespace Endereco\Shopware6Client\Service;

class ProcessContextService
{
    private bool $isStorefront = false;

    public function setIsStorefront(bool $isStorefront): void
    {
        $this->isStorefront = $isStorefront;
    }

    public function isStorefront(): bool
    {
        return $this->isStorefront;
    }

    public function reset(): void
    {
        $this->isStorefront = false;
    }
}
<?php

namespace App\Entity;

use App\Repository\SystemConfigRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SystemConfigRepository::class)]
#[ORM\Table(name: 'system_config')]
class SystemConfig
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 50)]
    private string $configKey;

    #[ORM\Column(type: 'string', length: 255)]
    private string $configValue;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    public function __construct(string $key, string $value)
    {
        $this->configKey = $key;
        $this->configValue = $value;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getConfigKey(): string
    {
        return $this->configKey;
    }

    public function getConfigValue(): string
    {
        return $this->configValue;
    }

    public function setConfigValue(string $value): self
    {
        $this->configValue = $value;
        $this->updatedAt = new \DateTimeImmutable();
        return $this;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function isEnabled(): bool
    {
        return $this->configValue === '1' || strtolower($this->configValue) === 'true';
    }
}

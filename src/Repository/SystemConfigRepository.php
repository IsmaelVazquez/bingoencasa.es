<?php

namespace App\Repository;

use App\Entity\SystemConfig;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class SystemConfigRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SystemConfig::class);
    }

    public function getValue(string $key, string $default = ''): string
    {
        $config = $this->find($key);
        return $config ? $config->getConfigValue() : $default;
    }

    public function isEnabled(string $key, bool $default = true): bool
    {
        $config = $this->find($key);
        if (!$config) {
            return $default;
        }
        return $config->isEnabled();
    }

    public function setValue(string $key, string $value): void
    {
        $config = $this->find($key);
        if ($config) {
            $config->setConfigValue($value);
        } else {
            $config = new SystemConfig($key, $value);
            $this->getEntityManager()->persist($config);
        }
        $this->getEntityManager()->flush();
    }

    public function getGameModeStatus(): array
    {
        return [
            'modoBombo' => $this->isEnabled('modo_bombo_enabled', true),
            'modoFiesta' => $this->isEnabled('modo_fiesta_enabled', true),
        ];
    }
}

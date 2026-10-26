<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261026120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Token de anfitrión por sala (solo se guarda el hash)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE room ADD host_token_hash VARCHAR(64) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE room DROP host_token_hash');
    }
}

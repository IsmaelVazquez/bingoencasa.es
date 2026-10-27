<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261026160000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Bola única por partida y sesiones en la base de datos';
    }

    public function up(Schema $schema): void
    {
        // Antes no había bloqueo y una bola podía salir dos veces: se queda la primera.
        $this->addSql('DELETE FROM draw d USING draw d2 WHERE d.game_id = d2.game_id AND d.number = d2.number AND d.id > d2.id');
        $this->addSql('CREATE UNIQUE INDEX uniq_draw_game_number ON draw (game_id, number)');

        // Tabla de PdoSessionHandler: las sesiones sobreviven a reinicios y despliegues.
        $this->addSql('CREATE TABLE sessions (sess_id VARCHAR(128) NOT NULL PRIMARY KEY, sess_data BYTEA NOT NULL, sess_lifetime INTEGER NOT NULL, sess_time INTEGER NOT NULL)');
        $this->addSql('CREATE INDEX sess_lifetime_idx ON sessions (sess_lifetime)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE sessions');
        $this->addSql('DROP INDEX uniq_draw_game_number');
    }
}

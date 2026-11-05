<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261105120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add download settings with the web download intake switch';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE download_settings (
              id SERIAL NOT NULL,
              web_intake_enabled BOOLEAN DEFAULT true NOT NULL,
              PRIMARY KEY(id)
            )
        SQL);
        $this->addSql('INSERT INTO download_settings (web_intake_enabled) VALUES (true)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE download_settings');
    }
}

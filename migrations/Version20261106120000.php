<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261106120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add the Telegram download progress interval setting';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE download_settings ADD progress_interval_minutes INT DEFAULT 5 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE download_settings DROP progress_interval_minutes');
    }
}

<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261111120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add the admin-configurable minimum free disk space setting';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE download_settings ADD min_free_disk_mb INT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE download_settings DROP min_free_disk_mb');
    }
}

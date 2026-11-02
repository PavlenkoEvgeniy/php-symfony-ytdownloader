<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261102183358 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add error details and timing to download tasks';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE download_task ADD error_message TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE download_task ADD attempts INT DEFAULT 0 NOT NULL');
        $this->addSql('ALTER TABLE download_task ADD started_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE download_task ADD finished_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('COMMENT ON COLUMN download_task.started_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN download_task.finished_at IS \'(DC2Type:datetime_immutable)\'');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE download_task DROP error_message');
        $this->addSql('ALTER TABLE download_task DROP attempts');
        $this->addSql('ALTER TABLE download_task DROP started_at');
        $this->addSql('ALTER TABLE download_task DROP finished_at');
    }
}

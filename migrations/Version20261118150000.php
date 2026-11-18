<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261118150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add the atomic Telegram update dedupe table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE telegram_update_dedupe (update_id BIGINT NOT NULL, seen_at TIMESTAMP(0) WITH TIME ZONE NOT NULL DEFAULT NOW(), PRIMARY KEY(update_id))');
        $this->addSql('CREATE INDEX IDX_TELEGRAM_UPDATE_DEDUPE_SEEN_AT ON telegram_update_dedupe (seen_at)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE telegram_update_dedupe');
    }
}

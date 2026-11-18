<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261118170000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Key the Telegram dedupe table by the stable message identity';
    }

    public function up(Schema $schema): void
    {
        // The local Bot API server renumbers updates, so the same message
        // arrives with different update ids through the webhook and polling.
        // The table is disposable (at-most-once claims only).
        $this->addSql('DROP TABLE telegram_update_dedupe');
        $this->addSql('CREATE TABLE telegram_update_dedupe (dedupe_key VARCHAR(190) NOT NULL, seen_at TIMESTAMP(0) WITH TIME ZONE NOT NULL DEFAULT NOW(), PRIMARY KEY(dedupe_key))');
        $this->addSql('CREATE INDEX IDX_TELEGRAM_UPDATE_DEDUPE_SEEN_AT ON telegram_update_dedupe (seen_at)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE telegram_update_dedupe');
    }
}

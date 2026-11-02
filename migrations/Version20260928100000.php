<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Aligns messenger_messages to the doctrine auto-setup schema (composite index,
 * no trigger), converging legacy installs and deploy databases (ADR-0004).
 */
final class Version20260928100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Align messenger_messages schema with the doctrine auto-setup schema (indexes and trigger cleanup)';
    }

    public function up(Schema $schema): void
    {
        $tableExists = (bool) $this->connection->fetchOne(
            "SELECT 1 FROM information_schema.tables WHERE table_schema = 'public' AND table_name = 'messenger_messages'"
        );

        if (!$tableExists) {
            return;
        }

        $this->addSql(<<<'SQL'
            CREATE INDEX IF NOT EXISTS idx_messenger_messages ON messenger_messages (queue_name, available_at, delivered_at, id)
        SQL);
        $this->addSql(<<<'SQL'
            DROP INDEX IF EXISTS IDX_75EA56E0FB7336F0
        SQL);
        $this->addSql(<<<'SQL'
            DROP INDEX IF EXISTS IDX_75EA56E0E3BD61CE
        SQL);
        $this->addSql(<<<'SQL'
            DROP INDEX IF EXISTS IDX_75EA56E016BA31DB
        SQL);
        $this->addSql(<<<'SQL'
            DROP TRIGGER IF EXISTS notify_trigger ON messenger_messages
        SQL);
        $this->addSql(<<<'SQL'
            DROP FUNCTION IF EXISTS notify_messenger_messages()
        SQL);
    }

    public function down(Schema $schema): void
    {
        // Nothing to restore: the trigger was unused and the indexes are the drift being removed.
    }
}

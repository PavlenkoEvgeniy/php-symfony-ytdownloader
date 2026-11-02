<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Creates messenger_messages for the doctrine transport, mirroring auto-setup.
 * Idempotent: workers may create it before migrations run (ADR-0003).
 */
final class Version20260923130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create messenger_messages table to replace the RabbitMQ transport with the doctrine transport';
    }

    public function up(Schema $schema): void
    {
        $tableExists = (bool) $this->connection->fetchOne(
            "SELECT 1 FROM information_schema.tables WHERE table_schema = 'public' AND table_name = 'messenger_messages'"
        );

        if ($tableExists) {
            return;
        }

        $this->addSql(<<<'SQL'
            CREATE TABLE messenger_messages (id BIGSERIAL NOT NULL, body TEXT NOT NULL, headers TEXT NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, available_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, delivered_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, PRIMARY KEY(id))
        SQL);
        $this->addSql(<<<'SQL'
            CREATE INDEX idx_messenger_messages ON messenger_messages (queue_name, available_at, delivered_at, id)
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            DROP TABLE messenger_messages
        SQL);
    }
}

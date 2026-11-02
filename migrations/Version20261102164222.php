<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261102164222 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create the audit_event table';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE TABLE audit_event (
              id SERIAL NOT NULL,
              occurred_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
              action VARCHAR(32) NOT NULL,
              channel VARCHAR(16) NOT NULL,
              actor_type VARCHAR(16) NOT NULL,
              success BOOLEAN NOT NULL,
              actor_id INT DEFAULT NULL,
              actor_label VARCHAR(255) DEFAULT NULL,
              target_type VARCHAR(32) DEFAULT NULL,
              target_id INT DEFAULT NULL,
              target_label VARCHAR(512) DEFAULT NULL,
              ip VARCHAR(45) DEFAULT NULL,
              user_agent VARCHAR(512) DEFAULT NULL,
              request_id VARCHAR(64) DEFAULT NULL,
              metadata JSON DEFAULT NULL,
              PRIMARY KEY(id)
            )
        SQL);
        $this->addSql('CREATE INDEX idx_audit_event_occurred_at ON audit_event (occurred_at)');
        $this->addSql('CREATE INDEX idx_audit_event_action ON audit_event (action)');
        $this->addSql('CREATE INDEX idx_audit_event_actor ON audit_event (actor_id)');
        $this->addSql('COMMENT ON COLUMN audit_event.occurred_at IS \'(DC2Type:datetime_immutable)\'');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE audit_event');
    }
}

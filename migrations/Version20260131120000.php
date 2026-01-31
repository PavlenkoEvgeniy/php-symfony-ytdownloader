<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260131120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create refresh_token table for API v2 refresh tokens';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE refresh_token (id SERIAL NOT NULL, user_id INT NOT NULL, token VARCHAR(128) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, expires_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, revoked_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_REFRESH_TOKEN_TOKEN ON refresh_token (token)');
        $this->addSql('CREATE INDEX IDX_REFRESH_TOKEN_USER ON refresh_token (user_id)');
        $this->addSql('ALTER TABLE refresh_token ADD CONSTRAINT FK_REFRESH_TOKEN_USER FOREIGN KEY (user_id) REFERENCES "user" (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE refresh_token');
    }
}

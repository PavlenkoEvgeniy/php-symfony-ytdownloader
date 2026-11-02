<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20250711110720 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add isEnabled field to user';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE "user"
            ADD is_enabled BOOLEAN
            DEFAULT true
            NOT NULL
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE "user" DROP is_enabled');
    }
}

<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261118160000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add the per-user Telegram bot language';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE "user" ADD bot_locale VARCHAR(5) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE "user" DROP bot_locale');
    }
}

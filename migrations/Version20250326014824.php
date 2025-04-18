<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
final class Version20250326014824 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE source ADD size DOUBLE PRECISION NOT NULL AFTER description');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE source DROP size');
    }
}

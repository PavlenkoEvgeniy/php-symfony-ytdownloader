<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;


final class Version20250325003220 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add table source';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE source (
            id INT AUTO_INCREMENT NOT NULL, 
            filename VARCHAR(255) NOT NULL, 
            filepath VARCHAR(255) NOT NULL, 
            description VARCHAR(255) DEFAULT NULL, 
            created_at DATETIME NOT NULL, 
            updated_at DATETIME NOT NULL, 
            deleted_at DATETIME DEFAULT NULL, 
            PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB'
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE source');
    }
}

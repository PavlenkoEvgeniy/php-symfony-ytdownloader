<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Introduces the download_metric singleton — the lifetime "Total downloaded"
 * counter. It is seeded from the sizes of the Source rows still present at
 * migration time (bytes of already deleted sources are unrecoverable) and is
 * incremented afterwards whenever a new Source is stored. Deleting sources
 * never reduces it, unlike the previous derived SUM(source.size) (issue #77,
 * ADR-0006).
 */
final class Version20260929100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add the download_metric singleton seeded from the existing source sizes';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE download_metric (id SERIAL NOT NULL, total_bytes DOUBLE PRECISION NOT NULL DEFAULT 0, PRIMARY KEY(id))
        SQL);
        $this->addSql(<<<'SQL'
            INSERT INTO download_metric (total_bytes) SELECT COALESCE(SUM(size), 0) FROM source WHERE NOT EXISTS (SELECT 1 FROM download_metric)
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            DROP TABLE download_metric
        SQL);
    }
}

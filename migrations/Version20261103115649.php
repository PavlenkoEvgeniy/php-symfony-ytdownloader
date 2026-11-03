<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261103115649 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Link users to Telegram and track download ownership and delivery';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE "user" ADD telegram_user_id BIGINT DEFAULT NULL');
        $this->addSql('ALTER TABLE "user" ADD telegram_username VARCHAR(64) DEFAULT NULL');
        $this->addSql('ALTER TABLE "user" ADD telegram_linked_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('COMMENT ON COLUMN "user".telegram_linked_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_TELEGRAM_USER_ID ON "user" (telegram_user_id)');

        $this->addSql('ALTER TABLE download_task ADD user_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE download_task ADD origin VARCHAR(16) DEFAULT NULL');
        $this->addSql('ALTER TABLE download_task ADD telegram_chat_id BIGINT DEFAULT NULL');
        $this->addSql('ALTER TABLE download_task ADD title VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE download_task ADD delivery_status VARCHAR(16) DEFAULT NULL');
        $this->addSql('ALTER TABLE download_task ADD delivered_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE download_task ADD telegram_message_id BIGINT DEFAULT NULL');
        $this->addSql('COMMENT ON COLUMN download_task.delivered_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE download_task ADD CONSTRAINT FK_478EE019A76ED395 FOREIGN KEY (user_id) REFERENCES "user" (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX IDX_478EE019A76ED395 ON download_task (user_id)');

        $this->addSql('ALTER TABLE source ADD download_task_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE source ADD origin VARCHAR(16) DEFAULT NULL');
        $this->addSql('ALTER TABLE source ADD CONSTRAINT FK_5F8A7F7321C80043 FOREIGN KEY (download_task_id) REFERENCES download_task (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX IDX_5F8A7F7321C80043 ON source (download_task_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE source DROP CONSTRAINT FK_5F8A7F7321C80043');
        $this->addSql('DROP INDEX IDX_5F8A7F7321C80043');
        $this->addSql('ALTER TABLE source DROP download_task_id');
        $this->addSql('ALTER TABLE source DROP origin');

        $this->addSql('ALTER TABLE download_task DROP CONSTRAINT FK_478EE019A76ED395');
        $this->addSql('DROP INDEX IDX_478EE019A76ED395');
        $this->addSql('ALTER TABLE download_task DROP user_id');
        $this->addSql('ALTER TABLE download_task DROP origin');
        $this->addSql('ALTER TABLE download_task DROP telegram_chat_id');
        $this->addSql('ALTER TABLE download_task DROP title');
        $this->addSql('ALTER TABLE download_task DROP delivery_status');
        $this->addSql('ALTER TABLE download_task DROP delivered_at');
        $this->addSql('ALTER TABLE download_task DROP telegram_message_id');

        $this->addSql('DROP INDEX UNIQ_TELEGRAM_USER_ID');
        $this->addSql('ALTER TABLE "user" DROP telegram_user_id');
        $this->addSql('ALTER TABLE "user" DROP telegram_username');
        $this->addSql('ALTER TABLE "user" DROP telegram_linked_at');
    }
}

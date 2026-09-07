<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260907103639 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE ai_chat_messages (id UUID NOT NULL, role VARCHAR(16) NOT NULL, content TEXT NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, audit_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_DE15F580BD29F359 ON ai_chat_messages (audit_id)');
        $this->addSql('CREATE INDEX idx_ai_chat_audit_created ON ai_chat_messages (audit_id, created_at)');
        $this->addSql('CREATE TABLE ai_insights (id UUID NOT NULL, type VARCHAR(32) NOT NULL, content JSON NOT NULL, provider VARCHAR(64) NOT NULL, generated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, audit_id UUID NOT NULL, finding_id UUID DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_15177624BD29F359 ON ai_insights (audit_id)');
        $this->addSql('CREATE INDEX IDX_151776244323B5E7 ON ai_insights (finding_id)');
        $this->addSql('CREATE INDEX idx_ai_insight_audit_type ON ai_insights (audit_id, type)');
        $this->addSql('ALTER TABLE ai_chat_messages ADD CONSTRAINT FK_DE15F580BD29F359 FOREIGN KEY (audit_id) REFERENCES audits (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE ai_insights ADD CONSTRAINT FK_15177624BD29F359 FOREIGN KEY (audit_id) REFERENCES audits (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE ai_insights ADD CONSTRAINT FK_151776244323B5E7 FOREIGN KEY (finding_id) REFERENCES audit_findings (id) ON DELETE CASCADE NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE ai_chat_messages DROP CONSTRAINT FK_DE15F580BD29F359');
        $this->addSql('ALTER TABLE ai_insights DROP CONSTRAINT FK_15177624BD29F359');
        $this->addSql('ALTER TABLE ai_insights DROP CONSTRAINT FK_151776244323B5E7');
        $this->addSql('DROP TABLE ai_chat_messages');
        $this->addSql('DROP TABLE ai_insights');
    }
}

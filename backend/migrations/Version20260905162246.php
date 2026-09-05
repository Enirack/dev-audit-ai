<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260905162246 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE audit_findings (id UUID NOT NULL, category VARCHAR(32) NOT NULL, severity VARCHAR(16) NOT NULL, title VARCHAR(255) NOT NULL, description TEXT NOT NULL, file_path VARCHAR(1024) DEFAULT NULL, start_line INT DEFAULT NULL, end_line INT DEFAULT NULL, recommendation TEXT DEFAULT NULL, confidence DOUBLE PRECISION DEFAULT NULL, source VARCHAR(128) NOT NULL, metadata JSON DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, audit_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_84071068BD29F359 ON audit_findings (audit_id)');
        $this->addSql('CREATE INDEX idx_finding_severity ON audit_findings (severity)');
        $this->addSql('CREATE INDEX idx_finding_category ON audit_findings (category)');
        $this->addSql('CREATE TABLE audit_scores (id UUID NOT NULL, category VARCHAR(32) NOT NULL, score DOUBLE PRECISION NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, audit_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_248E1B85BD29F359 ON audit_scores (audit_id)');
        $this->addSql('CREATE UNIQUE INDEX uniq_audit_score_category ON audit_scores (audit_id, category)');
        $this->addSql('CREATE TABLE audits (id UUID NOT NULL, summary TEXT DEFAULT NULL, generated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, repository_scan_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_32451E6C89C594D2 ON audits (repository_scan_id)');
        $this->addSql('CREATE TABLE repositories (id UUID NOT NULL, name VARCHAR(255) NOT NULL, url VARCHAR(2048) NOT NULL, provider VARCHAR(32) NOT NULL, default_branch VARCHAR(255) DEFAULT NULL, description TEXT DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, owner_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_7223F417E3C61F9 ON repositories (owner_id)');
        $this->addSql('CREATE TABLE repository_scans (id UUID NOT NULL, status VARCHAR(32) NOT NULL, commit_sha VARCHAR(64) DEFAULT NULL, started_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, finished_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, error_message TEXT DEFAULT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, repository_id UUID NOT NULL, triggered_by_id UUID DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_84C6B63A50C9D4F7 ON repository_scans (repository_id)');
        $this->addSql('CREATE INDEX IDX_84C6B63A63C5923F ON repository_scans (triggered_by_id)');
        $this->addSql('CREATE TABLE users (id UUID NOT NULL, email VARCHAR(180) NOT NULL, roles JSON NOT NULL, password VARCHAR(255) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_user_email ON users (email)');
        $this->addSql('ALTER TABLE audit_findings ADD CONSTRAINT FK_84071068BD29F359 FOREIGN KEY (audit_id) REFERENCES audits (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE audit_scores ADD CONSTRAINT FK_248E1B85BD29F359 FOREIGN KEY (audit_id) REFERENCES audits (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE audits ADD CONSTRAINT FK_32451E6C89C594D2 FOREIGN KEY (repository_scan_id) REFERENCES repository_scans (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE repositories ADD CONSTRAINT FK_7223F417E3C61F9 FOREIGN KEY (owner_id) REFERENCES users (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE repository_scans ADD CONSTRAINT FK_84C6B63A50C9D4F7 FOREIGN KEY (repository_id) REFERENCES repositories (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE repository_scans ADD CONSTRAINT FK_84C6B63A63C5923F FOREIGN KEY (triggered_by_id) REFERENCES users (id) ON DELETE SET NULL NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE audit_findings DROP CONSTRAINT FK_84071068BD29F359');
        $this->addSql('ALTER TABLE audit_scores DROP CONSTRAINT FK_248E1B85BD29F359');
        $this->addSql('ALTER TABLE audits DROP CONSTRAINT FK_32451E6C89C594D2');
        $this->addSql('ALTER TABLE repositories DROP CONSTRAINT FK_7223F417E3C61F9');
        $this->addSql('ALTER TABLE repository_scans DROP CONSTRAINT FK_84C6B63A50C9D4F7');
        $this->addSql('ALTER TABLE repository_scans DROP CONSTRAINT FK_84C6B63A63C5923F');
        $this->addSql('DROP TABLE audit_findings');
        $this->addSql('DROP TABLE audit_scores');
        $this->addSql('DROP TABLE audits');
        $this->addSql('DROP TABLE repositories');
        $this->addSql('DROP TABLE repository_scans');
        $this->addSql('DROP TABLE users');
    }
}

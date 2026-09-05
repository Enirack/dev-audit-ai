<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260905172313 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE audit_findings ADD priority INT NOT NULL');
        $this->addSql('CREATE INDEX idx_finding_priority ON audit_findings (priority)');
        $this->addSql('ALTER TABLE audits ADD overall_score DOUBLE PRECISION DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP INDEX idx_finding_priority');
        $this->addSql('ALTER TABLE audit_findings DROP priority');
        $this->addSql('ALTER TABLE audits DROP overall_score');
    }
}

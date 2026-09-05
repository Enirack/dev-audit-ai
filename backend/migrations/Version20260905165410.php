<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260905165410 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE repository_inventories (id UUID NOT NULL, total_files INT NOT NULL, total_directories INT NOT NULL, total_size_bytes INT NOT NULL, binary_file_count INT NOT NULL, ignored_file_count INT NOT NULL, language_stats JSON NOT NULL, extension_stats JSON NOT NULL, metadata JSON NOT NULL, generated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, repository_scan_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_C8BC2BD289C594D2 ON repository_inventories (repository_scan_id)');
        $this->addSql('ALTER TABLE repository_inventories ADD CONSTRAINT FK_C8BC2BD289C594D2 FOREIGN KEY (repository_scan_id) REFERENCES repository_scans (id) ON DELETE CASCADE NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE repository_inventories DROP CONSTRAINT FK_C8BC2BD289C594D2');
        $this->addSql('DROP TABLE repository_inventories');
    }
}

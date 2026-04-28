<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260428000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'import_job: add is_active flag (user-pinned active job per kind)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE import_job ADD COLUMN is_active BOOLEAN NOT NULL DEFAULT FALSE');
        $this->addSql('CREATE INDEX import_job_kind_active_idx ON import_job (kind, is_active)');

        // Marca como active el ultimo done de cada kind (para no romper la app actual).
        $this->addSql(<<<'SQL'
            UPDATE import_job SET is_active = TRUE
            WHERE id IN (
                SELECT DISTINCT ON (kind) id
                FROM import_job
                WHERE status = 'done'
                ORDER BY kind, finished_at DESC
            )
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX IF EXISTS import_job_kind_active_idx');
        $this->addSql('ALTER TABLE import_job DROP COLUMN is_active');
    }
}

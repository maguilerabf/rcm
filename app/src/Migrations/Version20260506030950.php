<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Agrega flujo de aprobación de registros: status, approval_token, approved_by_id, approved_at.
 * Los usuarios existentes quedan como `active` por default (no se les pide reaprobación).
 */
final class Version20260506030950 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Approval flow: agrega status/approval_token/approved_by_id/approved_at en users.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE users ADD status VARCHAR(16) DEFAULT 'active' NOT NULL");
        $this->addSql('ALTER TABLE users ADD approval_token VARCHAR(64) DEFAULT NULL');
        $this->addSql('ALTER TABLE users ADD approved_by_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE users ADD approved_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql("COMMENT ON COLUMN users.approved_at IS '(DC2Type:datetime_immutable)'");
        $this->addSql('ALTER TABLE users ADD CONSTRAINT FK_1483A5E92D234F6A FOREIGN KEY (approved_by_id) REFERENCES users (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX IDX_1483A5E92D234F6A ON users (approved_by_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users DROP CONSTRAINT FK_1483A5E92D234F6A');
        $this->addSql('DROP INDEX IDX_1483A5E92D234F6A');
        $this->addSql('ALTER TABLE users DROP approved_at');
        $this->addSql('ALTER TABLE users DROP approved_by_id');
        $this->addSql('ALTER TABLE users DROP approval_token');
        $this->addSql('ALTER TABLE users DROP status');
    }
}

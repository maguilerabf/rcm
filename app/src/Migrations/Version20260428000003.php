<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260428000003 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Tabla password_reset_tokens (recuperación de contraseña con caducidad + rate limit)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE password_reset_tokens (
                id UUID PRIMARY KEY,
                user_id INTEGER NOT NULL,
                token_hash CHAR(64) NOT NULL,
                expires_at TIMESTAMP(0) WITH TIME ZONE NOT NULL,
                used_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL,
                created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP,
                requester_ip VARCHAR(45) DEFAULT NULL,
                requester_ua VARCHAR(255) DEFAULT NULL,
                CONSTRAINT fk_prt_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            )
        SQL);
        $this->addSql('CREATE UNIQUE INDEX prt_token_hash_uniq ON password_reset_tokens (token_hash)');
        $this->addSql('CREATE INDEX prt_user_idx ON password_reset_tokens (user_id)');
        $this->addSql('CREATE INDEX prt_expires_idx ON password_reset_tokens (expires_at)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS password_reset_tokens');
    }
}

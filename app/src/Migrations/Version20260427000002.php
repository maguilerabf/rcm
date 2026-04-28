<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260427000002 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'users: replace name with first_name + last_name';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users ADD COLUMN first_name VARCHAR(120) NOT NULL DEFAULT \'\'');
        $this->addSql('ALTER TABLE users ADD COLUMN last_name VARCHAR(120) NOT NULL DEFAULT \'\'');
        // backfill best-effort: si había algo en name, tirarlo a first_name
        $this->addSql('UPDATE users SET first_name = COALESCE(name, \'\') WHERE name IS NOT NULL');
        $this->addSql('ALTER TABLE users DROP COLUMN name');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users ADD COLUMN name VARCHAR(120) DEFAULT NULL');
        $this->addSql('UPDATE users SET name = NULLIF(TRIM(first_name || \' \' || last_name), \'\')');
        $this->addSql('ALTER TABLE users DROP COLUMN first_name');
        $this->addSql('ALTER TABLE users DROP COLUMN last_name');
    }
}

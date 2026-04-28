<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260427000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Initial schema: users, import_job, inscrito, telesalud_solicitud + Messenger transport';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE users (
                id SERIAL PRIMARY KEY,
                email VARCHAR(180) NOT NULL,
                name VARCHAR(120) DEFAULT NULL,
                password VARCHAR(255) NOT NULL,
                roles JSONB NOT NULL DEFAULT '["ROLE_USER"]'::jsonb,
                created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP
            )
        SQL);
        $this->addSql('CREATE UNIQUE INDEX users_email_uniq ON users (email)');

        $this->addSql(<<<'SQL'
            CREATE TABLE import_job (
                id UUID PRIMARY KEY,
                kind VARCHAR(32) NOT NULL,
                status VARCHAR(16) NOT NULL DEFAULT 'pending',
                original_filename VARCHAR(255) NOT NULL,
                stored_path VARCHAR(255) NOT NULL,
                rows_total INTEGER DEFAULT NULL,
                rows_imported INTEGER DEFAULT NULL,
                error TEXT DEFAULT NULL,
                created_by_id INTEGER DEFAULT NULL,
                created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP,
                started_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL,
                finished_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL,
                CONSTRAINT fk_import_job_user FOREIGN KEY (created_by_id) REFERENCES users (id) ON DELETE SET NULL
            )
        SQL);
        $this->addSql('CREATE INDEX import_job_kind_status_idx ON import_job (kind, status)');

        $this->addSql(<<<'SQL'
            CREATE TABLE inscrito (
                id BIGSERIAL PRIMARY KEY,
                import_job_id UUID NOT NULL,
                establecimiento VARCHAR(64) DEFAULT NULL,
                run VARCHAR(32) DEFAULT NULL,
                dv VARCHAR(4) DEFAULT NULL,
                run_dv VARCHAR(64) DEFAULT NULL,
                nombres VARCHAR(255) DEFAULT NULL,
                apellido_paterno VARCHAR(255) DEFAULT NULL,
                apellido_materno VARCHAR(255) DEFAULT NULL,
                sexo VARCHAR(32) DEFAULT NULL,
                fecha_nacimiento VARCHAR(32) DEFAULT NULL,
                edad_anios SMALLINT DEFAULT NULL,
                edad_meses SMALLINT DEFAULT NULL,
                edad_dias SMALLINT DEFAULT NULL,
                sector VARCHAR(64) DEFAULT NULL,
                estado VARCHAR(32) DEFAULT NULL,
                situacion VARCHAR(32) DEFAULT NULL,
                CONSTRAINT fk_inscrito_import_job FOREIGN KEY (import_job_id) REFERENCES import_job (id) ON DELETE CASCADE
            )
        SQL);
        $this->addSql('CREATE INDEX inscrito_run_dv_idx ON inscrito (run_dv)');
        $this->addSql('CREATE INDEX inscrito_import_job_idx ON inscrito (import_job_id)');

        $this->addSql(<<<'SQL'
            CREATE TABLE telesalud_solicitud (
                id BIGSERIAL PRIMARY KEY,
                import_job_id UUID NOT NULL,
                external_id BIGINT DEFAULT NULL,
                cesfam VARCHAR(255) DEFAULT NULL,
                prioridad SMALLINT DEFAULT NULL,
                codigo_seguimiento VARCHAR(64) DEFAULT NULL,
                informacion_adicional TEXT DEFAULT NULL,
                fecha_solicitud TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL,
                genero VARCHAR(32) DEFAULT NULL,
                nombre VARCHAR(255) DEFAULT NULL,
                apellido_paterno VARCHAR(255) DEFAULT NULL,
                apellido_materno VARCHAR(255) DEFAULT NULL,
                nombre_social VARCHAR(255) DEFAULT NULL,
                tipo_identificador VARCHAR(32) DEFAULT NULL,
                num_identificador VARCHAR(64) DEFAULT NULL,
                identificador_norm VARCHAR(64) DEFAULT NULL,
                edad VARCHAR(32) DEFAULT NULL,
                email VARCHAR(255) DEFAULT NULL,
                telefono VARCHAR(64) DEFAULT NULL,
                direccion VARCHAR(500) DEFAULT NULL,
                tipo_prestador VARCHAR(64) DEFAULT NULL,
                motivo_consulta VARCHAR(255) DEFAULT NULL,
                especificidad VARCHAR(255) DEFAULT NULL,
                estado VARCHAR(64) DEFAULT NULL,
                contactado VARCHAR(32) DEFAULT NULL,
                extra JSONB DEFAULT NULL,
                CONSTRAINT fk_telesalud_import_job FOREIGN KEY (import_job_id) REFERENCES import_job (id) ON DELETE CASCADE
            )
        SQL);
        $this->addSql('CREATE INDEX telesalud_identificador_norm_idx ON telesalud_solicitud (identificador_norm)');
        $this->addSql('CREATE INDEX telesalud_import_job_idx ON telesalud_solicitud (import_job_id)');

        $this->addSql(<<<'SQL'
            CREATE TABLE messenger_messages (
                id BIGSERIAL PRIMARY KEY,
                body TEXT NOT NULL,
                headers TEXT NOT NULL,
                queue_name VARCHAR(190) NOT NULL,
                created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                available_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                delivered_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL
            )
        SQL);
        $this->addSql('CREATE INDEX messenger_queue_name_idx ON messenger_messages (queue_name)');
        $this->addSql('CREATE INDEX messenger_available_at_idx ON messenger_messages (available_at)');
        $this->addSql('CREATE INDEX messenger_delivered_at_idx ON messenger_messages (delivered_at)');

        // PostgreSQL NOTIFY trigger so Doctrine transport can use use_notify=true
        $this->addSql(<<<'SQL'
            CREATE OR REPLACE FUNCTION notify_messenger_messages() RETURNS TRIGGER AS $$
            BEGIN
                PERFORM pg_notify('messenger_messages', NEW.queue_name::text);
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;
        SQL);
        $this->addSql('CREATE TRIGGER notify_trigger AFTER INSERT OR UPDATE ON messenger_messages FOR EACH ROW EXECUTE PROCEDURE notify_messenger_messages();');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TRIGGER IF EXISTS notify_trigger ON messenger_messages');
        $this->addSql('DROP FUNCTION IF EXISTS notify_messenger_messages()');
        $this->addSql('DROP TABLE IF EXISTS messenger_messages');
        $this->addSql('DROP TABLE IF EXISTS telesalud_solicitud');
        $this->addSql('DROP TABLE IF EXISTS inscrito');
        $this->addSql('DROP TABLE IF EXISTS import_job');
        $this->addSql('DROP TABLE IF EXISTS users');
    }
}

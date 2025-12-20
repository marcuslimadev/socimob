BEGIN;

-- Tabela import_jobs
CREATE TABLE IF NOT EXISTS public.import_jobs (
    id bigserial PRIMARY KEY,
    tipo varchar(50) NOT NULL,
    status varchar(30) NOT NULL DEFAULT 'agendado',
    origem varchar(50),
    responsavel varchar(120) NOT NULL DEFAULT 'Sistema',
    parametros jsonb,
    total_itens integer NOT NULL DEFAULT 0,
    processados integer NOT NULL DEFAULT 0,
    erros integer NOT NULL DEFAULT 0,
    tempo_execucao smallint,
    inicio_previsto timestamp without time zone,
    iniciado_em timestamp without time zone,
    finalizado_em timestamp without time zone,
    created_at timestamp without time zone NOT NULL DEFAULT now(),
    updated_at timestamp without time zone NOT NULL DEFAULT now()
);

-- Tabela import_logs
CREATE TABLE IF NOT EXISTS public.import_logs (
    id bigserial PRIMARY KEY,
    job_id bigint,
    nivel varchar(20) NOT NULL DEFAULT 'info',
    codigo_imovel varchar(100),
    mensagem text NOT NULL,
    detalhes jsonb,
    created_at timestamp without time zone NOT NULL DEFAULT now(),
    updated_at timestamp without time zone NOT NULL DEFAULT now(),
    CONSTRAINT fk_import_logs_job FOREIGN KEY (job_id) REFERENCES public.import_jobs (id) ON DELETE CASCADE
);

-- Índices úteis
CREATE INDEX IF NOT EXISTS idx_import_logs_job_id ON public.import_logs (job_id);
CREATE INDEX IF NOT EXISTS idx_import_jobs_tipo ON public.import_jobs (tipo);

COMMIT;

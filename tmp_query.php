SELECT COUNT(*) as sem_inserido FROM imo_properties WHERE inserido_por_nome IS NULL OR inserido_por_nome = '';
SELECT inserido_por_nome, COUNT(*) as qtd FROM imo_properties GROUP BY inserido_por_nome ORDER BY qtd DESC LIMIT 10;

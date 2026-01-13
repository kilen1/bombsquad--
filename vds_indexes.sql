-- Дополнительные индексы для улучшения производительности
CREATE INDEX IF NOT EXISTS idx_username ON vds_servers (username);
CREATE INDEX IF NOT EXISTS idx_status ON vds_servers (status);
CREATE INDEX IF NOT EXISTS idx_created_at ON vds_servers (created_at);

-- Композитный индекс для частых запросов по пользователю и статусу
CREATE INDEX IF NOT EXISTS idx_user_status ON vds_servers (user_id, status);

-- Композитный индекс для запросов по IP и статусу
CREATE INDEX IF NOT EXISTS idx_ip_status ON vds_servers (vds_ip, status);
-- Представление для активных VDS серверов
CREATE OR REPLACE VIEW active_vds_servers_view AS
SELECT 
    id,
    user_id,
    username,
    vds_ip,
    server_path,
    created_at,
    updated_at
FROM vds_servers
WHERE status = 'active';

-- Представление для статистики по пользователям
CREATE OR REPLACE VIEW vds_user_stats_view AS
SELECT 
    username,
    COUNT(*) as total_vds_count,
    SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_vds_count
FROM vds_servers
GROUP BY user_id, username;

-- Представление для мониторинга новых VDS
CREATE OR REPLACE VIEW recent_vds_servers_view AS
SELECT 
    id,
    user_id,
    username,
    vds_ip,
    server_path,
    status,
    created_at
FROM vds_servers
WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
ORDER BY created_at DESC;
-- Процедура для добавления нового VDS сервера
DELIMITER $$
CREATE PROCEDURE sp_add_vds_server(
    IN p_user_id INT,
    IN p_username VARCHAR(255),
    IN p_vds_ip VARCHAR(45),
    IN p_server_path VARCHAR(500)
)
BEGIN
    INSERT INTO vds_servers (user_id, username, vds_ip, server_path, status)
    VALUES (p_user_id, p_username, p_vds_ip, p_server_path, 'active');
    
    SELECT LAST_INSERT_ID() AS new_vds_id;
END$$

-- Процедура для получения информации о VDS по IP
CREATE PROCEDURE sp_get_vds_by_ip(
    IN p_vds_ip VARCHAR(45)
)
BEGIN
    SELECT * FROM vds_servers WHERE vds_ip = p_vds_ip;
END$$

-- Процедура для обновления статуса VDS
CREATE PROCEDURE sp_update_vds_status(
    IN p_vds_id INT,
    IN p_status ENUM('active', 'inactive', 'pending')
)
BEGIN
    UPDATE vds_servers 
    SET status = p_status 
    WHERE id = p_vds_id;
END$$

DELIMITER ;
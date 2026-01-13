-- Триггер для автоматического обновления поля updated_at при INSERT
DELIMITER $$
CREATE TRIGGER tr_vds_servers_insert
    BEFORE INSERT ON vds_servers
    FOR EACH ROW
BEGIN
    SET NEW.updated_at = CURRENT_TIMESTAMP;
END$$

-- Триггер для автоматического обновления поля updated_at при UPDATE
CREATE TRIGGER tr_vds_servers_update
    BEFORE UPDATE ON vds_servers
    FOR EACH ROW
BEGIN
    SET NEW.updated_at = CURRENT_TIMESTAMP;
END$$

DELIMITER ;
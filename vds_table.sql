-- Таблица для хранения информации о VDS
CREATE TABLE IF NOT EXISTS vds_servers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    username VARCHAR(255) NOT NULL,
    vds_ip VARCHAR(45) NOT NULL UNIQUE,
    server_path VARCHAR(500) NOT NULL,
    status ENUM('active', 'inactive', 'pending') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_user_id (user_id),
    INDEX idx_vds_ip (vds_ip)
);
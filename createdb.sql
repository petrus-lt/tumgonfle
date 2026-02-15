CREATE DATABASE IF NOT EXISTS gonflages_db;
USE gonflages_db;

CREATE TABLE IF NOT EXISTS gonflages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    date_gonflage TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    proprietaire VARCHAR(100),
    volume_bloc INT,
    litres_o2_utilises FLOAT,
    prix_facture FLOAT,
    paye BOOLEAN DEFAULT FALSE
);

CREATE USER 'gonflageuser'@'localhost' IDENTIFIED BY 'monsuperpass';
GRANT CREATE, ALTER, DROP, INSERT, UPDATE, DELETE, SELECT, REFERENCES, RELOAD on gonflages_db.* TO 'gonflageuser'@'localhost' WITH GRANT OPTION;
FLUSH PRIVILEGES;


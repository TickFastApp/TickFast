-- Configuración de la base de datos TickFast
CREATE DATABASE IF NOT EXISTS tickfast;
USE tickfast;

-- Tabla Usuario
DROP TABLE IF EXISTS Usuario;
CREATE TABLE Usuario (
    id_usuario INT PRIMARY KEY AUTO_INCREMENT,
    Mail VARCHAR(100) UNIQUE NOT NULL,
    Nombre VARCHAR(100),
    Documento INT,
    FechaNac DATE,
    Direccion VARCHAR(200),
    NumTel VARCHAR(20),
    Contrasena VARCHAR(100),
    fecha_registro TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    activo BOOLEAN DEFAULT TRUE
);

-- Tabla Venue
DROP TABLE IF EXISTS Venue;
CREATE TABLE Venue (
    id_venue INT PRIMARY KEY AUTO_INCREMENT,
    nombre VARCHAR(100),
    Direccion VARCHAR(200),
    Capacidad INT,
    PrecioBase DECIMAL(10,2),
    imagen VARCHAR(255),
    descripcion TEXT,
    activo BOOLEAN DEFAULT TRUE
);

-- Tabla Sector
DROP TABLE IF EXISTS Sector;
CREATE TABLE Sector (
    idSector INT PRIMARY KEY AUTO_INCREMENT,
    idVenue INT,
    Nombre VARCHAR(100),
    Capacidad INT,
    Porc_agr DECIMAL(5,2),
    tickets_disponibles INT,
    FOREIGN KEY (idVenue) REFERENCES Venue(id_venue)
);

-- Tabla Artistas
DROP TABLE IF EXISTS Artistas;
CREATE TABLE Artistas (
    id_artista INT PRIMARY KEY AUTO_INCREMENT,
    Nombre VARCHAR(100),
    Apellido VARCHAR(100),
    genero VARCHAR(50),
    imagen VARCHAR(255),
    descripcion TEXT,
    activo BOOLEAN DEFAULT TRUE
);

-- Tabla Shows
DROP TABLE IF EXISTS Shows;
CREATE TABLE Shows (
    id_show INT PRIMARY KEY AUTO_INCREMENT,
    Nombre VARCHAR(100),
    Fecha DATE,
    Horario TIME,
    id_venue INT,
    imagen VARCHAR(255),
    descripcion TEXT,
    estado ENUM('activo', 'agotado', 'cancelado') DEFAULT 'activo',
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_venue) REFERENCES Venue(id_venue)
);

-- Tabla intermedia Show_Artistas (N:M)
DROP TABLE IF EXISTS Show_Artistas;
CREATE TABLE Show_Artistas (
    id_show INT,
    id_artista INT,
    PRIMARY KEY (id_show, id_artista),
    FOREIGN KEY (id_show) REFERENCES Shows(id_show),
    FOREIGN KEY (id_artista) REFERENCES Artistas(id_artista)
);

-- Tabla Ticket
DROP TABLE IF EXISTS Ticket;
CREATE TABLE Ticket (
    id_ticket INT PRIMARY KEY AUTO_INCREMENT,
    id_show INT,
    id_sector INT,
    id_usuario INT,
    precio DECIMAL(10,2),
    fecha_compra TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    codigo_qr VARCHAR(255),
    estado ENUM('vendido', 'usado', 'cancelado') DEFAULT 'vendido',
    FOREIGN KEY (id_show) REFERENCES Shows(id_show),
    FOREIGN KEY (id_sector) REFERENCES Sector(idSector),
    FOREIGN KEY (id_usuario) REFERENCES Usuario(id_usuario)
);

-- Datos de ejemplo
INSERT INTO Venue (nombre, Direccion, Capacidad, PrecioBase, descripcion) VALUES
('Estadio River Plate', 'Av. Presidente Figueroa Alcorta 7597, Buenos Aires', 70000, 5000.00, 'El Monumental, estadio del Club Atlético River Plate'),
('Luna Park', 'Bouchard 465, Buenos Aires', 8000, 3000.00, 'Estadio Luna Park, histórico venue de Buenos Aires'),
('Teatro Colón', 'Cerrito 628, Buenos Aires', 2500, 8000.00, 'Teatro de ópera y ballet más importante de América Latina');

INSERT INTO Sector (idVenue, Nombre, Capacidad, Porc_agr, tickets_disponibles) VALUES
(1, 'Platea', 20000, 0.00, 18000),
(1, 'Platea Alta', 15000, 25.00, 14000),
(1, 'Populares', 35000, -20.00, 32000),
(2, 'Ring Central', 2000, 50.00, 1800),
(2, 'Platea', 4000, 20.00, 3500),
(2, 'Pullman', 2000, 80.00, 1900),
(3, 'Platea', 1000, 100.00, 950),
(3, 'Palcos', 500, 200.00, 480),
(3, 'Tertulia', 1000, 50.00, 900);

INSERT INTO Artistas (Nombre, Apellido, genero, descripcion) VALUES
('Gustavo', 'Cerati', 'Rock', 'Icónico músico argentino, líder de Soda Stereo'),
('Los', 'Redonditos de Ricota', 'Rock', 'Banda de rock argentina legendaria'),
('Charly', 'García', 'Rock', 'Pionero del rock argentino'),
('Divididos', '', 'Rock', 'Banda argentina de rock alternativo'),
('La', 'Renga', 'Rock', 'Banda de hard rock argentina');

INSERT INTO Shows (Nombre, Fecha, Horario, id_venue, descripcion, estado) VALUES
('Cerati Eterno - Tributo', '2025-09-15', '21:00:00', 1, 'Tributo al eterno Gustavo Cerati', 'activo'),
('Los Redondos Vuelven', '2025-10-20', '20:30:00', 1, 'El regreso más esperado del rock nacional', 'activo'),
('Charly García - Unplugged', '2025-11-05', '21:30:00', 2, 'Charly en formato acústico e íntimo', 'activo'),
('Divididos - Gira 2025', '2025-12-10', '22:00:00', 2, 'Divididos presenta su nueva gira', 'activo'),
('Concierto Sinfónico', '2025-09-30', '20:00:00', 3, 'Orquesta Sinfónica Nacional', 'activo');

INSERT INTO Show_Artistas (id_show, id_artista) VALUES
(1, 1),
(2, 2),
(3, 3),
(4, 4),
(5, 5);

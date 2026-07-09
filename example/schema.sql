-- demo 专用 schema：模拟 yf 项目中的 di_* 与 pt_* 表
-- 包含两个模块：di（简单）与 parkinglot（含多对多 + bind + 嵌套关联）

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS di_notice;
DROP TABLE IF EXISTS di_smartpark;
DROP TABLE IF EXISTS pt_car_parking;
DROP TABLE IF EXISTS pt_car_car_owner;
DROP TABLE IF EXISTS pt_car_owner;
DROP TABLE IF EXISTS pt_car;
DROP TABLE IF EXISTS pt_parkinglot;
DROP TABLE IF EXISTS pt_user;
DROP TABLE IF EXISTS pt_smartpark;
SET FOREIGN_KEY_CHECKS = 1;

-- ===== di 模块 =====

CREATE TABLE di_smartpark (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    number VARCHAR(50),
    desp TEXT,
    detail_address VARCHAR(255)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE di_notice (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    smartpark_id INT UNSIGNED NOT NULL,
    name VARCHAR(100) NOT NULL,
    channels VARCHAR(200),
    payload LONGTEXT,
    status TINYINT DEFAULT 0,
    amount DECIMAL(10,2) DEFAULT 0,
    add_time INT UNSIGNED DEFAULT 0,
    INDEX idx_smartpark (smartpark_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ===== parkinglot 模块（pt_* 前缀，对齐 yf 真实表名规范） =====

-- 园区（与 di_smartpark 同结构，但用 pt_ 前缀；模拟 yf 多模块共享园区表）
CREATE TABLE pt_smartpark (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    number VARCHAR(50),
    desp TEXT,
    detail_address VARCHAR(255),
    status TINYINT DEFAULT 1,
    is_del TINYINT DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 用户表：被 bind 字段绑定的目标
CREATE TABLE pt_user (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50),
    face VARCHAR(255),
    email VARCHAR(100),
    mobile VARCHAR(20),
    nick_name VARCHAR(50),
    real_name VARCHAR(50),
    add_time INT UNSIGNED DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 停车场
CREATE TABLE pt_parkinglot (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    smartpark_id INT UNSIGNED NOT NULL,
    name VARCHAR(100) NOT NULL,
    number VARCHAR(50),
    is_del TINYINT DEFAULT 0,
    status TINYINT DEFAULT 1,
    add_time INT UNSIGNED DEFAULT 0,
    INDEX idx_smartpark (smartpark_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 车主：演示 belongsTo + bind（注：本表无 name 字段，name 由 user 表 bind 过来）
CREATE TABLE pt_car_owner (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    smartpark_id INT UNSIGNED NOT NULL,
    parkinglot_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED DEFAULT 0,
    add_time INT UNSIGNED DEFAULT 0,
    INDEX idx_smartpark (smartpark_id),
    INDEX idx_parkinglot (parkinglot_id),
    INDEX idx_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 车辆：演示 $insert + hasMany + belongsTo(条件)
CREATE TABLE pt_car (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    smartpark_id INT UNSIGNED NOT NULL,
    parkinglot_id INT UNSIGNED NOT NULL,
    last_smartpark_id INT UNSIGNED DEFAULT 0,
    number VARCHAR(20) NOT NULL,
    is_temp_number TINYINT DEFAULT 0,
    is_new_energy TINYINT DEFAULT 0,
    add_time INT UNSIGNED DEFAULT 0,
    INDEX idx_smartpark (smartpark_id),
    INDEX idx_parkinglot (parkinglot_id),
    INDEX idx_last_sp (last_smartpark_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 车辆-车主 多对多中间表
-- 注意：smartpark_id 用于演示 belongsToMany 上对 pivot 加条件
CREATE TABLE pt_car_car_owner (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    smartpark_id INT UNSIGNED NOT NULL,
    car_id INT UNSIGNED NOT NULL,
    car_owner_id INT UNSIGNED NOT NULL,
    UNIQUE KEY uk_car_owner (car_id, car_owner_id),
    INDEX idx_smartpark (smartpark_id),
    INDEX idx_car (car_id),
    INDEX idx_owner (car_owner_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 车辆停车记录：作为 Car::fixcarList 的 hasMany 目标
CREATE TABLE pt_car_parking (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    smartpark_id INT UNSIGNED NOT NULL,
    parkinglot_id INT UNSIGNED NOT NULL,
    car_id INT UNSIGNED NOT NULL,
    in_time INT UNSIGNED DEFAULT 0,
    out_time INT UNSIGNED DEFAULT 0,
    add_time INT UNSIGNED DEFAULT 0,
    INDEX idx_car (car_id),
    INDEX idx_smartpark (smartpark_id),
    INDEX idx_parkinglot (parkinglot_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

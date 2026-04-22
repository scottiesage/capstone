-- =========================
-- USER TABLE
-- =========================
CREATE TABLE User (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(150) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,

    verification_code VARCHAR(10) DEFAULT NULL,
    verification_expires DATETIME DEFAULT NULL,
    is_verified BOOLEAN DEFAULT FALSE,

    reset_code VARCHAR(6) DEFAULT NULL,
    reset_expires DATETIME DEFAULT NULL,

    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- =========================
-- ACCOUNT TABLE
-- =========================
CREATE TABLE Account (
    account_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    account_code VARCHAR(20) DEFAULT NULL,
    account_name VARCHAR(120) NOT NULL,
    account_type ENUM('Asset','Liability','Equity','Revenue','Expense') NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_account_user
        FOREIGN KEY (user_id) REFERENCES User(user_id)
        ON DELETE CASCADE,

    CONSTRAINT unique_user_account
        UNIQUE (user_id, account_name)
);

-- =========================
-- VENDOR TABLE
-- =========================
CREATE TABLE Vendor (
    vendor_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    vendor_name VARCHAR(120) NOT NULL,
    email VARCHAR(120) DEFAULT NULL,
    phone VARCHAR(50) DEFAULT NULL,
    address_line1 VARCHAR(150) DEFAULT NULL,
    city VARCHAR(100) DEFAULT NULL,
    state VARCHAR(50) DEFAULT NULL,
    zip_code VARCHAR(20) DEFAULT NULL,
    notes VARCHAR(255) DEFAULT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_vendor_user
        FOREIGN KEY (user_id) REFERENCES User(user_id)
        ON DELETE CASCADE,

    CONSTRAINT unique_user_vendor
        UNIQUE (user_id, vendor_name)
);

-- =========================
-- TRANSACTION TABLE
-- =========================
CREATE TABLE `Transaction` (
    transaction_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    vendor_id INT DEFAULT NULL,
    customer_name VARCHAR(120) DEFAULT NULL,

    transaction_type ENUM('Sale','Purchase','Payment','Receipt') NOT NULL,
    transaction_date DATE NOT NULL,
    amount DECIMAL(10,2) NOT NULL,

    debit_account_id INT NOT NULL,
    credit_account_id INT NOT NULL,

    description VARCHAR(255) DEFAULT NULL,
    memo VARCHAR(255) DEFAULT NULL,
    category VARCHAR(100) DEFAULT NULL,
    source VARCHAR(50) DEFAULT NULL,

    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_transaction_user
        FOREIGN KEY (user_id) REFERENCES User(user_id)
        ON DELETE CASCADE,

    CONSTRAINT fk_transaction_vendor
        FOREIGN KEY (vendor_id) REFERENCES Vendor(vendor_id)
        ON DELETE SET NULL,

    CONSTRAINT fk_transaction_debit_account
        FOREIGN KEY (debit_account_id) REFERENCES Account(account_id)
        ON DELETE RESTRICT,

    CONSTRAINT fk_transaction_credit_account
        FOREIGN KEY (credit_account_id) REFERENCES Account(account_id)
        ON DELETE RESTRICT,

    CONSTRAINT chk_different_accounts
        CHECK (debit_account_id <> credit_account_id)
);

-- =========================
-- DETECTION RULE TABLE
-- =========================
CREATE TABLE DetectionRule (
    rule_id INT AUTO_INCREMENT PRIMARY KEY,
    rule_name VARCHAR(120) NOT NULL,
    rule_category ENUM('Error','Fraud') NOT NULL,
    severity ENUM('Low','Medium','High') NOT NULL,
    is_active BOOLEAN DEFAULT TRUE
);

-- =========================
-- ALERT TABLE
-- =========================
CREATE TABLE Alert (
    alert_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    rule_id INT DEFAULT NULL,
    transaction_id INT DEFAULT NULL,
    status ENUM('Open','Reviewed','Resolved') DEFAULT 'Open',
    message VARCHAR(255) DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    reviewed_at DATETIME DEFAULT NULL,

    CONSTRAINT fk_alert_user
        FOREIGN KEY (user_id) REFERENCES User(user_id)
        ON DELETE CASCADE,

    CONSTRAINT fk_alert_rule
        FOREIGN KEY (rule_id) REFERENCES DetectionRule(rule_id)
        ON DELETE SET NULL,

    CONSTRAINT fk_alert_transaction
        FOREIGN KEY (transaction_id) REFERENCES `Transaction`(transaction_id)
        ON DELETE SET NULL
);
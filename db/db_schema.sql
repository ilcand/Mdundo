-- Countries table
CREATE TABLE countries (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  iso_code CHAR(2) NOT NULL UNIQUE
);

-- Country-level default annual entitlement (days/year)
CREATE TABLE country_entitlements (
  id INT AUTO_INCREMENT PRIMARY KEY,
  country_id INT NOT NULL,
  year INT NOT NULL,
  entitlement_days INT NOT NULL, -- e.g. 24
  UNIQUE(country_id, year),
  FOREIGN KEY (country_id) REFERENCES countries(id) ON DELETE CASCADE
);

-- Country public holidays
CREATE TABLE country_public_holidays (
  id INT AUTO_INCREMENT PRIMARY KEY,
  country_id INT NOT NULL,
  holiday_date DATE NOT NULL,
  name VARCHAR(255),
  UNIQUE(country_id, holiday_date),
  FOREIGN KEY (country_id) REFERENCES countries(id) ON DELETE CASCADE
);

-- Employees
CREATE TABLE employees (
  id INT AUTO_INCREMENT PRIMARY KEY,
  first_name VARCHAR(100) NOT NULL,
  last_name VARCHAR(100) NOT NULL,
  country_id INT NOT NULL,
  employment_start_date DATE NOT NULL,
  employment_end_date DATE DEFAULT NULL, -- NULL = active
  -- other fields: email, position, etc.
  FOREIGN KEY (country_id) REFERENCES countries(id)
);

-- Employee holidays (personal/annual leave)
CREATE TABLE employee_holidays (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  employee_id INT NOT NULL,
  holiday_date DATE NOT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  note VARCHAR(255) DEFAULT NULL,
  UNIQUE(employee_id, holiday_date),
  FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE
);

-- Indexes for faster queries
CREATE INDEX idx_employee_holidays_employee_date ON employee_holidays(employee_id, holiday_date);
CREATE INDEX idx_public_holidays_country_date ON country_public_holidays(country_id, holiday_date);

-- ==========================
-- 1. Countries
-- ==========================
INSERT INTO countries (id, name, iso_code)
VALUES
  (1, 'Kenya', 'KE'),
  (2, 'Bulgary', 'BG'),
  (3, 'Italy', 'IT');

-- ==========================
-- 2. Country Entitlements (days/year)
-- ==========================
INSERT INTO country_entitlements (country_id, year, entitlement_days)
VALUES
  (1, 2025, 24),
  (2, 2025, 22),
  (3, 2025, 20);

-- ==========================
-- 3. Country Public Holidays
-- ==========================
INSERT INTO country_public_holidays (country_id, holiday_date, name)
VALUES
  -- Kenya 2025 public holidays
  (1, '2025-01-01', 'New Year’s Day'),
  (1, '2025-04-18', 'Good Friday'),
  (1, '2025-04-21', 'Easter Monday'),
  (1, '2025-05-01', 'Labour Day'),
  (1, '2025-06-01', 'Madaraka Day'),
  (1, '2025-10-20', 'Mashujaa Day'),
  (1, '2025-12-12', 'Jamhuri Day'),
  (1, '2025-12-25', 'Christmas Day'),
  (1, '2025-12-26', 'Boxing Day'),

  -- Bulgary 2025 public holidays
  (2, '2025-01-01', 'New Year’s Day'),
  (2, '2025-04-18', 'Good Friday'),
  (2, '2025-05-01', 'Labour Day'),
  (2, '2025-06-09', 'National Heroes Day'),
  (2, '2025-10-09', 'Independence Day'),
  (2, '2025-12-25', 'Christmas Day'),
  (2, '2025-12-26', 'Boxing Day'),

  -- Italy 2025 public holidays
  (3, '2025-01-01', 'New Year’s Day'),
  (3, '2025-04-18', 'Good Friday'),
  (3, '2025-05-01', 'Workers’ Day'),
  (3, '2025-12-09', 'Independence Day'),
  (3, '2025-12-25', 'Christmas Day'),
  (3, '2025-12-26', 'Boxing Day');

-- ==========================
-- 4. Employees
-- ==========================
INSERT INTO employees (id, first_name, last_name, country_id, employment_start_date, employment_end_date)
VALUES
  (123, 'John', 'Doe', 1, '2023-06-15', NULL),  -- active employee in Kenya
  (124, 'Jane', 'Smith', 2, '2024-01-10', NULL),
  (125, 'Robert', 'Kamau', 1, '2020-02-01', '2024-12-31'),
  (126, 'Alice', 'Njoroge', 3, '2022-09-01', NULL);

-- ==========================
-- 5. Employee Personal Holidays
-- ==========================
-- For testing: John Doe (id=123) has taken 3 days off in 2025
INSERT INTO employee_holidays (employee_id, holiday_date, note)
VALUES
  (123, '2025-03-10', 'Family event'),
  (123, '2025-07-05', 'Short break'),
  (123, '2025-08-13', 'Before summer weekend'),

  -- Jane Smith (id=124) took 2 days
  (124, '2025-02-20', 'Travel'),
  (124, '2025-06-15', 'Vacation'),

  -- Alice (id=126) took 1 day
  (126, '2025-09-03', 'Personal');

-- ==========================
-- Verify the seed
-- ==========================
-- SELECT * FROM employees;
-- SELECT * FROM employee_holidays WHERE employee_id = 123;
-- SELECT * FROM country_public_holidays WHERE country_id = 1;

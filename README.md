# Mdundo Holiday Assignment

Technical test for **Full Stack PHP Web Developer** position at **Mdundo**.  
The assignment simulates an international company where each country has its own:
- public holidays
- annual holiday entitlements
- employees with different employment periods.

---

## Overview

The project implements the following functionality (as per the test requirements):

| # | Requirement | Implemented in |
|---|--------------|----------------|
| a | Database structure (tables, fields, types) | `seed_data.sql` |
| b | Method to add an employee holiday | `HolidayRepository::addEmployeeHoliday()` |
| c | Method to compute remaining holidays at a given time | `HolidayRepository::computeRemainingHolidays()` |
| d | Method to list all employees with total holidays and public holidays | `HolidayRepository::listEmployeesHolidays()` |

Additionally, the project includes:
- Example usage and minimal **Bootstrap** interface (`example.php`)
- SQL seed data for quick setup
- Exception handling 

## How to Run It
1. Requirements 
- PHP 8.1 or newer  
- MySQL (or MariaDB)  
- A local web server (Apache, Nginx, or PHP built-in server)

2. Database Setup
- create a new database called mdundo
- import db_schema and seed_date from the db folder 
- edit the connection settings in example.php if needed

3. Run the example
- open a page in your browser at: http://localhost:8000/example.php
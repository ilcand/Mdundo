<?php
// HolidayRepository.php
declare(strict_types=1);

class RepositoryException extends \Exception {}

class HolidayRepository
{
    /** @var \PDO */
    private $pdo;

    public function __construct(\PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /* ----------------------------
       Required public methods
       ---------------------------- */

    /**
     * Add a personal holiday (single date) for an employee.
     *
     * @param int $employeeId
     * @param string $date YYYY-MM-DD
     * @param string|null $note optional note
     * @return bool true on success
     * @throws RepositoryException on validation or DB errors
     */
    public function addEmployeeHoliday(int $employeeId, string $date, ?string $note = null): bool
    {
        // validate date format
        $d = \DateTime::createFromFormat('Y-m-d', $date);
        if (! $d || $d->format('Y-m-d') !== $date) {
            throw new RepositoryException("Invalid date format, expected YYYY-MM-DD");
        }

        // check employee exists and date is within employment period
        $emp = $this->getEmployeeById($employeeId);
        if (!$emp) {
            throw new RepositoryException("Employee not found: {$employeeId}");
        }

        $employmentStart = new \DateTime($emp['employment_start_date']);
        $employmentEnd = $emp['employment_end_date'] ? new \DateTime($emp['employment_end_date']) : null;
        $holidayDate = new \DateTime($date);

        if ($holidayDate < $employmentStart) {
            throw new RepositoryException("Holiday date is before employee start date.");
        }
        if ($employmentEnd !== null && $holidayDate > $employmentEnd) {
            throw new RepositoryException("Holiday date is after employee end date.");
        }

        // don't allow adding if date is a public holiday for this employee's country
        $isPublic = $this->isPublicHoliday($emp['country_id'], $date);
        if ($isPublic) {
            throw new RepositoryException("Cannot add personal holiday: date is a public holiday in employee's country.");
        }

        // Insert - use unique constraint to prevent duplicates
        $sql = "INSERT INTO employee_holidays (employee_id, holiday_date, note) VALUES (:e, :d, :n)";
        $stmt = $this->pdo->prepare($sql);
        try {
            return $stmt->execute([':e' => $employeeId, ':d' => $date, ':n' => $note]);
        } catch (\PDOException $ex) {
            // Unique constraint violation: already exists
            if ($this->isUniqueConstraintViolation($ex)) {
                throw new RepositoryException("Holiday already exists for that date.");
            }
            throw new RepositoryException("DB error adding holiday: " . $ex->getMessage());
        }
    }

    /**
     * Compute remaining personal holidays for an employee at a given date.
     *
     * @param int $employeeId
     * @param string $asOfDate YYYY-MM-DD (the date at which to compute remaining balance)
     * @return array ['year' => 2025, 'entitlement' => float, 'taken' => int, 'remaining' => float]
     * @throws RepositoryException
     */
    public function computeRemainingHolidays(int $employeeId, string $asOfDate): array
    {
        // validate date
        $d = \DateTime::createFromFormat('Y-m-d', $asOfDate);
        if (! $d || $d->format('Y-m-d') !== $asOfDate) {
            throw new RepositoryException("Invalid asOfDate format.");
        }
        $year = (int)$d->format('Y');

        $emp = $this->getEmployeeById($employeeId);
        if (!$emp) {
            throw new RepositoryException("Employee not found.");
        }

        // Determine entitlement for the employee's country for this year
        $entitlementDays = $this->getCountryEntitlementForYear((int)$emp['country_id'], $year);
        if ($entitlementDays === null) {
            throw new RepositoryException("No entitlement configured for employee's country for year {$year}.");
        }

        // Pro-rate entitlement based on days employed in this calendar year up to asOfDate (inclusive)
        // Employment window in that year: from max(start_date, Jan 1 year) to min(end_date or asOfDate, asOfDate or Dec 31)
        $startOfYear = new \DateTime("{$year}-01-01");
        $endOfYear = new \DateTime("{$year}-12-31");
        $empStart = new \DateTime($emp['employment_start_date']);
        $empEnd = $emp['employment_end_date'] ? new \DateTime($emp['employment_end_date']) : null;

        $windowStart = $empStart > $startOfYear ? $empStart : $startOfYear;
        // The entitlement accrues only up to asOfDate (we're computing remaining as of that date)
        $windowEndCandidates = [$d, $endOfYear];
        if ($empEnd !== null) {
            $windowEndCandidates[] = $empEnd;
        }
        // min of candidates
        usort($windowEndCandidates, function($a,$b){ return $a < $b ? -1 : ($a == $b ? 0 : 1); });
        // But we want the earliest of these that is not before windowStart
        $windowEnd = $windowEndCandidates[0];
        if ($windowEnd < $windowStart) {
            // Not employed yet in that year by asOfDate
            $proRatedEntitlement = 0.0;
        } else {
            // days employed in window (inclusive)
            $daysEmployed = $this->daysInclusive($windowStart, $windowEnd);
            $daysInYear = $startOfYear->format('L') === '1' || (int)$startOfYear->format('L') === 1 ? 366 : 365;
            // Use DateTime to detect leap year in a reliable way:
            $daysInYear = (new \DateTime("{$year}-12-31"))->format('z') + 1; // z is 0-based day count; +1 = total days
            $proRatedEntitlement = ($entitlementDays * $daysEmployed) / (int)$daysInYear;
            // Keep to two decimal places
            $proRatedEntitlement = round($proRatedEntitlement, 2);
        }

        // personal holidays taken by the employee from Jan 1 year up to asOfDate (inclusive)
        $takenCount = $this->countEmployeePersonalHolidaysInRange($employeeId, "{$year}-01-01", $asOfDate);

        $remaining = $proRatedEntitlement - $takenCount;
        if ($remaining < 0) $remaining = 0.0;

        return [
            'year' => $year,
            'entitlement' => $proRatedEntitlement,
            'taken' => (int)$takenCount,
            'remaining' => $remaining
        ];
    }

    /**
     * List all employees with total personal holidays and total public holidays in a given period.
     *
     * @param string $startDate YYYY-MM-DD
     * @param string $endDate YYYY-MM-DD
     * @return array of rows: [
     *   'employee_id'=>int, 'first_name'=>..., 'last_name'=>..., 'country_id'=>int,
     *   'personal_holidays'=>int, 'public_holidays'=>int
     * ]
     * @throws RepositoryException
     */
    public function listEmployeesHolidays(string $startDate, string $endDate): array
    {
        // validate dates
        $sd = \DateTime::createFromFormat('Y-m-d', $startDate);
        $ed = \DateTime::createFromFormat('Y-m-d', $endDate);
        if (! $sd || ! $ed || $sd->format('Y-m-d') !== $startDate || $ed->format('Y-m-d') !== $endDate) {
            throw new RepositoryException("Invalid date format.");
        }
        if ($sd > $ed) {
            throw new RepositoryException("startDate must be <= endDate");
        }

        // Query all employees with their personal holiday counts in the period
        $sql = "
            SELECT e.id AS employee_id, e.first_name, e.last_name, e.country_id,
                COALESCE(ph.personal_count, 0) AS personal_holidays
            FROM employees e
            LEFT JOIN (
                SELECT employee_id, COUNT(*) AS personal_count
                FROM employee_holidays
                WHERE holiday_date BETWEEN :start AND :end
                GROUP BY employee_id
            ) ph ON ph.employee_id = e.id
            ORDER BY e.id
        ";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':start' => $startDate, ':end' => $endDate]);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        // For each employee compute how many public holidays fall into the period AND also within their employment window
        $result = [];
        foreach ($rows as $r) {
            $publicCount = $this->countPublicHolidaysForEmployeeInRange((int)$r['employee_id'], $startDate, $endDate);
            $result[] = [
                'employee_id' => (int)$r['employee_id'],
                'first_name' => $r['first_name'],
                'last_name' => $r['last_name'],
                'country_id' => (int)$r['country_id'],
                'personal_holidays' => (int)$r['personal_holidays'],
                'public_holidays' => $publicCount
            ];
        }
        return $result;
    }

    /* ----------------------------
       Helper methods (concrete implementations provided where helpful)
       ---------------------------- */

    /**
     * Get employee row by id.
     * @return array|null
     */
    private function getEmployeeById(int $id): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM employees WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $r = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $r ?: null;
    }

    /**
     * Check if a given date is a public holiday for a country
     * @param int $countryId
     * @param string $date YYYY-MM-DD
     * @return bool
     */
    private function isPublicHoliday(int $countryId, string $date): bool
    {
        $stmt = $this->pdo->prepare("SELECT 1 FROM country_public_holidays WHERE country_id = :c AND holiday_date = :d LIMIT 1");
        $stmt->execute([':c' => $countryId, ':d' => $date]);
        return (bool)$stmt->fetchColumn();
    }

    /**
     * Count employee personal holidays in [start,end] inclusive
     * @return int
     */
    private function countEmployeePersonalHolidaysInRange(int $employeeId, string $start, string $end): int
    {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM employee_holidays WHERE employee_id = :e AND holiday_date BETWEEN :s AND :e");
        $stmt->execute([':e' => $employeeId, ':s' => $start, ':e' => $end]);
        return (int)$stmt->fetchColumn();
    }

    /**
     * Count public holidays for a specific employee's country that fall into [start,end],
     * but only dates that also lie within the employee's employment window.
     *
     * @return int
     */
    private function countPublicHolidaysForEmployeeInRange(int $employeeId, string $start, string $end): int
    {
        $emp = $this->getEmployeeById($employeeId);
        if (!$emp) return 0;

        $empStart = new \DateTime($emp['employment_start_date']);
        $empEnd = $emp['employment_end_date'] ? new \DateTime($emp['employment_end_date']) : null;

        // Adjust range to employee's employment window
        $rangeStart = new \DateTime($start);
        if ($empStart > $rangeStart) $rangeStart = $empStart;
        $rangeEnd = new \DateTime($end);
        if ($empEnd !== null && $empEnd < $rangeEnd) $rangeEnd = $empEnd;

        if ($rangeStart > $rangeEnd) return 0;

        $rs = $rangeStart->format('Y-m-d');
        $re = $rangeEnd->format('Y-m-d');

        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) FROM country_public_holidays
            WHERE country_id = :cid AND holiday_date BETWEEN :s AND :e
        ");
        $stmt->execute([':cid' => $emp['country_id'], ':s' => $rs, ':e' => $re]);
        return (int)$stmt->fetchColumn();
    }

    /**
     * Get country entitlement (days per year) for a country and a specific year.
     * Returns int entitlementDays or null if not configured.
     */
    private function getCountryEntitlementForYear(int $countryId, int $year): ?int
    {
        $stmt = $this->pdo->prepare("SELECT entitlement_days FROM country_entitlements WHERE country_id = :c AND year = :y LIMIT 1");
        $stmt->execute([':c' => $countryId, ':y' => $year]);
        $v = $stmt->fetchColumn();
        return $v === false ? null : (int)$v;
    }

    /**
     * Utility: inclusive days between two DateTime objects (both inclusive)
     */
    private function daysInclusive(\DateTime $start, \DateTime $end): int
    {
        // normalize time components
        $s = \DateTime::createFromFormat('Y-m-d', $start->format('Y-m-d'));
        $e = \DateTime::createFromFormat('Y-m-d', $end->format('Y-m-d'));
        $diff = $s->diff($e);
        return (int)$diff->days + 1; // +1 for inclusive
    }

    /**
     * Detect unique constraint violation (MySQL error code 23000 + SQLSTATE 1062)
     */
    private function isUniqueConstraintViolation(\PDOException $ex): bool
    {
        $code = $ex->getCode();
        $msg = $ex->getMessage();
        // Basic detection: SQLSTATE 23000 and/or 1062 in message
        if ($code === '23000') return true;
        if (strpos($msg, '1062') !== false) return true;
        return false;
    }

    /* 
      Additional helper method signatures (no full impl required by exercise):
      - public function addPublicHoliday(int $countryId, string $date, string $name = null): bool
      - public function setCountryEntitlement(int $countryId, int $year, int $days): bool
      - public function getEmployeeAccrualSummary(int $employeeId, int $year): array
    */
}

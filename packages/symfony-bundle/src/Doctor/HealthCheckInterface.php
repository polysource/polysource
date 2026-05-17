<?php

declare(strict_types=1);

namespace Polysource\Bundle\Doctor;

/**
 * Contract for a single doctor diagnostic check.
 *
 * Each check is a small, self-contained class that probes one
 * concern (PHP version, registered bundles, Doctrine schema, …)
 * and returns a {@see HealthCheckResult}. The {@see DoctorCommand}
 * iterates over every check and tables the results.
 *
 * Plugins register their own checks by tagging a service
 * `polysource.doctor.check`; the compiler pass collects them into
 * the command's iterator. The order in which checks run is not
 * guaranteed and should not be relied on — each check must be
 * independent.
 *
 * @since 0.9.0
 */
interface HealthCheckInterface
{
    /**
     * Short label for the check, displayed in the doctor table
     * (e.g. "PHP version", "Doctrine schema"). Keep under 30
     * characters so the rendered table doesn't wrap on a standard
     * terminal.
     */
    public function getName(): string;

    /**
     * Run the check and return the result. Implementations MUST
     * NOT throw — wrap any external-call failures in a WARN result
     * with the exception message embedded in the detail string.
     */
    public function run(): HealthCheckResult;
}

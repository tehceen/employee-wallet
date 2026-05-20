<?php

namespace App\Data\Payroll;

readonly class PayrollItemInput
{
    public function __construct(
        public string $employeeExternalRef,
        public int $amount,
        public ?string $externalItemId = null,
    ) {}

    /**
     * @param  array{employee_external_ref: string, amount: int, external_item_id?: string|null}  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            employeeExternalRef: $data['employee_external_ref'],
            amount: (int) $data['amount'],
            externalItemId: $data['external_item_id'] ?? null,
        );
    }
}

<?php

namespace App\Data\Payroll;

readonly class PayrollRunInput
{
    /**
     * @param  list<PayrollItemInput>  $items
     */
    public function __construct(
        public string $idempotencyKey,
        public array $items,
        public ?string $externalEventId = null,
    ) {}

    /**
     * @param  array{
     *     idempotency_key: string,
     *     external_event_id?: string|null,
     *     items: list<array{employee_external_ref: string, amount: int, external_item_id?: string|null}>
     * }  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            idempotencyKey: $data['idempotency_key'],
            items: array_map(
                fn (array $item): PayrollItemInput => PayrollItemInput::fromArray($item),
                $data['items'],
            ),
            externalEventId: $data['external_event_id'] ?? null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toPayload(): array
    {
        return [
            'idempotency_key' => $this->idempotencyKey,
            'external_event_id' => $this->externalEventId,
            'items' => array_map(
                fn (PayrollItemInput $item): array => [
                    'employee_external_ref' => $item->employeeExternalRef,
                    'amount' => $item->amount,
                    'external_item_id' => $item->externalItemId,
                ],
                $this->items,
            ),
        ];
    }
}

<?php

namespace App\Data\Bank;

readonly class BankCallbackInput
{
    public function __construct(
        public string $bankReference,
        public string $status,
        public string $idempotencyKey,
        public ?string $externalEventId = null,
        public ?string $failureReason = null,
    ) {}

    /**
     * @param  array{
     *     bank_reference: string,
     *     status: string,
     *     idempotency_key: string,
     *     external_event_id?: string|null,
     *     failure_reason?: string|null
     * }  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            bankReference: $data['bank_reference'],
            status: $data['status'],
            idempotencyKey: $data['idempotency_key'],
            externalEventId: $data['external_event_id'] ?? null,
            failureReason: $data['failure_reason'] ?? null,
        );
    }

    public function isSuccess(): bool
    {
        return $this->status === 'completed';
    }

    public function isFailure(): bool
    {
        return $this->status === 'failed';
    }

    /**
     * @return array<string, mixed>
     */
    public function toPayload(): array
    {
        return [
            'bank_reference' => $this->bankReference,
            'status' => $this->status,
            'idempotency_key' => $this->idempotencyKey,
            'external_event_id' => $this->externalEventId,
            'failure_reason' => $this->failureReason,
        ];
    }
}

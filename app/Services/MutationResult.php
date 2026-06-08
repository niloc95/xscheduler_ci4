<?php

namespace App\Services;

/**
 * Standardised return value for all mutation services.
 *
 * Controllers receive a MutationResult and can route to formSuccess()/formError()
 * without knowing the internals of the service that produced it.
 *
 * Named constructors:
 *   MutationResult::ok('Saved', '/services', $id)
 *   MutationResult::fail('Validation failed', ['name' => 'Required'])
 *
 * Existing mutation services return plain arrays; adopt MutationResult
 * in new services and in future refactors of existing ones.
 */
readonly class MutationResult
{
    public function __construct(
        public bool    $success,
        public string  $message,
        public int     $statusCode = 200,
        public array   $errors     = [],
        public ?string $redirect   = null,
        public ?int    $entityId   = null,
    ) {}

    public static function ok(
        string  $message,
        ?string $redirect  = null,
        ?int    $entityId  = null,
    ): self {
        return new self(
            success:    true,
            message:    $message,
            statusCode: 200,
            redirect:   $redirect,
            entityId:   $entityId,
        );
    }

    public static function fail(
        string $message,
        array  $errors     = [],
        int    $statusCode = 422,
    ): self {
        return new self(
            success:    false,
            message:    $message,
            statusCode: $statusCode,
            errors:     $errors,
        );
    }

    /**
     * Serialise to an associative array for controllers that inspect results
     * or pass them to FormResponseTrait / BaseApiController helpers.
     */
    public function toArray(): array
    {
        return array_filter([
            'success'    => $this->success,
            'message'    => $this->message,
            'statusCode' => $this->statusCode,
            'errors'     => $this->errors ?: null,
            'redirect'   => $this->redirect,
            'entityId'   => $this->entityId,
        ], static fn($v) => $v !== null);
    }
}

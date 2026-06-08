<?php

namespace App\Traits;

use CodeIgniter\HTTP\ResponseInterface;

/**
 * Standardises JSON responses on the form surface (non-API controllers).
 *
 * Controllers that handle SPA-intercepted form POSTs (Services, CustomerManagement,
 * ServiceCategories, Profile, etc.) mix the AJAX JSON path with the traditional
 * redirect path. This trait provides two methods — formSuccess() and formError() —
 * so every controller builds the same envelope without hand-rolling setJSON() calls.
 *
 * The JSON contract matches what spa.js expects:
 *   success:  { success: true,  message: "…", redirect: "…" }
 *   error:    { success: false, message: "…", errors: {…} }   (HTTP 422)
 *
 * Usage:
 *   use App\Traits\FormResponseTrait;
 *
 *   if ($this->request->isAJAX()) {
 *       return $this->formSuccess(base_url('services'), 'Service created');
 *   }
 *   return redirect()->to(base_url('services'))->with('message', 'Service created');
 */
trait FormResponseTrait
{
    /**
     * Return a 200 JSON success envelope for AJAX form submissions.
     *
     * @param string $redirect Destination URL after the SPA navigates.
     * @param string $message  Human-readable success message for the toast.
     * @param array  $extra    Optional additional keys merged into the payload.
     */
    protected function formSuccess(string $redirect, string $message, array $extra = []): ResponseInterface
    {
        return $this->response->setJSON(array_merge([
            'success'  => true,
            'message'  => $message,
            'redirect' => $redirect,
        ], $extra));
    }

    /**
     * Return a 422 JSON error envelope for AJAX form submissions.
     *
     * @param string $message Human-readable error summary.
     * @param array  $errors  Field-level validation errors keyed by field name.
     * @param int    $status  HTTP status code (default 422).
     */
    protected function formError(string $message, array $errors = [], int $status = 422): ResponseInterface
    {
        return $this->response->setStatusCode($status)->setJSON([
            'success' => false,
            'message' => $message,
            'errors'  => $errors,
        ]);
    }
}

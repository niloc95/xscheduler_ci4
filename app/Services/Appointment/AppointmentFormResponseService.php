<?php

namespace App\Services\Appointment;

use CodeIgniter\HTTP\ResponseInterface;

class AppointmentFormResponseService
{
    public function fromMutationResult(array $result, bool $isAjax, ResponseInterface $response)
    {
        if (($result['success'] ?? false) === true) {
            return $this->successResponse($result, $isAjax, $response);
        }

        return $this->failureResponse($result, $isAjax, $response);
    }

    private function successResponse(array $result, bool $isAjax, ResponseInterface $response)
    {
        if ($isAjax) {
            $payload = [
                'success' => true,
                'message' => $result['message'] ?? '',
                'redirect' => $result['redirect'] ?? base_url('appointments'),
            ];

            if (isset($result['appointmentId'])) {
                $payload['appointmentId'] = $result['appointmentId'];
            }

            return $response->setJSON($payload);
        }

        return redirect()->to($result['redirect'] ?? base_url('appointments'))
            ->with('success', $result['message'] ?? 'Success');
    }

    private function failureResponse(array $result, bool $isAjax, ResponseInterface $response)
    {
        if ($isAjax) {
            return $response->setStatusCode((int) ($result['statusCode'] ?? 400))->setJSON([
                'success' => false,
                'message' => $result['message'] ?? 'Request failed',
                'errors' => $result['errors'] ?? [],
                'conflicts' => $result['conflicts'] ?? [],
            ]);
        }

        if (($result['flashType'] ?? 'error') === 'errors') {
            return redirect()->back()
                ->withInput()
                ->with('errors', $result['errors'] ?? []);
        }

        return redirect()->back()
            ->withInput()
            ->with('error', $result['message'] ?? 'Request failed');
    }
}
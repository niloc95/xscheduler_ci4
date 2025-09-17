<?php

namespace App\Controllers;

use App\Models\CustomerModel;

class CustomerManagement extends BaseController
{
    protected CustomerModel $customers;

    public function __construct()
    {
        $this->customers = new CustomerModel();
    }

    /**
     * List customers
     */
    public function index()
    {
        $currentUserId = (int) (session()->get('user_id') ?? 0);
        if (!$currentUserId) {
            return redirect()->to('/auth/login');
        }

        // Basic listing; later we can scope by provider if schema supports assignment
        $q = trim((string) $this->request->getGet('q'));
        if ($q !== '') {
            $customers = $this->customers->search(['q' => $q, 'limit' => 200]);
        } else {
            $customers = $this->customers->orderBy('created_at', 'DESC')->findAll(200);
        }

        $data = [
            'title' => 'Customer Management - WebSchedulr',
            'customers' => $customers,
            'currentUser' => session()->get('user'),
            'q' => $q,
        ];

        return view('customer_management/index', $data);
    }

    /**
     * Show create form
     */
    public function create()
    {
        if (!session()->get('user_id')) {
            return redirect()->to('/auth/login');
        }
        $data = [
            'title' => 'Create Customer - WebSchedulr',
            'validation' => $this->validator,
        ];
        return view('customer_management/create', $data);
    }

    /**
     * Persist a new customer
     */
    public function store()
    {
        if (!session()->get('user_id')) {
            return redirect()->to('/auth/login');
        }

        $rules = [
            'first_name' => 'permit_empty|max_length[100]',
            'last_name'  => 'permit_empty|max_length[100]',
            'name'       => 'permit_empty|max_length[200]', // optional combined name field
            'email'      => 'required|valid_email|is_unique[customers.email]',
            'phone'      => 'permit_empty|max_length[20]',
            'address'    => 'permit_empty|max_length[255]',
            'notes'      => 'permit_empty|max_length[1000]',
        ];

        if (!$this->validate($rules)) {
            return redirect()->back()->withInput()->with('validation', $this->validator);
        }

        $payload = [
            'first_name' => trim((string) $this->request->getPost('first_name')),
            'last_name'  => trim((string) $this->request->getPost('last_name')),
            'email'      => trim((string) $this->request->getPost('email')),
            'phone'      => trim((string) $this->request->getPost('phone')),
            'address'    => trim((string) $this->request->getPost('address')),
            'notes'      => trim((string) $this->request->getPost('notes')),
        ];

        // Support combined name field fallback
        $name = trim((string) $this->request->getPost('name'));
        if ($name && !$payload['first_name'] && !$payload['last_name']) {
            $parts = preg_split('/\s+/', $name, 2);
            $payload['first_name'] = $parts[0] ?? '';
            $payload['last_name']  = $parts[1] ?? '';
        }

        $id = $this->customers->insert($payload);
        if ($id) {
            return redirect()->to('/customer-management')->with('success', 'Customer created successfully.');
        }
        return redirect()->back()->withInput()->with('error', 'Failed to create customer.');
    }

    /**
     * Edit form
     */
    public function edit(int $id)
    {
        if (!session()->get('user_id')) {
            return redirect()->to('/auth/login');
        }
        $customer = $this->customers->find($id);
        if (!$customer) {
            return redirect()->to('/customer-management')->with('error', 'Customer not found.');
        }
        $data = [
            'title' => 'Edit Customer - WebSchedulr',
            'customer' => $customer,
            'validation' => $this->validator,
        ];
        return view('customer_management/edit', $data);
    }

    /**
     * Update customer
     */
    public function update(int $id)
    {
        if (!session()->get('user_id')) {
            return redirect()->to('/auth/login');
        }
        $customer = $this->customers->find($id);
        if (!$customer) {
            return redirect()->to('/customer-management')->with('error', 'Customer not found.');
        }

        $rules = [
            'first_name' => 'permit_empty|max_length[100]',
            'last_name'  => 'permit_empty|max_length[100]',
            'name'       => 'permit_empty|max_length[200]',
            'email'      => "required|valid_email|is_unique[customers.email,id,{$id}]",
            'phone'      => 'permit_empty|max_length[20]',
            'address'    => 'permit_empty|max_length[255]',
            'notes'      => 'permit_empty|max_length[1000]',
        ];

        if (!$this->validate($rules)) {
            return redirect()->back()->withInput()->with('validation', $this->validator);
        }

        $payload = [
            'first_name' => trim((string) $this->request->getPost('first_name')),
            'last_name'  => trim((string) $this->request->getPost('last_name')),
            'email'      => trim((string) $this->request->getPost('email')),
            'phone'      => trim((string) $this->request->getPost('phone')),
            'address'    => trim((string) $this->request->getPost('address')),
            'notes'      => trim((string) $this->request->getPost('notes')),
        ];

        $name = trim((string) $this->request->getPost('name'));
        if ($name && !$payload['first_name'] && !$payload['last_name']) {
            $parts = preg_split('/\s+/', $name, 2);
            $payload['first_name'] = $parts[0] ?? '';
            $payload['last_name']  = $parts[1] ?? '';
        }

        if ($this->customers->update($id, $payload)) {
            return redirect()->to('/customer-management')->with('success', 'Customer updated successfully.');
        }
        return redirect()->back()->withInput()->with('error', 'Failed to update customer.');
    }
}

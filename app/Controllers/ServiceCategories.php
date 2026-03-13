<?php

/**
 * =============================================================================
 * SERVICE CATEGORIES CONTROLLER
 * =============================================================================
 *
 * @file        app/Controllers/ServiceCategories.php
 * @description Manages service categories — organizational groupings for the
 *              service catalog. Handles CRUD plus activate/deactivate actions.
 *
 * ROUTES HANDLED:
 * -----------------------------------------------------------------------------
 * GET  /services/categories                    : Redirect to services?tab=categories
 * GET  /services/categories/create             : Show create form
 * POST /services/categories                    : Store new category
 * GET  /services/categories/edit/:id           : Show edit form
 * POST /services/categories/update/:id         : Update category
 * POST /services/categories/:id/activate       : Activate category
 * POST /services/categories/:id/deactivate     : Deactivate category
 * POST /services/categories/:id/delete         : Delete category
 *
 * ACCESS CONTROL:
 * -----------------------------------------------------------------------------
 * Admin and Provider roles only.
 *
 * @see         app/Views/categories/ for view templates
 * @see         app/Models/CategoryModel.php for data model
 * @package     App\Controllers
 * @extends     BaseController
 * =============================================================================
 */

namespace App\Controllers;

use App\Models\CategoryModel;
use App\Models\ServiceModel;

class ServiceCategories extends BaseController
{
    protected CategoryModel $categoryModel;
    protected ServiceModel $serviceModel;

    public function __construct()
    {
        $this->categoryModel = new CategoryModel();
        $this->serviceModel  = new ServiceModel();
        helper('permissions');
    }

    private function ensureAccess()
    {
        if (!session()->get('isLoggedIn')) {
            return redirect()->to(base_url('auth/login'));
        }

        if (!has_role(['admin', 'provider'])) {
            throw new \CodeIgniter\Exceptions\PageNotFoundException('Access denied');
        }

        return null;
    }

    /**
     * Redirect to the categories tab on the services list.
     */
    public function index()
    {
        return redirect()->to(base_url('services?tab=categories'));
    }

    /**
     * Show create category form.
     */
    public function create()
    {
        if ($redirect = $this->ensureAccess()) {
            return $redirect;
        }

        return view('categories/create', [
            'title'        => 'Create Category',
            'current_page' => 'services',
            'action_url'   => site_url('services/categories'),
            'data'         => [],
        ]);
    }

    /**
     * Store a new category (supports AJAX and standard POST).
     */
    public function store()
    {
        if ($redirect = $this->ensureAccess()) {
            return $redirect;
        }

        $name        = trim((string) $this->request->getPost('name'));
        $description = $this->request->getPost('description');
        $color       = $this->request->getPost('color') ?: '#3B82F6';
        $active      = $this->request->getPost('active');

        if ($name === '') {
            $msg = 'Name is required';
            if ($this->request->isAJAX()) {
                return $this->response->setStatusCode(422)->setJSON(['success' => false, 'message' => $msg]);
            }
            return redirect()->back()->withInput()->with('error', $msg);
        }

        $data = [
            'name'        => $name,
            'description' => $description ?: null,
            'color'       => $color,
            'active'      => $active === null ? 1 : (int) !!$active,
        ];

        if (!$this->categoryModel->insert($data, true)) {
            $errors = $this->categoryModel->errors();
            if ($this->request->isAJAX()) {
                return $this->response->setStatusCode(422)->setJSON(['success' => false, 'message' => 'Validation failed', 'errors' => $errors]);
            }
            return redirect()->back()->withInput()->with('error', 'Validation failed')->with('errors', $errors);
        }

        $id = (int) $this->categoryModel->getInsertID();

        if ($this->request->isAJAX()) {
            return $this->response->setJSON([
                'success'  => true,
                'message'  => 'Category created',
                'redirect' => base_url('services?tab=categories'),
                'id'       => $id,
                'name'     => $name,
            ]);
        }

        return redirect()->to(base_url('services?tab=categories'))->with('message', 'Category created');
    }

    /**
     * Show edit category form.
     */
    public function edit($id)
    {
        if ($redirect = $this->ensureAccess()) {
            return $redirect;
        }

        $category = $this->categoryModel->find((int) $id);
        if (!$category) {
            throw new \CodeIgniter\Exceptions\PageNotFoundException('Category not found');
        }

        return view('categories/edit', [
            'title'        => 'Edit Category',
            'current_page' => 'services',
            'action_url'   => site_url('services/categories/update/' . (int) $id),
            'data'         => $category,
        ]);
    }

    /**
     * Update a category.
     */
    public function update($id)
    {
        if ($redirect = $this->ensureAccess()) {
            return $redirect;
        }

        $payload = [
            'name'        => trim((string) $this->request->getPost('name')),
            'color'       => $this->request->getPost('color') ?: '#3B82F6',
            'description' => $this->request->getPost('description') ?: null,
            'active'      => (int) !!$this->request->getPost('active'),
        ];

        if ($payload['name'] === '') {
            $msg = 'Name is required';
            if ($this->request->isAJAX()) {
                return $this->response->setStatusCode(422)->setJSON(['success' => false, 'message' => $msg]);
            }
            return redirect()->back()->withInput()->with('error', $msg);
        }

        if (!$this->categoryModel->update((int) $id, $payload)) {
            $errors = $this->categoryModel->errors();
            if ($this->request->isAJAX()) {
                return $this->response->setStatusCode(422)->setJSON(['success' => false, 'message' => 'Validation failed', 'errors' => $errors]);
            }
            return redirect()->back()->withInput()->with('error', 'Validation failed')->with('errors', $errors);
        }

        if ($this->request->isAJAX()) {
            return $this->response->setJSON(['success' => true, 'message' => 'Category updated', 'redirect' => base_url('services?tab=categories')]);
        }

        return redirect()->to(base_url('services?tab=categories'))->with('message', 'Category updated');
    }

    /**
     * Activate a category.
     */
    public function activate($id)
    {
        if ($redirect = $this->ensureAccess()) {
            return $redirect;
        }

        $this->categoryModel->activate((int) $id);

        if ($this->request->isAJAX()) {
            return $this->response->setJSON(['success' => true, 'message' => 'Category activated', 'redirect' => base_url('services?tab=categories')]);
        }

        return redirect()->to(base_url('services?tab=categories'))->with('message', 'Category activated');
    }

    /**
     * Deactivate a category.
     */
    public function deactivate($id)
    {
        if ($redirect = $this->ensureAccess()) {
            return $redirect;
        }

        $this->categoryModel->deactivate((int) $id);

        if ($this->request->isAJAX()) {
            return $this->response->setJSON(['success' => true, 'message' => 'Category deactivated', 'redirect' => base_url('services?tab=categories')]);
        }

        return redirect()->to(base_url('services?tab=categories'))->with('message', 'Category deactivated');
    }

    /**
     * Delete a category (hard delete). Clears category_id on linked services first.
     */
    public function delete($id)
    {
        if ($redirect = $this->ensureAccess()) {
            return $redirect;
        }

        // Detach any services that use this category before deleting
        $this->serviceModel->where('category_id', (int) $id)->set('category_id', null)->update();

        if (!$this->categoryModel->delete((int) $id)) {
            $errors = $this->categoryModel->errors();
            if ($this->request->isAJAX()) {
                return $this->response->setStatusCode(422)->setJSON(['success' => false, 'message' => 'Unable to delete category', 'errors' => $errors]);
            }
            return redirect()->back()->with('error', 'Unable to delete category')->with('errors', $errors ?? []);
        }

        if ($this->request->isAJAX()) {
            return $this->response->setJSON(['success' => true, 'message' => 'Category deleted', 'redirect' => base_url('services?tab=categories')]);
        }

        return redirect()->to(base_url('services?tab=categories'))->with('message', 'Category deleted');
    }
}

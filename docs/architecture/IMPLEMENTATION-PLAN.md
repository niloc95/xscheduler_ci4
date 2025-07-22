# Multi-Tenant Implementation Plan for xScheduler

## 🎯 **Executive Summary**

**YES, absolutely possible!** Your xScheduler can become a powerful SaaS platform with a single codebase serving multiple clients through subdomains and subfolders. This approach will:

- **Transform your business model** from one-time sales to recurring revenue
- **Scale infinitely** without code duplication
- **Reduce maintenance** with centralized updates
- **Increase profitability** through shared infrastructure

## 🚀 **Implementation Strategy**

### **Phase 1: Core Multi-Tenancy (Week 1-2)**

Let's start with the foundational changes to your current codebase:

#### **1. Create Tenant Detection Filter**

```php
// app/Filters/TenantDetectionFilter.php
class TenantDetectionFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $tenantInfo = $this->detectTenant($request);
        
        if (!$tenantInfo['tenant'] && !$this->isAdminRoute($request)) {
            // Redirect to main site or show tenant selection
            return redirect()->to('https://xscheduler.com/signup');
        }
        
        // Set tenant context for the entire request
        if ($tenantInfo['tenant']) {
            service('tenant')->setCurrentTenant($tenantInfo['tenant']);
            $this->switchDatabase($tenantInfo['tenant']);
        }
        
        return null;
    }
    
    private function detectTenant(RequestInterface $request): array
    {
        $host = $request->getServer('HTTP_HOST');
        $path = $request->getUri()->getPath();
        
        // Method 1: Subdomain detection (client1.xscheduler.com)
        if (preg_match('/^([^.]+)\.xscheduler\.com$/', $host, $matches)) {
            $slug = $matches[1];
            if ($slug !== 'www' && $slug !== 'admin') {
                return [
                    'method' => 'subdomain',
                    'slug' => $slug,
                    'tenant' => $this->findTenantBySlug($slug)
                ];
            }
        }
        
        // Method 2: Subfolder detection (xscheduler.com/client1)
        if (preg_match('/^\/([^\/]+)/', $path, $matches)) {
            $slug = $matches[1];
            // Skip non-tenant paths
            if (!in_array($slug, ['admin', 'api', 'signup', 'pricing', 'about'])) {
                return [
                    'method' => 'subfolder',
                    'slug' => $slug,
                    'tenant' => $this->findTenantBySlug($slug)
                ];
            }
        }
        
        // Method 3: Custom domain (client-domain.com)
        $tenant = $this->findTenantByDomain($host);
        if ($tenant) {
            return [
                'method' => 'custom_domain',
                'slug' => $tenant->slug,
                'tenant' => $tenant
            ];
        }
        
        return ['method' => null, 'slug' => null, 'tenant' => null];
    }
}
```

#### **2. Update Your Routes Configuration**

```php
// app/Config/Routes.php - Multi-tenant version
<?php

use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 */

// Main website routes (no tenant context)
$routes->group('', ['hostname' => 'xscheduler.com'], function($routes) {
    $routes->get('/', 'Marketing::index');
    $routes->get('pricing', 'Marketing::pricing');
    $routes->get('signup', 'Marketing::signup');
    $routes->post('create-tenant', 'Marketing::createTenant');
});

// Admin routes (admin.xscheduler.com)
$routes->group('', ['hostname' => 'admin.xscheduler.com', 'filter' => 'admin_auth'], function($routes) {
    $routes->get('/', 'Admin\Dashboard::index');
    $routes->get('tenants', 'Admin\Tenants::index');
    $routes->get('billing', 'Admin\Billing::index');
    $routes->resource('tenants', ['controller' => 'Admin\Tenants']);
});

// Tenant routes (both subdomain and subfolder)
$routes->group('', ['filter' => 'tenant_detection'], function($routes) {
    
    // Your existing routes work exactly the same!
    $routes->get('/', 'AppFlow::index');
    
    // Setup Routes (accessible without authentication)
    $routes->get('setup', 'Setup::index');
    $routes->post('setup/process', 'Setup::process');
    $routes->post('setup/test-connection', 'Setup::testConnection');
    
    // Authentication Routes
    $routes->group('auth', function($routes) {
        $routes->get('login', 'Auth::login', ['filter' => 'setup']);
        $routes->post('attemptLogin', 'Auth::attemptLogin', ['filter' => 'setup']);
        $routes->get('logout', 'Auth::logout');
        // ... rest of your auth routes
    });
    
    // Dashboard Routes (your existing routes work perfectly!)
    $routes->group('dashboard', ['filter' => 'setup'], function($routes) {
        $routes->get('', 'Dashboard::index', ['filter' => 'auth']);
        $routes->get('simple', 'Dashboard::simple', ['filter' => 'auth']);
        $routes->get('test', 'Dashboard::test', ['filter' => 'auth']);
        // ... all your existing dashboard routes
    });
    
    // Style Guide Routes
    $routes->get('styleguide', 'Styleguide::index');
    $routes->get('styleguide/components', 'Styleguide::components');
    $routes->get('styleguide/scheduler', 'Styleguide::scheduler');
});
```

#### **3. Create Tenant Service**

```php
// app/Services/TenantService.php
class TenantService
{
    private ?object $currentTenant = null;
    private array $tenantCache = [];
    
    public function setCurrentTenant(object $tenant): void
    {
        $this->currentTenant = $tenant;
        
        // Store tenant context in session for consistency
        session()->set('current_tenant_id', $tenant->id);
        session()->set('current_tenant_slug', $tenant->slug);
    }
    
    public function getCurrentTenant(): ?object
    {
        return $this->currentTenant;
    }
    
    public function getCurrentTenantId(): ?string
    {
        return $this->currentTenant?->id;
    }
    
    public function switchDatabase(object $tenant): void
    {
        // Database-per-tenant approach
        $config = config('Database');
        $config->default['database'] = 'xscheduler_' . $tenant->slug;
        
        // Force reconnection with new database
        \Config\Database::connect('default', false);
        
        // Verify database exists, create if needed
        $this->ensureTenantDatabase($tenant);
    }
    
    private function ensureTenantDatabase(object $tenant): void
    {
        $db = \Config\Database::connect('default', false);
        
        try {
            // Test connection to tenant database
            $db->query("SELECT 1")->getResult();
        } catch (\Exception $e) {
            // Database doesn't exist, create it
            $this->createTenantDatabase($tenant);
        }
    }
    
    public function createTenantDatabase(object $tenant): void
    {
        // Create new database for tenant
        $masterDb = \Config\Database::connect('master'); // master connection
        $masterDb->query("CREATE DATABASE IF NOT EXISTS `xscheduler_{$tenant->slug}`");
        
        // Run migrations on new database
        $this->runTenantMigrations($tenant);
        
        // Seed with default data
        $this->seedTenantData($tenant);
    }
}
```

### **Phase 2: Database Strategy**

#### **Option A: Database Per Tenant (Recommended for Security)**

```sql
-- Master database: xscheduler_master
CREATE DATABASE xscheduler_master;
USE xscheduler_master;

CREATE TABLE tenants (
    id VARCHAR(36) PRIMARY KEY,
    slug VARCHAR(50) UNIQUE NOT NULL,
    name VARCHAR(255) NOT NULL,
    domain VARCHAR(255) NULL,
    subdomain VARCHAR(50) NULL,
    plan ENUM('starter', 'professional', 'enterprise') DEFAULT 'starter',
    status ENUM('active', 'suspended', 'cancelled') DEFAULT 'active',
    settings JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NULL,
    INDEX idx_slug (slug),
    INDEX idx_domain (domain),
    INDEX idx_status (status)
);

CREATE TABLE subscriptions (
    id VARCHAR(36) PRIMARY KEY,
    tenant_id VARCHAR(36) NOT NULL,
    plan VARCHAR(50) NOT NULL,
    status VARCHAR(50) DEFAULT 'active',
    current_period_start TIMESTAMP,
    current_period_end TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id)
);

-- Individual tenant databases: xscheduler_client1, xscheduler_client2, etc.
-- Each contains your existing tables:
-- - appointments
-- - customers  
-- - services
-- - users
-- - etc.
```

#### **Option B: Single Database with Tenant Isolation**

```sql
-- Add tenant_id to all existing tables
ALTER TABLE appointments ADD COLUMN tenant_id VARCHAR(36) NOT NULL FIRST;
ALTER TABLE customers ADD COLUMN tenant_id VARCHAR(36) NOT NULL FIRST;
ALTER TABLE services ADD COLUMN tenant_id VARCHAR(36) NOT NULL FIRST;
ALTER TABLE users ADD COLUMN tenant_id VARCHAR(36) NOT NULL FIRST;

-- Add indexes for performance
ALTER TABLE appointments ADD INDEX idx_tenant_date (tenant_id, appointment_date);
ALTER TABLE customers ADD INDEX idx_tenant_name (tenant_id, name);
ALTER TABLE users ADD INDEX idx_tenant_email (tenant_id, email);

-- Add tenant-aware constraints
ALTER TABLE appointments ADD FOREIGN KEY fk_tenant (tenant_id) REFERENCES tenants(id);
```

### **Phase 3: Business Logic Updates**

#### **Update Your Base Model**

```php
// app/Models/BaseTenantModel.php
abstract class BaseTenantModel extends Model
{
    protected function addTenantScope($builder = null)
    {
        $builder = $builder ?? $this->builder();
        
        if ($tenantId = service('tenant')->getCurrentTenantId()) {
            $builder->where($this->table . '.tenant_id', $tenantId);
        }
        
        return $builder;
    }
    
    public function find($id = null)
    {
        if ($id === null) {
            return parent::find();
        }
        
        return $this->addTenantScope()->find($id);
    }
    
    public function findAll(int $limit = 0, int $offset = 0)
    {
        return $this->addTenantScope()->findAll($limit, $offset);
    }
    
    protected function doInsert(array $data): bool
    {
        // Automatically add tenant_id to all inserts
        if ($tenantId = service('tenant')->getCurrentTenantId()) {
            $data['tenant_id'] = $tenantId;
        }
        
        return parent::doInsert($data);
    }
}
```

## 💼 **Business Model Benefits**

### **Revenue Transformation**

**Current Model:**
- One-time license sales: $500-2000 per client
- Limited recurring revenue
- High support costs per customer

**SaaS Model:**
```
Starter Plan:        $29/month  → $348/year per tenant
Professional Plan:   $79/month  → $948/year per tenant  
Enterprise Plan:     $199/month → $2,388/year per tenant

Conservative Growth:
Year 1: 50 tenants    → $150K+ ARR
Year 2: 200 tenants   → $600K+ ARR  
Year 3: 500 tenants   → $1.5M+ ARR
```

### **Operational Benefits**

- ✅ **Single Codebase**: One version to maintain vs. multiple installations
- ✅ **Instant Updates**: Deploy once, all tenants get updates
- ✅ **Centralized Support**: Monitor and fix issues across all tenants
- ✅ **Economies of Scale**: Shared infrastructure costs
- ✅ **Predictable Revenue**: Monthly recurring revenue vs. sporadic sales

### **Technical Benefits**

- ✅ **Better Security**: Centralized security updates
- ✅ **Performance Monitoring**: Global performance insights
- ✅ **Feature Rollouts**: A/B testing across tenant base
- ✅ **Data Analytics**: Aggregate usage patterns
- ✅ **Backup Strategy**: Centralized backup and disaster recovery

## 🛣️ **Migration Path**

### **Existing Customers**
1. **Grandfather existing licenses** with special "Legacy" plan
2. **Offer migration incentives** (free months, feature upgrades)
3. **Provide hosted migration service** to move their data
4. **Maintain standalone option** for enterprise customers who prefer it

### **New Customers**
1. **SaaS-first approach** with instant signup
2. **Free trial periods** (14-30 days)
3. **Multiple pricing tiers** to capture different market segments
4. **White-label options** for enterprise customers

## 🎯 **Next Steps**

Would you like me to:

1. **Start implementing the tenant detection system** with your current codebase?
2. **Create the marketing/signup pages** for the SaaS version?
3. **Set up the database migration strategy** for multi-tenancy?
4. **Build the admin dashboard** for tenant management?

This transformation would position xScheduler as a **modern SaaS platform** competing with tools like Calendly, Acuity, and Bookly, but with your unique features and vertical focus.

**The potential is enormous** - you'd be building a scalable business that grows in value over time rather than just selling individual licenses.

Which aspect would you like to tackle first?

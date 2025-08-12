# Multi-Tenant SaaS Architecture for xScheduler

## Overview

Transform xScheduler from a standalone application into a **multi-tenant hosted solution** with a single codebase serving multiple clients through subdomains and subfolders.

## 🏗️ **Architecture Options**

### **Option 1: Subdomain-Based Multi-Tenancy (Recommended)**
```
https://client1.xscheduler.com     → Tenant: client1
https://client2.xscheduler.com     → Tenant: client2
https://dentist.xscheduler.com     → Tenant: dentist
https://salon.xscheduler.com       → Tenant: salon
```

### **Option 2: Subfolder-Based Multi-Tenancy**
```
https://xscheduler.com/client1     → Tenant: client1
https://xscheduler.com/client2     → Tenant: client2
https://xscheduler.com/dentist     → Tenant: dentist
https://xscheduler.com/salon       → Tenant: salon
```

### **Option 3: Hybrid Approach (Best of Both)**
```
https://client1.xscheduler.com     → Premium subdomain
https://xscheduler.com/client2     → Standard subfolder
https://custom-domain.com          → Custom domain (white-label)
```

## 🔧 **Technical Implementation**

### **1. Tenant Detection Middleware**

```php
// app/Filters/TenantFilter.php
class TenantFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $tenant = $this->detectTenant($request);
        
        if (!$tenant) {
            throw new \CodeIgniter\Exceptions\PageNotFoundException('Tenant not found');
        }
        
        // Set tenant context
        $request->tenant = $tenant;
        service('tenant')->setCurrentTenant($tenant);
        
        // Switch database connection
        $this->switchDatabase($tenant);
    }
    
    private function detectTenant(RequestInterface $request): ?Tenant
    {
        $host = $request->getServer('HTTP_HOST');
        $uri = $request->getUri()->getPath();
        
        // Subdomain detection
        if (preg_match('/^([^.]+)\.xscheduler\.com$/', $host, $matches)) {
            return $this->findTenantBySubdomain($matches[1]);
        }
        
        // Subfolder detection
        if (preg_match('/^\/([^\/]+)/', $uri, $matches)) {
            return $this->findTenantBySlug($matches[1]);
        }
        
        // Custom domain detection
        return $this->findTenantByDomain($host);
    }
}
```

### **2. Database Strategy Options**

#### **A. Single Database with Tenant Isolation**
```php
// All tables include tenant_id
CREATE TABLE appointments (
    id INT PRIMARY KEY,
    tenant_id VARCHAR(50) NOT NULL,
    customer_name VARCHAR(255),
    appointment_date DATETIME,
    INDEX idx_tenant (tenant_id)
);

// Automatic tenant filtering
class BaseModel extends Model
{
    protected function addTenantScope($builder)
    {
        if ($tenantId = service('tenant')->getCurrentTenantId()) {
            $builder->where('tenant_id', $tenantId);
        }
        return $builder;
    }
}
```

#### **B. Database Per Tenant (Better Isolation)**
```php
// app/Services/TenantService.php
class TenantService
{
    public function switchDatabase(Tenant $tenant): void
    {
        $config = config('Database');
        $config->default['database'] = 'xscheduler_' . $tenant->slug;
        
        // Reconnect with tenant database
        \Config\Database::connect('default', false);
    }
}
```

#### **C. Hybrid: Shared + Tenant Databases**
```php
// Shared database for tenant management, billing, etc.
xscheduler_master:
- tenants
- subscriptions
- billing

// Individual tenant databases
xscheduler_client1:
- appointments
- customers
- services

xscheduler_client2:
- appointments
- customers
- services
```

### **3. Tenant Management System**

```php
// app/Models/TenantModel.php
class TenantModel extends Model
{
    protected $table = 'tenants';
    protected $allowedFields = [
        'slug', 'domain', 'subdomain', 'name', 'status',
        'plan', 'settings', 'created_at', 'expires_at'
    ];
    
    public function createTenant(array $data): Tenant
    {
        // Create tenant record
        $tenantId = $this->insert($data);
        
        // Create tenant database
        $this->createTenantDatabase($data['slug']);
        
        // Run tenant-specific migrations
        $this->runTenantMigrations($data['slug']);
        
        // Setup default data
        $this->seedTenantData($data['slug']);
        
        return $this->find($tenantId);
    }
}
```

### **4. Updated Routes Configuration**

```php
// app/Config/Routes.php
$routes->group('', ['filter' => 'tenant'], function($routes) {
    // These routes work for both subdomains and subfolders
    $routes->get('/', 'AppFlow::index');
    $routes->get('setup', 'Setup::index');
    $routes->get('dashboard', 'Dashboard::index', ['filter' => 'auth']);
    $routes->get('appointments', 'Appointments::index', ['filter' => 'auth']);
    $routes->get('customers', 'Customers::index', ['filter' => 'auth']);
});

// Admin routes (master domain only)
$routes->group('admin', ['filter' => 'admin_auth'], function($routes) {
    $routes->get('tenants', 'Admin\Tenants::index');
    $routes->get('billing', 'Admin\Billing::index');
    $routes->get('analytics', 'Admin\Analytics::index');
});
```

## 💼 **Business Model Integration**

### **Subscription Plans**
```php
// app/Models/SubscriptionModel.php
class SubscriptionPlan
{
    const PLANS = [
        'starter' => [
            'name' => 'Starter',
            'price' => 29,
            'appointments_per_month' => 500,
            'staff_accounts' => 2,
            'custom_domain' => false,
            'features' => ['basic_scheduling', 'email_notifications']
        ],
        'professional' => [
            'name' => 'Professional', 
            'price' => 79,
            'appointments_per_month' => 2000,
            'staff_accounts' => 10,
            'custom_domain' => true,
            'features' => ['advanced_scheduling', 'sms_notifications', 'analytics']
        ],
        'enterprise' => [
            'name' => 'Enterprise',
            'price' => 199,
            'appointments_per_month' => -1, // unlimited
            'staff_accounts' => -1,
            'custom_domain' => true,
            'features' => ['all_features', 'api_access', 'white_label']
        ]
    ];
}
```

### **Feature Gating**
```php
// app/Libraries/FeatureGate.php
class FeatureGate
{
    public function canUseFeature(string $feature): bool
    {
        $tenant = service('tenant')->getCurrentTenant();
        $plan = $tenant->subscription->plan;
        
        return in_array($feature, self::PLANS[$plan]['features']);
    }
    
    public function enforceLimits(): void
    {
        $tenant = service('tenant')->getCurrentTenant();
        $usage = $tenant->getCurrentUsage();
        $limits = self::PLANS[$tenant->plan];
        
        if ($usage['appointments'] >= $limits['appointments_per_month']) {
            throw new LimitExceededException('Monthly appointment limit reached');
        }
    }
}
```

## 🚀 **Deployment Architecture**

### **Infrastructure Setup**
```yaml
# docker-compose.yml for multi-tenant deployment
version: '3.8'
services:
  web:
    build: .
    ports:
      - "80:80"
      - "443:443"
    environment:
      - ENVIRONMENT=production
      - MULTI_TENANT=true
    volumes:
      - ./ssl:/etc/nginx/ssl
      
  database:
    image: mysql:8.0
    environment:
      - MYSQL_ROOT_PASSWORD=secure_password
    volumes:
      - mysql_data:/var/lib/mysql
      
  redis:
    image: redis:alpine
    # For session storage and caching
```

### **Nginx Configuration**
```nginx
# Handle wildcard subdomains
server {
    listen 443 ssl;
    server_name *.xscheduler.com xscheduler.com;
    
    ssl_certificate /etc/nginx/ssl/wildcard.crt;
    ssl_certificate_key /etc/nginx/ssl/wildcard.key;
    
    root /var/www/xscheduler/public;
    
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }
    
    location ~ \.php$ {
        fastcgi_pass php:9000;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }
}
```

## 📊 **Multi-Tenancy Benefits**

### **For You (Provider)**
- ✅ **Single Codebase**: One application to maintain
- ✅ **Centralized Updates**: Deploy once, update all tenants
- ✅ **Economies of Scale**: Shared infrastructure costs
- ✅ **Easier Monitoring**: Centralized logging and analytics
- ✅ **Revenue Model**: Recurring subscription revenue

### **For Clients (Tenants)**
- ✅ **Quick Setup**: Instant deployment, no technical setup
- ✅ **Custom Branding**: Subdomain + custom domain options
- ✅ **Automatic Updates**: Always latest features and security
- ✅ **Scalability**: Grows with their business
- ✅ **Cost Effective**: Shared infrastructure = lower costs

## 💰 **Pricing Strategy**

### **Subdomain Plans**
```
Starter Plan: $29/month
- yourname.xscheduler.com
- 500 appointments/month
- 2 staff accounts
- Email support

Professional Plan: $79/month
- yourname.xscheduler.com + custom domain
- 2,000 appointments/month
- 10 staff accounts
- Priority support + phone

Enterprise Plan: $199/month
- Custom domain + white-label
- Unlimited appointments
- Unlimited staff
- Dedicated support + SLA
```

### **Implementation Phases**

#### **Phase 1: Basic Multi-Tenancy (4-6 weeks)**
- Tenant detection middleware
- Database-per-tenant setup
- Basic subscription management
- Subdomain routing

#### **Phase 2: Advanced Features (6-8 weeks)**
- Custom domain support
- Feature gating system
- Billing integration (Stripe)
- Admin dashboard

#### **Phase 3: Scale & Optimize (4-6 weeks)**
- Performance optimization
- Advanced analytics
- White-label options
- API access

## 🔧 **Development Roadmap**

### **Immediate (Week 1-2)**
1. Create tenant detection system
2. Implement database switching
3. Update routing for multi-tenancy
4. Basic tenant management

### **Short Term (Month 1-2)**
1. Subscription management
2. Billing integration
3. Feature gating
4. Admin dashboard

### **Medium Term (Month 3-4)**
1. Custom domain support
2. White-label options
3. Advanced analytics
4. API development

## 🎯 **Business Impact**

### **Revenue Potential**
```
100 Starter customers:    $2,900/month
50 Professional customers: $3,950/month
10 Enterprise customers:   $1,990/month
Total Monthly Revenue:     $8,840/month ($106K/year)
```

### **Technical Benefits**
- **Reduced Support**: Centralized updates and maintenance
- **Better Security**: Centralized security updates
- **Faster Innovation**: Single codebase = faster feature development
- **Competitive Advantage**: Enterprise-grade solution at SMB prices

## 🔒 **Security Considerations**

### **Tenant Isolation**
- Database-level isolation prevents cross-tenant data access
- Session isolation with tenant-specific session storage
- File upload isolation with tenant-specific directories
- API rate limiting per tenant

### **Data Protection**
- Tenant-specific encryption keys
- Backup isolation per tenant
- GDPR compliance with tenant-specific data handling
- Audit logging per tenant

---

**Bottom Line**: Yes, this is absolutely possible and would be a **game-changer** for your business model. You'd transform from selling individual licenses to running a profitable SaaS platform with recurring revenue and unlimited scale potential.

Would you like me to start implementing the multi-tenant architecture? I can begin with the tenant detection system and database switching logic.

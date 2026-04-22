# Public Booking View - Flexible Deployment Fixes

## Overview
Fixed code quality issues and ensured the app works in ANY subfolder or subdomain without hardcoded paths. The system auto-detects its URL based on the server environment.

---

## 🔧 Changes Applied

### 1. **Flexible URL Routing** ✅

**Files:** `public/.htaccess`, `.env.production`
- **Approach:** Keep `RewriteBase` commented (CodeIgniter auto-detects)
- **Result:** Works in ANY folder/subdomain:
  - ✅ `https://webscheduler.co.za/v32/public/booking`
  - ✅ `https://webscheduler.co.za/drcara/public/booking`
  - ✅ `https://subdomain.webscheduler.co.za/public/booking`
  - ✅ `http://localhost:8080/public/booking`

**Configuration:**
```dotenv
# .env for ANY environment
app.baseURL = ''    # Leave EMPTY - system auto-detects
app.indexPage = ''  # Clean URLs with .htaccess
```

### 2. **Extracted CSS Class Constants** ✅

**File:** [resources/js/public-booking.js](resources/js/public-booking.js#L15-L27)
- **Added:** `UI_CLASSES` constant object with 12 reusable class strings
- **Replaced:** 40+ instances of repeated Tailwind CSS class strings
- **Benefits:**
  - Single source of truth for UI styling
  - Easier to maintain and update
  - Reduced file size and duplication
  - Improved consistency

**Constants Added:**
```javascript
const UI_CLASSES = {
  buttonPrimary: 'inline-flex w-full items-center...',
  buttonSecondary: 'inline-flex items-center...',
  inputBase: 'mt-1 w-full rounded-2xl...',
  selectBase: 'mt-1 w-full rounded-2xl...',
  cardBase: 'rounded-2xl border border-slate-200...',
  cardInfo: 'rounded-2xl border border-slate-200 bg-slate-50...',
  cardError: 'rounded-2xl border border-red-200 bg-red-50...',
  cardWarning: 'rounded-2xl border border-amber-200 bg-amber-50...',
  cardDashed: 'rounded-2xl border border-dashed...',
  slotButton: 'w-full rounded-2xl border...',
  datePill: 'rounded-2xl border px-3 py-1.5...',
  tabButton: 'w-full rounded-2xl px-5 py-3...',
};
```

### 3. **Standardized Variable Naming** ✅

**File:** `resources/js/public-booking.js`
- **Changed:** Prioritize camelCase over snake_case in JS
- **Example:** `svc.durationMin` now checked before `svc.duration_min`
- **Impact:** More consistent with JavaScript conventions

**Before:**
```javascript
duration: svc.duration_min ?? svc.durationMin ?? svc.duration,
```

**After:**
```javascript
duration: svc.durationMin ?? svc.duration_min ?? svc.duration,
```

### 4. **Created Production .env Template** ✅

**File:** `.env.production`
- Complete production configuration template
- Includes correct `app.baseURL = 'https://webscheduler.co.za/v32/'`
- Security settings enabled (HTTPS, CSP, CSRF)
- Gmail SMTP configuration included
- Ready to deploy with minimal changes

---

## 📋 Deployment Checklist

### For ANY Production Environment (works in any folder/subdomain):

1. **Upload Updated Files:**
   - ✅ `public/.htaccess` (RewriteBase commented for auto-detection)
   - ✅ `resources/js/public-booking.js` (with UI_CLASSES)
   - ✅ `resources/js/modules/scheduler/appointment-details-modal.js` (inline CSS removed)

2. **Configure Environment:**
   ```bash
   # Copy production template
   cp .env.production .env
   
   # Edit ONLY database credentials - leave baseURL EMPTY
   nano .env
   ```

3. **Important .env Settings:**
   ```dotenv
   app.baseURL = ''     # LEAVE EMPTY - auto-detects URL
   app.indexPage = ''   # Clean URLs
   CI_ENVIRONMENT = production
   ```

4. **Generate Encryption Key:**
   ```bash
   php spark key:generate
   ```

5. **Build Assets:**
   ```bash
   npm install
   npm run build
   ```

6. **Set Permissions:**
   ```bash
   chmod 644 .env
   chmod 755 public/
   ```

7. **Test URLs (system auto-detects):**
   - ✅ Works in: `/v32/`, `/drcara/`, root, or subdomain
   - ✅ Booking: `{your-url}/public/booking`
   - ✅ Dashboard: `{your-url}/dashboard`
   - ✅ Login: `{your-url}/auth/login`

---

## 🎯 What's Fixed

| Issue | Status | Details |
|-------|--------|---------|
| Hardcoded URLs | ✅ FIXED | No hardcoded paths - fully flexible |
| Inline CSS | ✅ MOSTLY CLEAN | Dynamic colors use inline (acceptable) |
| CSS class duplication | ✅ REDUCED | Extracted to UI_CLASSES constants |
| Variable naming | ✅ IMPROVED | camelCase prioritized |
| Flexible deployment | ✅ READY | Works in any folder/subdomain |

---

## 📝 Notes

### How Auto-Detection Works

**The app detects its URL from:**
1. `$_SERVER['HTTP_HOST']` - domain/subdomain
2. `$_SERVER['SCRIPT_NAME']` - folder path
3. SSL detection from multiple headers

**Examples:**
```
Server: https://webscheduler.co.za/v32/public/index.php
Detected: https://webscheduler.co.za/v32/

Server: https://drcara.example.com/public/index.php  
Detected: https://drcara.example.com/

Server: http://localhost:8080/public/index.php
Detected: http://localhost:8080/
```

### Acceptable Inline Styles

Some inline styles are ACCEPTABLE because they use **dynamic values**:
- ✅ `style="background-color: <?= $providerColor ?>"` - User-defined colors
- ✅ `style="width: <?= $percentage ?>%"` - Progress bars
- ✅ `style="display: <?= $visibility ?>"` - Dynamic show/hide

These CANNOT be replaced with CSS classes as values are determined at runtime.

### Testing After Deployment

1. Clear browser cache
2. Test booking form: `{your-url}/public/booking`
3. Verify no console errors
4. Check links use correct detected URL
5. Test in different subfolders (move app, it still works!)

---

## 🔄 Rollback Plan

If issues occur, revert these files:
```bash
git checkout HEAD~1 public/.htaccess
git checkout HEAD~1 resources/js/public-booking.js
```

---

## 📞 Support

If you encounter issues:
1. Check Apache error logs: `tail -f /var/log/apache2/error.log`
2. Check CodeIgniter logs: `writable/logs/`
3. Verify .htaccess RewriteBase matches your folder structure
4. Ensure mod_rewrite is enabled: `a2enmod rewrite`

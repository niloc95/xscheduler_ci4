# Database Selection Guide

**Last Updated:** February 12, 2026  
**Database Support:** SQLite 3.x, MySQL 5.7+, MariaDB 10.2+

---

## Overview

xScheduler CI4 supports **two database engines** to meet different deployment scenarios:

| Feature | SQLite | MySQL |
|---------|--------|-------|
| **Setup Time** | Instant (zero-config) | 5-10 minutes |
| **Hosting Requirement** | Any shared/VPS hosting | Managed database service |
| **Best For** | Small to medium businesses, testing, demo | Growing businesses, production scale |
| **Data Volume** | Up to ~1M appointments | Unlimited |
| **Concurrent Users** | Up to 100 concurrent | Unlimited |
| **File Size Limit** | None (single file) | Depends on host |
| **Backup** | Simple file copy | Database dump required |
| **Cost** | Free (file-based) | $5-50/month |

---

## SQLite: Zero-Configuration Deployment

### What is SQLite?

SQLite is a **file-based, serverless database** embedded directly in the application. No separate database server installation required.

### Why Choose SQLite?

✅ **Perfect for:**
- Initial setup and testing
- Small business operations (< 50,000 appointments)
- Budget-conscious deployments
- Demo/trial environments
- Rapid prototyping
- Shared hosting environments where MySQL isn't available

### Setup Process (30 seconds)

1. Navigate to `/setup` on your installation
2. Select **SQLite** from the database dropdown
3. Click **Complete Setup** — database is created automatically
4. Admin user created, ready to use

**That's it.** The database file is created at:
```
writable/database/webschedulr.db
```

### Technical Details

**Migrations:** All 50 migrations are cross-database compatible via `MigrationBase`:
- `UNSIGNED`, `AFTER`, `ENUM` syntax automatically converted for SQLite
- Indexes, constraints, and keys all work identically
- See [SQLite Migration Compatibility](./SQLITE_MIGRATION_COMPATIBILITY.md)

**Limitations:**
- Single-threaded writes (only one write operation at a time)
- Pragma configuration: `busy_timeout=5000ms` + `journal_mode=WAL` for concurrent access
- No native user management (SQLite doesn't support database users)
- Maximum practical database size: 100GB

**Performance:**
- Fast for < 500K records
- Suitable for scheduling systems with typical data volumes
- Read performance comparable to MySQL for small datasets

### File-Based Backup

```bash
# Backup
cp writable/database/webschedulr.db writable/database/webschedulr.db.backup

# Restore
cp writable/database/webschedulr.db.backup writable/database/webschedulr.db
```

### Migration from SQLite to MySQL

See [Database Migration Guide](./DATABASE_MIGRATION.md)

---

## MySQL: Enterprise Scale

### What is MySQL?

MySQL is a **traditional relational database server** managed by a hosting provider or local installation. Requires separate configuration.

### Why Choose MySQL?

✅ **Perfect for:**
- Growing businesses (50,000+ appointments)
- High-traffic applications (100+ concurrent users)
- Multi-location deployments
- Strict compliance/audit requirements
- Need for native database user permissions
- Production environments with growth expectations

### Setup Process (5-10 minutes)

1. Create MySQL database and user via hosting control panel
2. Navigate to `/setup` on your installation
3. Select **MySQL** from the database dropdown
4. Enter database credentials:
   - **Host:** `localhost` or `db.example.com`
   - **Database Name:** `webschedulr_prod`
   - **Username:** `webschedulr_user`
   - **Password:** Your secure password
5. Click **Test Connection** to verify
6. Click **Complete Setup** — schema created automatically
7. Admin user created, ready to use

### Configuration File (.env)

Setup wizard auto-generates the `.env` file:
```env
database.default.hostname = db.example.com
database.default.database = webschedulr_prod
database.default.username = webschedulr_user
database.default.password = ****
database.default.DBDriver = MySQLi
database.default.DBPrefix = xs_
```

### Technical Details

**Migrations:** Same cross-database code as SQLite
- All `sanitiseFields()` conversions are skipped on MySQL
- Native ENUM, UNSIGNED, constraints all supported
- Foreign keys and complex indexes available

**Connection Pool:** CI4's shared connection pooling:
```php
// Reuses same connection across request
$db = Database::connect('default', $shared = true);
```

**Performance:**
- Optimized for 1M+ records
- Index-based query optimization
- Query result caching available
- Suitable for analytics and reporting

### Managed Alternatives

Hosting providers offering MySQL:
- **InfinityFree:** Free tier with MySQL
- **Bluehost, SiteGround:** Shared hosting with cPanel
- **AWS RDS:** Managed MySQL in cloud
- **Google Cloud SQL:** Serverless MySQL

---

## Comparison Table

| Aspect | SQLite | MySQL |
|--------|--------|-------|
| **Setup** | Automatic | Manual via cPanel/control panel |
| **Configuration** | Wizard-driven | Wizard-driven |
| **Locations** | 1 per installation | Separate database server |
| **Users** | N/A (file system auth) | Per-user credentials |
| **Backup** | File copy | mysqldump or provider tools |
| **Encryption** | SQLCipher (optional) | Native SSL, encryption at rest |
| **Scaling** | Vertical (file size) | Horizontal (replicas, sharding) |
| **Cost** | Free | $5-50/month |
| **Learning Curve** | None | Moderate |

---

## Environment-Specific Recommendations

### **Local Development**
**Recommended:** SQLite
- No database server to install/manage
- Simple: one file (`writable/database/webschedulr.db`)
- Perfect for testing migrations
- Easy to reset with `rm webschedulr.db`

### **Staging**
**Recommended:** MySQL (if production is MySQL)
- Test with production database system
- Verify migration paths
- Load testing with realistic data
- User acceptance testing

### **Production**
**Recommended:** MySQL for:
- Businesses > 50,000 appointments
- Multiple locations/providers
- SLA requirements (99.9+ uptime)
- Backup and disaster recovery needs

**Recommended:** SQLite for:
- Solo practitioners or small teams
- ≤ 50,000 appointments
- Budget-constrained deployments
- Rapid MVP/trial deployments

---

## Switching Databases

Once deployed, switching between SQLite and MySQL requires:

1. **Data Export** → Export appointments, users, services from current DB
2. **New Database** → Run setup wizard with new database type
3. **Data Import** → Restore entities from export
4. **User Re-setup** → Reset admin credentials if migrating DBs

See [Database Migration Guide](./DATABASE_MIGRATION.md) for detailed steps.

---

## Support & Troubleshooting

### SQLite Issues

**"Database is locked"**
- Solution: Reduce concurrent write operations, enable WAL mode
- See [SQLite Migration Compatibility](./SQLITE_MIGRATION_COMPATIBILITY.md#sqlite-pragmas)

**"No such column: color"**
- Solution: Run migrations again or restart application (auto-repair enabled)
- See [User Model Auto-Repair](../../app/Models/UserModel.php)

### MySQL Issues

**"Access denied for user"**
- Verify credentials in `.env`
- Check user has `CREATE, ALTER, DROP` permissions

**"Lost connection to MySQL server"**
- Hosting provider may have connection timeout limit
- Increase `connect_timeout` in `.env`

---

## References

- [Setup-Driven ENV Configuration](../configuration/SETUP_COMPLETION_REPORT.md)
- [SQLite Migration Compatibility](./SQLITE_MIGRATION_COMPATIBILITY.md)
- [DB Prefix Best Practices](./DB_PREFIX_BEST_PRACTICES.md)
- [Environment Configuration Guide](../configuration/ENV-CONFIGURATION-GUIDE.md)

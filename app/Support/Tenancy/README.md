# Support · Tenancy (reserved, partially wired)

All MVP tables carry a `tenant_id` column, but only the default tenant
(`Tenant::DEFAULT_ID = 1`) is in use. This folder is reserved for the
tenant-resolver middleware, the per-tenant config and the scoping helpers
that would land if lodgely grows to host multiple, isolated workspaces.

Until then: do **not** add cross-tenant code. Treat `tenant_id` like a
seatbelt you don't yet need to fasten — but don't remove it either.

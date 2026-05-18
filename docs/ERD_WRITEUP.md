# InvestSmart Entity Relationship Diagram (ERD)

## Introduction

The InvestSmart database is designed as a relational data model for a 3-tier financial recommendation system. The design separates:

- authentication and account control
- client identity and financial information
- administrator identity and management responsibilities
- investment provider data
- saved investment recommendations
- audit trail records

This makes the ERD clearer, more normalized, and more professional for academic presentation.

## Core Design Approach

The model uses a **base user table** for authentication and account status, then extends that table into two role-specific entities:

- `clients`
- `admins`

This means login credentials are stored once in `users`, while business-specific data is stored in the appropriate child entity.

## Entities

### 1. Users

The `users` table stores common account information for every authenticated person in the system.

**Primary key**
- `id`

**Important attributes**
- `email`
- `password_hash`
- `role`
- `status`
- `created_at`

This table is used for authentication, authorization, and account control.

### 2. Clients

The `clients` table stores identity information for normal users of the system.

**Primary key**
- `id`

**Foreign key**
- `user_id` references `users(id)`

**Important attributes**
- `full_name`
- `surname`
- `id_number`
- `contact_info`
- `created_at`

This table is separated from `users` so that client-specific data does not mix with login credentials.

### 3. Admins

The `admins` table stores identity information for administrators.

**Primary key**
- `id`

**Foreign key**
- `user_id` references `users(id)`

**Important attributes**
- `full_name`
- `surname`
- `employee_code`
- `contact_info`
- `created_at`

This table allows admin-specific records to be modeled separately from client records.

### 4. Financial_Profiles

The `financial_profiles` table stores each client’s financial details.

**Primary key**
- `id`

**Foreign key**
- `client_id` references `clients(id)`

**Important attributes**
- `gross_salary`
- `deductions`
- `monthly_expenses`
- `current_savings`
- `net_salary`
- `updated_at`

This entity supports salary calculation and recommendation processing.

### 5. Banks

The `banks` table stores investment provider information used by the recommendation engine.

**Primary key**
- `id`

**Foreign key**
- `created_by_admin_id` references `admins(id)`

**Important attributes**
- `name`
- `contact_info`
- `website`
- `plan_type`
- `expected_return`
- `risk`
- `liquidity`
- `horizon`
- `allows_monthly`
- `details`
- `created_at`

The `website` field stores the direct page where the bank explains its investment offering.

### 6. Investment_Plans

The `investment_plans` table stores the plans saved by clients after a recommendation is made.

**Primary key**
- `id`

**Foreign keys**
- `client_id` references `clients(id)`
- `bank_id` references `banks(id)`

**Important attributes**
- `user_plan_name`
- `investment_amount`
- `monthly_contribution`
- `monthly_amount`
- `investment_goal`
- `risk`
- `liquidity`
- `horizon`
- `expected_return`
- `score`
- `bank_investment_url`
- `created_at`
- `updated_at`

The `bank_investment_url` field stores the exact investment page link used when the recommendation was saved, ensuring the saved plan still points to the correct investment information later.

### 7. Activity_Logs

The `activity_logs` table stores the audit trail of actions performed in the system.

**Primary key**
- `id`

**Foreign keys**
- `user_id` references `users(id)`
- `client_id` references `clients(id)`
- `admin_id` references `admins(id)`

**Important attributes**
- `action`
- `entity_type`
- `entity_id`
- `description`
- `created_at`

This entity improves accountability, traceability, and reporting.

## Relationships

### Users to Clients

**Relationship type:** One-to-One

Each client account must be linked to exactly one base user account.

### Users to Admins

**Relationship type:** One-to-One

Each administrator account must be linked to exactly one base user account.

### Clients to Financial_Profiles

**Relationship type:** One-to-One

Each client has one financial profile, and each financial profile belongs to one client.

### Clients to Investment_Plans

**Relationship type:** One-to-Many

A client can save many investment plans, but each investment plan belongs to one client.

### Admins to Banks

**Relationship type:** One-to-Many

An administrator can create and maintain many bank records.

### Banks to Investment_Plans

**Relationship type:** One-to-Many

One bank can be linked to many saved investment plans.

### Users / Clients / Admins to Activity_Logs

**Relationship type:** One-to-Many

Audit logs are linked to the authenticated user and, where relevant, the specific client or admin actor involved in the activity.

## Professional Design Justification

This ERD is more professional because:

- authentication data is separated from business identity data
- client data and admin data are modeled independently
- financial and investment tables depend on `clients`, not directly on `users`
- bank maintenance is clearly associated with administrators
- saved plans preserve the exact investment page URL used during recommendation
- the audit trail supports both client and admin actions

## Conclusion

The InvestSmart ERD presents a normalized and clearly structured data model appropriate for a 3-tier system. The design improves clarity, role separation, referential integrity, and maintainability while supporting all required business processes in the application.

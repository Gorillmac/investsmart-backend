# InvestSmart ERD

```mermaid
erDiagram
    USERS {
        INT id PK
        VARCHAR email UK
        VARCHAR password_hash
        ENUM role
        ENUM status
        TIMESTAMP created_at
    }

    CLIENTS {
        INT id PK
        INT user_id FK UK
        VARCHAR full_name
        VARCHAR surname
        VARCHAR id_number UK
        VARCHAR contact_info
        TIMESTAMP created_at
    }

    ADMINS {
        INT id PK
        INT user_id FK UK
        VARCHAR full_name
        VARCHAR surname
        VARCHAR employee_code UK
        VARCHAR contact_info
        TIMESTAMP created_at
    }

    FINANCIAL_PROFILES {
        INT id PK
        INT client_id FK UK
        DECIMAL gross_salary
        DECIMAL deductions
        DECIMAL monthly_expenses
        DECIMAL current_savings
        DECIMAL net_salary
        TIMESTAMP updated_at
    }

    BANKS {
        INT id PK
        VARCHAR name
        VARCHAR contact_info
        VARCHAR website
        VARCHAR plan_type
        DECIMAL expected_return
        ENUM risk
        ENUM liquidity
        ENUM horizon
        TINYINT allows_monthly
        TEXT details
        INT created_by_admin_id FK
        TIMESTAMP created_at
    }

    INVESTMENT_PLANS {
        INT id PK
        INT client_id FK
        INT bank_id FK
        VARCHAR user_plan_name
        DECIMAL investment_amount
        TINYINT monthly_contribution
        DECIMAL monthly_amount
        VARCHAR investment_goal
        ENUM risk
        ENUM liquidity
        ENUM horizon
        DECIMAL expected_return
        INT score
        VARCHAR bank_investment_url
        TIMESTAMP created_at
        TIMESTAMP updated_at
    }

    ACTIVITY_LOGS {
        INT id PK
        INT user_id FK
        INT client_id FK
        INT admin_id FK
        VARCHAR action
        VARCHAR entity_type
        INT entity_id
        VARCHAR description
        TIMESTAMP created_at
    }

    USERS ||--|| CLIENTS : extends
    USERS ||--|| ADMINS : extends
    CLIENTS ||--|| FINANCIAL_PROFILES : has
    CLIENTS ||--o{ INVESTMENT_PLANS : saves
    ADMINS ||--o{ BANKS : creates
    BANKS ||--o{ INVESTMENT_PLANS : recommended_for
    USERS ||--o{ ACTIVITY_LOGS : authenticates
    CLIENTS ||--o{ ACTIVITY_LOGS : performs
    ADMINS ||--o{ ACTIVITY_LOGS : performs
```

## Relationship Summary

- `users` to `clients`:
  One-to-one. A client account extends a base user login.

- `users` to `admins`:
  One-to-one. An admin account extends a base user login.

- `clients` to `financial_profiles`:
  One-to-one. Each client has exactly one financial profile.

- `clients` to `investment_plans`:
  One-to-many. A client can save many investment plans.

- `admins` to `banks`:
  One-to-many. An admin can create and maintain many bank records.

- `banks` to `investment_plans`:
  One-to-many. One bank can be linked to many saved plans.

- `users`, `clients`, and `admins` to `activity_logs`:
  The audit trail records the authenticated user and, where relevant, the specific client or admin actor.

## Design Notes

- `users` stores login credentials and account status only.
- `clients` stores student/user identity data used in investment matching.
- `admins` stores administrator identity data separately from client records.
- `financial_profiles` and `investment_plans` depend on `clients`, not directly on `users`.
- `banks.website` stores the direct investment information page.
- `investment_plans.bank_investment_url` stores the investment page snapshot so the saved plan keeps the exact link used at recommendation time.

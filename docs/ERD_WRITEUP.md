# InvestSmart Entity Relationship Diagram (ERD)

**Project Title:** InvestSmart Financial Recommendation System  
**Prepared By:** `[Your Full Name]`  
**Student Number:** `[Your Student Number]`  
**Course / Module:** `[Course or Module Name]`  
**Date:** `[Submission Date]`

## 1. Introduction

The Entity Relationship Diagram (ERD) for the InvestSmart system represents the structure of the database used to support the application. The system follows a 3-tier architecture consisting of:

- the **presentation layer** for user interaction
- the **business logic layer** for processing rules and recommendations
- the **data layer** for storing persistent information in a MySQL database

The database design has been structured to support user registration and authentication, financial profile management, bank recommendation logic, investment plan storage, and audit trail monitoring.

## 2. Overview of Entities

The InvestSmart database contains five main entities:

1. `Users`
2. `Financial_Profiles`
3. `Banks`
4. `Investment_Plans`
5. `Activity_Logs`

Each entity is described below.

## 3. Entity Descriptions

### 3.1 Users

The `Users` entity stores all people who access the system, including both normal users and administrators.

**Primary Key:**
- `id`

**Important attributes:**
- `full_name`
- `surname`
- `id_number`
- `email`
- `password_hash`
- `role`
- `status`
- `contact_info`
- `created_at`

**Constraints:**
- `id_number` must be unique
- `email` must be unique
- `role` distinguishes between `user` and `admin`

### 3.2 Financial_Profiles

The `Financial_Profiles` entity stores the financial information captured by each user. This data is used by the system to calculate net salary and support investment recommendations.

**Primary Key:**
- `id`

**Foreign Key:**
- `user_id` references `Users(id)`

**Important attributes:**
- `gross_salary`
- `deductions`
- `monthly_expenses`
- `current_savings`
- `net_salary`
- `updated_at`

**Constraint:**
- `user_id` is unique, ensuring that each user has only one financial profile

### 3.3 Banks

The `Banks` entity stores the banks or investment providers available in the system. The calculator uses this data to recommend the most suitable bank based on the user's selected investment criteria.

**Primary Key:**
- `id`

**Important attributes:**
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

This entity plays a central role in the recommendation engine because it stores the investment characteristics against which the user input is matched.

### 3.4 Investment_Plans

The `Investment_Plans` entity stores the plans saved by users after a recommendation has been generated.

**Primary Key:**
- `id`

**Foreign Keys:**
- `user_id` references `Users(id)`
- `bank_id` references `Banks(id)`

**Important attributes:**
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
- `created_at`
- `updated_at`

This entity preserves the result of the recommendation process and allows users to view, edit, and delete their saved plans.

### 3.5 Activity_Logs

The `Activity_Logs` entity stores the audit trail for system actions. It records important activities performed by both users and administrators.

**Primary Key:**
- `id`

**Foreign Key:**
- `user_id` references `Users(id)`

**Important attributes:**
- `action`
- `entity_type`
- `entity_id`
- `description`
- `created_at`

This entity supports accountability, monitoring, and administrative reporting.

## 4. Relationships Between Entities

### 4.1 Users and Financial_Profiles

**Relationship type:** One-to-One  

Each user has one financial profile, and each financial profile belongs to exactly one user.

This relationship is enforced using the foreign key `user_id` in the `Financial_Profiles` table, which is also defined as unique.

### 4.2 Users and Investment_Plans

**Relationship type:** One-to-Many  

One user can create and save many investment plans, but each investment plan belongs to only one user.

This relationship is implemented through the foreign key `user_id` in the `Investment_Plans` table.

### 4.3 Banks and Investment_Plans

**Relationship type:** One-to-Many  

One bank can be linked to many investment plans, but each investment plan is associated with one bank at a time.

This relationship is implemented through the foreign key `bank_id` in the `Investment_Plans` table.

### 4.4 Users and Activity_Logs

**Relationship type:** One-to-Many  

One user can generate many activity log records, but each activity log record belongs to one user.

This relationship is implemented through the foreign key `user_id` in the `Activity_Logs` table.

## 5. Normalization and Design Justification

The ERD has been designed to reduce redundancy and improve data consistency.

- User personal information is stored separately from financial profile information.
- Bank recommendation data is stored separately from user-saved investment plans.
- Audit information is stored in a dedicated logging table.
- Primary keys uniquely identify each record.
- Foreign keys maintain referential integrity between related tables.

The design supports the operational requirements of the system while maintaining a clear separation of concerns expected in a relational database used in a 3-tier application.

## 6. Conclusion

The InvestSmart ERD provides a structured representation of the database that supports the full functionality of the system. It enables:

- secure user and admin management
- financial data capture
- bank recommendation processing
- storage of investment decisions
- audit trail reporting

The model is suitable for implementation in MySQL and aligns with the functional and structural requirements of the InvestSmart application.

## 7. ERD Files Included

The following ERD files are included in the project:

- [SVG ERD](C:/Users/Msima/Documents/Codex/2026-05-05/can-you-code/docs/investsmart-erd.svg)
- [PNG ERD](C:/Users/Msima/Documents/Codex/2026-05-05/can-you-code/docs/investsmart-erd.png)
- [Draw.io XML ERD](C:/Users/Msima/Documents/Codex/2026-05-05/can-you-code/docs/investsmart-erd.drawio.xml)

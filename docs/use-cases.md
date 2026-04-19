# Use Cases Guide

`quillbytes/payroll-engine` is designed to be the computation core inside a larger payroll module.

This guide maps common payroll business scenarios to the package API that fits them best.

## Choosing The Right Entry Point

| Need | Use | Typical app layer |
| --- | --- | --- |
| Preview one employee's payroll | `compute()` | controller, action, Livewire component, API endpoint |
| Process a full payroll cutoff | `run()` | queued job, service, payroll batch command |
| Produce a single payslip payload | `payslip()` | Blade view, PDF generator, API serializer |
| Produce a register or bank-export source | `payrollRegister()` or `generatePayrollFiles()` | export service, CSV builder, finance integration |
| Group totals by project or cost center | `allocationSummary()` | finance reports, project costing, management dashboards |
| Release retroactive differences only | `retroAdjustmentInput()` | adjustment workflow, payroll correction module |

## 1. Payroll Preview Before Finalization

Use `compute()` when the business wants to inspect payroll before processing a whole cutoff.

Typical scenarios:

- HR previews payroll after approving overtime
- finance reviews a manual adjustment
- employees see a draft payslip in a portal
- an API endpoint returns a payroll preview

Why `compute()` fits:

- it works on one company, one employee, and one payroll input
- it returns a full `PayrollResult`
- it gives you issues, totals, deductions, contributions, and audit metadata

Typical Laravel flow:

1. Load the company policy record.
2. Load the employee profile and related compensation data.
3. Build the payroll input from attendance, overtime, and adjustments.
4. Call `compute()`.
5. Transform the result into JSON, Blade data, or a preview DTO.

## 2. Full Payroll Cutoff Processing

Use `run()` when you need to process many employees for one payroll period.

Typical scenarios:

- semi-monthly payroll generation
- weekly payroll for field teams
- nightly background processing before payroll review

Why `run()` fits:

- it accepts one company and one payroll period
- it loops through employees and skips employees who are not active during the period
- it returns a `PayrollRun` with lifecycle helpers and audit entries

Typical Laravel flow:

1. Fetch eligible employees for the cutoff.
2. Map each employee into an item containing `employee` and optional `input`.
3. Call `run()`.
4. Store run metadata and result snapshots in your payroll tables.
5. Move the run through prepare, approve, process, and release steps in your own module.

## 3. Approval And Release Workflows

Use the `PayrollRun` lifecycle when your application has approval gates.

This is a good fit when:

- payroll must be prepared by one role and approved by another
- release is only allowed on or after the configured release date
- payslip generation should be blocked until payroll is processed

Relevant lifecycle methods:

- `prepare()`
- `approve()`
- `process()`
- `reopen()`
- `release()`
- `assertEditable()`
- `assertCanGeneratePayrollFiles()`
- `assertCanGeneratePayslips()`

Typical Laravel flow:

1. A preparer generates the run.
2. An approver signs off in your application.
3. A background job marks the run as processed and stores export data.
4. The release step opens payouts, register generation, and payslip generation.

## 4. Payslips, Registers, And Bank Export Sources

Use the reporting methods when payroll needs to leave the engine and enter your delivery layer.

### Single-result payslip generation

Use `payslip($result)` when you already have a `PayrollResult` and need a payslip-ready array for:

- Blade templates
- PDF rendering
- API responses
- employee self-service screens

### Run-based export generation

Use:

- `generatePayslips($run)` for a processed payroll run
- `generatePayrollFiles($run)` for a processed payroll register/export source

These methods enforce the run lifecycle guards so the application does not generate release artifacts too early.

## 5. Allocation And Cost Distribution

Use `allocationSummary()` when payroll totals must be grouped by allocation dimensions.

Typical scenarios:

- project-based payroll charging
- department labor reporting
- vessel or branch payroll distribution
- cost center billing

Examples of supported dimensions:

- `project_code`
- `project_name`
- `department`
- `branch`
- `cost_center`
- `vessel`

This is especially useful when the same payroll engine powers operations, finance, and management reporting.

## 6. Off-Cycle Payroll

Use the same engine for payroll runs that are not part of the normal cutoff.

Common off-cycle use cases:

- incentive release
- correction runs
- bonus payout
- manual reimbursement
- isolated loan recovery or deduction release

Recommended pattern:

1. Keep the same company and employee mapping rules.
2. Pass a payroll period with an explicit `run_type`.
3. Supply only the payroll lines that belong to that off-cycle event.

This lets your application reuse one payroll engine instead of maintaining separate logic for regular and special runs.

## 7. Final Pay And Separation Payroll

Use final-settlement run types when an employee is leaving and payroll must compute the last release.

Typical scenarios:

- final salary release
- final allowance payout
- leave conversion or deductions handled in your application
- last loan or recovery adjustments

Recommended Laravel approach:

1. Mark the employee's separation or resignation date in your own data model.
2. Build a final-pay payroll input with the proper period and adjustments.
3. Call `compute()` for a single employee or `run()` for a batch of separated employees.
4. Store the output separately from regular cutoff history if your payroll UI distinguishes final pay from normal payroll.

## 8. Retroactive Corrections

Use `retroAdjustmentInput()` when a historical payroll needs to be recomputed and only the difference should be released.

Typical scenarios:

- late attendance approval
- salary correction effective in a previous period
- missed allowance or overtime
- statutory or tax corrections

Recommended flow:

1. Compute or load the original payroll result.
2. Recompute the same historical period using corrected data.
3. Call `retroAdjustmentInput($original, $recomputed, $releasePeriod)`.
4. Release the returned adjustment input in a separate adjustment run.

The generated adjustment input contains only the delta, not a duplicate of the full historical payroll.

## 9. Multi-Tenant Or Multi-Client Payroll Platforms

The package is a strong fit for SaaS payroll products or internal systems that serve multiple business units.

Use it when:

- each tenant has a different baseline policy
- some tenants need different strategies
- your application wants one payroll engine package with per-client behavior

Recommended policy setup:

- `presets.{client_code}` for default values
- `strategies.clients.{client_code}` for formula overrides
- `edge_case_policy` metadata for company-, employee-, or input-specific runtime rules

This keeps tenant differences explicit and versionable.

## 10. Audit, Debugging, And Payroll Review

Use the package when traceability matters as much as computation.

Helpful outputs for audit flows:

- payroll issues and warnings
- audit metadata on the `PayrollResult`
- line-level metadata on earnings and deductions
- strategy and policy names included in the audit payload

This is useful for:

- payroll review screens
- admin troubleshooting tools
- downstream export validation
- compliance and approval records

## Recommended Use-Case Boundaries

The package is a good fit when your application owns:

- companies, employees, attendance, and deductions
- payroll scheduling and approval UI
- persistence of payroll history
- PDF, CSV, or bank file generation

The package is not intended to replace:

- your database schema
- attendance ingestion
- HR master data management
- UI or workflow screens

Treat it as the payroll computation core and keep the surrounding application responsibilities in Laravel.

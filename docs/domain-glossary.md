# Domain Glossary

This glossary defines the payroll terms used throughout the package documentation and codebase.

## Payroll Period

The covered date range for a payroll computation, including:

- start date
- end date
- optional release date
- optional run type

## Payroll Cutoff

A scheduled payroll slice such as first half of the month, second half of the month, weekly run, or another recurring pay cycle.

## Payroll Run

A batch payroll output for one company and one payroll period. In the package this is represented by `PayrollRun`.

## Payroll Result

The payroll computation output for one employee for one payroll period. In the package this is represented by `PayrollResult`.

## Basic Pay

The regular scheduled salary amount for the payroll period before allowances, overtime, adjustments, deductions, and taxes are applied.

## Scheduled Basic Pay

The package's computed regular basic pay for the active payroll period. It may be zero for special runs and prorated for final-settlement runs.

## Gross Pay

The total earnings before employee contributions and deductions are subtracted.

## Taxable Income

The portion of earnings that remains subject to withholding tax after non-taxable lines and employee mandatory contributions are considered.

## Net Pay

Gross pay minus employee contributions and deductions.

## Take-Home Pay

Net pay plus any separate payouts. This is the amount the employee actually receives in the release output.

## Allowance

An earning that is not necessarily part of basic salary. In the package, allowances may be included in regular pay or released separately depending on policy.

## Variable Earning

A non-basic earning such as:

- sales commission
- production incentive
- quota bonus
- other custom variable earning entries

## Adjustment

A payroll earning-side correction or additional earning for the current run.

## Deduction

An amount subtracted from pay, such as manual deductions, loans, tardiness, or withholding tax.

## Loan Deduction

A deduction tied to a loan or recovery reference, commonly used for salary loans or company advances.

## Contribution

A statutory payroll amount, such as:

- SSS
- PhilHealth
- Pag-IBIG

The package tracks both employee and employer shares.

## Overtime

An earning derived from worked overtime hours or manual OT amount input, depending on company policy.

## Tardiness / Late Deduction

A deduction applied because the employee was late for scheduled work.

## Undertime Deduction

A deduction applied when the employee worked less than the scheduled shift without using a leave type that would offset the shortfall.

## Final Settlement / Final Pay

A payroll run used when an employee has separated and the final release may need prorated basic pay and separation-specific adjustments.

## Retro Adjustment

A difference-only payroll release created by comparing a historical payroll result against a recomputed version of the same period.

## Payroll Policy

The effective set of rules used for a computation, including defaults, presets, company overrides, strategies, and runtime edge-case decisions.

## Strategy

A replaceable calculator or workflow class selected by client code to customize part or all of the payroll computation.

## Edge-Case Policy

A prepare/finalize policy object that handles runtime exceptions or special conditions such as missing attendance, overlapping deductions, or insufficient net pay.

## Audit Trail

Structured metadata explaining how a payroll result was derived, including applied strategies, policies, rate details, and line-level trace context.


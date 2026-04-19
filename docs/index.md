# Documentation Index

This package ships with documentation in three layers:

1. Public package documentation for developers who install and use the package.
2. Engineering documentation for maintainers and contributors.
3. Operational documentation for rollout, support, and production debugging.

## Start Here

If you are new to the package, read these in order:

1. [README](../README.md)
2. [Installation Guide](installation.md)
3. [Quick Start Guide](quick-start.md)
4. [Usage Guide](usage-guide.md)
5. [Laravel Implementation Guide](laravel-implementation.md)

## Public Package Docs

| Document | Audience | Purpose |
| --- | --- | --- |
| [README](../README.md) | New users | Entry point, value proposition, requirements, install, and quick orientation |
| [Installation Guide](installation.md) | App developers | Install, publish config, verify setup, and understand what the package does and does not publish |
| [Quick Start Guide](quick-start.md) | App developers | Compute payroll in the fastest possible path with realistic sample input and output |
| [Usage Guide](usage-guide.md) | App developers | Real-world usage patterns for periods, earnings, deductions, contributions, reports, and persistence |
| [Configuration Reference](configuration-reference.md) | App developers | Complete explanation of published config keys, defaults, and when to change them |
| [Use Cases Guide](use-cases.md) | App developers | Scenario-driven guide to choosing the right entry point and workflow |
| [Policies Guide](policies.md) | App developers | Policy hierarchy, runtime edge-case rules, and strategy selection |
| [Laravel Implementation Guide](laravel-implementation.md) | App developers | Container wiring, app structure, actions, controllers, jobs, and exports |

## Engineering Docs

| Document | Audience | Purpose |
| --- | --- | --- |
| [Architecture Overview](architecture.md) | Maintainers | Package boundaries, modules, pipeline, and extension points |
| [Default Pipeline Guide](default-pipeline.md) | Maintainers and debuggers | Step-by-step map of the built-in payroll sequence, default keys, and debug checkpoints |
| [Domain Glossary](domain-glossary.md) | Maintainers and users | Shared payroll vocabulary used across the package |
| [Extending the Package](extending.md) | Advanced integrators | Add custom rules, swap strategies, and introduce tenant-specific behavior |
| [API Reference](api-reference.md) | Maintainers and integrators | High-level reference for core classes, DTOs, and strategy contracts |
| [Workflow Reference](workflow-reference.md) | Maintainers and integrators | Method-by-method reference for engine workflows, lifecycle gates, samples, and outputs |
| [Testing Guide](testing.md) | Maintainers and contributors | Test commands, scenario coverage, and how to add new regression tests |
| [Contributing Guide](../CONTRIBUTING.md) | Contributors | Local setup, coding standards, pull request expectations, and release conventions |

## Operational Docs

| Document | Audience | Purpose |
| --- | --- | --- |
| [Runbook](runbook.md) | Support and release owners | Deployment, rollout, smoke tests, rollback, and escalation |
| [Troubleshooting Guide](troubleshooting.md) | Support and app developers | Diagnose common install, config, lifecycle, and computation issues |
| [Upgrade Guide](upgrade-guide.md) | Release owners and app developers | Safe package upgrades, config diff review, and verification after update |
| [Changelog](../CHANGELOG.md) | All audiences | Release history and notable changes |
| [Security Policy](../SECURITY.md) | Security reporters | How to report vulnerabilities responsibly |

## Documentation Conventions

- `company`, `employee`, and `input` refer to the host application's payroll payloads before the engine computes results.
- `PayrollResult` refers to a single employee payroll output.
- `PayrollRun` refers to a multi-employee payroll batch output with lifecycle state.
- All money values shown in examples are in major currency units unless explicitly noted otherwise.

## Maintaining The Docs

When package behavior changes:

- update the most relevant guide first
- update [README](../README.md) if the public install or quick-start flow changes
- update [Configuration Reference](configuration-reference.md) when config keys or defaults change
- update [API Reference](api-reference.md) when public classes, methods, or contracts change
- update [Runbook](runbook.md), [Troubleshooting Guide](troubleshooting.md), or [Upgrade Guide](upgrade-guide.md) when rollout or support behavior changes

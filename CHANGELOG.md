# Changelog

All notable changes to this project will be documented in this file. See [standard-version](https://github.com/conventional-changelog/standard-version) for commit guidelines.

### [1.2.1](https://github.com/quillbytes/payroll-engine/compare/v1.2.0...v1.2.1) (2026-05-20)

## 1.2.0 (2026-04-19)


### Features

* Add `AttributeReader` and `MoneyHelper` utility classes for improved attribute handling and currency operations ([daf2797](https://github.com/jdclzn/payroll-engine/commit/daf279739bd326d260e80a8e667cdd56e3158389))
* Add `composer.json` and `composer.lock` for package configuration and dependency management ([b87d9a3](https://github.com/jdclzn/payroll-engine/commit/b87d9a3a4de409dc81227fceffdafd2a17b66ba4))
* Add `InvalidPayrollData` exception class for handling invalid payroll input data ([3c132ce](https://github.com/jdclzn/payroll-engine/commit/3c132ce6fab524b4d55ffe069e60010242b833bd))
* Add audit trail builder, trace enricher, and metadata utilities for payroll processing ([574f496](https://github.com/jdclzn/payroll-engine/commit/574f49612b75903bc6b3c5a6c494aa54f3ff6704))
* Add comprehensive calculators for payroll processing ([1ee359c](https://github.com/jdclzn/payroll-engine/commit/1ee359c377b31ae8cba626b7b174cfce74afe7aa))
* Add configuration, builders, and strategy resolver for payroll processing ([a494f50](https://github.com/jdclzn/payroll-engine/commit/a494f504be2eee9e0f464a81e62c028d61dd3f5a))
* Add enumerations for payroll configuration and status handling ([0ca026e](https://github.com/jdclzn/payroll-engine/commit/0ca026ed38b6edf6451266c73db5406403250b20))
* Add GitHub Actions workflow for automated testing ([117d352](https://github.com/jdclzn/payroll-engine/commit/117d3528f0ee0188bcec5ef6fae44c4de10adfef))
* Add initial data models for payroll system ([914f743](https://github.com/jdclzn/payroll-engine/commit/914f743d47629751916af018cb99e7780c172c62))
* Add Laravel integration tests, release automation scripts, and config management improvements ([a0370d3](https://github.com/jdclzn/payroll-engine/commit/a0370d3c112d6a5821b1c1f52aaf092d81f27398))
* Add normalizer classes for standardizing company, employee, payroll input, and payroll period data ([7f3a1c1](https://github.com/jdclzn/payroll-engine/commit/7f3a1c1c2377b4bd8799fb31e52bb43db103c173))
* Add test coverage for `PayrollEngine` core, helpers, and client-specific strategies ([6c96218](https://github.com/jdclzn/payroll-engine/commit/6c9621870cf67765d7baf165792cc7d6a1d3ecf0))
* Add tests for payroll design rules and enhance audit traceability across engine components ([10f4107](https://github.com/jdclzn/payroll-engine/commit/10f41077c85c7b6e36500fc1268ec80bbc393726))
* Add validators for company, employee, and payroll input data validation ([7fa2264](https://github.com/jdclzn/payroll-engine/commit/7fa226488fd9e10299e65e86153bfc2c9bce7137))
* Implement trace metadata across calculators for enhanced auditability ([7ff0127](https://github.com/jdclzn/payroll-engine/commit/7ff01276c4db8649fdefc73cd80298512db90c25))
* Integrate trace metadata into PhilHealth contributions for improved auditability ([c1eac64](https://github.com/jdclzn/payroll-engine/commit/c1eac64162d6c96772de3c2922acab8a0a06d26c))
* Introduce `PayrollEngine` core implementation with facade and service provider ([bca6dab](https://github.com/jdclzn/payroll-engine/commit/bca6dabecec515aec00058acd9c01faf84a798bb))
* Introduce client policy presets and registry for flexible client-specific configurations ([be66790](https://github.com/jdclzn/payroll-engine/commit/be667903ebcd6eb543851630f8090b06048ac2fe))
* Introduce core contract interfaces for payroll engine ([2c5aa42](https://github.com/jdclzn/payroll-engine/commit/2c5aa424b20df8b721a14641e137dc6423e04455))

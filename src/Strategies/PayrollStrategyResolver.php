<?php

namespace QuillBytes\PayrollEngine\Strategies;

use QuillBytes\PayrollEngine\Calculators\OvertimeCalculator;
use QuillBytes\PayrollEngine\Calculators\PagIbigContributionCalculator;
use QuillBytes\PayrollEngine\Calculators\PayrollCalculator;
use QuillBytes\PayrollEngine\Calculators\PhilHealthContributionCalculator;
use QuillBytes\PayrollEngine\Calculators\RateCalculator;
use QuillBytes\PayrollEngine\Calculators\SssContributionCalculator;
use QuillBytes\PayrollEngine\Calculators\VariableEarningCalculator;
use QuillBytes\PayrollEngine\Calculators\WithholdingTaxCalculator;
use QuillBytes\PayrollEngine\Contracts\OvertimeCalculator as OvertimeCalculatorContract;
use QuillBytes\PayrollEngine\Contracts\PagIbigContributionCalculator as PagIbigContributionCalculatorContract;
use QuillBytes\PayrollEngine\Contracts\PayrollWorkflow;
use QuillBytes\PayrollEngine\Contracts\PhilHealthContributionCalculator as PhilHealthContributionCalculatorContract;
use QuillBytes\PayrollEngine\Contracts\RateCalculator as RateCalculatorContract;
use QuillBytes\PayrollEngine\Contracts\SssContributionCalculator as SssContributionCalculatorContract;
use QuillBytes\PayrollEngine\Contracts\VariableEarningCalculator as VariableEarningCalculatorContract;
use QuillBytes\PayrollEngine\Contracts\WithholdingTaxCalculator as WithholdingTaxCalculatorContract;
use QuillBytes\PayrollEngine\Exceptions\InvalidPayrollData;

final class PayrollStrategyResolver
{
    /**
     * @var (callable(class-string):object)|null
     */
    private $factory;

    /**
     * @var array<string, PayrollWorkflow>
     */
    private array $workflowCache = [];

    /**
     * @var array<string, RateCalculatorContract>
     */
    private array $rateCache = [];

    /**
     * @var array<string, OvertimeCalculatorContract>
     */
    private array $overtimeCache = [];

    /**
     * @var array<string, WithholdingTaxCalculatorContract>
     */
    private array $withholdingCache = [];

    /**
     * @var array<string, VariableEarningCalculatorContract>
     */
    private array $variableEarningCache = [];

    /**
     * @var array<string, PagIbigContributionCalculatorContract>
     */
    private array $pagIbigCache = [];

    /**
     * @var array<string, SssContributionCalculatorContract>
     */
    private array $sssCache = [];

    /**
     * @var array<string, PhilHealthContributionCalculatorContract>
     */
    private array $philHealthCache = [];

    /**
     * @param  array<string, mixed>  $config
     * @param  (callable(class-string):object)|null  $factory
     */
    public function __construct(private readonly array $config = [], ?callable $factory = null)
    {
        $this->factory = $factory === null ? null : $factory(...);
    }

    public function workflowFor(string $clientCode): PayrollWorkflow
    {
        $clientCode = $this->normalizeClientCode($clientCode);

        if (isset($this->workflowCache[$clientCode])) {
            return $this->workflowCache[$clientCode];
        }

        $definition = $this->definitionFor($clientCode, 'workflow');

        if ($definition !== null && ! $this->isDefaultWorkflowDefinition($definition)) {
            return $this->workflowCache[$clientCode] = $this->resolve(
                $definition,
                PayrollWorkflow::class,
                'workflow'
            );
        }

        return $this->workflowCache[$clientCode] = new PayrollCalculator(
            $this->rateCalculatorFor($clientCode),
            $this->overtimeCalculatorFor($clientCode),
            $this->variableEarningCalculatorFor($clientCode),
            $this->sssContributionCalculatorFor($clientCode),
            $this->philHealthContributionCalculatorFor($clientCode),
            $this->pagIbigContributionCalculatorFor($clientCode),
            $this->withholdingTaxCalculatorFor($clientCode),
        );
    }

    /**
     * @return array<string, string>
     */
    public function describeFor(string $clientCode): array
    {
        $workflow = $this->workflowFor($clientCode);
        $rate = $this->rateCalculatorFor($clientCode);
        $overtime = $this->overtimeCalculatorFor($clientCode);
        $variableEarnings = $this->variableEarningCalculatorFor($clientCode);
        $withholding = $this->withholdingTaxCalculatorFor($clientCode);
        $sss = $this->sssContributionCalculatorFor($clientCode);
        $philHealth = $this->philHealthContributionCalculatorFor($clientCode);
        $pagIbig = $this->pagIbigContributionCalculatorFor($clientCode);

        return [
            'workflow' => $workflow::class,
            'rate' => $rate::class,
            'overtime' => $overtime::class,
            'variable_earnings' => $variableEarnings::class,
            'withholding' => $withholding::class,
            'sss' => $sss::class,
            'philhealth' => $philHealth::class,
            'pagibig' => $pagIbig::class,
        ];
    }

    private function rateCalculatorFor(string $clientCode): RateCalculatorContract
    {
        $clientCode = $this->normalizeClientCode($clientCode);

        if (isset($this->rateCache[$clientCode])) {
            return $this->rateCache[$clientCode];
        }

        return $this->rateCache[$clientCode] = $this->resolve(
            $this->definitionFor($clientCode, 'rate') ?? RateCalculator::class,
            RateCalculatorContract::class,
            'rate'
        );
    }

    private function overtimeCalculatorFor(string $clientCode): OvertimeCalculatorContract
    {
        $clientCode = $this->normalizeClientCode($clientCode);

        if (isset($this->overtimeCache[$clientCode])) {
            return $this->overtimeCache[$clientCode];
        }

        return $this->overtimeCache[$clientCode] = $this->resolve(
            $this->definitionFor($clientCode, 'overtime') ?? OvertimeCalculator::class,
            OvertimeCalculatorContract::class,
            'overtime'
        );
    }

    private function withholdingTaxCalculatorFor(string $clientCode): WithholdingTaxCalculatorContract
    {
        $clientCode = $this->normalizeClientCode($clientCode);

        if (isset($this->withholdingCache[$clientCode])) {
            return $this->withholdingCache[$clientCode];
        }

        return $this->withholdingCache[$clientCode] = $this->resolve(
            $this->definitionFor($clientCode, 'withholding') ?? WithholdingTaxCalculator::class,
            WithholdingTaxCalculatorContract::class,
            'withholding'
        );
    }

    private function variableEarningCalculatorFor(string $clientCode): VariableEarningCalculatorContract
    {
        $clientCode = $this->normalizeClientCode($clientCode);

        if (isset($this->variableEarningCache[$clientCode])) {
            return $this->variableEarningCache[$clientCode];
        }

        return $this->variableEarningCache[$clientCode] = $this->resolve(
            $this->definitionFor($clientCode, 'variable_earnings') ?? VariableEarningCalculator::class,
            VariableEarningCalculatorContract::class,
            'variable_earnings'
        );
    }

    private function pagIbigContributionCalculatorFor(string $clientCode): PagIbigContributionCalculatorContract
    {
        $clientCode = $this->normalizeClientCode($clientCode);

        if (isset($this->pagIbigCache[$clientCode])) {
            return $this->pagIbigCache[$clientCode];
        }

        return $this->pagIbigCache[$clientCode] = $this->resolve(
            $this->definitionFor($clientCode, 'pagibig') ?? PagIbigContributionCalculator::class,
            PagIbigContributionCalculatorContract::class,
            'pagibig'
        );
    }

    private function sssContributionCalculatorFor(string $clientCode): SssContributionCalculatorContract
    {
        $clientCode = $this->normalizeClientCode($clientCode);

        if (isset($this->sssCache[$clientCode])) {
            return $this->sssCache[$clientCode];
        }

        return $this->sssCache[$clientCode] = $this->resolve(
            $this->definitionFor($clientCode, 'sss') ?? SssContributionCalculator::class,
            SssContributionCalculatorContract::class,
            'sss'
        );
    }

    private function philHealthContributionCalculatorFor(string $clientCode): PhilHealthContributionCalculatorContract
    {
        $clientCode = $this->normalizeClientCode($clientCode);

        if (isset($this->philHealthCache[$clientCode])) {
            return $this->philHealthCache[$clientCode];
        }

        return $this->philHealthCache[$clientCode] = $this->resolve(
            $this->definitionFor($clientCode, 'philhealth') ?? PhilHealthContributionCalculator::class,
            PhilHealthContributionCalculatorContract::class,
            'philhealth'
        );
    }

    private function isDefaultWorkflowDefinition(mixed $definition): bool
    {
        return $definition instanceof PayrollCalculator || $definition === PayrollCalculator::class;
    }

    private function normalizeClientCode(string $clientCode): string
    {
        $clientCode = strtolower(trim($clientCode));

        return $clientCode === '' ? 'base' : $clientCode;
    }

    private function definitionFor(string $clientCode, string $key): mixed
    {
        $strategies = $this->config['strategies'] ?? [];
        $defaultStrategies = is_array($strategies['default'] ?? null) ? $strategies['default'] : [];
        $clientStrategies = is_array($strategies['clients'][$clientCode] ?? null) ? $strategies['clients'][$clientCode] : [];
        $fallbacks = $this->defaultDefinitions();

        return $clientStrategies[$key] ?? $defaultStrategies[$key] ?? $fallbacks[$key] ?? null;
    }

    /**
     * @return array<string, class-string>
     */
    private function defaultDefinitions(): array
    {
        return [
            'workflow' => PayrollCalculator::class,
            'rate' => RateCalculator::class,
            'overtime' => OvertimeCalculator::class,
            'variable_earnings' => VariableEarningCalculator::class,
            'withholding' => WithholdingTaxCalculator::class,
            'sss' => SssContributionCalculator::class,
            'philhealth' => PhilHealthContributionCalculator::class,
            'pagibig' => PagIbigContributionCalculator::class,
        ];
    }

    /**
     * @template T of object
     *
     * @param  class-string<T>  $contract
     * @return T
     */
    private function resolve(mixed $definition, string $contract, string $key): object
    {
        if ($definition instanceof $contract) {
            return $definition;
        }

        if (! is_string($definition) || $definition === '') {
            throw new InvalidPayrollData(sprintf(
                'Configured payroll %s strategy must be an instance or class-string of %s.',
                $key,
                $contract
            ));
        }

        $instance = $this->factory !== null
            ? ($this->factory)($definition)
            : new $definition;

        if (! $instance instanceof $contract) {
            throw new InvalidPayrollData(sprintf(
                'Configured payroll %s strategy [%s] must implement %s.',
                $key,
                $definition,
                $contract
            ));
        }

        return $instance;
    }
}

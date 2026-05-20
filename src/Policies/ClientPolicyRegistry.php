<?php

namespace QuillBytes\PayrollEngine\Policies;

use QuillBytes\PayrollEngine\Contracts\ClientPolicyPreset;

final readonly class ClientPolicyRegistry
{
    /**
     * @param  array<int, ClientPolicyPreset>|null  $presets
     */
    public function __construct(
        private ?array $presets = null
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function defaultsFor(string $clientCode): array
    {
        $clientCode = strtolower(trim($clientCode));

        foreach ($this->presets() as $preset) {
            if ($preset->supports($clientCode)) {
                return $preset->defaults();
            }
        }

        return (new BaseClientPolicyPreset)->defaults();
    }

    /**
     * @return array<int, ClientPolicyPreset>
     */
    private function presets(): array
    {
        return $this->presets ?? [
            new Enterprise365ClientPolicyPreset,
            new BaseClientPolicyPreset,
        ];
    }
}

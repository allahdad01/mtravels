<?php

class OnboardingGuide
{
    private PDO $pdo;
    private int $tenant_id;
    private ?int $branch_id;

    private array $steps = ['main_account', 'supplier', 'client'];

    public function __construct(PDO $pdo, int $tenant_id, ?int $branch_id = null)
    {
        $this->pdo = $pdo;
        $this->tenant_id = $tenant_id;
        $this->branch_id = $branch_id > 0 ? $branch_id : null;
    }

    public function shouldShow(): bool
    {
        return count($this->getIncompleteSteps()) > 0;
    }

    public function getIncompleteSteps(): array
    {
        $incomplete = [];
        foreach ($this->steps as $step) {
            if (!$this->isStepComplete($step)) {
                $incomplete[] = $step;
            }
        }
        return $incomplete;
    }

    public function getCurrentStep(): ?string
    {
        foreach ($this->steps as $step) {
            if (!$this->isStepComplete($step)) {
                return $step;
            }
        }
        return null;
    }

    public function getProgress(): array
    {
        $result = [];
        foreach ($this->steps as $step) {
            $result[$step] = $this->isStepComplete($step);
        }
        return $result;
    }

    public function getProgressPercent(): int
    {
        $done = 0;
        foreach ($this->steps as $step) {
            if ($this->isStepComplete($step)) $done++;
        }
        return (int) round(($done / count($this->steps)) * 100);
    }

    public function isStepComplete(string $step): bool
    {
        return match ($step) {
            'main_account' => $this->hasMainAccounts(),
            'supplier'     => $this->hasSuppliers(),
            'client'       => $this->hasClients(),
            default        => true,
        };
    }

    private function countForBranch(string $table, string $extraWhere = '', array $extraParams = []): int
    {
        $sql = "SELECT COUNT(*) FROM {$table} WHERE tenant_id = ?";
        $params = [$this->tenant_id];

        if ($this->branch_id) {
            $sql .= ' AND (branch_id = ? OR branch_id IS NULL)';
            $params[] = $this->branch_id;
        }

        if ($extraWhere !== '') {
            $sql .= " AND {$extraWhere}";
            $params = array_merge($params, $extraParams);
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    private function hasMainAccounts(): bool
    {
        return $this->countForBranch('main_account') > 0;
    }

    private function hasSuppliers(): bool
    {
        return $this->countForBranch('suppliers', "status = 'active'") > 0;
    }

    private function hasClients(): bool
    {
        return $this->countForBranch('clients', "status = 'active'") > 0;
    }

    public static function getStepLabel(string $step): string
    {
        return match ($step) {
            'main_account' => 'Create a Main Account',
            'supplier'     => 'Add a Supplier',
            'client'       => 'Add a Client',
            default        => '',
        };
    }

    public static function getStepDescription(string $step): string
    {
        return match ($step) {
            'main_account' => 'Set up your first main account to start managing finances. Go to the Accounts page and click "Add Account".',
            'supplier'     => 'Add your first supplier to track purchases and manage vendor relationships.',
            'client'       => 'Create your first client profile to start managing bookings and sales.',
            default        => '',
        };
    }

    public static function getStepPage(string $step): string
    {
        return match ($step) {
            'main_account' => 'accounts.php',
            'supplier'     => 'supplier.php',
            'client'       => 'client.php',
            default        => 'dashboard.php',
        };
    }
}

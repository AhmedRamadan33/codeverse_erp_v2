<?php

namespace Modules\Core\Installation;

final readonly class InstallationData
{
    public function __construct(
        public string $companyName,
        public string $baseCurrency,
        public string $locale,
        public string $branchNameAr,
        public ?string $branchNameEn,
        public string $branchCode,
        public string $adminName,
        public string $adminEmail,
        public string $adminPassword,
    ) {}
}

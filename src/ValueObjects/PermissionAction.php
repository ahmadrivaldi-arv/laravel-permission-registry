<?php

declare(strict_types=1);

namespace Ahmdrv\PermissionRegistry\ValueObjects;

use Ahmdrv\PermissionRegistry\Enums\RiskLevel;

final readonly class PermissionAction
{
    /** @param list<PermissionRecommendation> $recommendations */
    private function __construct(
        public string $key,
        public ?string $actionLabel = null,
        public bool $hasLabel = false,
        public ?string $actionDescription = null,
        public bool $hasDescription = false,
        public ?RiskLevel $riskLevel = null,
        public ?bool $isDirectGrantable = null,
        public array $recommendations = [],
    ) {}

    public static function make(string $key): self
    {
        return new self($key);
    }

    public function label(string $label): self
    {
        return new self($this->key, $label, true, $this->actionDescription, $this->hasDescription, $this->riskLevel, $this->isDirectGrantable, $this->recommendations);
    }

    public function description(?string $description): self
    {
        return new self($this->key, $this->actionLabel, $this->hasLabel, $description, true, $this->riskLevel, $this->isDirectGrantable, $this->recommendations);
    }

    public function risk(RiskLevel $risk): self
    {
        return new self($this->key, $this->actionLabel, $this->hasLabel, $this->actionDescription, $this->hasDescription, $risk, $this->isDirectGrantable, $this->recommendations);
    }

    public function directGrantable(bool $grantable = true): self
    {
        return new self($this->key, $this->actionLabel, $this->hasLabel, $this->actionDescription, $this->hasDescription, $this->riskLevel, $grantable, $this->recommendations);
    }

    public function recommend(PermissionRecommendation $recommendation): self
    {
        return new self($this->key, $this->actionLabel, $this->hasLabel, $this->actionDescription, $this->hasDescription, $this->riskLevel, $this->isDirectGrantable, [...$this->recommendations, $recommendation]);
    }
}
